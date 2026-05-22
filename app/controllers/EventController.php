<?php
// ParlReg — Event Controller
// GET    /api/v1/events                  — list events
// POST   /api/v1/events                  — create event
// GET    /api/v1/events/{id}             — get event
// PUT    /api/v1/events/{id}             — update event
// DELETE /api/v1/events/{id}             — delete event
// POST   /api/v1/events/{id}/clone       — clone event
// POST   /api/v1/events/{id}/publish     — publish event
// POST   /api/v1/events/{id}/unpublish   — unpublish event
// GET    /api/v1/events/{id}/sections    — get section config
// PUT    /api/v1/events/{id}/sections    — update section config
// GET    /api/v1/events/{id}/faqs        — list FAQs
// POST   /api/v1/events/{id}/faqs        — create FAQ
// PUT    /api/v1/events/{id}/faqs/{fid}  — update FAQ
// DELETE /api/v1/events/{id}/faqs/{fid}  — delete FAQ
// GET    /api/v1/events/{id}/schema      — get form schema
// PUT    /api/v1/events/{id}/schema      — update form schema

class EventController {
    // ─── Events ──────────────────────────────────────────────────────────────

    public function listPublished(): never {
        // Public endpoint - no authentication required
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $total = DB::row("SELECT COUNT(*) as cnt FROM events WHERE status = 'published'")['cnt'];
        $events = DB::all("SELECT e.id, e.slug, e.name_en, e.name_fr, e.date_start, e.date_end,
                                  e.location_en, e.location_fr, e.theme_color,
                                  e.registration_deadline, e.capacity,
                                  (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) as registrant_count
                           FROM events e
                           WHERE e.status = 'published'
                           ORDER BY e.date_start DESC
                           LIMIT ? OFFSET ?", [$limit, $offset]);

        Response::json([
            'success'     => true,
            'data'        => $events,
            'total'       => (int)$total,
            'page'        => $page,
            'per_page'    => $limit,
            'total_pages' => ceil($total / $limit),
        ]);
    }

    public function index(): never {
        Auth::requireRole('admin', 'organizer');

        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];

        if ($status && in_array($status, ['draft','published','closed'], true)) {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }
        if ($search) {
            $where[] = '(e.name_en LIKE ? OR e.name_fr LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        // Organizers only see their own events
        if (Auth::role() === 'organizer') {
            $where[] = 'e.created_by = ?';
            $params[] = Auth::id();
        }

        $whereStr = implode(' AND ', $where);
        $total    = DB::row("SELECT COUNT(*) as cnt FROM events e WHERE $whereStr", $params)['cnt'];
        $events   = DB::all("SELECT e.id, e.slug, e.name_en, e.name_fr, e.date_start, e.date_end,
                                    e.status, e.capacity, e.approval_mode,
                                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) as registrant_count,
                                    u.fullname as created_by_name
                             FROM events e
                             JOIN users u ON u.id = e.created_by
                             WHERE $whereStr
                             ORDER BY e.date_start DESC
                             LIMIT $limit OFFSET $offset", $params);

        Response::json([
            'success'     => true,
            'data'        => $events,
            'total'       => (int)$total,
            'page'        => $page,
            'per_page'    => $limit,
            'total_pages' => ceil($total / $limit),
        ]);
    }

    public function show(int $id): never {
        Auth::requireRole('admin', 'organizer');
        $event = $this->findEvent($id);
        Response::json(['success' => true, 'data' => $event]);
    }

    public function store(): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->validateEventData($data);

        $slug = $data['slug'] ?? Validator::slugify($data['name_en']);
        if (DB::row('SELECT id FROM events WHERE slug = ?', [$slug])) {
            Response::json(['success' => false, 'error' => 'Slug already exists.'], 409);
        }

        $id = DB::insert(
            'INSERT INTO events (slug, name_en, name_fr, date_start, date_end, location_en, location_fr,
                                  meta_title_en, meta_title_fr, meta_desc_en, meta_desc_fr,
                                  capacity, approval_mode, theme_color, registration_deadline,
                                  smtp_profile_id, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'draft\',?)',
            [
                $slug,
                $data['name_en'],
                $data['name_fr']             ?? null,
                $data['date_start'],
                $data['date_end'],
                $data['location_en']         ?? null,
                $data['location_fr']         ?? null,
                $data['meta_title_en']       ?? null,
                $data['meta_title_fr']       ?? null,
                $data['meta_desc_en']        ?? null,
                $data['meta_desc_fr']        ?? null,
                $data['capacity']            ?? null,
                $data['approval_mode']       ?? 'auto',
                $data['theme_color']         ?? null,
                $data['registration_deadline'] ?? null,
                $data['smtp_profile_id']     ?? null,
                Auth::id(),
            ]
        );

        // Insert default section config
        $defaultSections = $this->defaultSectionConfig();
        DB::run('UPDATE events SET config_json = ? WHERE id = ?',
                [json_encode(['sections' => $defaultSections]), $id]);

        Audit::log('event_create', 'event', $id, $data['name_en']);
        Response::json(['success' => true, 'data' => ['id' => $id, 'slug' => $slug]], 201);
    }

    public function update(int $id): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $this->findEvent($id);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $fields  = [];
        $params  = [];
        $allowed = ['name_en','name_fr','date_start','date_end','location_en','location_fr',
                    'meta_title_en','meta_title_fr','meta_desc_en','meta_desc_fr',
                    'capacity','approval_mode','theme_color','registration_deadline','smtp_profile_id'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) Response::json(['success' => false, 'error' => 'No fields to update.'], 400);

        $params[] = $id;
        DB::run('UPDATE events SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

        Audit::log('event_update', 'event', $id);
        Response::json(['success' => true, 'message' => 'Event updated.']);
    }

    public function destroy(int $id): never {
        Auth::requireRole('admin');
        CSRF::verify();
        $event = $this->findEvent($id);

        DB::run('DELETE FROM events WHERE id = ?', [$id]);
        Audit::log('event_delete', 'event', $id, $event['name_en']);
        Response::json(['success' => true, 'message' => 'Event deleted.']);
    }

    public function clone(int $id): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $event = $this->findEvent($id);

        $newSlug = $event['slug'] . '-copy-' . time();
        $newId   = DB::insert(
            'INSERT INTO events (slug, name_en, name_fr, date_start, date_end, location_en, location_fr,
                                  config_json, form_schema_json, capacity, approval_mode,
                                  theme_color, smtp_profile_id, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,\'draft\',?)',
            [
                $newSlug,
                'Copy of ' . $event['name_en'],
                $event['name_fr'] ? 'Copie de ' . $event['name_fr'] : null,
                $event['date_start'],
                $event['date_end'],
                $event['location_en'],
                $event['location_fr'],
                $event['config_json'],
                $event['form_schema_json'],
                $event['capacity'],
                $event['approval_mode'],
                $event['theme_color'],
                $event['smtp_profile_id'],
                Auth::id(),
            ]
        );

        // Clone FAQs
        $faqs = DB::all('SELECT * FROM faqs WHERE event_id = ?', [$id]);
        foreach ($faqs as $faq) {
            DB::run('INSERT INTO faqs (event_id, question_en, question_fr, answer_en, answer_fr, sort_order)
                     VALUES (?,?,?,?,?,?)',
                    [$newId, $faq['question_en'], $faq['question_fr'], $faq['answer_en'], $faq['answer_fr'], $faq['sort_order']]);
        }

        // Clone email templates
        $templates = DB::all('SELECT * FROM email_templates WHERE event_id = ?', [$id]);
        foreach ($templates as $t) {
            DB::run('INSERT INTO email_templates (event_id, type, subject_en, subject_fr, body_en, body_fr)
                     VALUES (?,?,?,?,?,?)',
                    [$newId, $t['type'], $t['subject_en'], $t['subject_fr'], $t['body_en'], $t['body_fr']]);
        }

        Audit::log('event_clone', 'event', $newId, "Cloned from event $id");
        Response::json(['success' => true, 'data' => ['id' => $newId, 'slug' => $newSlug]], 201);
    }

    public function publish(int $id): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $event = $this->findEvent($id);

        // Check for missing French content warnings (non-blocking but returned)
        $warnings = $this->checkFrenchWarnings($event);

        DB::run("UPDATE events SET status = 'published' WHERE id = ?", [$id]);
        Audit::log('event_publish', 'event', $id);
        Response::json(['success' => true, 'message' => 'Event published.', 'warnings' => $warnings]);
    }

    public function unpublish(int $id): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $this->findEvent($id);
        DB::run("UPDATE events SET status = 'draft' WHERE id = ?", [$id]);
        Audit::log('event_unpublish', 'event', $id);
        Response::json(['success' => true, 'message' => 'Event unpublished.']);
    }

    // ─── Sections ─────────────────────────────────────────────────────────────

    public function getSections(int $id): never {
        Auth::requireRole('admin', 'organizer');
        $event = $this->findEvent($id);
        $config = json_decode($event['config_json'] ?? '{}', true);
        Response::json(['success' => true, 'data' => $config['sections'] ?? $this->defaultSectionConfig()]);
    }

    public function updateSections(int $id): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $event  = $this->findEvent($id);
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!isset($data['sections']) || !is_array($data['sections'])) {
            Response::json(['success' => false, 'error' => 'sections array is required.'], 422);
        }

        $config = json_decode($event['config_json'] ?? '{}', true);
        $config['sections'] = $data['sections'];

        DB::run('UPDATE events SET config_json = ? WHERE id = ?', [json_encode($config), $id]);
        Audit::log('sections_update', 'event', $id);
        Response::json(['success' => true, 'message' => 'Sections updated.']);
    }

    // ─── FAQs ─────────────────────────────────────────────────────────────────

    public function listFaqs(int $id): never {
        Auth::requireRole('admin', 'organizer');
        $this->findEvent($id);
        $faqs = DB::all('SELECT * FROM faqs WHERE event_id = ? ORDER BY sort_order ASC', [$id]);
        Response::json(['success' => true, 'data' => $faqs]);
    }

    public function createFaq(int $id): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $this->findEvent($id);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = new Validator($data);
        $v->required('question_en', 'Question (EN)')->required('answer_en', 'Answer (EN)');
        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);

        $maxOrder = DB::row('SELECT MAX(sort_order) as max FROM faqs WHERE event_id = ?', [$id])['max'] ?? 0;

        $faqId = DB::insert(
            'INSERT INTO faqs (event_id, question_en, question_fr, answer_en, answer_fr, sort_order)
             VALUES (?,?,?,?,?,?)',
            [$id, $data['question_en'], $data['question_fr'] ?? null,
             $data['answer_en'], $data['answer_fr'] ?? null, $maxOrder + 1]
        );

        Audit::log('faq_create', 'faq', $faqId);
        Response::json(['success' => true, 'data' => ['id' => $faqId]], 201);
    }

    public function updateFaq(int $id, int $faqId): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $faq = DB::row('SELECT * FROM faqs WHERE id = ? AND event_id = ?', [$faqId, $id]);
        if (!$faq) Response::json(['success' => false, 'error' => 'FAQ not found.'], 404);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        DB::run('UPDATE faqs SET question_en = ?, question_fr = ?, answer_en = ?, answer_fr = ?, sort_order = ? WHERE id = ?',
                [$data['question_en'] ?? $faq['question_en'],
                 $data['question_fr'] ?? $faq['question_fr'],
                 $data['answer_en']   ?? $faq['answer_en'],
                 $data['answer_fr']   ?? $faq['answer_fr'],
                 $data['sort_order']  ?? $faq['sort_order'],
                 $faqId]);

        Audit::log('faq_update', 'faq', $faqId);
        Response::json(['success' => true, 'message' => 'FAQ updated.']);
    }

    public function deleteFaq(int $id, int $faqId): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $faq = DB::row('SELECT id FROM faqs WHERE id = ? AND event_id = ?', [$faqId, $id]);
        if (!$faq) Response::json(['success' => false, 'error' => 'FAQ not found.'], 404);
        DB::run('DELETE FROM faqs WHERE id = ?', [$faqId]);
        Audit::log('faq_delete', 'faq', $faqId);
        Response::json(['success' => true, 'message' => 'FAQ deleted.']);
    }

    // ─── Form Schema ──────────────────────────────────────────────────────────

    public function getSchema(int $id): never {
        Auth::requireRole('admin', 'organizer');
        $event  = $this->findEvent($id);
        $schema = json_decode($event['form_schema_json'] ?? '[]', true);
        Response::json(['success' => true, 'data' => $schema]);
    }

    public function updateSchema(int $id): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $this->findEvent($id);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!isset($data['schema']) || !is_array($data['schema'])) {
            Response::json(['success' => false, 'error' => 'schema array is required.'], 422);
        }

        DB::run('UPDATE events SET form_schema_json = ? WHERE id = ?',
                [json_encode($data['schema']), $id]);

        Audit::log('schema_update', 'event', $id);
        Response::json(['success' => true, 'message' => 'Form schema saved.']);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function findEvent(int $id): array {
        $event = DB::row('SELECT * FROM events WHERE id = ?', [$id]);
        if (!$event) Response::json(['success' => false, 'error' => 'Event not found.'], 404);
        if (Auth::role() === 'organizer' && $event['created_by'] !== Auth::id()) {
            Response::json(['success' => false, 'error' => 'Forbidden.'], 403);
        }
        return $event;
    }

    private function validateEventData(array $data): void {
        $v = (new Validator($data))
            ->required('name_en', 'Event Name (EN)')
            ->required('date_start', 'Start Date')
            ->required('date_end', 'End Date')
            ->in('approval_mode', ['auto', 'manual']);
        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);
    }

    private function defaultSectionConfig(): array {
        return [
            ['key' => 'hero',         'enabled' => true,  'locked' => true,  'order' => 1,  'content_en' => '', 'content_fr' => ''],
            ['key' => 'key_info',     'enabled' => true,  'locked' => false, 'order' => 2,  'content_en' => '', 'content_fr' => ''],
            ['key' => 'event_details','enabled' => true,  'locked' => true,  'order' => 3,  'content_en' => '', 'content_fr' => ''],
            ['key' => 'agenda',       'enabled' => false, 'locked' => false, 'order' => 4,  'content_en' => '', 'content_fr' => ''],
            ['key' => 'faqs',         'enabled' => true,  'locked' => false, 'order' => 5,  'content_en' => '', 'content_fr' => ''],
            ['key' => 'form',         'enabled' => true,  'locked' => true,  'order' => 6,  'content_en' => '', 'content_fr' => ''],
            ['key' => 'contact',      'enabled' => true,  'locked' => true,  'order' => 7,  'content_en' => '', 'content_fr' => ''],
        ];
    }

    private function checkFrenchWarnings(array $event): array {
        $warnings = [];
        if (!$event['name_fr'])     $warnings[] = 'Event name (FR) is missing.';
        if (!$event['location_fr']) $warnings[] = 'Location (FR) is missing.';

        $config   = json_decode($event['config_json'] ?? '{}', true);
        $sections = $config['sections'] ?? [];
        foreach ($sections as $s) {
            if ($s['enabled'] && empty($s['content_fr'])) {
                $warnings[] = "Section '{$s['key']}' has no French content.";
            }
        }
        return $warnings;
    }
}
