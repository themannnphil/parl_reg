<?php require_once __DIR__ . '/../../app/bootstrap.php'; 
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {

    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Parliamentary Services | Registration Builder</title>
  <!-- Google Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0,1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#0d631b',
            'primary-container': '#2e7d32',
            'on-primary': '#ffffff',
            surface: '#f9f9f9',
            'surface-container-low': '#f3f3f3',
            'surface-container-lowest': '#ffffff',
            'surface-variant': '#e2e2e2',
            'on-surface': '#1a1c1c',
            'on-surface-variant': '#40493d',
            outline: '#707a6c',
            'outline-variant': '#bfcaba',
          },
          fontFamily: { 'body-md': ['Inter', 'sans-serif'] },
        }
      }
    }
  </script>
  <style>
    /* CRITICAL: Fix scrolling - ensure body and main have proper height constraints */
    html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
    body { display: flex; flex-direction: column; overflow: hidden; }
    
    /* Main flex column takes remaining space and allows inner scrolling */
    .main-workspace {
      flex: 1;
      min-height: 0;  /* flex children can shrink below content size */
      display: flex;
      overflow: hidden;
    }
    
    /* Each panel independently scrollable */
    .left-sidebar { overflow-y: auto; overflow-x: hidden; height: 100%; }
    .canvas-area { overflow-y: auto; overflow-x: hidden; height: 100%; }
    .right-sidebar { overflow-y: auto; overflow-x: hidden; height: 100%; display: flex; flex-direction: column; }
    .settings-scroll { flex: 1; overflow-y: auto; }
    
    .canvas-bg {
      background-image: radial-gradient(#d1d5db 0.8px, transparent 0.8px);
      background-size: 24px 24px;
    }
    .component-item { cursor: grab; user-select: none; }
    .component-item:active { cursor: grabbing; }
    .drop-zone-active { border-color: #0d631b !important; background-color: rgba(46, 125, 50, 0.08); }
    .builder-item { transition: all 0.2s ease; }
    .builder-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #e2e2e2; border-radius: 8px; }
    ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 8px; }
    ::-webkit-scrollbar-thumb:hover { background: #0d631b; }

    /* Parliament logo badge styling */
    .parliament-logo {
      transition: transform 0.2s ease;
    }
    .parliament-logo:hover {
      transform: scale(1.02);
    }
  </style>
</head>
<body class="bg-surface text-on-surface font-body-md">
  <!-- Top Navigation - fixed height, no overflow -->
  <header class="bg-white border-b border-outline-variant shadow-sm z-50 shrink-0">
    <div class="flex justify-between items-center w-full px-6 py-3 max-w-[1400px] mx-auto">
      <div class="flex items-center gap-4">
        <!-- Left section: Logo + Branding (enhanced with an emblem image) -->
<div class="flex items-center gap-3">
  <!-- Parliamentary Services Emblem / Logo (SVG-based emblem with classic parliamentary look) -->
  <div class="parliament-logo flex items-center justify-center w-10 h-10 bg-primary/10 rounded-xl shadow-sm border border-primary/20">
    <img src="C:\Users\Enoch Nartey\Downloads\Ghana_Parliament_Emblem.png" alt="Parliamentary Services Logo" class="w-8 h-8 object-contain">
      <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#0d631b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="#a3f69c" fill-opacity="0.3"/>
      <path d="M2 17L12 22L22 17" stroke="#0d631b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      <path d="M2 12L12 17L22 12" stroke="#0d631b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      <circle cx="12" cy="12" r="2" fill="#0d631b" stroke="white" stroke-width="1"/>
      <path d="M7 8L12 10.5L17 8" stroke="#0d631b" stroke-width="1.2" stroke-linecap="round"/>
    </svg>
  </div>
  <div class="flex items-baseline gap-2">
  </div>
</div>
        <span class="text-xl font-bold text-on-surface">Parliamentary Services</span>
        <div class="h-6 w-px bg-outline-variant"></div>
        <h1 class="text-xl font-bold text-primary">Registration Builder </h1>
      </div>
      <nav class="hidden md:flex items-center gap-4">
        <a href="#" class="text-sm font-medium text-primary border-b-2 border-primary pb-1">Dashboard</a>
        <a href="#" class="text-sm font-medium text-on-surface-variant hover:text-primary">Events</a>
        <a href="#" class="text-sm font-medium text-on-surface-variant hover:text-primary">Resources</a>
        <a href="#" class="text-sm font-medium text-on-surface-variant hover:text-primary">Directory</a>
      </nav>
      <div class="flex items-center gap-2">
        <button class="p-1 text-on-surface-variant hover:text-primary"><span class="material-symbols-outlined text-xl">language</span></button>
        <button class="p-1 text-on-surface-variant hover:text-primary"><span class="material-symbols-outlined text-xl">help</span></button>
        <button id="previewPortalBtn" class="bg-primary text-on-primary px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-[#0a4e16] transition shadow-sm">Preview Portal</button>
      </div>
    </div>
  </header>

  <!-- Main Workspace: three columns with independent scrolling -->
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
        <div draggable="true" data-type="file-upload" class="component-item group bg-white border border-outline-variant p-3 rounded-lg flex items-center gap-3 hover:border-primary">
          <span class="material-symbols-outlined">upload_file</span>
          <span class="text-sm font-medium">File Upload</span>
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
      </div>
    </aside>

    <!-- CENTER: Canvas Area (scrollable) -->
    <section class="flex-1 bg-surface-container-low p-6 canvas-area canvas-bg" id="canvasSection">
      <div class="max-w-3xl mx-auto space-y-4" id="canvasContainer"></div>
      <div class="max-w-3xl mx-auto mt-2 text-center text-sm text-on-surface-variant opacity-60 pb-4">
        <span class="material-symbols-outlined text-base align-middle">drag_indicator</span> Drag elements from left or reorder using handles
      </div>
    </section>

    <!-- RIGHT SIDEBAR: Settings panel (scrollable) -->
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
          <span class="material-symbols-outlined text-base">save</span> Save Registration Structure
        </button>
      </div>
    </aside>
  </div>

  <!-- Footer - subtle, not blocking scroll -->
  <footer class="bg-white border-t border-outline-variant py-2.5 px-6 shrink-0">
    <div class="max-w-[1400px] mx-auto flex flex-col md:flex-row justify-between items-center gap-2 text-xs text-on-surface-variant">
      <span>© 2024 Parliamentary Services. Official Portal for Diplomatic Relations.</span>
      <div class="flex gap-4">
        <a href="#" class="hover:text-primary transition">Contact Support</a>
        <a href="#" class="hover:text-primary transition">Privacy Policy</a>
        <a href="#" class="hover:text-primary transition">Accessibility</a>
      </div>
    </div>
  </footer>

  <script>
    // --- Registration Builder State (full interactive)---
    let formComponents = [];
    let selectedComponentId = null;

    function uid() { return Date.now() + '-' + Math.random().toString(36).substring(2, 10); }

    function getDefaultSettings(type) {
      switch(type) {
        case 'header': return { title: 'Registration: Diplomatic Summit', subtitle: 'Official Delegate Entry Portal', logoAlign: 'left', required: false };
        case 'text-input': return { label: 'Full Diplomatic Name', placeholder: 'As written on passport', required: true };
        case 'file-upload': return { label: 'Credential Verification', accept: 'PDF, JPG or PNG', maxSize: '5MB', required: false };
        case 'info-block': return { title: 'Security Clearance Required', message: 'All delegates must provide valid diplomatic credentials and proof of government affiliation to finalize their registration.', icon: 'verified_user' };
        case 'date-selector': return { label: 'Arrival Date', placeholder: 'Select date', required: false };
        case 'location-pin': return { label: 'Diplomatic Mission Location', placeholder: 'Embassy / Mission address', required: false };
        case 'checkbox-group': return { label: 'Access Requirements', options: ['Press Accreditation', 'VIP Transport', 'Interpreter Needed'], required: false };
        default: return {};
      }
    }

    function escapeHtml(str) { if(!str) return ''; return String(str).replace(/[&<>]/g, function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

    function renderComponentPreview(comp) {
      const s = comp.settings;
      switch(comp.type) {
        case 'header':
          return `<div class="flex items-center gap-4"><div class="w-14 h-14 bg-[#d9e6da] rounded-xl flex items-center justify-center"><span class="material-symbols-outlined text-primary text-3xl">account_balance</span></div><div><h2 class="text-2xl font-semibold text-on-surface">${escapeHtml(s.title)}</h2><p class="text-base text-on-surface-variant">${escapeHtml(s.subtitle)}</p></div></div>`;
        case 'text-input':
          return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)} ${s.required ? '<span class="text-red-600">*</span>' : ''}</label><input class="w-full bg-white border border-outline-variant rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-primary outline-none" type="text" placeholder="${escapeHtml(s.placeholder)}" disabled style="background:#fefefe;">`;
        case 'file-upload':
          return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)}</label><div class="border-2 border-dashed border-outline-variant bg-[#d9e6da]/30 p-4 rounded-xl flex flex-col items-center gap-1.5"><span class="material-symbols-outlined text-3xl text-on-surface-variant">cloud_upload</span><p class="text-sm font-medium">Drag or <span class="text-primary font-bold">Browse</span></p><p class="text-xs text-on-surface-variant">${s.accept} max ${s.maxSize}</p></div>`;
        case 'info-block':
          return `<div class="flex gap-3 items-start"><span class="material-symbols-outlined text-primary">${s.icon || 'info'}</span><div><p class="font-semibold text-base">${escapeHtml(s.title)}</p><p class="text-on-surface-variant text-sm">${escapeHtml(s.message)}</p></div></div>`;
        case 'date-selector':
          return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)}</label><input type="date" class="w-full border border-outline-variant rounded-lg px-3 py-2 bg-white" disabled>`;
        case 'location-pin':
          return `<label class="text-sm font-medium block mb-1.5">${escapeHtml(s.label)}</label><div class="flex items-center gap-2 border border-outline-variant rounded-lg px-3 py-2 bg-white"><span class="material-symbols-outlined text-outline text-base">location_on</span><span class="text-on-surface-variant text-sm">${escapeHtml(s.placeholder)}</span></div>`;
        case 'checkbox-group':
          const opts = (s.options || []).map(opt => `<label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-outline" disabled> <span>${escapeHtml(opt)}</span></label>`).join('');
          return `<fieldset><legend class="text-sm font-medium mb-2">${escapeHtml(s.label)}</legend><div class="space-y-1.5">${opts}</div></fieldset>`;
        default: return `<div class="text-error">Preview error</div>`;
      }
    }

    function renderCanvas() {
      const container = document.getElementById('canvasContainer');
      if (!container) return;
      if (formComponents.length === 0) {
        container.innerHTML = `<div class="border-2 border-dashed border-outline-variant rounded-xl p-8 flex flex-col items-center justify-center text-on-surface-variant bg-white/60">
          <span class="material-symbols-outlined text-5xl mb-2">add_circle</span>
          <p class="text-base font-medium">Drag & drop elements from left sidebar</p>
          <p class="text-xs mt-1">Build your diplomatic registration form</p>
        </div>`;
        return;
      }
      container.innerHTML = formComponents.map(comp => {
        const isSelected = selectedComponentId === comp.id;
        const borderClass = isSelected ? 'ring-2 ring-primary ring-offset-1' : 'hover:border-primary/50';
        return `
          <div class="bg-white border border-outline-variant rounded-xl overflow-hidden builder-item transition-all ${borderClass}" data-component-id="${comp.id}">
            <div class="bg-surface-variant px-4 py-2.5 border-b border-outline-variant flex justify-between items-center">
              <span class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">${comp.type.toUpperCase().replace('-',' ')}</span>
              <div class="flex gap-1">
                <button class="drag-handle-btn p-1.5 hover:bg-outline-variant rounded transition cursor-move" data-id="${comp.id}" title="Drag to reorder">
                  <span class="material-symbols-outlined text-sm">drag_handle</span>
                </button>
                <button class="delete-component p-1.5 hover:bg-red-50 rounded transition" data-id="${comp.id}" title="Delete">
                  <span class="material-symbols-outlined text-sm text-red-600">delete</span>
                </button>
              </div>
            </div>
            <div class="p-4 cursor-pointer" data-select-id="${comp.id}">
              ${renderComponentPreview(comp)}
            </div>
          </div>
        `;
      }).join('');
      
      // Attach listeners
      document.querySelectorAll('.delete-component').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const id = btn.getAttribute('data-id');
          deleteComponentById(id);
        });
      });
      document.querySelectorAll('[data-select-id]').forEach(el => {
        el.addEventListener('click', (e) => {
          const id = el.getAttribute('data-select-id');
          if (id) selectComponent(id);
        });
      });
      attachDragSorting();
    }

    function deleteComponentById(id) {
      formComponents = formComponents.filter(c => c.id !== id);
      if (selectedComponentId === id) selectedComponentId = null;
      renderCanvas();
      if (selectedComponentId === null) updateSettingsPanel(null);
      showToast('Element removed');
    }

    function selectComponent(id) {
      selectedComponentId = id;
      renderCanvas();
      const comp = formComponents.find(c => c.id === id);
      updateSettingsPanel(comp);
    }

    function updateSettingsPanel(component) {
      const panel = document.getElementById('settingsPanel');
      if (!component) {
        panel.innerHTML = `<div class="text-on-surface-variant text-center text-sm py-12 opacity-70">✨ Select any form element on the canvas to edit its properties</div>`;
        return;
      }
      const s = component.settings;
      const type = component.type;
      let html = `<div class="space-y-5"><div class="flex items-center gap-2 border-b border-outline-variant pb-2"><span class="material-symbols-outlined text-primary">tune</span><span class="font-bold text-base">${type.toUpperCase()} Settings</span></div>`;
      
      if (type === 'header') {
        html += `<div><label class="text-xs font-semibold uppercase">Main Title</label><input id="set_title" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" value="${escapeHtml(s.title)}"></div>
                 <div><label class="text-xs font-semibold uppercase">Subtitle</label><input id="set_subtitle" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" value="${escapeHtml(s.subtitle)}"></div>
                 <div><label class="text-xs font-semibold uppercase">Logo Alignment</label><div class="flex gap-2 mt-2"><button data-align="left" class="align-btn px-3 py-1.5 text-sm border rounded-lg ${s.logoAlign === 'left' ? 'bg-primary text-white border-primary' : 'bg-white'}">Left</button><button data-align="center" class="align-btn px-3 py-1.5 text-sm border rounded-lg ${s.logoAlign === 'center' ? 'bg-primary text-white border-primary' : 'bg-white'}">Center</button><button data-align="right" class="align-btn px-3 py-1.5 text-sm border rounded-lg ${s.logoAlign === 'right' ? 'bg-primary text-white border-primary' : 'bg-white'}">Right</button></div></div>
                 <div class="flex justify-between items-center"><span class="text-sm">Required field</span><input type="checkbox" id="set_required" ${s.required ? 'checked' : ''} class="w-4 h-4"></div>`;
      } 
      else if (type === 'text-input') {
        html += `<div><label>Label</label><input id="set_label" class="w-full border rounded-lg px-3 py-2 text-sm" value="${escapeHtml(s.label)}"></div>
                 <div><label>Placeholder</label><input id="set_placeholder" class="w-full border rounded-lg px-3 py-2 text-sm" value="${escapeHtml(s.placeholder)}"></div>
                 <div class="flex justify-between"><label>Required</label><input type="checkbox" id="set_required" ${s.required ? 'checked' : ''}></div>`;
      }
      else if (type === 'file-upload') {
        html += `<div><label>Label</label><input id="set_label" class="w-full border rounded-lg px-3 py-2 text-sm" value="${escapeHtml(s.label)}"></div>
                 <div><label>Accepted formats</label><input id="set_accept" value="${escapeHtml(s.accept)}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                 <div class="flex justify-between"><label>Required</label><input type="checkbox" id="set_required" ${s.required ? 'checked' : ''}></div>`;
      }
      else if (type === 'info-block') {
        html += `<div><label>Title</label><input id="set_title" class="w-full border rounded-lg px-3 py-2 text-sm" value="${escapeHtml(s.title)}"></div>
                 <div><label>Message</label><textarea id="set_message" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm">${escapeHtml(s.message)}</textarea></div>
                 <div><label>Icon name</label><input id="set_icon" value="${escapeHtml(s.icon)}" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="verified_user"></div>`;
      }
      else if (type === 'date-selector') {
        html += `<div><label>Label</label><input id="set_label" value="${escapeHtml(s.label)}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                 <div class="flex justify-between"><label>Required</label><input type="checkbox" id="set_required" ${s.required ? 'checked' : ''}></div>`;
      }
      else if (type === 'location-pin') {
        html += `<div><label>Label</label><input id="set_label" value="${escapeHtml(s.label)}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                 <div><label>Hint / placeholder</label><input id="set_placeholder" value="${escapeHtml(s.placeholder)}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>`;
      }
      else if (type === 'checkbox-group') {
        html += `<div><label>Group Label</label><input id="set_label" value="${escapeHtml(s.label)}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                 <div><label>Options (comma separated)</label><input id="set_options" value="${(s.options || []).join(', ')}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                 <div class="flex justify-between"><label>Required</label><input type="checkbox" id="set_required" ${s.required ? 'checked' : ''}></div>`;
      }
      html += `<button id="applySettingsBtn" class="w-full mt-5 bg-primary/90 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-primary transition">Apply Changes</button></div>`;
      panel.innerHTML = html;
      
      // Alignment logic for header
      document.querySelectorAll('.align-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          if (component.type === 'header') {
            const align = btn.getAttribute('data-align');
            component.settings.logoAlign = align;
            renderCanvas();
            selectComponent(component.id);
          }
        });
      });
      
      document.getElementById('applySettingsBtn')?.addEventListener('click', () => {
        const comp = formComponents.find(c => c.id === component.id);
        if (!comp) return;
        const upd = comp.settings;
        if (type === 'header') {
          upd.title = document.getElementById('set_title')?.value || upd.title;
          upd.subtitle = document.getElementById('set_subtitle')?.value || upd.subtitle;
          upd.required = document.getElementById('set_required')?.checked || false;
        } else if (type === 'text-input' || type === 'file-upload' || type === 'date-selector' || type === 'location-pin') {
          if (document.getElementById('set_label')) upd.label = document.getElementById('set_label').value;
          if (document.getElementById('set_placeholder')) upd.placeholder = document.getElementById('set_placeholder').value;
          if (document.getElementById('set_required')) upd.required = document.getElementById('set_required').checked;
          if (type === 'file-upload' && document.getElementById('set_accept')) upd.accept = document.getElementById('set_accept').value;
        } else if (type === 'info-block') {
          upd.title = document.getElementById('set_title')?.value || upd.title;
          upd.message = document.getElementById('set_message')?.value || upd.message;
          upd.icon = document.getElementById('set_icon')?.value || upd.icon;
        } else if (type === 'checkbox-group') {
          upd.label = document.getElementById('set_label')?.value || upd.label;
          const optsRaw = document.getElementById('set_options')?.value || '';
          upd.options = optsRaw.split(',').map(s => s.trim()).filter(s => s);
          upd.required = document.getElementById('set_required')?.checked || false;
        }
        renderCanvas();
        selectComponent(comp.id);
        showToast('Settings applied');
      });
    }

    function addComponent(type, insertAfterId = null) {
      const newComp = { id: uid(), type: type, settings: getDefaultSettings(type) };
      if (insertAfterId) {
        const idx = formComponents.findIndex(c => c.id === insertAfterId);
        if (idx !== -1) formComponents.splice(idx + 1, 0, newComp);
        else formComponents.push(newComp);
      } else {
        formComponents.push(newComp);
      }
      renderCanvas();
      selectComponent(newComp.id);
      showToast(`Added ${type.replace('-',' ')}`);
    }

    function showToast(msg) {
      let toast = document.getElementById('dynamicToast');
      if (!toast) {
        toast = document.createElement('div');
        toast.id = 'dynamicToast';
        toast.className = 'fixed bottom-20 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-4 py-2 rounded-full text-sm shadow-lg z-50 transition-all opacity-0';
        document.body.appendChild(toast);
      }
      toast.innerText = msg;
      toast.style.opacity = '1';
      setTimeout(() => toast.style.opacity = '0', 2000);
    }

    // Drag & drop from sidebar
    document.querySelectorAll('.component-item').forEach(el => {
      el.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('text/plain', el.getAttribute('data-type'));
        e.dataTransfer.effectAllowed = 'copy';
        el.style.opacity = '0.5';
      });
      el.addEventListener('dragend', (e) => { el.style.opacity = '1'; });
    });
    
    const canvasContainerDiv = document.getElementById('canvasContainer');
    if (canvasContainerDiv) {
      canvasContainerDiv.addEventListener('dragover', (e) => { e.preventDefault(); canvasContainerDiv.classList.add('drop-zone-active'); });
      canvasContainerDiv.addEventListener('dragleave', () => { canvasContainerDiv.classList.remove('drop-zone-active'); });
      canvasContainerDiv.addEventListener('drop', (e) => {
        e.preventDefault();
        canvasContainerDiv.classList.remove('drop-zone-active');
        const compType = e.dataTransfer.getData('text/plain');
        if (compType) addComponent(compType);
      });
    }
    
    // Reorder via drag handles
    function attachDragSorting() {
      document.querySelectorAll('.drag-handle-btn').forEach(handle => {
        handle.setAttribute('draggable', 'true');
        handle.addEventListener('dragstart', (ev) => {
          ev.stopPropagation();
          const id = handle.getAttribute('data-id');
          ev.dataTransfer.setData('text/sort-id', id);
          ev.dataTransfer.effectAllowed = 'move';
        });
      });
      document.querySelectorAll('[data-component-id]').forEach(el => {
        el.addEventListener('dragover', (e) => e.preventDefault());
        el.addEventListener('drop', (e) => {
          e.preventDefault();
          const sourceId = e.dataTransfer.getData('text/sort-id');
          const targetEl = e.target.closest('[data-component-id]');
          if (!sourceId || !targetEl) return;
          const targetId = targetEl.getAttribute('data-component-id');
          if (sourceId === targetId) return;
          const srcIdx = formComponents.findIndex(c => c.id === sourceId);
          const tgtIdx = formComponents.findIndex(c => c.id === targetId);
          if (srcIdx !== -1 && tgtIdx !== -1) {
            const [moved] = formComponents.splice(srcIdx, 1);
            formComponents.splice(tgtIdx, 0, moved);
            renderCanvas();
            selectComponent(moved.id);
            showToast('Order updated');
          }
        });
      });
    }
    
    // Load defaults: Header + Info + Text + FileUpload + Checkbox for demo
    function initForm() {
      formComponents = [
        { id: uid(), type: 'header', settings: getDefaultSettings('header') },
        { id: uid(), type: 'info-block', settings: getDefaultSettings('info-block') },
        { id: uid(), type: 'text-input', settings: getDefaultSettings('text-input') },
        { id: uid(), type: 'file-upload', settings: getDefaultSettings('file-upload') },
        { id: uid(), type: 'checkbox-group', settings: getDefaultSettings('checkbox-group') }
      ];
      renderCanvas();
      if (formComponents.length) selectComponent(formComponents[0].id);
    }
    initForm();
    
    document.getElementById('previewPortalBtn')?.addEventListener('click', () => {
      showToast('📋 Preview ready: Registration structure reflects current builder design');
      alert('Preview Mode: Your registration flow includes ' + formComponents.length + ' form sections.');
    });
    document.getElementById('globalSaveBtn')?.addEventListener('click', () => {
      localStorage.setItem('parliamentary_registration_builder', JSON.stringify(formComponents));
      showToast('✅ Registration structure saved successfully!');
    });
  </script>
</body>
</html>