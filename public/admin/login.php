<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In — Parliamentary Services</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <!-- Heroicons via unpkg -->
  <script src="https://unpkg.com/heroicons@2.1.3/dist/heroicons.min.js" defer></script>
  <link rel="stylesheet" href="../assets/css/parlreg.css"/>
  <style>
    :root { --ps-green: #0b6b1b; --ps-green-dark: #046415; }

    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #f0f7f1 0%, #e8f5e9 100%);
      display: flex; flex-direction: column;
    }

    /* ── Login card ────────────────────────────────────────────────── */
    .login-card {
      border: none;
      border-radius: 20px;
      box-shadow: 0 8px 40px rgba(11,107,27,.10);
      animation: slideUp .45s cubic-bezier(.4,0,.2,1) both;
    }
    @keyframes slideUp {
      from { opacity:0; transform:translateY(28px); }
      to   { opacity:1; transform:translateY(0); }
    }

    .login-brand-icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, #0b6b1b 0%, #2e7d32 100%);
      border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 14px rgba(11,107,27,.30);
      margin: 0 auto;
    }

    /* ── Inputs ────────────────────────────────────────────────────── */
    .form-control-ps {
      border: 1.5px solid #d1e8d4;
      border-radius: 10px;
      padding: 10px 14px 10px 42px;
      font-size: 14px;
      transition: border-color .2s, box-shadow .2s;
      background: #fff;
    }
    .form-control-ps:focus {
      border-color: var(--ps-green);
      box-shadow: 0 0 0 3px rgba(11,107,27,.12);
      outline: none;
    }
    .input-icon {
      position: absolute; left: 14px; top: 50%;
      transform: translateY(-50%);
      color: #9dbda3; pointer-events: none;
      width: 18px; height: 18px;
    }
    .toggle-pw {
      position: absolute; right: 14px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      color: #9dbda3; cursor: pointer; padding: 0;
      transition: color .15s;
    }
    .toggle-pw:hover { color: var(--ps-green); }

    /* ── Button ────────────────────────────────────────────────────── */
    .btn-ps {
      background: var(--ps-green);
      color: #fff;
      border-radius: 10px;
      font-weight: 600;
      padding: 11px;
      border: none;
      transition: background .2s, transform .1s, box-shadow .2s;
      position: relative; overflow: hidden;
    }
    .btn-ps:hover {
      background: var(--ps-green-dark);
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(11,107,27,.25);
    }
    .btn-ps:active { transform: translateY(0); }
    .btn-ps .spinner-border { width:1rem; height:1rem; border-width:2px; }

    /* ── Divider ───────────────────────────────────────────────────── */
    .divider { display:flex; align-items:center; gap:12px; color:#9dbda3; font-size:12px; }
    .divider::before, .divider::after { content:''; flex:1; height:1px; background:#d1e8d4; }

    /* ── Alert ─────────────────────────────────────────────────────── */
    .alert-ps {
      border-radius: 10px; font-size: 13.5px;
      border: 1px solid; padding: 10px 14px;
      display: flex; align-items: center; gap: 8px;
    }
    .alert-ps.error   { background:#fff0f0; border-color:#fcc; color:#b91c1c; }
    .alert-ps.success { background:#f0faf0; border-color:#a7f3d0; color:#065f46; }

    /* ── Footer ────────────────────────────────────────────────────── */
    .login-footer { font-size: 12px; color: #9dbda3; text-align: center; padding: 20px 0; }
    .login-footer a { color: var(--ps-green); text-decoration: none; }
    .login-footer a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────────────── -->
<nav class="navbar bg-white border-bottom py-3 shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="parliamentaryservices.html">
      <div style="width:34px;height:34px;background:var(--ps-green);border-radius:8px;display:flex;align-items:center;justify-content:center;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="white" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/></svg>
      </div>
      Parliamentary Services
    </a>
    <div class="d-flex gap-2">
      <a href="../index.php" class="btn btn-sm btn-outline-success px-3">View Portal</a>
    </div>
  </div>
</nav>

<!-- ── Main ────────────────────────────────────────────────────────── -->
<main class="flex-grow-1 d-flex align-items-center py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

        <!-- Card -->
        <div class="login-card card p-4 p-md-5">

          <!-- Brand -->
          <div class="text-center mb-4">
            <div class="login-brand-icon mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="white" width="30" height="30"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/></svg>
            </div>
            <h4 class="fw-bold mb-1">Welcome back</h4>
            <p class="text-muted small">Sign in to Parliamentary Services</p>
          </div>

          <!-- Alert -->
          <div id="loginAlert" class="mb-3" style="display:none"></div>

          <!-- Form -->
          <form id="loginForm" novalidate>
            <!-- Email -->
            <div class="mb-3">
              <label class="form-label small fw-semibold">Email address</label>
              <div class="position-relative">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                <input type="email" id="email" class="form-control form-control-ps w-100" placeholder="admin@parliament.local" autocomplete="email" required/>
              </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
              <div class="d-flex justify-content-between">
                <label class="form-label small fw-semibold">Password</label>
                <a href="forgot-password.php" class="small text-success text-decoration-none">Forgot password?</a>
              </div>
              <div class="position-relative">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                <input type="password" id="password" class="form-control form-control-ps w-100 pe-5" placeholder="••••••••" autocomplete="current-password" required/>
                <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password">
                  <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
              </div>
            </div>

            <!-- Remember -->
            <div class="mb-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember"/>
                <label class="form-check-label small" for="remember">Keep me signed in</label>
              </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn-ps w-100 d-flex align-items-center justify-content-center gap-2" id="submitBtn">
              <span id="btnText">Sign In</span>
              <span class="spinner-border spinner-border-sm d-none" id="btnSpinner" role="status"></span>
            </button>
          </form>

          <div class="divider my-4">or</div>

          <div class="text-center small">
            <span class="text-muted">Need access?</span>
            <a href="mailto:it@parliament.local" class="text-success fw-semibold ms-1">Contact IT Support</a>
          </div>

        </div>
        <!-- /card -->

        <!-- Dev hint -->
        <div class="text-center mt-3 small text-muted">
          <span class="badge bg-light text-secondary border" style="font-size:11px;cursor:pointer" onclick="fillDemo()">
            Demo: admin@parliament.local / Admin@ParlReg1
          </span>
        </div>

      </div>
    </div>
  </div>
</main>

<div class="login-footer">
  <p class="mb-1">© 2026 Parliamentary Services. Official Portal for Diplomatic Relations.</p>
  <a href="#">Privacy Policy</a> · <a href="#">Accessibility</a> · <a href="#">Contact Support</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/api.js"></script>
<script>
  // Toggle password visibility
  const togglePw = document.getElementById('togglePw');
  const pwInput  = document.getElementById('password');
  const eyeIcon  = document.getElementById('eyeIcon');
  togglePw.addEventListener('click', () => {
    const show = pwInput.type === 'password';
    pwInput.type = show ? 'text' : 'password';
    eyeIcon.innerHTML = show
      ? '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>'
      : '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>';
  });

  // Fill demo credentials
  function fillDemo() {
    document.getElementById('email').value    = 'admin@parliament.local';
    document.getElementById('password').value = 'Admin@ParlReg1';
  }

  // Show alert
  function showAlert(msg, type = 'error') {
    const el = document.getElementById('loginAlert');
    el.style.display = 'block';
    el.innerHTML = `<div class="alert-ps ${type}">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
        ${type === 'error'
          ? '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>'
          : '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'}
      </svg>
      ${msg}
    </div>`;
    // Animate in
    el.style.animation = 'none';
    el.offsetHeight;
    el.style.animation = 'slideUp .3s ease both';
  }

  // Submit
  document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    if (!email || !password) { showAlert('Please fill in all fields.'); return; }

    const btn     = document.getElementById('submitBtn');
    const txt     = document.getElementById('btnText');
    const spinner = document.getElementById('btnSpinner');
    btn.disabled  = true;
    txt.textContent = 'Signing in…';
    spinner.classList.remove('d-none');

    try {
      const res = await ParlRegAPI.post('/auth/login', { email, password });
      if (res.success) {
        showAlert('Signed in successfully. Redirecting…', 'success');
        setTimeout(() => { window.location.href = 'events.php'; }, 800);
      } else {
        showAlert(res.error || 'Invalid credentials. Please try again.');
      }
    } catch (err) {
      showAlert('Connection error. Please check the server is running.');
    } finally {
      btn.disabled = false;
      txt.textContent = 'Sign In';
      spinner.classList.add('d-none');
    }
  });
</script>
</body>
</html>