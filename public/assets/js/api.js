const ParlRegAPI = (() => {
  // Use your local PHP built-in server
  const BASE = 'http://localhost:8000/api/v1';
  // Try multiple bases; will use the first one that exists
//   const BASE = 
//     window.location.hostname === 'localhost' && window.location.port === '8000' ? 'http://localhost:8000/api/v1' :
//     window.location.hostname === 'localhost' && window.location.port === '8888' ? 'http://localhost:8888/api/v1' :
//     window.location.hostname === '0.0.0.0' ? 'http://0.0.0.0:8000/api/v1' :
//     'http://localhost:8000/api/v1'; // fallback

// console.log("Using API base:", BASE);
  

  let _csrf = '';

  function setCSRF(token) { _csrf = token || ''; }
  function getCSRF() { 
    return _csrf || window.PARLREG_CSRF || sessionStorage.getItem('parlreg_csrf') || ''; 
  }

  async function request(method, path, body = null, isForm = false) {
    const headers = { 'X-CSRF-Token': getCSRF() };
    if (!isForm && body) headers['Content-Type'] = 'application/json';

    const opts = { method, headers, credentials: 'include' };
    if (body) opts.body = isForm ? body : JSON.stringify(body);

    try {
      const res = await fetch(BASE + path, opts);
      const data = await res.json().catch(() => ({}));

      if (data.csrf_token) {
        _csrf = data.csrf_token;
        sessionStorage.setItem('parlreg_csrf', _csrf);
      }
      if (data.user) {
        sessionStorage.setItem('parlreg_user', JSON.stringify(data.user));
      }

      return data;
    } catch (err) {
      console.error('API request failed:', err);
      return { success: false, error: 'Connection error' };
    }
  }

  return {
    setCSRF,
    get: (path) => request('GET', path),
    post: (path, body) => request('POST', path, body),
    put: (path, body) => request('PUT', path, body),
    del: (path) => request('DELETE', path),
    upload: (path, fd) => request('POST', path, fd, true),

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

    requireAuth(redirectTo = 'login.php') {
      if (!this.isLoggedIn()) {
        window.location.href = redirectTo;
        return false;
      }
      return true;
    },
  };
})();