<?php
// ParlReg — Portal Controller
// GET /api/v1/portal/{slug}        — JSON data
// GET /api/v1/portal/{slug}/schema — form schema JSON
// GET /events/{slug}               — rendered HTML page

class PortalController {

    public function getEvent(string $slug): never {
        $event = $this->loadPublishedEvent($slug);
        $event['faqs']     = DB::all('SELECT question_en,question_fr,answer_en,answer_fr FROM faqs WHERE event_id=? ORDER BY sort_order', [$event['id']]);
        $event['sections'] = json_decode($event['config_json'] ?? '{}', true)['sections'] ?? [];
        $event['registrant_count'] = (int)DB::row('SELECT COUNT(*) as cnt FROM registrations WHERE event_id=?', [$event['id']])['cnt'];
        unset($event['config_json']);
        Response::json(['success' => true, 'data' => $event]);
    }

    public function getSchema(string $slug): never {
        $event  = $this->loadPublishedEvent($slug);
        $schema = json_decode($event['form_schema_json'] ?? '[]', true);
        Response::json(['success' => true, 'data' => $schema]);
    }

    public function renderPage(string $slug): never {
        $event = DB::row("SELECT * FROM events WHERE slug=? AND status='published'", [$slug]);
        if (!$event) { http_response_code(404); echo '<h1>Event not found</h1>'; exit; }

        $lang = $_COOKIE['parlreg_lang'] ?? 'en';
        $lang = in_array($lang, ['en','fr'], true) ? $lang : 'en';
        Translator::setLang($lang);

        $event['faqs']             = DB::all('SELECT * FROM faqs WHERE event_id=? ORDER BY sort_order', [$event['id']]);
        $event['registrant_count'] = (int)DB::row('SELECT COUNT(*) as cnt FROM registrations WHERE event_id=?', [$event['id']])['cnt'];
        $config = json_decode($event['config_json'] ?? '{}', true);
        $event['sections'] = $config['sections'] ?? [];

        ob_start();
        require BASE_PATH . '/app/views/portal/event.php';
        echo ob_get_clean();
        exit;
    }

    private function loadPublishedEvent(string $slug): array {
        $event = DB::row("SELECT id,slug,name_en,name_fr,date_start,date_end,
                                 location_en,location_fr,meta_title_en,meta_title_fr,
                                 meta_desc_en,meta_desc_fr,config_json,form_schema_json,
                                 status,capacity,approval_mode,theme_color,registration_deadline
                          FROM events WHERE slug=? AND status='published'", [$slug]);
        if (!$event) Response::json(['success'=>false,'error'=>'Event not found.'], 404);
        return $event;
    }
}
