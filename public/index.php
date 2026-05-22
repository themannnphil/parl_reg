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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Parliamentary Services</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>

  <!-- Colleague's original CSS — untouched -->
  <link rel="stylesheet" href="parliamentaryservices.css" />
  <!-- ParlReg additions (animations, toasts, status badges) -->
  <link rel="stylesheet" href="css/parlreg.css" />

  <style>
    /* ── Additions only — never overriding colleague's styles ──────── */

    /* Smooth nav transition */
    .navbar { transition: box-shadow .25s; }
    .navbar.scrolled { box-shadow: 0 2px 16px rgba(0,0,0,.10) !important; }

    /* Hero CTA pulse on load */
    @keyframes heroPulse {
      0%   { box-shadow: 0 0 0 0 rgba(11,107,27,.35); }
      70%  { box-shadow: 0 0 0 14px rgba(11,107,27,0); }
      100% { box-shadow: 0 0 0 0 rgba(11,107,27,0); }
    }
    .btn-hero-cta { animation: heroPulse 2.2s infinite; }

    /* Hero text entrance */
    @keyframes heroFade {
      from { opacity:0; transform: translateY(28px); }
      to   { opacity:1; transform: translateY(0); }
    }
    .hero-badge-anim   { animation: heroFade .55s .1s both ease; }
    .hero-title-anim   { animation: heroFade .55s .25s both ease; }
    .hero-meta-anim    { animation: heroFade .55s .38s both ease; }
    .hero-buttons-anim { animation: heroFade .55s .50s both ease; }

    /* Lang dropdown */
    .lang-dropdown-menu {
      min-width: 110px; border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0,0,0,.10);
      border: 1px solid #e5e5e5;
    }
    .lang-dropdown-menu .dropdown-item { font-size: 13.5px; }
    .lang-dropdown-menu .dropdown-item.active { background: #edf7ef; color: #0b6b1b; font-weight: 600; }

    /* Countdown timer */
    .countdown-strip {
      background: #0c2d17; color: #fff;
      padding: 10px 0; font-size: 13px;
      text-align: center;
    }
    .countdown-unit {
      display: inline-flex; flex-direction: column;
      align-items: center; min-width: 44px;
      background: rgba(255,255,255,.10);
      border-radius: 8px; padding: 4px 8px;
      margin: 0 4px;
    }
    .countdown-num { font-size: 22px; font-weight: 700; line-height: 1; }
    .countdown-lbl { font-size: 9px; text-transform: uppercase; letter-spacing: .08em; opacity: .75; }

    /* Info card icon on hover */
    .info-card:hover .info-icon {
      background: #0b6b1b;
      color: #fff;
      transition: .3s;
    }

    /* Sticky registration bar (mobile) */
    .sticky-reg-bar {
      position: fixed; bottom: 0; left: 0; right: 0;
      background: #0b6b1b; color: #fff;
      padding: 12px 20px; z-index: 50;
      display: flex; align-items: center; justify-content: space-between;
      box-shadow: 0 -4px 20px rgba(0,0,0,.15);
      transform: translateY(100%); transition: transform .35s ease;
    }
    .sticky-reg-bar.show { transform: translateY(0); }

    /* Agenda tabs */
    .agenda-tab-btn {
      padding: 7px 18px; border-radius: 50px;
      font-size: 13px; font-weight: 500; cursor: pointer;
      border: 1.5px solid #d1e8d4; background: #fff; color: #555;
      transition: all .2s;
    }
    .agenda-tab-btn.active {
      background: #0b6b1b; color: #fff; border-color: #0b6b1b;
    }

    /* Speaker cards */
    .speaker-avatar {
      width: 56px; height: 56px; border-radius: 50%;
      background: #edf7ef; display: flex; align-items: center; justify-content: center;
      font-size: 22px; font-weight: 700; color: #0b6b1b;
      flex-shrink: 0;
    }

    /* Map embed placeholder interactive */
    .map-box { cursor: pointer; transition: background .2s; }
    .map-box:hover { background: #d9eed9; }
  </style>
</head>

<body>

<!-- ── Page loader ───────────────────────────────────────────────────── -->
<div class="ps-loader" id="pageLoader"></div>

<!-- ── Countdown strip ──────────────────────────────────────────────── -->
<div class="countdown-strip" id="countdownStrip">
  <span class="me-2 opacity-75">Registration closes in</span>
  <span class="countdown-unit"><span class="countdown-num" id="cd-d">--</span><span class="countdown-lbl">Days</span></span>
  <span class="countdown-unit"><span class="countdown-num" id="cd-h">--</span><span class="countdown-lbl">Hrs</span></span>
  <span class="countdown-unit"><span class="countdown-num" id="cd-m">--</span><span class="countdown-lbl">Min</span></span>
  <span class="countdown-unit"><span class="countdown-num" id="cd-s">--</span><span class="countdown-lbl">Sec</span></span>
  <a href="register.php" class="btn btn-sm btn-light ms-3 px-3 fw-semibold" style="border-radius:50px;font-size:12px">Register →</a>
</div>

<!-- ── Navbar — colleague's layout, language toggle wired ───────────── -->
<nav class="navbar navbar-expand-lg bg-white border-bottom py-3" id="mainNav">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#">
      <div style="width:32px;height:32px;background:#0b6b1b;border-radius:8px;display:flex;align-items:center;justify-content:center;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="white" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/></svg>
      </div>
      Parliamentary Services
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active-link" href="#">Events</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Resources</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Directory</a></li>
      </ul>

      <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
        <!-- Language switcher — now working -->
        <div class="dropdown">
          <button class="btn btn-light border btn-sm px-3 dropdown-toggle" data-bs-toggle="dropdown" id="langBtn">
            <i class="bi bi-globe me-1"></i> <span id="langLabel">FR</span>
          </button>
          <ul class="dropdown-menu lang-dropdown-menu">
            <li><a class="dropdown-item active" href="#" data-lang="en" onclick="setLang('en');return false;">🇬🇧 English</a></li>
            <li><a class="dropdown-item" href="#" data-lang="fr" onclick="setLang('fr');return false;">🇫🇷 Français</a></li>
          </ul>
        </div>
        <a href="admin/login.php" class="btn btn-success btn-sm px-4">
          <i class="bi bi-person me-1"></i>
          <span data-en="Sign In" data-fr="Connexion">Sign In</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- ── Hero — colleague's structure, animations added ───────────────── -->
<header class="hero-section">
  <div class="hero-overlay"></div>
  <div class="container position-relative hero-content">
    <div class="text-center text-white">
      <span class="hero-badge hero-badge-anim" data-en="Annual Summit 2026" data-fr="Sommet Annuel 2026">Annual Summit 2026</span>
      <h1 class="display-4 fw-bold mt-4 hero-title-anim" data-en="Global Diplomatic Forum" data-fr="Forum Diplomatique Mondial">Global Diplomatic Forum</h1>
      <div class="d-flex flex-wrap justify-content-center gap-4 mt-4 hero-meta-anim">
        <div class="hero-meta">
          <i class="bi bi-calendar3"></i>
          <span data-en="September 1 – 3, 2026" data-fr="1 – 3 septembre 2026">September 1 – 3, 2026</span>
        </div>
        <div class="hero-meta">
          <i class="bi bi-geo-alt"></i>
          <span data-en="Parliament House, Accra, Ghana" data-fr="Parlement, Accra, Ghana">Parliament House, Accra, Ghana</span>
        </div>
        <div class="hero-meta" id="spotsHero">
          <i class="bi bi-people"></i>
          <span id="spotsLabel">Loading…</span>
        </div>
      </div>
      <div class="row justify-content-center g-3 mt-4 hero-buttons-anim">
        <div class="col-12 col-sm-auto">
          <a href="register.php?event=inter-parliamentary-forum-2026" class="btn btn-success btn-lg w-100 px-4 btn-hero-cta">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18" class="me-1"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            <span data-en="Register Now" data-fr="S'inscrire">Register Now</span>
          </a>
        </div>
        <div class="col-12 col-sm-auto">
          <button class="btn btn-light btn-lg w-100 px-4" onclick="scrollToSection('agenda')">
            <span data-en="View Agenda" data-fr="Voir le programme">View Agenda</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- ── About — colleague's layout, reveal added ─────────────────────── -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="row g-4 reveal-stagger" id="aboutSection">
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100 about-card">
          <div class="card-body p-4">
            <h2 class="fw-bold mb-4" data-en="About the Forum" data-fr="À propos du Forum">About the Forum</h2>
            <p class="text-muted mb-0" data-en="The Global Diplomatic Forum serves as the premier international platform for parliamentary leaders, policy experts, and diplomatic envoys to address the most pressing challenges of our era. This year's focus centres on 'Sustainable Governance in a Digital Age,' exploring global cooperation and innovation." data-fr="Le Forum Diplomatique Mondial est la principale plateforme internationale pour les dirigeants parlementaires, les experts en politiques et les envoyés diplomatiques. Cette année, l'accent est mis sur 'La gouvernance durable à l'ère numérique'.">
              The Global Diplomatic Forum serves as the premier international platform for parliamentary leaders, policy experts, and diplomatic envoys to address the most pressing challenges of our era. This year's focus centres on "Sustainable Governance in a Digital Age," exploring global cooperation and innovation.
            </p>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="schedule-card h-100">
          <div class="d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-clock schedule-icon"></i>
            <h5 class="mb-0 fw-semibold" data-en="Duration &amp; Schedule" data-fr="Durée &amp; Programme">Duration &amp; Schedule</h5>
          </div>
          <h2 class="fw-bold mb-2" data-en="3 Days" data-fr="3 Jours">3 Days</h2>
          <p class="mb-3 text-light-emphasis" data-en="8:00 AM – 5:00 PM Daily" data-fr="8h00 – 17h00 Quotidien">8:00 AM – 5:00 PM Daily</p>
          <a href="register.php?event=inter-parliamentary-forum-2026" class="btn btn-light btn-sm mt-2 px-3 fw-semibold">
            <span data-en="Register Now →" data-fr="S'inscrire →">Register Now →</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Agenda (new section) ──────────────────────────────────────────── -->
<section class="py-5" id="agenda">
  <div class="container">
    <h2 class="fw-bold mb-2 reveal" data-en="Event Agenda" data-fr="Programme de l'événement">Event Agenda</h2>
    <p class="text-muted mb-4 reveal" data-en="Three days of high-level dialogue and collaboration." data-fr="Trois jours de dialogue de haut niveau.">Three days of high-level dialogue and collaboration.</p>

    <div class="d-flex gap-2 mb-4 flex-wrap reveal">
      <button class="agenda-tab-btn active" onclick="switchDay(1,this)" data-en="Day 1" data-fr="Jour 1">Day 1</button>
      <button class="agenda-tab-btn" onclick="switchDay(2,this)" data-en="Day 2" data-fr="Jour 2">Day 2</button>
      <button class="agenda-tab-btn" onclick="switchDay(3,this)" data-en="Day 3" data-fr="Jour 3">Day 3</button>
    </div>

    <div id="agendaContent" class="reveal">
      <!-- Day 1 -->
      <div class="agenda-day active" id="day1">
        <div class="d-flex flex-column gap-3">
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">09:00</span>
            <div><h6 class="fw-bold mb-1" data-en="Opening Ceremony" data-fr="Cérémonie d'ouverture">Opening Ceremony</h6><p class="text-muted small mb-0" data-en="Welcome address by the Speaker of Parliament and keynote by the UN Secretary-General." data-fr="Discours de bienvenue du Président du Parlement.">Welcome address by the Speaker of Parliament and keynote by the UN Secretary-General.</p></div>
          </div>
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">11:00</span>
            <div><h6 class="fw-bold mb-1" data-en="Panel: Digital Governance" data-fr="Panel: Gouvernance numérique">Panel: Digital Governance</h6><p class="text-muted small mb-0" data-en="Parliamentary leaders discuss AI, data sovereignty, and digital rights frameworks." data-fr="Les dirigeants parlementaires discutent de l'IA et de la souveraineté des données.">Parliamentary leaders discuss AI, data sovereignty, and digital rights frameworks.</p></div>
          </div>
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">14:00</span>
            <div><h6 class="fw-bold mb-1" data-en="Working Groups" data-fr="Groupes de travail">Working Groups</h6><p class="text-muted small mb-0" data-en="Thematic working groups on climate, trade, and security cooperation." data-fr="Groupes thématiques sur le climat, le commerce et la sécurité.">Thematic working groups on climate, trade, and security cooperation.</p></div>
          </div>
        </div>
      </div>
      <!-- Day 2 -->
      <div class="agenda-day d-none" id="day2">
        <div class="d-flex flex-column gap-3">
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">09:00</span>
            <div><h6 class="fw-bold mb-1">Bilateral Meetings</h6><p class="text-muted small mb-0">Scheduled diplomatic consultations between participating delegations.</p></div>
          </div>
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">11:30</span>
            <div><h6 class="fw-bold mb-1">Democracy &amp; Elections Forum</h6><p class="text-muted small mb-0">Sharing best practices in electoral integrity and democratic governance.</p></div>
          </div>
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">15:00</span>
            <div><h6 class="fw-bold mb-1">Cultural Programme</h6><p class="text-muted small mb-0">Reception and cultural showcase hosted by Parliamentary Services.</p></div>
          </div>
        </div>
      </div>
      <!-- Day 3 -->
      <div class="agenda-day d-none" id="day3">
        <div class="d-flex flex-column gap-3">
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">09:30</span>
            <div><h6 class="fw-bold mb-1">Plenary Session</h6><p class="text-muted small mb-0">Full assembly — adopting the joint declaration and communiqué.</p></div>
          </div>
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">12:00</span>
            <div><h6 class="fw-bold mb-1">Signing Ceremony</h6><p class="text-muted small mb-0">Formal signing of the Accra Declaration on Digital Governance.</p></div>
          </div>
          <div class="info-card d-flex align-items-start gap-3">
            <span class="badge-ps px-3 py-2" style="min-width:70px;text-align:center;font-size:11px">14:30</span>
            <div><h6 class="fw-bold mb-1">Closing Reception</h6><p class="text-muted small mb-0">Closing remarks and farewell reception for all delegates.</p></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Info + Map — colleague's layout, reveal added ────────────────── -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="info-card mb-4 reveal">
          <div class="d-flex gap-3">
            <div class="info-icon"><i class="bi bi-building"></i></div>
            <div>
              <h5 class="fw-bold" data-en="Accommodation" data-fr="Hébergement">Accommodation</h5>
              <p class="text-muted mb-0" data-en="Preferential rates available at partner hotels for registered delegates." data-fr="Tarifs préférentiels disponibles dans les hôtels partenaires.">Preferential rates available at partner hotels for registered delegates.</p>
            </div>
          </div>
        </div>
        <div class="info-card mb-4 reveal">
          <div class="d-flex gap-3">
            <div class="info-icon"><i class="bi bi-bus-front"></i></div>
            <div>
              <h5 class="fw-bold" data-en="Transportation" data-fr="Transport">Transportation</h5>
              <p class="text-muted mb-0" data-en="Shuttle services between the airport and Parliament House every 30 minutes." data-fr="Navettes entre l'aéroport et le Parlement toutes les 30 minutes.">Shuttle services between the airport and Parliament House every 30 minutes.</p>
            </div>
          </div>
        </div>
        <div class="info-card reveal">
          <div class="d-flex gap-3">
            <div class="info-icon"><i class="bi bi-person-vcard"></i></div>
            <div>
              <h5 class="fw-bold" data-en="Accreditation" data-fr="Accréditation">Accreditation</h5>
              <p class="text-muted mb-0" data-en="Bring a valid passport or diplomatic ID for on-site badge collection." data-fr="Apportez un passeport valide ou une pièce d'identité diplomatique.">Bring a valid passport or diplomatic ID for on-site badge collection.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-7 reveal">
        <div class="map-card shadow-sm">
          <div class="map-box text-center" onclick="openMap()">
            <div>
              <i class="bi bi-map map-icon"></i>
              <h5 class="mt-3 mb-1" data-en="Parliament House, Accra" data-fr="Parlement, Accra">Parliament House, Accra</h5>
              <p class="text-muted mb-0">Parliament Road, Osu, Accra, Ghana</p>
              <p class="text-success small mt-2 fw-semibold">
                <i class="bi bi-box-arrow-up-right me-1"></i>
                <span data-en="Click to open in Google Maps" data-fr="Cliquer pour ouvrir dans Google Maps">Click to open in Google Maps</span>
              </p>
            </div>
          </div>
          <div class="p-3 border-top">
            <button class="btn btn-outline-success w-100" onclick="openMap()">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="16" height="16" class="me-1"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/></svg>
              <span data-en="Get Directions" data-fr="Obtenir l'itinéraire">Get Directions</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Speakers (new) ─────────────────────────────────────────────────── -->
<section class="py-5">
  <div class="container">
    <h2 class="fw-bold mb-2 reveal" data-en="Featured Speakers" data-fr="Intervenants">Featured Speakers</h2>
    <p class="text-muted mb-4 reveal">Global leaders and policy experts.</p>
    <div class="row g-3 reveal-stagger" id="speakersGrid">
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="info-card h-100 d-flex align-items-center gap-3">
          <div class="speaker-avatar">KA</div>
          <div><h6 class="fw-bold mb-1 small">Dr. Kwame Asante</h6><p class="text-muted mb-0" style="font-size:11px">Speaker of Parliament, Ghana</p></div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="info-card h-100 d-flex align-items-center gap-3">
          <div class="speaker-avatar">MD</div>
          <div><h6 class="fw-bold mb-1 small">H.E. Marie Dupont</h6><p class="text-muted mb-0" style="font-size:11px">Ambassador, Assemblée Nationale</p></div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="info-card h-100 d-flex align-items-center gap-3">
          <div class="speaker-avatar">JM</div>
          <div><h6 class="fw-bold mb-1 small">John Mwangi</h6><p class="text-muted mb-0" style="font-size:11px">MP, National Assembly Kenya</p></div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="info-card h-100 d-flex align-items-center gap-3">
          <div class="speaker-avatar">AD</div>
          <div><h6 class="fw-bold mb-1 small">Amina Diallo</h6><p class="text-muted mb-0" style="font-size:11px">Député, Assemblée Nationale Sénégal</p></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA — colleague's layout, button wired ───────────────────────── -->
<section class="cta-section text-center text-white reveal">
  <div class="container">
    <h2 class="fw-bold mb-4" data-en="Secure Your Delegation's Place" data-fr="Réservez la place de votre délégation">Secure Your Delegation's Place</h2>
    <p class="cta-text mx-auto mb-5" data-en="Registration ends August 25th. Group discounts are available for delegations of five or more members." data-fr="Les inscriptions se terminent le 25 août. Des réductions de groupe sont disponibles pour les délégations de cinq membres ou plus.">
      Registration ends August 25th. Group discounts are available for delegations of five or more members.
    </p>
    <div class="row justify-content-center g-3">
      <div class="col-12 col-sm-auto">
        <a href="register.php?event=inter-parliamentary-forum-2026" class="btn btn-light btn-lg px-5 fw-semibold w-100">
          <span data-en="Register Now" data-fr="S'inscrire">Register Now</span>
        </a>
      </div>
      <div class="col-12 col-sm-auto">
        <a href="login.php" class="btn btn-outline-light btn-lg px-5 fw-semibold w-100">
          <span data-en="Admin Sign In" data-fr="Connexion Admin">Admin Sign In</span>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ── Footer — colleague's layout unchanged ─────────────────────────── -->
<footer class="footer-section py-4">
  <div class="container">
    <div class="row align-items-center gy-3">
      <div class="col-lg-4 text-center text-lg-start">
        <h5 class="fw-bold mb-1">Parliamentary Services</h5>
        <small class="text-muted">© 2026 Parliamentary Services. Official Portal for Diplomatic Relations.</small>
      </div>
      <div class="col-lg-6">
        <div class="d-flex flex-wrap justify-content-center gap-4 footer-links">
          <a href="#">Contact Support</a>
          <a href="#">Privacy Policy</a>
          <a href="#">Accessibility Statement</a>
          <a href="#">Terms of Service</a>
        </div>
      </div>
      <div class="col-lg-2">
        <div class="d-flex justify-content-center justify-content-lg-end gap-3">
          <i class="bi bi-globe"></i>
          <i class="bi bi-question-circle"></i>
        </div>
      </div>
    </div>
  </div>
</footer>

<!-- ── Mobile sticky register bar ───────────────────────────────────── -->
<div class="sticky-reg-bar d-lg-none" id="stickyBar">
  <div><p class="mb-0 fw-bold small" data-en="Global Diplomatic Forum" data-fr="Forum Diplomatique Mondial">Global Diplomatic Forum</p><p class="mb-0 opacity-75" style="font-size:11px" data-en="Sep 1–3 · Accra" data-fr="1–3 Sept · Accra">Sep 1–3 · Accra</p></div>
  <a href="register.php?event=inter-parliamentary-forum-2026" class="btn btn-light btn-sm fw-bold px-4" data-en="Register →" data-fr="S'inscrire →">Register →</a>
</div>

<!-- Toast -->
<div class="ps-toast" id="psToast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/api.js"></script>
<script>
  // ── Page loader ──────────────────────────────────────────────────────
  const loader = document.getElementById('pageLoader');
  loader.style.width = '70%';

  // ── Language system ──────────────────────────────────────────────────
  let currentLang = localStorage.getItem('parlreg_lang') || 'en';

  function setLang(lang) {
    currentLang = lang;
    localStorage.setItem('parlreg_lang', lang);
    document.documentElement.lang = lang;
    document.getElementById('langLabel').textContent = lang === 'en' ? 'FR' : 'EN';
    // Update all [data-en] [data-fr] elements
    document.querySelectorAll('[data-' + lang + ']').forEach(el => {
      const val = el.dataset[lang];
      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') el.placeholder = val;
      else el.textContent = val;
    });
    // Update dropdown active state
    document.querySelectorAll('.lang-dropdown-menu .dropdown-item').forEach(item => {
      item.classList.toggle('active', item.dataset.lang === lang);
    });
    showToast(lang === 'fr' ? '🇫🇷 Français activé' : '🇬🇧 English selected');
  }

  // Apply saved language on load
  if (currentLang !== 'en') setLang(currentLang);

  // ── Toast ────────────────────────────────────────────────────────────
  function showToast(msg) {
    const t = document.getElementById('psToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2800);
  }

  // ── Scroll-reveal ────────────────────────────────────────────────────
  const revealObs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); revealObs.unobserve(e.target); } });
  }, { threshold: 0.07 });
  document.querySelectorAll('.reveal, .reveal-stagger').forEach(el => revealObs.observe(el));

  // ── Navbar shadow on scroll ──────────────────────────────────────────
  window.addEventListener('scroll', () => {
    document.getElementById('mainNav').classList.toggle('scrolled', window.scrollY > 20);
    // Show sticky mobile bar after scrolling past hero
    document.getElementById('stickyBar')?.classList.toggle('show', window.scrollY > 400);
  });

  // ── Countdown timer ──────────────────────────────────────────────────
  const DEADLINE = new Date('2026-08-25T23:59:59');
  function updateCountdown() {
    const now  = new Date();
    const diff = DEADLINE - now;
    if (diff <= 0) {
      document.getElementById('countdownStrip').innerHTML =
        '<span class="fw-semibold">Registration has closed.</span>';
      return;
    }
    const d = Math.floor(diff / 864e5);
    const h = Math.floor((diff % 864e5) / 36e5);
    const m = Math.floor((diff % 36e5) / 6e4);
    const s = Math.floor((diff % 6e4) / 1e3);
    document.getElementById('cd-d').textContent = String(d).padStart(2,'0');
    document.getElementById('cd-h').textContent = String(h).padStart(2,'0');
    document.getElementById('cd-m').textContent = String(m).padStart(2,'0');
    document.getElementById('cd-s').textContent = String(s).padStart(2,'0');
  }
  updateCountdown();
  setInterval(updateCountdown, 1000);

  // ── Agenda tab switcher ──────────────────────────────────────────────
  function switchDay(n, btn) {
    document.querySelectorAll('.agenda-day').forEach(d => d.classList.add('d-none'));
    document.getElementById('day' + n).classList.remove('d-none');
    document.querySelectorAll('.agenda-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    // Re-animate
    const day = document.getElementById('day' + n);
    day.style.animation = 'none';
    day.offsetHeight;
    day.style.animation = 'fadeSection .3s ease both';
  }

  // ── Map ──────────────────────────────────────────────────────────────
  function openMap() {
    window.open('https://maps.google.com/?q=Parliament+of+Ghana,+Accra', '_blank');
  }

  // ── Smooth scroll ────────────────────────────────────────────────────
  function scrollToSection(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
  }

  // ── Load live event data from API ────────────────────────────────────
  async function loadEventData() {
    try {
      const res = await ParlRegAPI.get('/portal/inter-parliamentary-forum-2026');
      if (res.success && res.data) {
        const ev = res.data;
        // Update spots
        if (ev.capacity) {
          const left = ev.capacity - (ev.registrant_count || 0);
          document.getElementById('spotsLabel').textContent =
            left > 0 ? left + ' spots remaining' : 'Registration Full';
        } else {
          document.getElementById('spotsLabel').textContent = 'Open Registration';
        }
      }
    } catch {
      document.getElementById('spotsLabel').textContent = 'Open Registration';
    }
    loader.style.width = '100%';
    setTimeout(() => loader.classList.add('done'), 400);
  }
  loadEventData();
</script>
</body>
</html>