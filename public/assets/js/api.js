/**
 * API Client for ParlReg Backend
 * Handles all communication with the API endpoints
 */

const API_BASE = '/api/v1';

class APIClient {
  constructor(baseUrl = API_BASE) {
    this.baseUrl = baseUrl;
    this.headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
  }

  /**
   * Make HTTP request
   */
  async request(method, endpoint, body = null) {
    const url = this.baseUrl + endpoint;
    const options = {
      method,
      headers: this.headers,
    };

    if (body) {
      options.body = JSON.stringify(body);
    }

    try {
      const response = await fetch(url, options);
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || `HTTP ${response.status}`);
      }

      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // ── Auth Endpoints ────────────────────────────────────────────────────────
  login(email, password) {
    return this.request('POST', '/auth/login', { email, password });
  }

  logout() {
    return this.request('POST', '/auth/logout');
  }

  getCurrentUser() {
    return this.request('GET', '/auth/me');
  }

  // ── Events Endpoints ──────────────────────────────────────────────────────
  getEvents(filters = {}) {
    const params = new URLSearchParams(filters).toString();
    return this.request('GET', `/events${params ? '?' + params : ''}`);
  }

  getEvent(eventId) {
    return this.request('GET', `/events/${eventId}`);
  }

  createEvent(data) {
    return this.request('POST', '/events', data);
  }

  updateEvent(eventId, data) {
    return this.request('PUT', `/events/${eventId}`, data);
  }

  // ── Portal Endpoints ──────────────────────────────────────────────────────
  getPortalEvent(slug) {
    return this.request('GET', `/portal/${slug}`);
  }

  getPortalSchema(slug) {
    return this.request('GET', `/portal/${slug}/schema`);
  }

  // ── Registration Endpoints ────────────────────────────────────────────────
  submitRegistration(eventId, data) {
    return this.request('POST', `/events/${eventId}/register`, data);
  }

  getRegistrations(eventId, filters = {}) {
    const params = new URLSearchParams(filters).toString();
    return this.request('GET', `/events/${eventId}/registrations${params ? '?' + params : ''}`);
  }

  getRegistration(eventId, registrationId) {
    return this.request('GET', `/events/${eventId}/registrations/${registrationId}`);
  }

  updateRegistrationStatus(eventId, registrationId, status) {
    return this.request('PUT', `/events/${eventId}/registrations/${registrationId}/status`, { status });
  }

  // ── Error Handler ─────────────────────────────────────────────────────────
  handleError(error) {
    if (error.message.includes('401') || error.message.includes('Unauthenticated')) {
      // Redirect to login
      window.location.href = '/admin/login.html';
    } else if (error.message.includes('403') || error.message.includes('Unauthorized')) {
      console.error('Access denied');
    } else {
      console.error('Request failed:', error.message);
    }
  }
}

// Create global API instance
const api = new APIClient();
