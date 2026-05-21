<?php require_once __DIR__ . '/../../app/bootstrap.php'; 
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {

    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — Parliamentary Services Admin</title>
  <!-- Colleague used Tailwind + Material Symbols — keeping both -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <!-- ParlReg shared CSS for status badges and toast -->
  <link rel="stylesheet" href="../assets/css/parlreg.css"/>

  <script>
    window.PARLREG_CSRF = "<?= CSRF::token() ?>";
  </script>
  
  <script>
    // Colleague's Tailwind config — unchanged
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#0b6b1b',
            'on-primary': '#ffffff',
            'surface-container-lowest': '#f9fafb',
            'surface-container-low': '#f3f4f6',
            'surface-variant': '#e8f5e9',
            'on-surface': '#1c1b1f',
            'on-surface-variant': '#49454f',
            'outline-variant': '#cac4d0',
          }
        }
      }
    };
  </script>

  <style>
    /* Colleague's styles — untouched */
    html, body { height: 100%; }
    body { display: flex; flex-direction: column; font-family: 'Google Sans', system-ui, sans-serif; }
    .main-workspace { flex: 1; display: flex; overflow: hidden; min-height: 0; }
    .left-sidebar, .right-sidebar { overflow-y: auto; }
    .canvas-area { overflow-y: auto; }
    .canvas-bg { background-image: radial-gradient(circle, #c8e6c9 1px, transparent 1px); background-size: 22px 22px; }
    .drop-zone-active { outline: 2px dashed #0b6b1b; outline-offset: -4px; }
    .builder-item { transition: box-shadow 0.15s, border-color 0.15s; }

    /* ── NEW: Admin nav additions ──────────────────────────────────── */
    .admin-nav-link { transition: color .15s; }
    .admin-nav-link:hover { color: #0b6b1b !important; }
    .admin-nav-link.active { color: #0b6b1b !important; border-bottom: 2px solid #0b6b1b; padding-bottom: 2px; }

    /* ── NEW: Stats bar ────────────────────────────────────────────── */
    .stat-chip {
      display: inline-flex; align-items: center; gap: 6px;
      background: #e8f5e9; color: #0b6b1b;
      border: 1px solid #c8e6c9; border-radius: 50px;
      padding: 3px 12px; font-size: 12px; font-weight: 600;
    }

    /* ── NEW: Event select ─────────────────────────────────────────── */
    #eventSelect {
      border: 1.5px solid #e0e0e0; border-radius: 8px;
      padding: 5px 10px; font-size: 13px; min-width: 220px;
    }
    #eventSelect:focus { outline: none; border-color: #0b6b1b; }

    /* ── NEW: Save indicator ───────────────────────────────────────── */
    .save-indicator {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 11px; padding: 3px 10px;
      border-radius: 50px; font-weight: 600;
    }
    .save-indicator.saved  { background:#d1fae5; color:#065f46; }
    .save-indicator.saving { background:#fef3c7; color:#92400e; }
    .save-indicator.unsaved{ background:#fee2e2; color:#991b1b; }
  </style>
</head>
<body class="bg-surface-container-lowest text-on-surface">

<!-- ── Top bar — colleague's layout + event selector + live save ─────── -->
<header class="bg-white border-b border-outline-variant px-6 py-3 flex items-center gap-4 shrink-0 shadow-sm">
  <div class="flex items-center gap-3">
    <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
      <span class="material-symbols-outlined text-white text-base">account_balance</span>
    </div>
    <span class="text-xl font-bold text-on-surface">Parliamentary Services</span>
    <div class="h-6 w-px bg-outline-variant"></div>
    <h1 class="text-xl font-bold text-primary">Registration Builder</h1>
  </div>

  <!-- Event selector (new) -->
  <select id="eventSelect" onchange="loadEvent(this.value)" title="Select event">
    <option value="">— Select Event —</option>
  </select>

  <!-- Save indicator (new) -->
  <span class="save-indicator unsaved" id="saveIndicator">
    <span class="material-symbols-outlined text-xs">circle</span>
    Unsaved
  </span>

  <nav class="hidden md:flex items-center gap-4 ml-auto">
    <a href="../index.php" class="text-sm font-medium text-on-surface-variant admin-nav-link">Portal</a>
    <a href="#" class="text-sm font-medium text-primary border-b-2 border-primary pb-1 admin-nav-link active">Builder</a>
    <a href="events.php" class="text-sm font-medium text-on-surface-variant admin-nav-link">Events</a>
    <a href="registrations.php" class="text-sm font-medium text-on-surface-variant admin-nav-link">Registrations</a>
  </nav>

  <div class="flex items-center gap-2 ml-4">
    <!-- User display (new) -->
    <div class="flex items-center gap-2 bg-surface-container-low border border-outline-variant rounded-lg px-3 py-1.5" id="userChip" style="display:none!important">
      <div class="w-6 h-6 bg-primary rounded-full flex items-center justify-center text-white text-xs font-bold" id="userAvatar">A</div>
      <span class="text-xs font-medium text-on-surface-variant" id="userName">Admin</span>
    </div>
    <button id="previewPortalBtn" class="bg-primary text-on-primary px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-[#0a4e16] transition shadow-sm">
      Preview Portal
    </button>
    <button onclick="doLogout()" class="text-sm font-medium text-on-surface-variant hover:text-red-600 transition px-2" title="Sign out">
      <span class="material-symbols-outlined text-base align-middle">logout</span>
    </button>
  </div>
</header>

<!-- ── Stats bar (new) ───────────────────────────────────────────────── -->
<div class="bg-surface-variant border-b border-outline-variant px-6 py-2 flex items-center gap-3 shrink-0 text-xs" id="statsBar" style="display:none">
  <span class="text-on-surface-variant font-semibold mr-1">Event Status:</span>
  <span class="stat-chip" id="statStatus">—</span>
  <span class="stat-chip" id="statRegistrants">— registrants</span>
  <span class="stat-chip" id="statFields">— fields</span>
  <button onclick="publishEvent()" class="ml-auto bg-primary text-white text-xs px-3 py-1 rounded-full font-semibold hover:bg-[#0a4e16] transition" id="publishBtn">Publish</button>
  <button onclick="unpublishEvent()" class="bg-white border border-outline-variant text-xs px-3 py-1 rounded-full font-semibold hover:bg-red-50 hover:text-red-600 transition" id="unpublishBtn" style="display:none">Unpublish</button>
</div>

<!-- ── Main workspace — COLLEAGUE'S layout exactly preserved ─────────── -->
<div class="main-workspace">
  <!-- LEFT SIDEBAR: Components -->
  <aside class="w-72 bg-surface-container-lowest border-r border-outline-variant flex flex-col shrink-0 left-sidebar">
    <div class="p-4 border-b border-outline-variant bg-white/40 sticky top-0 z-10 backdrop-blur-sm">
      <h2 class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Form Elements</h2>
    </div>
    <div class="p-4 flex flex-col gap-3 pb-8">
      <div draggable="true" data-type="header" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary hover:shadow-sm transition">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">title</span>
        <span class="text-sm font-medium">Header Block</span>
      </div>
      <div draggable="true" data-type="text-input" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary hover:shadow-sm">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">text_fields</span>
        <span class="text-sm font-medium">Text Input</span>
      </div>
      <div draggable="true" data-type="email-input" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary hover:shadow-sm">
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary">alternate_email</span>
        <span class="text-sm font-medium">Email Input</span>
      </div>
      <div draggable="true" data-type="file-upload" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
        <span class="material-symbols-outlined">upload_file</span>
        <span class="text-sm font-medium">File Upload</span>
      </div>
      <div draggable="true" data-type="dropdown" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
        <span class="material-symbols-outlined">arrow_drop_down_circle</span>
        <span class="text-sm font-medium">Dropdown</span>
      </div>
      <div draggable="true" data-type="info-block" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
        <span class="material-symbols-outlined">info</span>
        <span class="text-sm font-medium">Info Block</span>
      </div>
      <div draggable="true" data-type="date-selector" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
        <span class="material-symbols-outlined">calendar_month</span>
        <span class="text-sm font-medium">Date Selector</span>
      </div>
      <div draggable="true" data-type="location-pin" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
        <span class="material-symbols-outlined">location_on</span>
        <span class="text-sm font-medium">Location Pin</span>
      </div>
      <div draggable="true" data-type="checkbox-group" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
        <span class="material-symbols-outlined">check_box</span>
        <span class="text-sm font-medium">Checkbox Group</span>
      </div>
      <div draggable="true" data-type="radio-group" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
        <span class="material-symbols-outlined">radio_button_checked</span>
        <span class="text-sm font-medium">Radio Group</span>
      </div>
      <div draggable="true" data-type="textarea" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
        <span class="material-symbols-outlined">notes</span>
        <span class="text-sm font-medium">Textarea</span>
      </div>
    </div>
  </aside>

  <!-- CENTER: Canvas Area -->
  <section class="flex-1 bg-surface-container-low p-6 canvas-area canvas-bg" id="canvasSection">
    <div class="max-w-3xl mx-auto space-y-4" id="canvasContainer"></div>
    <div class="max-w-3xl mx-auto mt-2 text-center text-sm text-on-surface-variant opacity-60 pb-4">
      <span class="material-symbols-outlined text-base align-middle">drag_indicator</span>
      Drag elements from left or reorder using handles
    </div>
  </section>

  <!-- RIGHT SIDEBAR: Settings panel -->
  <aside class="w-80 bg-surface-container-lowest border-l border-outline-variant flex flex-col shrink-0 right-sidebar">
    <div class="p-4 border-b border-outline-variant bg-white/40 sticky top-0 z-10 backdrop-blur-sm flex items-center justify-between">
      <h2 class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Element Settings</h2>
      <span class="bg-[#a3f69c] text-[#005312] text-[10px] px-2 py-0.5 rounded-full font-bold">LIVE EDIT</span>
    </div>
    <div class="settings-scroll p-4 space-y-4" id="settingsPanel">
      <div class="text-on-surface-variant text-center text-sm py-12 opacity-70">✨ Select any form element on the canvas to edit its properties</div>
    </div>
    <div class="p-4 border-t border-outline-variant bg-surface-container-low shrink-0">
      <button id="globalSaveBtn" class="w-full bg-primary text-on-primary py-2.5 rounded-lg text-sm font-medium shadow-sm hover:bg-[#0a5517] transition flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-base">save</span>
        <span id="saveBtnText">Save Form Schema</span>
      </button>
    </div>
  </aside>
</div>

<!-- Footer — colleague's exactly -->
<footer class="bg-white border-t border-outline-variant py-2.5 px-6 shrink-0">
  <div class="max-w-[1400px] mx-auto flex flex-col md:flex-row justify-between items-center gap-2 text-xs text-on-surface-variant">
    <span>© 2026 Parliamentary Services. Official Portal for Diplomatic Relations.</span>
    <div class="flex gap-4">
      <a href="#" class="hover:text-primary transition">Contact Support</a>
      <a href="#" class="hover:text-primary transition">Privacy Policy</a>
      <a href="#" class="hover:text-primary transition">Accessibility</a>
    </div>
  </div>
</footer>

<div class="ps-toast" id="psToast"></div>

<script src="../assets/js/api.js"></script>
<script>
  // ── State ─────────────────────────────────────────────────────────────
  let formComponents   = [];
  let selectedId       = null;
  let currentEventId   = null;
  let autoSaveTimer    = null;

  // ── Auth guard ────────────────────────────────────────────────────────
  window.addEventListener('DOMContentLoaded', async () => {
    // Show user info if logged in
    const user = ParlRegAPI.getUser();
    if (user) {
      const chip = document.getElementById('userChip');
      chip.style.display = 'flex';
      document.getElementById('userAvatar').textContent = user.fullname?.[0]?.toUpperCase() || 'A';
      document.getElementById('userName').textContent   = user.fullname;
    }
    await loadEvents();
    initForm(); // Load colleague's default demo form
  });

  // ── Load events into selector ─────────────────────────────────────────
  async function loadEvents() {
    try {
      const res = await ParlRegAPI.get('/events');
      if (res.success && res.data?.length) {
        const sel = document.getElementById('eventSelect');
        res.data.forEach(ev => {
          const opt = document.createElement('option');
          opt.value = ev.id;
          opt.textContent = ev.name_en + ' (' + ev.status + ')';
          sel.appendChild(opt);
        });
      }
    } catch { /* API not running — show demo mode */ }
  }

  // ── Load event schema ─────────────────────────────────────────────────
  async function loadEvent(eventId) {
    if (!eventId) { currentEventId = null; document.getElementById('statsBar').style.display = 'none'; return; }
    currentEventId = eventId;
    showToast('Loading schema…');
    try {
      // Load schema
      const sr = await ParlRegAPI.get('/events/' + eventId + '/schema');
      if (sr.success && sr.data?.length) {
        formComponents = sr.data.map(f => schemaFieldToComponent(f));
        renderCanvas();
        showToast('Schema loaded — ' + formComponents.length + ' fields');
      }
      // Load stats
      const er = await ParlRegAPI.get('/events/' + eventId);
      if (er.success) {
        const ev = er.data;
        document.getElementById('statsBar').style.display = 'flex';
        document.getElementById('statStatus').textContent = ev.status || '—';
        document.getElementById('statRegistrants').textContent = (ev.registrant_count || 0) + ' registrants';
        document.getElementById('statFields').textContent = formComponents.length + ' fields';
        const isPub = ev.status === 'published';
        document.getElementById('publishBtn').style.display   = isPub ? 'none'   : '';
        document.getElementById('unpublishBtn').style.display = isPub ? '' : 'none';
      }
    } catch { showToast('Could not load event from API'); }
    setSaveState('saved');
  }

  // ── Convert JSON schema field → builder component ─────────────────────
  function schemaFieldToComponent(field) {
    const typeMap = { text:'text-input', email:'email-input', file:'file-upload',
                      select:'dropdown', textarea:'textarea', radio:'radio-group',
                      checkbox:'checkbox-group', date:'date-selector', header:'header' };
    return {
      id: field.id || uid(),
      type: typeMap[field.type] || field.type,
      settings: {
        label:       field.label?.en || field.type,
        label_fr:    field.label?.fr || '',
        placeholder: field.placeholder?.en || '',
        required:    field.required || false,
        options:     (field.options || []).map(o => o.label?.en || o.value).join(', '),
      }
    };
  }

  // ── Convert builder component → JSON schema field ─────────────────────
  function componentToSchemaField(comp, order) {
    const typeMap = { 'text-input':'text', 'email-input':'email', 'file-upload':'file',
                      'dropdown':'select', 'textarea':'textarea', 'radio-group':'radio',
                      'checkbox-group':'checkbox', 'date-selector':'date', 'header':'header' };
    const s = comp.settings;
    const opts = (s.options || '').split(',').map(o => o.trim()).filter(Boolean);
    return {
      id:    comp.id,
      type:  typeMap[comp.type] || comp.type,
      label: { en: s.label || '', fr: s.label_fr || '' },
      placeholder: { en: s.placeholder || '', fr: '' },
      required: s.required || false,
      order,
      options: opts.map(o => ({ value: o.toLowerCase().replace(/\s+/g,'-'), label: { en: o, fr: o } })),
      validation: comp.type === 'file-upload' ? { maxSize: 5, acceptedTypes: ['application/pdf','image/jpeg','image/png'] } : {},
    };
  }

  // ── Save schema to API ────────────────────────────────────────────────
  async function saveSchema() {
    if (!currentEventId) {
      // Save to localStorage as fallback (colleague's original behaviour)
      localStorage.setItem('parliamentary_registration_builder', JSON.stringify(formComponents));
      showToast('✅ Saved to local storage (no event selected)');
      setSaveState('saved');
      return;
    }
    setSaveState('saving');
    document.getElementById('saveBtnText').textContent = 'Saving…';
    try {
      const schema = formComponents.map((c, i) => componentToSchemaField(c, i + 1));
      const res = await ParlRegAPI.put('/events/' + currentEventId + '/schema', { schema });
      if (res.success) {
        showToast('✅ Schema saved to server');
        setSaveState('saved');
        document.getElementById('statFields').textContent = formComponents.length + ' fields';
      } else {
        showToast('Save failed: ' + (res.error || 'Unknown error'));
        setSaveState('unsaved');
      }
    } catch {
      showToast('⚠️ Offline — saved to local storage');
      localStorage.setItem('parliamentary_registration_builder', JSON.stringify(formComponents));
      setSaveState('saved');
    }
    document.getElementById('saveBtnText').textContent = 'Save Form Schema';
  }

  function setSaveState(state) {
    const el = document.getElementById('saveIndicator');
    el.className = 'save-indicator ' + state;
    el.innerHTML = `<span class="material-symbols-outlined text-xs">circle</span> ${
      state === 'saved' ? 'Saved' : state === 'saving' ? 'Saving…' : 'Unsaved'
    }`;
  }

  // ── Publish / Unpublish ────────────────────────────────────────────────
  async function publishEvent() {
    if (!currentEventId) { showToast('Select an event first'); return; }
    try {
      const res = await ParlRegAPI.post('/events/' + currentEventId + '/publish');
      if (res.success) {
        showToast('🌐 Event published!');
        if (res.warnings?.length) showToast('⚠️ ' + res.warnings[0]);
        document.getElementById('statStatus').textContent = 'published';
        document.getElementById('publishBtn').style.display = 'none';
        document.getElementById('unpublishBtn').style.display = '';
      } else showToast('Error: ' + res.error);
    } catch { showToast('Cannot reach API'); }
  }
  async function unpublishEvent() {
    if (!currentEventId) return;
    try {
      const res = await ParlRegAPI.post('/events/' + currentEventId + '/unpublish');
      if (res.success) {
        showToast('Event unpublished');
        document.getElementById('statStatus').textContent = 'draft';
        document.getElementById('publishBtn').style.display = '';
        document.getElementById('unpublishBtn').style.display = 'none';
      }
    } catch { showToast('Cannot reach API'); }
  }

  // ── Logout ────────────────────────────────────────────────────────────
  async function doLogout() {
    await ParlRegAPI.logout();
    window.location.href = '../login.php';
  }

  // ── Toast ─────────────────────────────────────────────────────────────
  function showToast(msg) {
    const t = document.getElementById('psToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2800);
  }

  // ─────────────────────────────────────────────────────────────────────
  // COLLEAGUE'S ORIGINAL BUILDER CODE — fully preserved, only hooked up
  // ─────────────────────────────────────────────────────────────────────

  let selectedComponentId = null;
  function uid() { return Date.now() + '-' + Math.random().toString(36).substring(2, 10); }

  function getDefaultSettings(type) {
    switch(type) {
      case 'header':       return { title: 'Registration: Diplomatic Summit', subtitle: 'Official Delegate Entry Portal', logoAlign: 'left', required: false };
      case 'text-input':   return { label: 'Full Diplomatic Name',   placeholder: 'As written on passport', required: true };
      case 'email-input':  return { label: 'Email Address',          placeholder: 'official@example.com', required: true };
      case 'file-upload':  return { label: 'Credential Verification', accept: 'PDF, JPG or PNG', maxSize: '5MB', required: false };
      case 'info-block':   return { title: 'Security Clearance Required', message: 'All delegates must provide valid diplomatic credentials.', icon: 'verified_user' };
      case 'date-selector':return { label: 'Arrival Date', placeholder: 'Select date', required: false };
      case 'location-pin': return { label: 'Diplomatic Mission Location', placeholder: 'Embassy / Mission address', required: false };
      case 'checkbox-group':return { label: 'Access Requirements', options: 'Press Accreditation, VIP Transport, Interpreter Needed', required: false };
      case 'radio-group':  return { label: 'Delegation Type', options: 'Government, Observer, Press', required: false };
      case 'dropdown':     return { label: 'Country of Origin', options: 'Ghana, France, Kenya, Senegal', required: true };
      case 'textarea':     return { label: 'Additional Notes', placeholder: 'Any special requirements…', required: false };
      default: return {};
    }
  }

  function escapeHtml(str) {
    if(!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function renderComponentPreview(comp) {
    const s = comp.settings;
    switch(comp.type) {
      case 'header':
        return `<div class="flex items-center gap-4"><div class="w-14 h-14 bg-[#d9e6da] rounded-xl flex items-center justify-center"><span class="material-symbols-outlined text-primary text-3xl">account_balance</span></div><div><h2 class="text-2xl font-semibold text-on-surface">${escapeHtml(s.title)}</h2><p class="text-base text-on-surface-variant">${escapeHtml(s.subtitle)}</p></div></div>`;
      case 'text-input':
        return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)} ${s.required?'<span class="text-red-600">*</span>':''}</label><input class="w-full bg-white border border-outline-variant rounded-lg px-3 py-2" type="text" placeholder="${escapeHtml(s.placeholder)}" disabled>`;
      case 'email-input':
        return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)} ${s.required?'<span class="text-red-600">*</span>':''}</label><input class="w-full bg-white border border-outline-variant rounded-lg px-3 py-2" type="email" placeholder="${escapeHtml(s.placeholder)}" disabled>`;
      case 'file-upload':
        return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)}</label><div class="border-2 border-dashed border-outline-variant bg-[#d9e6da]/30 p-4 rounded-xl flex flex-col items-center gap-1.5"><span class="material-symbols-outlined text-3xl text-on-surface-variant">cloud_upload</span><p class="text-sm font-medium">Drag or <span class="text-primary font-bold">Browse</span></p><p class="text-xs text-on-surface-variant">${escapeHtml(s.accept)} max ${escapeHtml(s.maxSize)}</p></div>`;
      case 'info-block':
        return `<div class="flex gap-3 items-start"><span class="material-symbols-outlined text-primary">${escapeHtml(s.icon||'info')}</span><div><p class="font-semibold text-base">${escapeHtml(s.title)}</p><p class="text-on-surface-variant text-sm">${escapeHtml(s.message)}</p></div></div>`;
      case 'date-selector':
        return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)}</label><input type="date" class="w-full border border-outline-variant rounded-lg px-3 py-2 bg-white" disabled>`;
      case 'location-pin':
        return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)}</label><div class="flex items-center gap-2 border border-outline-variant rounded-lg px-3 py-2 bg-white"><span class="material-symbols-outlined text-outline text-base">location_on</span><span class="text-on-surface-variant text-sm">${escapeHtml(s.placeholder)}</span></div>`;
      case 'checkbox-group': {
        let optStr = Array.isArray(s.options) ? s.options.map(o => o.label?.en || o.value || o).join(', ') : (s.options||'');
        const opts = optStr.split(',').map(o=>o.trim()).filter(Boolean);
        return `<fieldset><legend class="text-sm font-medium mb-2">${escapeHtml(s.label)}</legend><div class="space-y-1.5">${opts.map(o=>`<label class="flex items-center gap-2 text-sm"><input type="checkbox" disabled> <span>${escapeHtml(o)}</span></label>`).join('')}</div></fieldset>`;
      }
      case 'radio-group': {
        let optStr = Array.isArray(s.options) ? s.options.map(o => o.label?.en || o.value || o).join(', ') : (s.options||'');
        const opts = optStr.split(',').map(o=>o.trim()).filter(Boolean);
        return `<fieldset><legend class="text-sm font-medium mb-2">${escapeHtml(s.label)}</legend><div class="space-y-1.5">${opts.map(o=>`<label class="flex items-center gap-2 text-sm"><input type="radio" disabled> <span>${escapeHtml(o)}</span></label>`).join('')}</div></fieldset>`;
      }
      case 'dropdown': {
        let optStr = Array.isArray(s.options) ? s.options.map(o => o.label?.en || o.value || o).join(', ') : (s.options||'');
        const opts = optStr.split(',').map(o=>o.trim()).filter(Boolean);
        return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)} ${s.required?'<span class="text-red-600">*</span>':''}</label><select class="w-full border border-outline-variant rounded-lg px-3 py-2 bg-white" disabled><option>— Select —</option>${opts.map(o=>`<option>${escapeHtml(o)}</option>`).join('')}</select>`;
      }
      case 'textarea':
        return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)}</label><textarea class="w-full border border-outline-variant rounded-lg px-3 py-2 bg-white text-sm" rows="3" placeholder="${escapeHtml(s.placeholder)}" disabled></textarea>`;
      default: return `<div class="text-red-500 text-sm">Unknown type: ${comp.type}</div>`;
    }
  }

  function renderCanvas() {
    const container = document.getElementById('canvasContainer');
    if (!container) return;
    if (formComponents.length === 0) {
      container.innerHTML = `<div class="border-2 border-dashed border-outline-variant rounded-xl p-8 flex flex-col items-center justify-center text-on-surface-variant bg-white/60"><span class="material-symbols-outlined text-5xl mb-2">add_circle</span><p class="text-base font-medium">Drag & drop elements from left sidebar</p><p class="text-xs mt-1">Build your diplomatic registration form</p></div>`;
      return;
    }
    container.innerHTML = formComponents.map(comp => {
      const isSelected = selectedComponentId === comp.id;
      return `<div class="bg-white border border-outline-variant rounded-xl overflow-hidden builder-item transition-all ${isSelected?'ring-2 ring-primary ring-offset-1':'hover:border-primary/50'}" data-component-id="${comp.id}">
        <div class="bg-surface-variant px-4 py-2.5 border-b border-outline-variant flex justify-between items-center">
          <span class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">${comp.type.toUpperCase().replace(/-/g,' ')}</span>
          <div class="flex gap-1">
            <button class="drag-handle-btn p-1.5 hover:bg-outline-variant rounded transition cursor-move" data-id="${comp.id}" title="Drag to reorder"><span class="material-symbols-outlined text-sm">drag_handle</span></button>
            <button class="delete-component p-1.5 hover:bg-red-50 rounded transition" data-id="${comp.id}" title="Delete"><span class="material-symbols-outlined text-sm text-red-600">delete</span></button>
          </div>
        </div>
        <div class="p-4 cursor-pointer" data-select-id="${comp.id}">${renderComponentPreview(comp)}</div>
      </div>`;
    }).join('');
    document.querySelectorAll('.delete-component').forEach(btn => {
      btn.addEventListener('click', e => { e.stopPropagation(); deleteComponentById(btn.getAttribute('data-id')); });
    });
    document.querySelectorAll('[data-select-id]').forEach(el => {
      el.addEventListener('click', () => selectComponent(el.getAttribute('data-select-id')));
    });
    attachDragSorting();
    setSaveState('unsaved');
    // Auto-save after 2 seconds of inactivity
    clearTimeout(autoSaveTimer);
    if (currentEventId) {
      autoSaveTimer = setTimeout(saveSchema, 2000);
    }
  }

  function deleteComponentById(id) {
    formComponents = formComponents.filter(c => c.id !== id);
    if (selectedComponentId === id) { selectedComponentId = null; updateSettingsPanel(null); }
    renderCanvas();
    showToast('Element removed');
  }

  function selectComponent(id) {
    selectedComponentId = id;
    renderCanvas();
    updateSettingsPanel(formComponents.find(c => c.id === id));
  }

  function updateSettingsPanel(component) {
    const panel = document.getElementById('settingsPanel');
    if (!component) { panel.innerHTML = `<div class="text-on-surface-variant text-center text-sm py-12 opacity-70">✨ Select any form element on the canvas to edit its properties</div>`; return; }
    const s = comp => comp.settings;
    const type = component.type;
    let html = `<div class="space-y-4"><div class="flex items-center gap-2 border-b border-outline-variant pb-2"><span class="material-symbols-outlined text-primary">tune</span><span class="font-bold text-sm">${type.toUpperCase().replace(/-/g,' ')} Settings</span></div>`;

    const field = (id, label, val, type='text') =>
      `<div><label class="text-xs font-semibold uppercase text-on-surface-variant block mb-1">${label}</label><input id="set_${id}" type="${type}" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm" value="${escapeHtml(val)}"></div>`;
    const check = (id, label, val) =>
      `<div class="flex justify-between items-center"><label class="text-sm">${label}</label><input type="checkbox" id="set_${id}" ${val?'checked':''} class="w-4 h-4 accent-green-700"></div>`;
    const optField = (val) =>
      `<div><label class="text-xs font-semibold uppercase text-on-surface-variant block mb-1">Options (comma separated)</label><input id="set_options" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm" value="${escapeHtml(val)}"></div>`;

    if (type === 'header') {
      html += field('title', 'Main Title', s(component).title) + field('subtitle', 'Subtitle', s(component).subtitle);
      html += `<div><label class="text-xs font-semibold uppercase text-on-surface-variant block mb-1">Label (FR)</label><input id="set_label_fr" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm" value="${escapeHtml(s(component).subtitle_fr||'')}"></div>`;
    } else if (type === 'info-block') {
      html += field('title','Title',s(component).title) + `<div><label class="text-xs font-semibold uppercase text-on-surface-variant block mb-1">Message</label><textarea id="set_message" rows="3" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm">${escapeHtml(s(component).message)}</textarea></div>` + field('icon','Icon name',s(component).icon||'info');
    } else {
      html += field('label','Label (EN)',s(component).label||'') + field('label_fr','Label (FR)',s(component).label_fr||'');
      if (type !== 'date-selector') html += field('placeholder','Placeholder',s(component).placeholder||'');
      if (['checkbox-group','radio-group','dropdown'].includes(type)) html += optField(s(component).options||'');
      if (type === 'file-upload') html += field('accept','Accepted types',s(component).accept||'PDF, JPG, PNG');
      html += check('required','Required field', s(component).required);
    }
    html += `<button id="applySettingsBtn" class="w-full mt-4 bg-primary text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-[#0a5517] transition">Apply Changes</button></div>`;
    panel.innerHTML = html;

    document.getElementById('applySettingsBtn')?.addEventListener('click', () => {
      const comp = formComponents.find(c => c.id === component.id);
      if (!comp) return;
      const g = id => document.getElementById('set_' + id)?.value;
      const gc = id => document.getElementById('set_' + id)?.checked;
      if (type === 'header') {
        comp.settings.title    = g('title')    || comp.settings.title;
        comp.settings.subtitle = g('subtitle') || comp.settings.subtitle;
      } else if (type === 'info-block') {
        comp.settings.title   = g('title')   || comp.settings.title;
        comp.settings.message = g('message') || comp.settings.message;
        comp.settings.icon    = g('icon')    || comp.settings.icon;
      } else {
        comp.settings.label       = g('label')       || comp.settings.label;
        comp.settings.label_fr    = g('label_fr')    || '';
        comp.settings.placeholder = g('placeholder') || comp.settings.placeholder;
        comp.settings.required    = gc('required');
        if (document.getElementById('set_options'))  comp.settings.options  = g('options');
        if (document.getElementById('set_accept'))   comp.settings.accept   = g('accept');
      }
      renderCanvas();
      selectComponent(comp.id);
      showToast('Settings applied');
    });
  }

  function addComponent(type) {
    const newComp = { id: uid(), type, settings: getDefaultSettings(type) };
    formComponents.push(newComp);
    renderCanvas();
    selectComponent(newComp.id);
    showToast('Added ' + type.replace(/-/g,' '));
  }

  // Drag & drop from sidebar
  document.querySelectorAll('.component-item').forEach(el => {
    el.addEventListener('dragstart', e => { e.dataTransfer.setData('text/plain', el.getAttribute('data-type')); el.style.opacity = '0.5'; });
    el.addEventListener('dragend',   e => { el.style.opacity = '1'; });
  });
  const canvas = document.getElementById('canvasContainer');
  if (canvas) {
    canvas.addEventListener('dragover', e => { e.preventDefault(); canvas.classList.add('drop-zone-active'); });
    canvas.addEventListener('dragleave', () => canvas.classList.remove('drop-zone-active'));
    canvas.addEventListener('drop', e => {
      e.preventDefault(); canvas.classList.remove('drop-zone-active');
      const t = e.dataTransfer.getData('text/plain');
      if (t) addComponent(t);
    });
  }

  function attachDragSorting() {
    document.querySelectorAll('.drag-handle-btn').forEach(h => {
      h.setAttribute('draggable', 'true');
      h.addEventListener('dragstart', ev => { ev.stopPropagation(); ev.dataTransfer.setData('text/sort-id', h.getAttribute('data-id')); });
    });
    document.querySelectorAll('[data-component-id]').forEach(el => {
      el.addEventListener('dragover', e => e.preventDefault());
      el.addEventListener('drop', e => {
        e.preventDefault();
        const srcId = e.dataTransfer.getData('text/sort-id');
        const tgt   = e.target.closest('[data-component-id]');
        if (!srcId || !tgt) return;
        const tgtId = tgt.getAttribute('data-component-id');
        if (srcId === tgtId) return;
        const si = formComponents.findIndex(c => c.id === srcId);
        const ti = formComponents.findIndex(c => c.id === tgtId);
        if (si !== -1 && ti !== -1) { const [m] = formComponents.splice(si,1); formComponents.splice(ti,0,m); renderCanvas(); selectComponent(m.id); showToast('Order updated'); }
      });
    });
  }

  function initForm() {
    // Load saved from localStorage first, then colleague's defaults
    const saved = localStorage.getItem('parliamentary_registration_builder');
    if (saved) {
      try { formComponents = JSON.parse(saved); } catch { formComponents = []; }
    }
    if (!formComponents.length) {
      formComponents = [
        { id: uid(), type: 'header',       settings: getDefaultSettings('header') },
        { id: uid(), type: 'info-block',   settings: getDefaultSettings('info-block') },
        { id: uid(), type: 'text-input',   settings: getDefaultSettings('text-input') },
        { id: uid(), type: 'email-input',  settings: getDefaultSettings('email-input') },
        { id: uid(), type: 'file-upload',  settings: getDefaultSettings('file-upload') },
        { id: uid(), type: 'checkbox-group', settings: getDefaultSettings('checkbox-group') },
      ];
    }
    renderCanvas();
    if (formComponents.length) selectComponent(formComponents[0].id);
  }

  document.getElementById('previewPortalBtn')?.addEventListener('click', () => {
    if (currentEventId) {
      // Try to get the slug
      ParlRegAPI.get('/events/' + currentEventId).then(r => {
        if (r.success && r.data?.slug) {
          window.open('../parliamentaryservices_updated.php', '_blank');
        }
      });
    } else {
      showToast('Select an event to preview its portal');
    }
  });

  document.getElementById('globalSaveBtn')?.addEventListener('click', saveSchema);
</script>
</body>
</html>