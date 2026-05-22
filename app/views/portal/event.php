<?php
// ParlReg — Public Portal View
// Called by PortalController::renderPage($slug)
// $event array is passed in from controller
if (!isset($event)) die('No event data.');

$lang     = Translator::getLang();
$sections = $event['sections'] ?? [];
usort($sections, fn($a,$b) => ($a['order']??0) <=> ($b['order']??0));

$nameKey     = "name_$lang";
$locKey      = "location_$lang";
$metaTitleKey = "meta_title_$lang";
$metaDescKey  = "meta_desc_$lang";
$eventName   = htmlspecialchars($event[$nameKey] ?? $event['name_en'] ?? 'Event');
$location    = htmlspecialchars($event[$locKey]  ?? $event['location_en'] ?? '');
$metaTitle   = htmlspecialchars($event[$metaTitleKey] ?? $event['meta_title_en'] ?? $eventName);
$metaDesc    = htmlspecialchars($event[$metaDescKey]  ?? $event['meta_desc_en']  ?? '');
$themeColor  = htmlspecialchars($event['theme_color'] ?? '#1B3A6B');
$startDate   = date('d F Y', strtotime($event['date_start']));
$endDate     = date('d F Y', strtotime($event['date_end']));
$dateRange   = $startDate === $endDate ? $startDate : "$startDate – $endDate";
$isFull      = $event['capacity'] && $event['registrant_count'] >= $event['capacity'];
$isDeadline  = $event['registration_deadline'] && strtotime($event['registration_deadline']) < time();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $metaTitle ?></title>
  <meta name="description" content="<?= $metaDesc ?>"/>
  <link rel="canonical" href="<?= APP_URL ?>/events/<?= $event['slug'] ?>"/>
  <?php if (!empty($event['favicon'])): ?>
  <link rel="icon" href="<?= htmlspecialchars($event['favicon']) ?>"/>
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/portal.css"/>
  <style>:root { --event-color: <?= $themeColor ?>; }</style>
</head>
<body>

<!-- ── Navbar ────────────────────────────────────────────────────────────── -->
<nav class="portal-nav" role="navigation" aria-label="Site navigation">
  <div class="brand">
    <?php if (!empty($event['parliament_logo'])): ?>
    <img src="<?= htmlspecialchars($event['parliament_logo']) ?>" alt="Parliament logo" height="36"/>
    <?php endif; ?>
    <span class="brand-name">Parliamentary Services</span>
  </div>
  <div class="lang-switch" role="group" aria-label="Language selection">
    <button class="lang-btn <?= $lang==='en' ? 'active' : '' ?>" data-lang="en"
            aria-pressed="<?= $lang==='en' ? 'true':'false' ?>">English</button>
    <button class="lang-btn <?= $lang==='fr' ? 'active' : '' ?>" data-lang="fr"
            aria-pressed="<?= $lang==='fr' ? 'true':'false' ?>">Français</button>
  </div>
</nav>

<?php foreach ($sections as $section):
  if (empty($section['enabled'])) continue;
  $key        = $section['key'];
  $contentKey = "content_$lang";
  $content    = $section[$contentKey] ?? $section['content_en'] ?? '';
?>

<?php if ($key === 'hero'): ?>
<!-- ── Hero ────────────────────────────────────────────────────────────── -->
<section class="portal-hero" aria-labelledby="event-title">
  <?php if (!empty($event['event_logo'])): ?>
  <img src="<?= htmlspecialchars($event['event_logo']) ?>" alt="Event logo" class="event-logo"/>
  <?php endif; ?>
  <h1 id="event-title"><?= $eventName ?></h1>
  <div class="meta">
    <span class="meta-item">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/>
      </svg>
      <?= htmlspecialchars($dateRange) ?>
    </span>
    <?php if ($location): ?>
    <span class="meta-item">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
      </svg>
      <?= $location ?>
    </span>
    <?php endif; ?>
  </div>
  <?php if (!$isFull && !$isDeadline): ?>
  <a href="#registration-form" class="btn-register">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
    </svg>
    Register Now
  </a>
  <?php elseif ($isFull): ?>
  <p style="background:rgba(255,255,255,0.15);padding:10px 20px;border-radius:8px;font-weight:600">Registration is full.</p>
  <?php else: ?>
  <p style="background:rgba(255,255,255,0.15);padding:10px 20px;border-radius:8px;font-weight:600">Registration deadline has passed.</p>
  <?php endif; ?>
</section>

<?php elseif ($key === 'key_info' && $content): ?>
<!-- ── Key Information ──────────────────────────────────────────────────── -->
<section class="portal-section reveal" aria-labelledby="key-info-title">
  <div class="section-title" id="key-info-title"><?= $lang==='fr' ? 'Informations clés' : 'Key Information' ?></div>
  <div class="section-divider" aria-hidden="true"></div>
  <div class="key-info-content"><?= $content ?></div>
</section>

<?php elseif ($key === 'event_details'): ?>
<!-- ── Event Details ────────────────────────────────────────────────────── -->
<section class="portal-section reveal" aria-labelledby="details-title">
  <div class="section-title" id="details-title"><?= $lang==='fr' ? 'Détails de l\'événement' : 'Event Details' ?></div>
  <div class="section-divider" aria-hidden="true"></div>
  <div class="details-grid">
    <div class="detail-card">
      <svg class="detail-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/>
      </svg>
      <span class="detail-label"><?= $lang==='fr' ? 'Date' : 'Date' ?></span>
      <span class="detail-value"><?= htmlspecialchars($dateRange) ?></span>
    </div>
    <?php if ($location): ?>
    <div class="detail-card">
      <svg class="detail-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
      </svg>
      <span class="detail-label"><?= $lang==='fr' ? 'Lieu' : 'Location' ?></span>
      <span class="detail-value"><?= $location ?></span>
    </div>
    <?php endif; ?>
    <?php if ($event['capacity']): ?>
    <div class="detail-card">
      <svg class="detail-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
      </svg>
      <span class="detail-label"><?= $lang==='fr' ? 'Capacité' : 'Capacity' ?></span>
      <span class="detail-value"><?= $event['registrant_count'] ?> / <?= $event['capacity'] ?></span>
    </div>
    <?php endif; ?>
    <?php if ($event['registration_deadline']): ?>
    <div class="detail-card">
      <svg class="detail-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span class="detail-label"><?= $lang==='fr' ? 'Date limite' : 'Registration Deadline' ?></span>
      <span class="detail-value"><?= date('d F Y', strtotime($event['registration_deadline'])) ?></span>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php elseif ($key === 'agenda' && $content): ?>
<!-- ── Agenda ───────────────────────────────────────────────────────────── -->
<section class="portal-section reveal" aria-labelledby="agenda-title">
  <div class="section-title" id="agenda-title"><?= $lang==='fr' ? 'Programme' : 'Agenda' ?></div>
  <div class="section-divider" aria-hidden="true"></div>
  <div class="agenda-content"><?= $content ?></div>
</section>

<?php elseif ($key === 'faqs' && !empty($event['faqs'])): ?>
<!-- ── FAQs ─────────────────────────────────────────────────────────────── -->
<section class="portal-section reveal" aria-labelledby="faq-title">
  <div class="section-title" id="faq-title"><?= $lang==='fr' ? 'Foire Aux Questions' : 'Frequently Asked Questions' ?></div>
  <div class="section-divider" aria-hidden="true"></div>
  <div class="faq-list">
    <?php foreach ($event['faqs'] as $i => $faq):
      $qKey = "question_$lang"; $aKey = "answer_$lang";
      $q = htmlspecialchars($faq[$qKey] ?? $faq['question_en']);
      $a = $faq[$aKey] ?? $faq['answer_en'];
    ?>
    <div class="faq-item" id="faq-<?= $i ?>">
      <div class="faq-question" aria-expanded="false" aria-controls="faq-answer-<?= $i ?>">
        <span><?= $q ?></span>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
        </svg>
      </div>
      <div class="faq-answer" id="faq-answer-<?= $i ?>" role="region">
        <div class="faq-answer-inner"><?= $a ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<?php elseif ($key === 'form' && !$isFull && !$isDeadline): ?>
<!-- ── Registration Form ─────────────────────────────────────────────────── -->
<section class="portal-section reveal" id="registration-form" aria-labelledby="form-title">
  <div class="section-title" id="form-title"><?= $lang==='fr' ? 'Formulaire d\'inscription' : 'Registration Form' ?></div>
  <div class="section-divider" aria-hidden="true"></div>
  <div id="form-section">
    <div class="form-wrapper">
      <div id="form-global-error" class="alert alert-error" style="display:none" role="alert" aria-live="assertive"></div>
      <form id="registration-form" novalidate data-event-id="<?= $event['id'] ?>">
        <div id="form-fields">
          <!-- Rendered by portal.js FormRenderer.init() -->
          <p style="color:#9CA3AF;font-size:14px">Loading form…</p>
        </div>

        <!-- Consent -->
        <div class="consent-row">
          <input type="checkbox" id="consent-checkbox" name="consent" value="1" aria-required="true"/>
          <label for="consent-checkbox">
            <?= $lang==='fr'
              ? 'Je consens au traitement de mes données personnelles conformément à la politique de confidentialité des Services Parlementaires.'
              : 'I consent to the processing of my personal data in accordance with Parliamentary Services\' privacy policy.' ?>
          </label>
        </div>
        <div class="field-error" id="consent-error" role="alert"></div>

        <button type="submit" class="btn-submit" id="submit-btn">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
          </svg>
          <?= $lang==='fr' ? 'Soumettre l\'inscription' : 'Submit Registration' ?>
        </button>
      </form>
    </div>
  </div>
</section>

<?php elseif ($key === 'contact'): ?>
<!-- ── Contact & Footer ──────────────────────────────────────────────────── -->
<footer class="portal-footer" role="contentinfo">
  <div class="footer-top">
    <span class="footer-brand">Parliamentary Services</span>
    <div class="footer-links">
      <a href="#"><?= $lang==='fr' ? 'Accessibilité' : 'Accessibility' ?></a>
      <a href="#"><?= $lang==='fr' ? 'Confidentialité' : 'Privacy' ?></a>
      <a href="#"><?= $lang==='fr' ? 'Contact' : 'Contact' ?></a>
    </div>
  </div>
  <div class="footer-bottom">
    &copy; <?= date('Y') ?> Parliamentary Services. All rights reserved.
  </div>
</footer>

<?php endif; ?>
<?php endforeach; ?>

<script src="/assets/js/portal.js"></script>
<script>
  // Init form renderer with schema from server
  const SCHEMA   = <?= json_encode(json_decode($event['form_schema_json'] ?? '[]', true)) ?>;
  const EVENT_ID = <?= (int)$event['id'] ?>;
  document.addEventListener('DOMContentLoaded', () => {
    FormRenderer.init(SCHEMA, EVENT_ID);
  });
</script>
</body>
</html>
