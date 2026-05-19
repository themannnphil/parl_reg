<?php
// ParlReg — Front Controller & API Router
// All requests enter here via .htaccess RewriteRule

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Never expose errors in output

// ─── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../app/bootstrap.php';

// ─── Session ──────────────────────────────────────────────────────────────────
Auth::start();

// ─── Security Headers ─────────────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (APP_ENV === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ─── CORS (dev only) ──────────────────────────────────────────────────────────
if (APP_ENV === 'development') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ─── Routing ──────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

// Strip API prefix
$prefix = '/api/v1';
$path   = str_starts_with($uri, $prefix) ? substr($uri, strlen($prefix)) : $uri;
$path   = $path ?: '/';

// Segment split
$segments = array_values(array_filter(explode('/', $path)));

try {
    route($method, $path, $segments);
} catch (Throwable $e) {
    Logger::error($e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
    Response::json(['success' => false, 'error' => 'An unexpected error occurred.'], 500);
}

// ─── Route Dispatcher ─────────────────────────────────────────────────────────
function route(string $method, string $path, array $seg): never {
    $auth  = new AuthController();
    $event = new EventController();
    $reg   = new RegistrationController();
    $user  = new UserController();
    $set   = new SettingsController();
    $portal = new PortalController();

    // ── Auth ─────────────────────────────────────────────────────────────────
    if ($method === 'POST' && $path === '/auth/login')           $auth->login();
    if ($method === 'POST' && $path === '/auth/logout')          $auth->logout();
    if ($method === 'GET'  && $path === '/auth/me')              $auth->me();
    if ($method === 'POST' && $path === '/auth/forgot-password') $auth->forgotPassword();
    if ($method === 'POST' && $path === '/auth/reset-password')  $auth->resetPassword();

    // ── Public Portal ────────────────────────────────────────────────────────
    if ($method === 'GET' && isset($seg[0], $seg[1]) && $seg[0] === 'portal') {
        if (isset($seg[2]) && $seg[2] === 'schema') $portal->getSchema($seg[1]);
        $portal->getEvent($seg[1]);
    }

    // ── Events ───────────────────────────────────────────────────────────────
    if ($method === 'GET'    && $path === '/events')             $event->index();
    if ($method === 'POST'   && $path === '/events')             $event->store();

    if (isset($seg[0], $seg[1]) && $seg[0] === 'events' && is_numeric($seg[1])) {
        $eid = (int)$seg[1];
        $sub = $seg[2] ?? null;

        if ($method === 'GET'    && !$sub)                          $event->show($eid);
        if ($method === 'PUT'    && !$sub)                          $event->update($eid);
        if ($method === 'DELETE' && !$sub)                          $event->destroy($eid);
        if ($method === 'POST'   && $sub === 'clone')               $event->clone($eid);
        if ($method === 'POST'   && $sub === 'publish')             $event->publish($eid);
        if ($method === 'POST'   && $sub === 'unpublish')           $event->unpublish($eid);
        if ($method === 'GET'    && $sub === 'sections')            $event->getSections($eid);
        if ($method === 'PUT'    && $sub === 'sections')            $event->updateSections($eid);
        if ($method === 'GET'    && $sub === 'schema')              $event->getSchema($eid);
        if ($method === 'PUT'    && $sub === 'schema')              $event->updateSchema($eid);

        // FAQs
        if ($method === 'GET'    && $sub === 'faqs')                $event->listFaqs($eid);
        if ($method === 'POST'   && $sub === 'faqs')                $event->createFaq($eid);
        if ($sub === 'faqs' && isset($seg[3]) && is_numeric($seg[3])) {
            $fid = (int)$seg[3];
            if ($method === 'PUT')    $event->updateFaq($eid, $fid);
            if ($method === 'DELETE') $event->deleteFaq($eid, $fid);
        }

        // Registrations (admin)
        if ($method === 'GET'    && $sub === 'registrations')       $reg->index($eid);
        if ($method === 'POST'   && $sub === 'register')            $reg->submit($eid);
        if ($sub === 'registrations') {
            if (isset($seg[3])) {
                if ($seg[3] === 'export')       $reg->export($eid);
                if ($seg[3] === 'bulk-status')  $reg->bulkStatus($eid);
                if (is_numeric($seg[3])) {
                    $rid = (int)$seg[3];
                    if ($method === 'GET') $reg->show($eid, $rid);
                    if ($method === 'PUT' && ($seg[4] ?? '') === 'status') $reg->updateStatus($eid, $rid);
                }
            }
        }
    }

    // ── Users ────────────────────────────────────────────────────────────────
    if ($method === 'GET'    && $path === '/users')              $user->index();
    if ($method === 'POST'   && $path === '/users')              $user->store();
    if (isset($seg[0], $seg[1]) && $seg[0] === 'users' && is_numeric($seg[1])) {
        $uid = (int)$seg[1];
        if ($method === 'GET')    $user->show($uid);
        if ($method === 'PUT')    $user->update($uid);
        if ($method === 'DELETE') $user->destroy($uid);
    }

    // ── Settings ─────────────────────────────────────────────────────────────
    if ($method === 'GET'    && $path === '/settings/smtp')                  $set->listSmtp();
    if ($method === 'POST'   && $path === '/settings/smtp')                  $set->createSmtp();
    if ($method === 'GET'    && $path === '/audit-log')                      $set->auditLog();
    if (isset($seg[0], $seg[1], $seg[2]) && $seg[0] === 'settings' && $seg[1] === 'smtp' && is_numeric($seg[2])) {
        $sid = (int)$seg[2];
        if ($method === 'PUT')    $set->updateSmtp($sid);
        if ($method === 'DELETE') $set->deleteSmtp($sid);
        if ($method === 'POST' && ($seg[3] ?? '') === 'test') $set->testSmtp($sid);
    }

    // ── File Download ─────────────────────────────────────────────────────────
    if ($method === 'GET' && isset($seg[0], $seg[1]) && $seg[0] === 'files' && is_numeric($seg[1])) {
        $reg->downloadFile((int)$seg[1]);
    }


    // ── HTML Portal Page ─────────────────────────────────────────────────────
    if ($method === 'GET' && isset($seg[0], $seg[1]) && $seg[0] === 'events' && !is_numeric($seg[1]) && !$seg[2]) {
        $portal->renderPage($seg[1]);
    }

    // ── 404 ───────────────────────────────────────────────────────────────────
    Response::json(['success' => false, 'error' => 'Endpoint not found.'], 404);
}
