<?php
// ParlReg — Registration Controller
// POST /api/v1/events/{id}/register              — public form submit
// GET  /api/v1/events/{id}/registrations         — admin list
// GET  /api/v1/events/{id}/registrations/{rid}   — admin view single
// PUT  /api/v1/events/{id}/registrations/{rid}/status — update status
// POST /api/v1/events/{id}/registrations/bulk-status  — bulk status
// GET  /api/v1/events/{id}/registrations/export  — CSV export
// GET  /api/v1/registrations/{rid}/files         — list files
// GET  /api/v1/files/{fid}/download              — serve file

class RegistrationController {
    // ─── Public: Submit Registration ─────────────────────────────────────────

    public function submit(int $eventId): never {
        // Rate limit per IP
        RateLimit::check('register', RateLimit::ip(), FORM_MAX_ATTEMPTS, FORM_WINDOW_SECONDS);

        $event = DB::row("SELECT * FROM events WHERE id = ? AND status = 'published'", [$eventId]);
        if (!$event) Response::json(['success' => false, 'error' => 'Event not found or not open.'], 404);

        // Check registration deadline
        if ($event['registration_deadline'] && strtotime($event['registration_deadline']) < time()) {
            Response::json(['success' => false, 'error' => 'Registration deadline has passed.'], 410);
        }

        // Check capacity
        if ($event['capacity']) {
            $count = DB::row('SELECT COUNT(*) as cnt FROM registrations WHERE event_id = ?', [$eventId])['cnt'];
            if ($count >= $event['capacity']) {
                Response::json(['success' => false, 'error' => 'Registration is full.'], 410);
            }
        }

        // CAPTCHA verification (skip if secret not configured)
        if (RECAPTCHA_SECRET) {
            $captcha = $_POST['g-recaptcha-response'] ?? '';
            if (!$this->verifyCaptcha($captcha)) {
                Response::json(['success' => false, 'error' => 'CAPTCHA verification failed.'], 422);
            }
        }

        // Validate required fixed fields
        $post = $_POST;
        $v = (new Validator($post))
            ->required('fullname', 'Full Name')
            ->required('email',    'Email')
            ->email('email')
            ->required('consent',  'Consent');

        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);

        // Validate schema fields
        $schema = json_decode($event['form_schema_json'] ?? '[]', true);
        $schemaErrors = $this->validateSchemaFields($schema, $post, $_FILES);
        if (!empty($schemaErrors)) {
            Response::json(['success' => false, 'errors' => $schemaErrors], 422);
        }

        $refNo = $this->generateReference();

        DB::beginTransaction();
        try {
            $status = $event['approval_mode'] === 'manual' ? 'pending' : 'approved';
            $rid = DB::insert(
                'INSERT INTO registrations (event_id, fullname, email, phone, organisation, country,
                                            status, reference_no, data_json, consent_given, consent_ts, consent_ip)
                 VALUES (?,?,?,?,?,?,?,?,?,1,NOW(),?)',
                [
                    $eventId,
                    Validator::sanitize($post['fullname']),
                    strtolower(trim($post['email'])),
                    Validator::sanitize($post['phone'] ?? ''),
                    Validator::sanitize($post['organisation'] ?? ''),
                    Validator::sanitize($post['country'] ?? ''),
                    $status,
                    $refNo,
                    json_encode($this->extractSchemaData($schema, $post)),
                    RateLimit::ip(),
                ]
            );

            // Handle file uploads
            foreach ($schema as $field) {
                if ($field['type'] !== 'file') continue;
                $fkey = $field['id'];
                if (isset($_FILES[$fkey]) && $_FILES[$fkey]['error'] === UPLOAD_ERR_OK) {
                    $stored = FileHandler::store($_FILES[$fkey], $eventId, $rid, $fkey);
                    DB::run(
                        'INSERT INTO uploaded_files (registration_id, event_id, field_name, stored_filename,
                                                      original_filename, mime_type, filesize, stored_path)
                         VALUES (?,?,?,?,?,?,?,?)',
                        [$rid, $eventId, $fkey, $stored['stored_filename'], $stored['original_filename'],
                         $stored['mime_type'], $stored['filesize'], $stored['stored_path']]
                    );
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollback();
            Logger::error('Registration failed: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => 'Submission failed. Please try again.'], 500);
        }

        // Send emails (non-blocking — don't fail the registration on email error)
        $lang = $_POST['lang'] ?? 'en';
        $vars = [
            'event_name'               => $event['name_en'],
            'participant_name'         => Validator::sanitize($post['fullname']),
            'participant_email'        => $post['email'],
            'participant_organisation' => Validator::sanitize($post['organisation'] ?? ''),
            'participant_country'      => Validator::sanitize($post['country'] ?? ''),
            'reference_number'         => $refNo,
            'event_date'               => date('d F Y', strtotime($event['date_start'])),
            'event_location'           => $event['location_en'] ?? '',
            'submitted_at'             => date('d F Y H:i'),
            'registration_status'      => ucfirst($status),
        ];

        Mailer::sendTemplate('confirmation', ['email' => $post['email'], 'name' => $post['fullname']],
                             $vars, $eventId, $event['smtp_profile_id'], $lang);

        // Admin notification (uses organizer email)
        $creator = DB::row('SELECT email, fullname FROM users WHERE id = ?', [$event['created_by']]);
        if ($creator) {
            Mailer::sendTemplate('admin_notification', ['email' => $creator['email'], 'name' => $creator['fullname']],
                                 $vars, $eventId, $event['smtp_profile_id']);
        }

        Audit::log('registration_submit', 'registration', $rid, "event=$eventId ref=$refNo");

        Response::json([
            'success'          => true,
            'reference_number' => $refNo,
            'status'           => $status,
            'message'          => $status === 'pending'
                ? 'Your registration is pending approval.'
                : 'Registration successful.',
        ], 201);
    }

    // ─── Admin: List Registrations ────────────────────────────────────────────

    public function index(int $eventId): never {
        Auth::requireRole('admin', 'organizer');
        $this->checkEventAccess($eventId);

        $status  = $_GET['status']  ?? null;
        $country = $_GET['country'] ?? null;
        $search  = $_GET['search']  ?? null;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $limit   = 50;
        $offset  = ($page - 1) * $limit;

        $where  = ['event_id = ?'];
        $params = [$eventId];

        if ($status && in_array($status, ['pending','approved','rejected'], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($country) {
            $where[] = 'country = ?';
            $params[] = $country;
        }
        if ($search) {
            $where[] = '(fullname LIKE ? OR email LIKE ? OR organisation LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereStr = implode(' AND ', $where);
        $total    = DB::row("SELECT COUNT(*) as cnt FROM registrations WHERE $whereStr", $params)['cnt'];
        $rows     = DB::all("SELECT id, fullname, email, phone, organisation, country, status,
                                    reference_no, submitted_at
                             FROM registrations WHERE $whereStr
                             ORDER BY submitted_at DESC
                             LIMIT $limit OFFSET $offset", $params);

        Response::json([
            'success'     => true,
            'data'        => $rows,
            'total'       => (int)$total,
            'page'        => $page,
            'per_page'    => $limit,
            'total_pages' => ceil($total / $limit),
        ]);
    }

    public function show(int $eventId, int $rid): never {
        Auth::requireRole('admin', 'organizer');
        $this->checkEventAccess($eventId);

        $reg = DB::row('SELECT * FROM registrations WHERE id = ? AND event_id = ?', [$rid, $eventId]);
        if (!$reg) Response::json(['success' => false, 'error' => 'Registration not found.'], 404);

        $files = DB::all('SELECT id, field_name, original_filename, mime_type, filesize, uploaded_at
                          FROM uploaded_files WHERE registration_id = ?', [$rid]);

        $reg['data_json'] = json_decode($reg['data_json'] ?? '[]', true);
        $reg['files']     = $files;

        Response::json(['success' => true, 'data' => $reg]);
    }

    public function updateStatus(int $eventId, int $rid): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $this->checkEventAccess($eventId);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $v = (new Validator($data))->required('status')->in('status', ['pending','approved','rejected']);
        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);

        $reg = DB::row('SELECT * FROM registrations WHERE id = ? AND event_id = ?', [$rid, $eventId]);
        if (!$reg) Response::json(['success' => false, 'error' => 'Registration not found.'], 404);

        DB::run('UPDATE registrations SET status = ? WHERE id = ?', [$data['status'], $rid]);
        Audit::log('registration_status_update', 'registration', $rid, "status={$data['status']}");

        // Send approval/rejection email if requested
        if (in_array($data['status'], ['approved','rejected'], true) && !empty($data['send_email'])) {
            $event   = DB::row('SELECT * FROM events WHERE id = ?', [$eventId]);
            $type    = $data['status'] === 'approved' ? 'approval' : 'rejection';
            $vars    = [
                'event_name'       => $event['name_en'],
                'participant_name' => $reg['fullname'],
                'reference_number' => $reg['reference_no'],
            ];
            Mailer::sendTemplate($type, ['email' => $reg['email'], 'name' => $reg['fullname']],
                                 $vars, $eventId, $event['smtp_profile_id']);
        }

        Response::json(['success' => true, 'message' => 'Status updated.']);
    }

    public function bulkStatus(int $eventId): never {
        Auth::requireRole('admin', 'organizer');
        CSRF::verify();
        $this->checkEventAccess($eventId);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['ids']) || !is_array($data['ids'])) {
            Response::json(['success' => false, 'error' => 'ids array is required.'], 422);
        }
        $v = (new Validator($data))->required('status')->in('status', ['pending','approved','rejected']);
        if ($v->fails()) Response::json(['success' => false, 'errors' => $v->errors()], 422);

        $ids         = array_map('intval', $data['ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params       = array_merge([$data['status'], $eventId], $ids);

        DB::run("UPDATE registrations SET status = ? WHERE event_id = ? AND id IN ($placeholders)", $params);

        Audit::log('registration_bulk_status', 'event', $eventId,
                   "status={$data['status']} ids=" . implode(',', $ids));

        Response::json(['success' => true, 'message' => count($ids) . ' registrations updated.']);
    }

    // ─── Admin: CSV Export ────────────────────────────────────────────────────

    public function export(int $eventId): never {
        Auth::requireRole('admin', 'organizer');
        $this->checkEventAccess($eventId);

        $event = DB::row('SELECT name_en FROM events WHERE id = ?', [$eventId]);
        $rows  = DB::all('SELECT id, reference_no, fullname, email, phone, organisation, country,
                                 status, submitted_at, data_json
                          FROM registrations WHERE event_id = ?
                          ORDER BY submitted_at ASC', [$eventId]);

        Audit::log('registration_export', 'event', $eventId);

        $filename = 'registrations_' . Validator::slugify($event['name_en']) . '_' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8 compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Fixed headers
        $fixedHeaders = ['ID','Reference','Full Name','Email','Phone','Organisation','Country','Status','Submitted At'];
        $schemaHeaders = [];

        // Collect dynamic headers from first row
        if (!empty($rows)) {
            $firstData = json_decode($rows[0]['data_json'] ?? '[]', true);
            foreach ($firstData as $key => $value) {
                $schemaHeaders[] = $key;
            }
        }

        fputcsv($out, array_merge($fixedHeaders, $schemaHeaders));

        foreach ($rows as $row) {
            $extraData = json_decode($row['data_json'] ?? '[]', true);
            $line = [
                $row['id'],
                $row['reference_no'],
                $row['fullname'],
                $row['email'],
                $row['phone'],
                $row['organisation'],
                $row['country'],
                $row['status'],
                $row['submitted_at'],
            ];
            foreach ($schemaHeaders as $h) {
                $val = $extraData[$h] ?? '';
                $line[] = is_array($val) ? implode(', ', $val) : $val;
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    // ─── File Download ────────────────────────────────────────────────────────

    public function downloadFile(int $fileId): never {
        Auth::requireAuth();
        FileHandler::serve($fileId);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function checkEventAccess(int $eventId): void {
        $event = DB::row('SELECT created_by FROM events WHERE id = ?', [$eventId]);
        if (!$event) Response::json(['success' => false, 'error' => 'Event not found.'], 404);
        if (Auth::role() === 'organizer' && $event['created_by'] !== Auth::id()) {
            Response::json(['success' => false, 'error' => 'Forbidden.'], 403);
        }
    }

    private function validateSchemaFields(array $schema, array $post, array $files): array {
        $errors = [];
        foreach ($schema as $field) {
            if ($field['type'] === 'header') continue;
            $key   = $field['id'];
            $label = $field['label']['en'] ?? $key;

            if (!empty($field['required'])) {
                if ($field['type'] === 'file') {
                    if (!isset($files[$key]) || $files[$key]['error'] !== UPLOAD_ERR_OK) {
                        $errors[$key] = "$label is required.";
                    }
                } elseif (empty($post[$key])) {
                    $errors[$key] = "$label is required.";
                }
            }
        }
        return $errors;
    }

    private function extractSchemaData(array $schema, array $post): array {
        $data = [];
        foreach ($schema as $field) {
            if (in_array($field['type'], ['header', 'file'], true)) continue;
            $key = $field['id'];
            if (isset($post[$key])) {
                $data[$field['label']['en'] ?? $key] = is_array($post[$key])
                    ? $post[$key]
                    : Validator::sanitize($post[$key]);
            }
        }
        return $data;
    }

    private function generateReference(): string {
        return 'PARL-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
    }

    private function verifyCaptcha(string $response): bool {
        $result = file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify?secret='
            . RECAPTCHA_SECRET . '&response=' . urlencode($response)
            . '&remoteip=' . urlencode(RateLimit::ip())
        );
        $json = json_decode($result, true);
        return !empty($json['success']);
    }
}
