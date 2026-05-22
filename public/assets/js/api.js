/**
 * ParlReg API Client
 * Connects frontend pages to the PHP backend at /api/v1
 * Used by: login.php, events.php, register.php, dashboard.php
 */

const ParlRegAPI = (() => {
  // Adjust this base URL to match your local setup
  const BASE = (window.PARLREG_BASE || '') + '/api/v1';
  let _csrf = '';

  // Extract CSRF token from last login response and store it
  function setCSRF(token) { _csrf = token || ''; }
  function getCSRF() { return _csrf || window.PARLREG_CSRF || sessionStorage.getItem('parlreg_csrf') || ''; }

  async function request(method, path, body = null, isForm = false) {
    const headers = { 'X-CSRF-Token': getCSRF() };
    if (!isForm && body) headers['Content-Type'] = 'application/json';

    const opts = { method, headers, credentials: 'include' };
    if (body) opts.body = isForm ? body : JSON.stringify(body);

    const res = await fetch(BASE + path, opts);
    const data = await res.json().catch(() => ({}));

    // Auto-store CSRF token from login
    if (data.csrf_token) {
      _csrf = data.csrf_token;
      sessionStorage.setItem('parlreg_csrf', _csrf);
    }
    // Auto-store user info
    if (data.user) {
      sessionStorage.setItem('parlreg_user', JSON.stringify(data.user));
    }

    return data;
  }

  return {
    setCSRF,
    get: (path) => request('GET', path),
    post: (path, body) => request('POST', path, body),
    put: (path, body) => request('PUT', path, body),
    del: (path) => request('DELETE', path),
    upload: (path, fd) => request('POST', path, fd, true),

    // Auth helpers
    getUser() {
      try { return JSON.parse(sessionStorage.getItem('parlreg_user') || 'null'); }
      catch { return null; }
    },
    isLoggedIn() { return !!this.getUser(); },
    logout() {
      sessionStorage.removeItem('parlreg_user');
      sessionStorage.removeItem('parlreg_csrf');
      return this.post('/auth/logout');
    },

    // Require auth — redirect to login if not authenticated
    requireAuth(redirectTo = '../login.php') {
      if (!this.isLoggedIn()) {
        window.location.href = redirectTo;
        return false;
      }
      return true;
    },
  };
})();