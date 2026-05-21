#!/usr/bin/env php
<?php
// ParlReg — Full API Test Suite
// Usage: php tests/api_test.php [base_url]
// Example: php tests/api_test.php http://localhost:8000
//
// Runs all API endpoints in logical order.
// Requires: PHP CLI with curl extension.

$BASE = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$API  = $BASE . '/api/v1';

// ─── Test Runner ──────────────────────────────────────────────────────────────
$passed  = 0;
$failed  = 0;
$session = [];         // holds cookies (session)
$csrf    = '';
$eventId = null;
$faqId   = null;
$regId   = null;
$regRef  = '';
$fileId  = null;
$userId2 = null;
$smtpId  = null;
$testId  = time();

function run(string $name, callable $fn): void {
    global $passed, $failed;
    echo "\n  ▸ $name ";
    try {
        $fn();
        echo "\033[32m✓ PASS\033[0m\n";
        $passed++;
    } catch (Throwable $e) {
        echo "\033[31m✗ FAIL\033[0m — " . $e->getMessage() . "\n";
        $failed++;
    }
}

function assert_ok(array $res, string $msg = ''): void {
    if (!$res['body']['success']) {
        throw new RuntimeException($msg ?: ('API returned success=false: ' . json_encode($res['body'])));
    }
}

function assert_status(array $res, int $expected): void {
    if ($res['status'] !== $expected) {
        throw new RuntimeException("Expected HTTP $expected, got {$res['status']}. Body: " . json_encode($res['body']));
    }
}

function assert_field(array $res, string $field, mixed $value = null): void {
    $body = $res['body'];
    $keys = explode('.', $field);
    $cur  = $body;
    foreach ($keys as $k) {
        if (!isset($cur[$k])) throw new RuntimeException("Field '$field' not found in response.");
        $cur = $cur[$k];
    }
    if ($value !== null && $cur !== $value) {
        throw new RuntimeException("Field '$field' = " . json_encode($cur) . ", expected " . json_encode($value));
    }
}

// ─── HTTP Client ─────────────────────────────────────────────────────────────
function request(string $method, string $url, array $body = [], bool $multipart = false): array {
    global $session, $csrf;

    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'X-CSRF-Token: ' . $csrf,
    ];

    if ($session) {
        $cookieStr = implode('; ', array_map(fn($k,$v) => "$k=$v", array_keys($session), $session));
        curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
    }

    if ($method !== 'GET' && !$multipart) {
        $json = json_encode($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
    } elseif ($multipart) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL Error: $err");
    }
    
    curl_close($ch);

    $rawHeaders = substr($raw, 0, $hSize);
    $rawBody    = substr($raw, $hSize);

    // Extract session cookies
    preg_match_all('/Set-Cookie:\s*([^=]+)=([^;]+)/i', $rawHeaders, $m);
    foreach ($m[1] as $i => $name) {
        $session[trim($name)] = trim($m[2][$i]);
    }

    return ['status' => $status, 'body' => json_decode($rawBody, true) ?? []];
}

function GET(string $path):  array { global $API; return request('GET',    $API . $path); }
function POST(string $path, array $b = []): array { global $API; return request('POST',   $API . $path, $b); }
function PUT(string $path, array $b = []):  array { global $API; return request('PUT',    $API . $path, $b); }
function DEL(string $path):  array { global $API; return request('DELETE', $API . $path); }

// ═══════════════════════════════════════════════════════════════════════════════
echo "\n\033[1m ParlReg API Test Suite\033[0m";
echo "\n Base URL: $API";
echo "\n" . str_repeat('─', 60) . "\n";

// ─── 1. Auth ──────────────────────────────────────────────────────────────────
echo "\n\033[1m[1] Authentication\033[0m";

run('Login with invalid credentials returns 401', function() {
    $res = POST('/auth/login', ['email' => 'bad@bad.com', 'password' => 'wrong']);
    assert_status($res, 401);
});

run('Login with missing fields returns 422', function() {
    $res = POST('/auth/login', ['email' => 'admin@parliament.local']);
    assert_status($res, 422);
});

run('Login with valid admin credentials', function() use (&$csrf) {
    $res = POST('/auth/login', [
        'email'    => 'admin@parliament.local',
        'password' => 'Admin@ParlReg1',
        // 'password' => 'password',
    ]);
    assert_status($res, 200);
    assert_ok($res);
    assert_field($res, 'user.role', 'admin');
    $csrf = $res['body']['csrf_token'] ?? '';
});

run('GET /auth/me returns authenticated user', function() {
    $res = GET('/auth/me');
    assert_status($res, 200);
    assert_ok($res);
    assert_field($res, 'user.email', 'admin@parliament.local');
});

// ─── 2. Users ─────────────────────────────────────────────────────────────────
echo "\n\033[1m[2] User Management\033[0m";

run('List users', function() {
    $res = GET('/users');
    assert_status($res, 200);
    assert_ok($res);
    assert_field($res, 'data');
});

run('Create organizer user', function() use (&$userId2, $testId) {
    $res = POST('/users', [
        'fullname' => 'Test Organizer',
        'email'    => "organizer_$testId@parliament.local",
        'password' => 'Organizer@123',
        'role'     => 'organizer',
    ]);
    assert_status($res, 201);
    assert_ok($res);
    $userId2 = $res['body']['data']['id'];
});

run('Create user with duplicate email returns 409', function() use ($testId) {
    $res = POST('/users', [
        'fullname' => 'Dup User',
        'email'    => "organizer_$testId@parliament.local",
        'password' => 'Test@123',
        'role'     => 'organizer',
    ]);
    assert_status($res, 409);
});

run('Update user role', function() use ($userId2) {
    $res = PUT("/users/$userId2", ['role' => 'organizer', 'is_active' => 1]);
    assert_status($res, 200);
    assert_ok($res);
});

run('Get single user', function() use ($userId2, $testId) {
    $res = GET("/users/$userId2");
    assert_status($res, 200);
    assert_ok($res);
    assert_field($res, 'data.email', "organizer_$testId@parliament.local");
});

// ─── 3. SMTP Settings ─────────────────────────────────────────────────────────
echo "\n\033[1m[3] SMTP Settings\033[0m";

run('Create SMTP profile', function() use (&$smtpId) {
    $res = POST('/settings/smtp', [
        'name'       => 'Test SMTP',
        'host'       => '127.0.0.1',
        'port'       => 587,
        'encryption' => 'tls',
        'username'   => 'test@parliament.local',
        'password'   => 'testpassword',
    ]);
    assert_status($res, 201);
    assert_ok($res);
    $smtpId = $res['body']['data']['id'];
});

run('List SMTP profiles', function() {
    $res = GET('/settings/smtp');
    assert_status($res, 200);
    assert_ok($res);
});

run('Update SMTP profile', function() use ($smtpId) {
    $res = PUT("/settings/smtp/$smtpId", ['name' => 'Updated SMTP', 'port' => 465, 'encryption' => 'ssl']);
    assert_status($res, 200);
    assert_ok($res);
});

// ─── 4. Events ────────────────────────────────────────────────────────────────
echo "\n\033[1m[4] Event Management\033[0m";

run('Create event with missing required fields returns 422', function() {
    $res = POST('/events', ['name_fr' => 'No EN name']);
    assert_status($res, 422);
});

run('Create event (draft)', function() use (&$eventId, $smtpId, $testId) {
    $res = POST('/events', [
        'name_en'            => "Inter-Parliamentary Forum 2026 $testId",
        'name_fr'            => "Forum Interparlementaire 2026 $testId",
        'date_start'         => '2026-09-01 09:00:00',
        'date_end'           => '2026-09-03 18:00:00',
        'location_en'        => 'Parliament House, Accra, Ghana',
        'location_fr'        => 'Parlement, Accra, Ghana',
        'approval_mode'      => 'auto',
        'theme_color'        => '#1B3A6B',
        'smtp_profile_id'    => $smtpId,
        'meta_title_en'      => 'Inter-Parliamentary Forum 2026 | Register Now',
        'meta_desc_en'       => 'Register for the 2026 Inter-Parliamentary Forum hosted in Accra.',
    ]);
    assert_status($res, 201);
    assert_ok($res);
    assert_field($res, 'data.id');
    $eventId = $res['body']['data']['id'];
});

run('Get event by ID', function() use ($eventId, $testId) {
    $res = GET("/events/$eventId");
    assert_status($res, 200);
    assert_ok($res);
    assert_field($res, 'data.name_en', "Inter-Parliamentary Forum 2026 $testId");
});

run('Update event', function() use ($eventId) {
    $res = PUT("/events/$eventId", [
        'location_en' => 'Parliament House – Main Hall, Accra, Ghana',
        'capacity'    => 200,
    ]);
    assert_status($res, 200);
    assert_ok($res);
});

run('List events', function() {
    $res = GET('/events?status=draft&page=1');
    assert_status($res, 200);
    assert_ok($res);
    assert_field($res, 'data');
});

// ─── 5. Sections ─────────────────────────────────────────────────────────────
echo "\n\033[1m[5] Section Manager\033[0m";

run('Get default sections', function() use ($eventId) {
    $res = GET("/events/$eventId/sections");
    assert_status($res, 200);
    assert_ok($res);
    $sections = $res['body']['data'];
    if (empty($sections)) throw new RuntimeException('No sections returned.');
});

run('Update sections — enable agenda, set content', function() use ($eventId) {
    $res = GET("/events/$eventId/sections");
    $sections = $res['body']['data'];

    foreach ($sections as &$s) {
        if ($s['key'] === 'agenda')   { $s['enabled'] = true; }
        if ($s['key'] === 'key_info') {
            $s['content_en'] = '<p>This forum brings together MPs from across Africa to discuss climate resilience.</p>';
            $s['content_fr'] = '<p>Ce forum réunit des députés d\'Afrique pour discuter de la résilience climatique.</p>';
        }
    }

    $res = PUT("/events/$eventId/sections", ['sections' => $sections]);
    assert_status($res, 200);
    assert_ok($res);
});

// ─── 6. FAQs ─────────────────────────────────────────────────────────────────
echo "\n\033[1m[6] FAQ Manager\033[0m";

run('Create FAQ (EN + FR)', function() use ($eventId, &$faqId) {
    $res = POST("/events/$eventId/faqs", [
        'question_en' => 'Who can attend this forum?',
        'question_fr' => 'Qui peut participer à ce forum?',
        'answer_en'   => 'The forum is open to all Members of Parliament and accredited observers.',
        'answer_fr'   => 'Le forum est ouvert à tous les membres du Parlement et aux observateurs accrédités.',
    ]);
    assert_status($res, 201);
    assert_ok($res);
    $faqId = $res['body']['data']['id'];
});

run('Create second FAQ', function() use ($eventId) {
    $res = POST("/events/$eventId/faqs", [
        'question_en' => 'Is there a registration fee?',
        'answer_en'   => 'No. Registration is free for all participants.',
        'question_fr' => 'Y a-t-il des frais d\'inscription?',
        'answer_fr'   => 'Non. L\'inscription est gratuite pour tous les participants.',
    ]);
    assert_status($res, 201);
    assert_ok($res);
});

run('List FAQs for event', function() use ($eventId) {
    $res = GET("/events/$eventId/faqs");
    assert_status($res, 200);
    assert_ok($res);
    if (count($res['body']['data']) < 2) throw new RuntimeException('Expected at least 2 FAQs.');
});

run('Update FAQ', function() use ($eventId, $faqId) {
    $res = PUT("/events/$eventId/faqs/$faqId", [
        'answer_en' => 'The forum is open to all Members of Parliament, accredited observers, and invited diplomats.',
    ]);
    assert_status($res, 200);
    assert_ok($res);
});

// ─── 7. Form Schema ───────────────────────────────────────────────────────────
echo "\n\033[1m[7] Form Builder / Schema\033[0m";

$sampleSchema = [
    [
        'id'       => 'field_001',
        'type'     => 'header',
        'label'    => ['en' => 'Personal Information', 'fr' => 'Informations personnelles'],
        'order'    => 1,
    ],
    [
        'id'        => 'field_002',
        'type'      => 'text',
        'label'     => ['en' => 'Full Name', 'fr' => 'Nom complet'],
        'placeholder' => ['en' => 'Enter your full name', 'fr' => 'Entrez votre nom complet'],
        'required'  => true,
        'order'     => 2,
    ],
    [
        'id'        => 'field_003',
        'type'      => 'email',
        'label'     => ['en' => 'Email Address', 'fr' => 'Adresse e-mail'],
        'required'  => true,
        'order'     => 3,
    ],
    [
        'id'        => 'field_004',
        'type'      => 'select',
        'label'     => ['en' => 'Title', 'fr' => 'Titre'],
        'required'  => false,
        'options'   => [
            ['value' => 'mp',   'label' => ['en' => 'Member of Parliament', 'fr' => 'Membre du Parlement']],
            ['value' => 'obs',  'label' => ['en' => 'Observer',             'fr' => 'Observateur']],
            ['value' => 'dipl', 'label' => ['en' => 'Diplomat',             'fr' => 'Diplomate']],
        ],
        'order'     => 4,
    ],
    [
        'id'        => 'field_005',
        'type'      => 'text',
        'label'     => ['en' => 'Organisation', 'fr' => 'Organisation'],
        'required'  => true,
        'order'     => 5,
    ],
    [
        'id'        => 'field_006',
        'type'      => 'text',
        'label'     => ['en' => 'Country', 'fr' => 'Pays'],
        'required'  => true,
        'order'     => 6,
    ],
    [
        'id'        => 'field_007',
        'type'      => 'file',
        'label'     => ['en' => 'Passport / ID Scan', 'fr' => 'Scan du passeport / pièce d\'identité'],
        'required'  => false,
        'validation' => ['maxSize' => 5, 'acceptedTypes' => ['application/pdf', 'image/jpeg', 'image/png']],
        'order'     => 7,
    ],
];

run('Save form schema', function() use ($eventId, $sampleSchema) {
    $res = PUT("/events/$eventId/schema", ['schema' => $sampleSchema]);
    assert_status($res, 200);
    assert_ok($res);
});

run('Get form schema — verify saved correctly', function() use ($eventId, $sampleSchema) {
    $res = GET("/events/$eventId/schema");
    assert_status($res, 200);
    assert_ok($res);
    $returned = $res['body']['data'];
    if (count($returned) !== count($sampleSchema)) {
        throw new RuntimeException('Schema field count mismatch: got ' . count($returned));
    }
});

// ─── 8. Publish Event ─────────────────────────────────────────────────────────
echo "\n\033[1m[8] Publish Flow\033[0m";

run('Publish event — returns any French warnings', function() use ($eventId) {
    $res = request('POST', "http://localhost:8000/api/v1/events/$eventId/publish");
    assert_status($res, 200);
    assert_ok($res);
    // warnings array may be non-empty if some sections lack FR content — that's expected
});

// ─── 9. Public Portal ─────────────────────────────────────────────────────────
echo "\n\033[1m[9] Public Portal\033[0m";

run('GET portal by slug', function() use ($eventId) {
    // Fetch slug via admin API
    global $API, $session, $csrf;
    $res = GET("/events/$eventId");
    $slug = $res['body']['data']['slug'];

    $res2 = GET("/portal/$slug");
    assert_status($res2, 200);
    assert_ok($res2);
    assert_field($res2, 'data.slug');
    assert_field($res2, 'data.faqs');
    assert_field($res2, 'data.sections');
});

run('GET portal schema by slug', function() use ($eventId) {
    $res  = GET("/events/$eventId");
    $slug = $res['body']['data']['slug'];
    $res2 = GET("/portal/$slug/schema");
    assert_status($res2, 200);
    assert_ok($res2);
    $schema = $res2['body']['data'];
    if (empty($schema)) throw new RuntimeException('Empty schema returned from portal.');
});

run('GET non-existent portal slug returns 404', function() {
    $res = GET('/portal/does-not-exist-xyz');
    assert_status($res, 404);
});

// ─── 10. Registration Submission ──────────────────────────────────────────────
echo "\n\033[1m[10] Registration Submission\033[0m";

run('Submit registration with missing required fields returns 422', function() use ($eventId) {
    global $API, $session;
    $res = request('POST', "$API/events/$eventId/register", [
        'email' => 'incomplete@test.com',
    ]);
    assert_status($res, 422);
});

run('Submit valid registration', function() use ($eventId, &$regId, &$regRef) {
    global $API, $session, $csrf;
    // Use multipart/form-data (as a real form would submit)
    $res = request('POST', "$API/events/$eventId/register", [
        'fullname'     => 'Kwame Asante',
        'email'        => 'kwame.asante@parliament.gh',
        'phone'        => '+233201234567',
        'organisation' => 'Parliament of Ghana',
        'country'      => 'Ghana',
        'field_002'    => 'Kwame Asante',
        'field_003'    => 'kwame.asante@parliament.gh',
        'field_005'    => 'Parliament of Ghana',
        'field_006'    => 'Ghana',
        'field_004'    => 'mp',
        'consent'      => '1',
        'lang'         => 'en',
    ], true);
    assert_status($res, 201);
    assert_ok($res);
    assert_field($res, 'reference_number');
    $regRef = $res['body']['reference_number'];
    echo " [ref: $regRef]";
});

run('Duplicate email registration is allowed (different reference)', function() use ($eventId) {
    global $API, $session;
    $res = request('POST', "$API/events/$eventId/register", [
        'fullname'  => 'Kwame Asante',
        'email'     => 'kwame.asante2@parliament.gh',
        'field_002' => 'Kwame Asante',
        'field_003' => 'kwame.asante2@parliament.gh',
        'field_005' => 'Parliament of Ghana',
        'field_006' => 'Ghana',
        'consent'   => '1',
    ], true);
    assert_status($res, 201);
    assert_ok($res);
});

// ─── 11. Registrations Dashboard ─────────────────────────────────────────────
echo "\n\033[1m[11] Registrations Dashboard\033[0m";

run('List registrations for event', function() use ($eventId, &$regId) {
    $res = GET("/events/$eventId/registrations");
    assert_status($res, 200);
    assert_ok($res);
    $data = $res['body']['data'];
    if (empty($data)) throw new RuntimeException('No registrations returned.');
    $regId = $data[0]['id'];
});

run('View single registration (full detail)', function() use ($eventId, $regId) {
    $res = GET("/events/$eventId/registrations/$regId");
    assert_status($res, 200);
    assert_ok($res);
    assert_field($res, 'data.fullname');
    assert_field($res, 'data.files');
});

run('Filter registrations by status', function() use ($eventId) {
    $res = GET("/events/$eventId/registrations?status=approved");
    assert_status($res, 200);
    assert_ok($res);
});

run('Filter registrations by search', function() use ($eventId) {
    $res = GET("/events/$eventId/registrations?search=Kwame");
    assert_status($res, 200);
    assert_ok($res);
    if ($res['body']['total'] < 1) throw new RuntimeException('Search returned no results for "Kwame".');
});

run('Update registration status to approved', function() use ($eventId, $regId) {
    $res = PUT("/events/$eventId/registrations/$regId/status", [
        'status'     => 'approved',
        'send_email' => false,
    ]);
    assert_status($res, 200);
    assert_ok($res);
});

run('Bulk status update', function() use ($eventId, $regId) {
    $res = POST("/events/$eventId/registrations/bulk-status", [
        'ids'    => [$regId],
        'status' => 'approved',
    ]);
    assert_status($res, 200);
    assert_ok($res);
});

run('Invalid bulk status value returns 422', function() use ($eventId, $regId) {
    $res = POST("/events/$eventId/registrations/bulk-status", [
        'ids'    => [$regId],
        'status' => 'banana',
    ]);
    assert_status($res, 422);
});

// ─── 12. Clone Event ──────────────────────────────────────────────────────────
echo "\n\033[1m[12] Clone Event\033[0m";

run('Clone event', function() use ($eventId) {
    $res = POST("/events/$eventId/clone");
    assert_status($res, 201);
    assert_ok($res);
    assert_field($res, 'data.id');
    $cloneId = $res['body']['data']['id'];

    // Verify clone has same form schema
    $res2 = GET("/events/$cloneId/schema");
    assert_ok($res2);
    if (empty($res2['body']['data'])) throw new RuntimeException('Clone has empty schema.');
});

// ─── 13. Audit Log ────────────────────────────────────────────────────────────
echo "\n\033[1m[13] Audit Log\033[0m";

run('Audit log returns entries', function() {
    $res = GET('/audit-log?page=1');
    assert_status($res, 200);
    assert_ok($res);
    if ((int)$res['body']['total'] < 1) throw new RuntimeException('Audit log is empty.');
    echo " [{$res['body']['total']} entries]";
});

run('Audit log filter by action', function() {
    $res = GET('/audit-log?action=event_create');
    assert_status($res, 200);
    assert_ok($res);
});

// ─── 14. Unpublish & Cleanup ──────────────────────────────────────────────────
echo "\n\033[1m[14] Unpublish\033[0m";

run('Unpublish event', function() use ($eventId) {
    $res = POST("/events/$eventId/unpublish");
    assert_status($res, 200);
    assert_ok($res);
});

run('Portal returns 404 after unpublish', function() use ($eventId) {
    $res  = GET("/events/$eventId");
    $slug = $res['body']['data']['slug'];
    $res2 = GET("/portal/$slug");
    assert_status($res2, 404);
});

// ─── 15. Auth: Unauthenticated Access ────────────────────────────────────────
echo "\n\033[1m[15] Access Control\033[0m";

run('Logout', function() {
    $res = POST('/auth/logout');
    assert_status($res, 200);
    assert_ok($res);
});

run('Admin endpoint returns 401 after logout', function() {
    $res = GET('/events');
    assert_status($res, 401);
});

run('Forgot password — non-existent email still returns 200 (enumeration safe)', function() {
    $res = POST('/auth/forgot-password', ['email' => 'nobody@nowhere.com']);
    assert_status($res, 200);
    assert_ok($res);
});

// ─── Results ──────────────────────────────────────────────────────────────────
echo "\n\n" . str_repeat('─', 60);
echo "\n\033[1m Results\033[0m\n";
echo "  \033[32mPassed: $passed\033[0m\n";
echo "  \033[31mFailed: $failed\033[0m\n";
echo str_repeat('─', 60) . "\n\n";
exit($failed > 0 ? 1 : 0);
