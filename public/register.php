<?php require_once __DIR__ . '/../app/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register — Parliamentary Services</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/parliamentaryservices.css"/>
  <link rel="stylesheet" href="assets/css/parlreg.css"/>
  <script>
    window.PARLREG_CSRF = "<?= CSRF::token() ?>";
  </script>
  <style>
    .progress-steps { display:flex; align-items:center; gap:0; margin-bottom:32px; }
    .step-item { display:flex; flex-direction:column; align-items:center; flex:1; position:relative; }
    .step-item:not(:last-child)::after {
      content:''; position:absolute; top:18px; left:60%; width:80%;
      height:2px; background:#d1e8d4; z-index:0;
    }
    .step-item.done:not(:last-child)::after { background:var(--ps-green); }
    .step-dot {
      width:36px; height:36px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-size:14px; font-weight:700; z-index:1;
      background:#e8f5e9; color:#9dbda3; border:2px solid #d1e8d4;
      transition: all .3s;
    }
    .step-item.active .step-dot { background:var(--ps-green); color:#fff; border-color:var(--ps-green); box-shadow:0 0 0 4px rgba(11,107,27,.15); }
    .step-item.done .step-dot   { background:var(--ps-green); color:#fff; border-color:var(--ps-green); }
    .step-label { font-size:11px; margin-top:6px; color:#9dbda3; font-weight:500; }
    .step-item.active .step-label, .step-item.done .step-label { color:var(--ps-green); }

    .form-section { display:none; }
    .form-section.active { display:block; animation: fadeSection .35s ease both; }
    @keyframes fadeSection { from{opacity:0;transform:translateX(16px)} to{opacity:1;transform:translateX(0)} }

    .consent-box {
      background:#f0faf0; border:1.5px solid #c3dfc7;
      border-radius:10px; padding:16px 18px;
    }
  </style>
</head>
<body>

<!-- Loader -->
<div class="ps-loader" id="pageLoader"></div>

<!-- ── Navbar (exact copy of colleague's) ──────────────────────────── -->
<nav class="navbar navbar-expand-lg bg-white border-bottom py-3">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">Parliamentary Services</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active-link" href="#">Events</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Resources</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Directory</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
        <button class="btn btn-light border btn-sm px-3" id="langToggle">
          <i class="bi bi-globe"></i> <span id="langLabel">FR</span>
        </button>
        <a href="admin/login.php" class="btn btn-success btn-sm px-4">Sign In</a>
      </div>
    </div>
  </div>
</nav>

<!-- ── Page header ──────────────────────────────────────────────────── -->
<section class="py-4 bg-light border-bottom">
  <div class="container">
    <div class="d-flex align-items-center gap-2 text-muted small mb-1">
      <a href="index.php" class="text-success text-decoration-none">Home</a>
      <i class="bi bi-chevron-right" style="font-size:10px"></i>
      <a href="#" class="text-success text-decoration-none" id="breadcrumbEvent">Event</a>
      <i class="bi bi-chevron-right" style="font-size:10px"></i>
      <span>Register</span>
    </div>
    <h2 class="fw-bold mb-1" id="pageTitle">Event Registration</h2>
    <p class="text-muted mb-0 small" id="pageSubtitle">Complete the form below to secure your place.</p>
  </div>
</section>

<!-- ── Main ─────────────────────────────────────────────────────────── -->
<main class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-7 col-xl-6">

        <!-- Progress steps -->
        <div class="progress-steps reveal" id="progressSteps">
          <div class="step-item active" id="step1">
            <div class="step-dot">1</div>
            <span class="step-label">Personal</span>
          </div>
          <div class="step-item" id="step2">
            <div class="step-dot">2</div>
            <span class="step-label">Professional</span>
          </div>
          <div class="step-item" id="step3">
            <div class="step-dot">3</div>
            <span class="step-label">Documents</span>
          </div>
          <div class="step-item" id="step4">
            <div class="step-dot">4</div>
            <span class="step-label">Confirm</span>
          </div>
        </div>

        <!-- Global error -->
        <div id="globalError" class="alert alert-danger d-flex align-items-center gap-2 mb-4 d-none" role="alert">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
          <span id="globalErrorMsg"></span>
        </div>

        <form id="registrationForm" novalidate enctype="multipart/form-data">

          <!-- ── Section 1: Personal ──────────────────────────────── -->
          <div class="form-section active card border-0 shadow-sm p-4 rounded-4 mb-3" id="section1">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="#0b6b1b" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
              Personal Information
            </h5>
            <div class="row g-3">
              <div class="col-12">
                <div class="reg-field-group">
                  <label class="reg-label">Full Name <span class="text-danger">*</span></label>
                  <input type="text" class="reg-input form-control" name="fullname" id="inp_fullname" placeholder="As on official ID" required/>
                  <div class="reg-error" id="err_fullname"></div>
                </div>
              </div>
              <div class="col-12">
                <div class="reg-field-group">
                  <label class="reg-label">Email Address <span class="text-danger">*</span></label>
                  <input type="email" class="reg-input form-control" name="email" id="inp_email" placeholder="official@example.com" required/>
                  <div class="reg-error" id="err_email"></div>
                </div>
              </div>
              <div class="col-12 col-sm-6">
                <div class="reg-field-group">
                  <label class="reg-label">Phone Number</label>
                  <input type="tel" class="reg-input form-control" name="phone" id="inp_phone" placeholder="+1 234 567 8900"/>
                </div>
              </div>
              <div class="col-12 col-sm-6">
                <div class="reg-field-group">
                  <label class="reg-label">Country <span class="text-danger">*</span></label>
                  <input type="text" class="reg-input form-control" name="country" id="inp_country" placeholder="Country" required/>
                  <div class="reg-error" id="err_country"></div>
                </div>
              </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
              <button type="button" class="btn btn-success px-5" onclick="goToStep(2)">Continue <i class="bi bi-arrow-right ms-1"></i></button>
            </div>
          </div>

          <!-- ── Section 2: Professional ──────────────────────────── -->
          <div class="form-section card border-0 shadow-sm p-4 rounded-4 mb-3" id="section2">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="#0b6b1b" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"/></svg>
              Professional Details
            </h5>
            <div class="row g-3">
              <div class="col-12">
                <div class="reg-field-group">
                  <label class="reg-label">Title / Role <span class="text-danger">*</span></label>
                  <select class="reg-select form-select" name="role" id="inp_role" required>
                    <option value="">— Select your role —</option>
                    <option value="mp">Member of Parliament</option>
                    <option value="obs">Official Observer</option>
                    <option value="dipl">Diplomat</option>
                    <option value="staff">Parliamentary Staff</option>
                    <option value="press">Press / Media</option>
                  </select>
                  <div class="reg-error" id="err_role"></div>
                </div>
              </div>
              <div class="col-12">
                <div class="reg-field-group">
                  <label class="reg-label">Organisation / Parliament <span class="text-danger">*</span></label>
                  <input type="text" class="reg-input form-control" name="organisation" id="inp_organisation" placeholder="e.g. Parliament of Ghana" required/>
                  <div class="reg-error" id="err_organisation"></div>
                </div>
              </div>
              <div class="col-12">
                <div class="reg-field-group">
                  <label class="reg-label">Dietary Requirements / Special Needs</label>
                  <textarea class="reg-textarea form-control" name="dietary" id="inp_dietary" rows="3" placeholder="Leave blank if none"></textarea>
                </div>
              </div>
            </div>
            <div class="d-flex justify-content-between mt-3">
              <button type="button" class="btn btn-outline-secondary px-4" onclick="goToStep(1)"><i class="bi bi-arrow-left me-1"></i> Back</button>
              <button type="button" class="btn btn-success px-5" onclick="goToStep(3)">Continue <i class="bi bi-arrow-right ms-1"></i></button>
            </div>
          </div>

          <!-- ── Section 3: Documents ─────────────────────────────── -->
          <div class="form-section card border-0 shadow-sm p-4 rounded-4 mb-3" id="section3">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="#0b6b1b" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
              Documents <span class="badge-ps ms-2">Optional</span>
            </h5>
            <div class="reg-field-group">
              <label class="reg-label">Passport / ID Scan</label>
              <label class="reg-file-label" for="inp_passport">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                <span id="passportFileName">Click to upload — PDF, JPG, PNG (max 5MB)</span>
              </label>
              <input type="file" id="inp_passport" name="f001" accept=".pdf,.jpg,.jpeg,.png" class="d-none"/>
            </div>
            <div class="d-flex justify-content-between mt-3">
              <button type="button" class="btn btn-outline-secondary px-4" onclick="goToStep(2)"><i class="bi bi-arrow-left me-1"></i> Back</button>
              <button type="button" class="btn btn-success px-5" onclick="goToStep(4)">Continue <i class="bi bi-arrow-right ms-1"></i></button>
            </div>
          </div>

          <!-- ── Section 4: Confirm ────────────────────────────────── -->
          <div class="form-section card border-0 shadow-sm p-4 rounded-4 mb-3" id="section4">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="#0b6b1b" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Review & Confirm
            </h5>
            <!-- Summary -->
            <div class="bg-light rounded-3 p-3 mb-4 small" id="summaryBox">
              <div class="row g-2" id="summaryContent"></div>
            </div>
            <!-- Consent -->
            <div class="consent-box mb-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="consentCheck" name="consent" required/>
                <label class="form-check-label small" for="consentCheck">
                  I consent to the processing of my personal data in accordance with
                  Parliamentary Services' <a href="#" class="text-success">privacy policy</a>.
                  I confirm that all information provided is accurate.
                </label>
              </div>
              <div class="reg-error" id="err_consent"></div>
            </div>
            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-outline-secondary px-4" onclick="goToStep(3)"><i class="bi bi-arrow-left me-1"></i> Back</button>
              <button type="submit" class="btn btn-success px-5 fw-semibold d-flex align-items-center gap-2" id="submitBtn">
                <span id="submitText">Submit Registration</span>
                <span class="spinner-border spinner-border-sm d-none" id="submitSpinner"></span>
              </button>
            </div>
          </div>

        </form>

        <!-- ── Success ────────────────────────────────────────────── -->
        <div class="reg-success d-none card border-0 shadow-sm rounded-4 p-5" id="successSection">
          <div class="reg-success-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="#0b6b1b" width="36" height="36"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <h3 class="fw-bold mb-2">Registration Received</h3>
          <p class="text-muted mb-3">Thank you for registering. A confirmation has been sent to your email address.</p>
          <div class="badge-ps fs-6 d-inline-block mx-auto mb-4 px-4 py-2" id="refNumber"></div>
          <div id="pendingNote" class="d-none alert alert-warning small">
            <i class="bi bi-clock me-1"></i>
            Your registration is pending approval. You will be notified by email once reviewed.
          </div>
          <a href="index.php" class="btn btn-outline-success mt-3 px-4">Return to Portal</a>
        </div>

      </div>

      <!-- ── Right: Event info sidebar ──────────────────────────────── -->
      <div class="col-12 col-lg-4 col-xl-3 mt-4 mt-lg-0">
        <div class="card border-0 shadow-sm rounded-4 p-4 reveal" id="eventInfoCard">
          <div class="schedule-card rounded-3 mb-3 p-3" style="background:#0c2d17;color:white">
            <p class="small mb-1 opacity-75">Event</p>
            <h6 class="fw-bold mb-0" id="sidebarEventName">Loading…</h6>
          </div>
          <div class="d-flex flex-column gap-3 small">
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-calendar3 text-success mt-1"></i>
              <div><strong>Date</strong><br/><span class="text-muted" id="sidebarDate">—</span></div>
            </div>
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-geo-alt text-success mt-1"></i>
              <div><strong>Location</strong><br/><span class="text-muted" id="sidebarLocation">—</span></div>
            </div>
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-people text-success mt-1"></i>
              <div><strong>Spots</strong><br/><span class="text-muted" id="sidebarCapacity">Open</span></div>
            </div>
          </div>
        </div>
        <!-- FAQ preview -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mt-3 reveal" id="faqCard">
          <h6 class="fw-bold mb-3">Quick Info</h6>
          <div class="accordion accordion-flush" id="faqAccordion"></div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- ── Footer (same as colleague's) ────────────────────────────────── -->
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

<!-- Toast -->
<div class="ps-toast" id="psToast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/api.js"></script>
<script>
  // ── Page setup ──────────────────────────────────────────────────────
  let currentStep  = 1;
  let eventSlug    = new URLSearchParams(location.search).get('event') || 'inter-parliamentary-forum-2026';
  let eventData    = null;

  // Page loader
  const loader = document.getElementById('pageLoader');
  loader.style.width = '60%';

  // ── Load event data ─────────────────────────────────────────────────
  async function loadEvent() {
    try {
      const res = await ParlRegAPI.get('/portal/' + eventSlug);
      if (res.success) {
        eventData = res.data;
        populateEventInfo(eventData);
      }
    } catch(e) {
      // If API not available, show static data (collegues' design works standalone)
      populateEventInfo({
        name_en: 'Global Diplomatic Forum',
        date_start: '2026-09-01',
        date_end: '2026-09-03',
        location_en: 'Palais des Nations, Geneva',
        capacity: null,
        registrant_count: 0,
        faqs: [
          { question_en: 'Is there a registration fee?', answer_en: 'No. Registration is free for all participants.' },
          { question_en: 'What ID do I need?', answer_en: 'A valid passport or government-issued ID is required.' },
        ]
      });
    }
    loader.style.width = '100%';
    setTimeout(() => { loader.classList.add('done'); }, 400);
  }

  function populateEventInfo(ev) {
    const lang = document.documentElement.lang === 'fr' ? 'fr' : 'en';
    const name = ev['name_'+lang] || ev.name_en;
    document.getElementById('pageTitle').textContent = name;
    document.getElementById('breadcrumbEvent').textContent = name;
    document.getElementById('sidebarEventName').textContent = name;
    document.getElementById('sidebarDate').textContent =
      ev.date_start ? new Date(ev.date_start).toLocaleDateString('en-GB', {day:'numeric',month:'long',year:'numeric'}) : '—';
    document.getElementById('sidebarLocation').textContent = ev.location_en || '—';
    if (ev.capacity) {
      const left = ev.capacity - (ev.registrant_count || 0);
      document.getElementById('sidebarCapacity').textContent = left > 0 ? left + ' spots left' : 'Full';
    }
    // FAQs
    const faqEl = document.getElementById('faqAccordion');
    (ev.faqs || []).slice(0, 3).forEach((faq, i) => {
      faqEl.innerHTML += `
        <div class="accordion-item border-0 border-bottom">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed px-0 py-2 bg-transparent text-dark small fw-semibold" type="button"
                    data-bs-toggle="collapse" data-bs-target="#faq${i}">
              ${faq.question_en}
            </button>
          </h2>
          <div id="faq${i}" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body px-0 pt-0 pb-2 text-muted small">${faq.answer_en}</div>
          </div>
        </div>`;
    });
  }

  // ── Step navigation ─────────────────────────────────────────────────
  function goToStep(n) {
    if (n > currentStep && !validateStep(currentStep)) return;
    // Update step indicators
    for (let i = 1; i <= 4; i++) {
      const el = document.getElementById('step' + i);
      el.classList.remove('active', 'done');
      if (i < n) el.classList.add('done');
      if (i === n) el.classList.add('active');
    }
    // Show/hide sections
    document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
    document.getElementById('section' + n).classList.add('active');

    if (n === 4) buildSummary();
    currentStep = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ── Validation ──────────────────────────────────────────────────────
  function validateStep(step) {
    clearErrors();
    let ok = true;
    if (step === 1) {
      ok = requireField('fullname', 'Full name is required.') && ok;
      ok = requireEmail('email')  && ok;
      ok = requireField('country', 'Country is required.') && ok;
    }
    if (step === 2) {
      ok = requireSelect('role', 'Please select your role.') && ok;
      ok = requireField('organisation', 'Organisation is required.') && ok;
    }
    return ok;
  }

  function requireField(id, msg) {
    const el = document.getElementById('inp_' + id);
    if (!el || !el.value.trim()) {
      showFieldError(id, msg);
      el?.classList.add('invalid');
      return false;
    }
    return true;
  }
  function requireSelect(id, msg) {
    const el = document.getElementById('inp_' + id);
    if (!el || !el.value) { showFieldError(id, msg); el?.classList.add('invalid'); return false; }
    return true;
  }
  function requireEmail(id) {
    const el = document.getElementById('inp_' + id);
    const v  = el?.value.trim();
    if (!v) { showFieldError(id, 'Email is required.'); el?.classList.add('invalid'); return false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { showFieldError(id, 'Please enter a valid email.'); el?.classList.add('invalid'); return false; }
    return true;
  }
  function showFieldError(id, msg) {
    const el = document.getElementById('err_' + id);
    if (el) el.textContent = msg;
  }
  function clearErrors() {
    document.querySelectorAll('.reg-error').forEach(e => e.textContent = '');
    document.querySelectorAll('.invalid').forEach(e => e.classList.remove('invalid'));
  }

  // ── Summary ─────────────────────────────────────────────────────────
  function buildSummary() {
    const fields = [
      { label: 'Full Name',     val: document.getElementById('inp_fullname')?.value },
      { label: 'Email',         val: document.getElementById('inp_email')?.value },
      { label: 'Country',       val: document.getElementById('inp_country')?.value },
      { label: 'Role',          val: document.getElementById('inp_role')?.options[document.getElementById('inp_role')?.selectedIndex]?.text },
      { label: 'Organisation',  val: document.getElementById('inp_organisation')?.value },
    ];
    document.getElementById('summaryContent').innerHTML = fields.map(f =>
      `<div class="col-12 col-sm-6"><span class="text-muted">${f.label}:</span> <strong>${f.val || '—'}</strong></div>`
    ).join('');
  }

  // ── File upload label ────────────────────────────────────────────────
  document.getElementById('inp_passport').addEventListener('change', function() {
    const name = this.files[0]?.name;
    document.getElementById('passportFileName').textContent = name || 'Click to upload — PDF, JPG, PNG (max 5MB)';
  });

  // ── Form submit ──────────────────────────────────────────────────────
  document.getElementById('registrationForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();

    // Validate consent
    if (!document.getElementById('consentCheck').checked) {
      document.getElementById('err_consent').textContent = 'You must consent to continue.';
      return;
    }

    const btn     = document.getElementById('submitBtn');
    const txt     = document.getElementById('submitText');
    const spinner = document.getElementById('submitSpinner');
    btn.disabled  = true;
    txt.textContent = 'Submitting…';
    spinner.classList.remove('d-none');

    try {
      const fd = new FormData(document.getElementById('registrationForm'));
      fd.append('lang', document.documentElement.lang === 'fr' ? 'fr' : 'en');

      const eventId = eventData?.id || 1;
      const res = await ParlRegAPI.upload('/events/' + eventId + '/register', fd);

      if (res.success) {
        document.getElementById('registrationForm').classList.add('d-none');
        document.getElementById('progressSteps').classList.add('d-none');
        const suc = document.getElementById('successSection');
        suc.classList.remove('d-none');
        document.getElementById('refNumber').textContent = 'Reference: ' + (res.reference_number || 'PARL-XXXXXXXX');
        if (res.status === 'pending') document.getElementById('pendingNote').classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        // Show field errors from server
        if (res.errors) {
          Object.entries(res.errors).forEach(([k, v]) => {
            const el = document.getElementById('err_' + k);
            if (el) el.textContent = v;
          });
        }
        const errEl = document.getElementById('globalError');
        errEl.classList.remove('d-none');
        document.getElementById('globalErrorMsg').textContent = res.error || 'Submission failed. Please check the form.';
      }
    } catch(err) {
      const errEl = document.getElementById('globalError');
      errEl.classList.remove('d-none');
      document.getElementById('globalErrorMsg').textContent = 'Network error. Please check your connection.';
    } finally {
      btn.disabled = false;
      txt.textContent = 'Submit Registration';
      spinner.classList.add('d-none');
    }
  });

  // ── Language toggle (matches colleague's button) ─────────────────────
  let lang = 'en';
  document.getElementById('langToggle').addEventListener('click', () => {
    lang = lang === 'en' ? 'fr' : 'en';
    document.getElementById('langLabel').textContent = lang === 'en' ? 'FR' : 'EN';
    document.documentElement.lang = lang;
  });

  // ── Reveal on scroll ────────────────────────────────────────────────
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
  }, { threshold: 0.08 });
  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

  // ── Init ─────────────────────────────────────────────────────────────
  loadEvent();
</script>
</body>
</html>