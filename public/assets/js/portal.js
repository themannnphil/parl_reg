/**
 * Portal JavaScript
 * Handles event listing and registration form submission
 */

let currentEventId = null;
let currentEventSlug = null;

document.addEventListener('DOMContentLoaded', async () => {
  loadEvents();
  setupFormListeners();
  setupLanguageToggle();
});

/**
 * Load and display available events
 */
async function loadEvents() {
  try {
    const response = await api.request('GET', '/events?status=published');
    const events = response.data || [];

    const eventsList = document.getElementById('eventsList');
    eventsList.innerHTML = '';

    if (events.length === 0) {
      eventsList.innerHTML = '<p class="text-muted">No events available at this time.</p>';
      return;
    }

    events.forEach(event => {
      const eventCard = document.createElement('button');
      eventCard.type = 'button';
      eventCard.className = 'btn btn-outline-success text-start p-3 rounded-3';
      eventCard.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h6 class="mb-1 fw-bold">${event.name_en}</h6>
            <small class="text-muted">
              <i class="bi bi-calendar"></i> ${formatDate(event.date_start)}
            </small>
          </div>
          <i class="bi bi-chevron-right"></i>
        </div>
      `;
      eventCard.addEventListener('click', () => selectEvent(event));
      eventsList.appendChild(eventCard);
    });
  } catch (error) {
    console.error('Failed to load events:', error);
    document.getElementById('eventsList').innerHTML = 
      '<div class="alert alert-warning">Failed to load events. Please try again.</div>';
  }
}

/**
 * Select event and show registration form
 */
async function selectEvent(event) {
  currentEventId = event.id;
  currentEventSlug = event.slug;

  // Update form with event info
  document.getElementById('heroTitle').textContent = event.name_en;
  document.getElementById('eventDate').textContent = formatDateRange(event.date_start, event.date_end);
  document.getElementById('eventLocation').textContent = event.location_en;
  document.getElementById('eventOverview').textContent = 
    `This international forum brings together Members of Parliament and officials to address key policy challenges. ${event.description_en || ''}`;

  // Show registration form
  document.getElementById('registrationForm').style.display = 'block';
  
  // Scroll to form
  document.getElementById('registrationForm').scrollIntoView({ behavior: 'smooth' });

  // Load form schema if available
  try {
    const schemaResponse = await api.request('GET', `/events/${event.id}/schema`);
    if (schemaResponse.data) {
      buildDynamicForm(schemaResponse.data);
    }
  } catch (error) {
    console.warn('Could not load custom form schema:', error);
  }
}

/**
 * Build dynamic form from schema
 */
function buildDynamicForm(schema) {
  const form = document.getElementById('submitForm');
  const staticFields = form.querySelectorAll('input[type!="hidden"], textarea, select');
  
  // Keep static fields, add schema fields
  schema.forEach(field => {
    if (field.type === 'text' && !form.querySelector(`[name="${field.id}"]`)) {
      const div = document.createElement('div');
      div.className = 'mb-3';
      div.innerHTML = `
        <label for="${field.id}" class="form-label fw-5">${field.label.en}</label>
        <input type="text" class="form-control" id="${field.id}" name="${field.id}" 
               placeholder="${field.placeholder?.en || ''}" ${field.required ? 'required' : ''}>
      `;
      form.insertBefore(div, form.querySelector('button'));
    }
  });
}

/**
 * Setup form submission
 */
function setupFormListeners() {
  const form = document.getElementById('submitForm');
  
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (!currentEventId) {
      showFormMessage('Please select an event first', 'warning');
      return;
    }

    const button = form.querySelector('button[type="submit"]');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

    try {
      const formData = new FormData(form);
      const data = Object.fromEntries(formData);
      data.lang = document.documentElement.lang || 'en';

      const response = await api.request('POST', `/events/${currentEventId}/register`, data);

      showFormMessage(
        `Registration successful! Reference: <strong>${response.reference_number}</strong>. A confirmation email has been sent.`,
        'success'
      );
      form.reset();
      
      // Redirect after success
      setTimeout(() => {
        window.location.href = `/`;
      }, 3000);
    } catch (error) {
      showFormMessage(`Registration failed: ${error.message}`, 'danger');
    } finally {
      button.disabled = false;
      button.innerHTML = originalText;
    }
  });
}

/**
 * Setup language toggle
 */
function setupLanguageToggle() {
  const langToggle = document.getElementById('langToggle');
  const langText = document.getElementById('langText');
  
  langToggle.addEventListener('click', () => {
    const currentLang = document.documentElement.lang || 'en';
    const newLang = currentLang === 'en' ? 'fr' : 'en';
    document.documentElement.lang = newLang;
    langText.textContent = newLang === 'en' ? 'FR' : 'EN';
    
    // Store preference
    localStorage.setItem('language', newLang);
    
    // Reload events with new language
    loadEvents();
  });
}

/**
 * Helper: Show form message
 */
function showFormMessage(message, type = 'info') {
  const messageDiv = document.getElementById('formMessage');
  messageDiv.className = `alert alert-${type}`;
  messageDiv.innerHTML = message;
  messageDiv.style.display = 'block';
}

/**
 * Helper: Format date
 */
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  });
}

/**
 * Helper: Format date range
 */
function formatDateRange(startDate, endDate) {
  const start = new Date(startDate);
  const end = new Date(endDate);
  
  if (start.toDateString() === end.toDateString()) {
    return start.toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    });
  }
  
  return `${formatDate(startDate)} - ${formatDate(endDate)}`;
}
