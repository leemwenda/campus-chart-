<?php
require_once __DIR__ . '/config/auth.php';
startSession();

if (!empty($_SESSION['user_id'])) {
    redirect('pages/feed.php');
}

$error      = '';
$studentId  = '';
$role       = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? 'student';

    if (!$studentId || !$password) {
        $error = 'Please enter your Student ID (or email) and password.';
    } else {
        $result = login($studentId, $password);
        if ($result['success']) {
            redirect('pages/feed.php');
        } else {
            $error = $result['message'];
        }
    }
}

// Role UI helpers
$roles = [
    'student' => [
        'label' => 'Student',
        'icon'  => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
        'hint'  => 'e.g. KCA/2023/001',
    ],
    'staff' => [
        'label' => 'Staff',
        'icon'  => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'hint'  => 'e.g. STAFF/001',
    ],
    'admin' => [
        'label' => 'Admin',
        'icon'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'hint'  => 'e.g. ADMIN001',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — KCA Chat</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <style>
    /* Login-page extras */
    .kca-wordmark {
      display: flex; align-items: center; gap: 10px; margin-bottom: 32px;
    }
    .kca-wordmark .badge {
      width: 48px; height: 48px; background: var(--white); border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 16px rgba(0,0,0,.18);
    }
    .kca-wordmark .badge span {
      font-family: var(--font-serif); font-weight: 700; font-size: 1rem;
      color: var(--kca-navy); letter-spacing: -.5px;
    }
    .kca-wordmark .text .top {
      font-family: var(--font-serif); font-weight: 700; font-size: 1.1rem;
      color: var(--white); line-height: 1;
    }
    .kca-wordmark .text .sub {
      font-size: .72rem; color: rgba(255,255,255,.55); margin-top: 2px;
    }

    .brand-graphic {
      margin: 32px 0; display: flex; flex-direction: column; gap: 14px;
    }
    .brand-feature {
      display: flex; align-items: center; gap: 14px;
      background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
      border-radius: 12px; padding: 14px 18px;
    }
    .brand-feature .feat-icon {
      width: 38px; height: 38px; border-radius: 9px;
      background: rgba(255,255,255,.12);
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .brand-feature .feat-icon svg { width: 18px; height: 18px; stroke: rgba(255,255,255,.9); }
    .brand-feature .feat-title { font-size: .85rem; font-weight: 600; color: var(--white); }
    .brand-feature .feat-desc  { font-size: .74rem; color: rgba(255,255,255,.5); }

    .id-hint {
      display: flex; align-items: center; gap: 6px;
      font-size: .76rem; color: var(--gray-400); margin-top: 6px;
    }
    .id-hint svg { width: 13px; height: 13px; flex-shrink: 0; }

    .role-tabs {
      display: flex; gap: 4px; background: var(--gray-100);
      border-radius: 10px; padding: 4px; margin-bottom: 24px;
    }
    .role-tab {
      flex: 1; display: flex; align-items: center; justify-content: center; gap: 7px;
      padding: 9px 8px; border-radius: 7px; cursor: pointer;
      font-size: .82rem; font-weight: 600; color: var(--gray-500);
      border: none; background: none; transition: all var(--transition);
    }
    .role-tab svg { width: 15px; height: 15px; stroke: currentColor; flex-shrink: 0; }
    .role-tab.active {
      background: var(--white); color: var(--kca-navy);
      box-shadow: 0 1px 6px rgba(0,48,135,.14);
    }

    .error-banner {
      background: rgba(196,43,43,.07); border: 1px solid rgba(196,43,43,.18);
      border-left: 3px solid var(--danger);
      border-radius: 8px; padding: 12px 14px;
      font-size: .84rem; color: #B02020;
      margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px;
    }
    .error-banner svg { flex-shrink: 0; margin-top: 1px; }

    .pw-toggle {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: var(--gray-400);
      padding: 4px; border-radius: 4px; display: flex; transition: color var(--transition);
    }
    .pw-toggle:hover { color: var(--kca-navy); }
    .pw-toggle svg { width: 17px; height: 17px; }

    .login-cta { margin-top: 14px; }

    .divider-text {
      text-align: center; font-size: .75rem; color: var(--gray-400);
      position: relative; margin: 20px 0;
    }
    .divider-text::before, .divider-text::after {
      content: ''; position: absolute; top: 50%; width: 42%; height: 1px;
      background: var(--gray-200);
    }
    .divider-text::before { left: 0; }
    .divider-text::after  { right: 0; }

    .demo-accounts {
      background: var(--gray-50); border: 1px solid var(--gray-200);
      border-radius: 8px; padding: 12px 14px;
    }
    .demo-accounts p {
      font-size: .74rem; font-weight: 700; color: var(--gray-500);
      text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px;
    }
    .demo-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 5px 0; border-bottom: 1px solid var(--gray-100);
      font-size: .78rem;
    }
    .demo-row:last-child { border-bottom: none; }
    .demo-row .label { color: var(--gray-500); }
    .demo-row .val   { color: var(--kca-navy); font-weight: 600; font-family: monospace; letter-spacing: .02em; }
    .demo-fill {
      background: none; border: none; cursor: pointer; color: var(--kca-navy);
      font-size: .72rem; font-weight: 600; padding: 2px 8px; border-radius: 4px;
      transition: background var(--transition);
    }
    .demo-fill:hover { background: rgba(0,48,135,.08); }
  </style>
</head>
<body>

<div class="auth-page">

  <!-- ═══ LEFT BRAND PANEL ═══ -->
  <div class="auth-brand" style="
    background: url('<?= SITE_URL ?>/assets/images/kca_gate.jpg') center center / cover no-repeat;
    position: relative;
  ">
    <!-- Dark overlay so text stays readable over the photo -->
    <div style="
      position:absolute;inset:0;z-index:0;
      background: linear-gradient(
        180deg,
        rgba(0,15,55,0.88) 0%,
        rgba(0,25,75,0.80) 30%,
        rgba(0,15,50,0.88) 70%,
        rgba(0,8,35,0.97)  100%
      );
    "></div>
    <!-- Gold shimmer line at top -->
    <div style="position:absolute;top:0;left:0;right:0;height:3px;z-index:1;
      background:linear-gradient(90deg,transparent,#F5C842,transparent)"></div>
    <div class="kca-wordmark" style="position:relative;z-index:1">
      <div class="badge"><span>KCA</span></div>
      <div class="text">
        <div class="top">KCA Chat</div>
        <div class="sub">KCA University &mdash; Nairobi</div>
      </div>
    </div>

    <div style="position:relative;z-index:1;max-width:340px">
      <h1 style="font-family:var(--font-serif);font-size:2rem;font-weight:700;color:var(--white);line-height:1.22;margin-bottom:12px">
        Your campus,<br><span style="color:var(--kca-gold-light)">all in one place.</span>
      </h1>
      <p style="font-size:.88rem;color:rgba(255,255,255,.65);line-height:1.7">
        Connect with classmates, collaborate in academic spaces, stay updated on events, and communicate — all within the official KCA University community platform.
      </p>
    </div>

    <div class="brand-graphic" style="position:relative;z-index:1">
      <div class="brand-feature" style="backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        </div>
        <div>
          <div class="feat-title">Academic Spaces</div>
          <div class="feat-desc">Unit groups, project teams, department boards</div>
        </div>
      </div>
      <div class="brand-feature">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
          <div class="feat-title">Campus Events</div>
          <div class="feat-desc">RSVP, reminders, and event discovery</div>
        </div>
      </div>
      <div class="brand-feature">
        <div class="feat-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div>
          <div class="feat-title">Direct Messages</div>
          <div class="feat-desc">Private conversations with peers and staff</div>
        </div>
      </div>
    </div>

    <div style="position:relative;z-index:1;display:flex;gap:28px;margin-top:auto;padding-top:20px;border-top:1px solid rgba(255,255,255,.1)">
      <div class="auth-stat"><span class="num">2,400+</span><span class="lbl">Students</span></div>
      <div class="auth-stat"><span class="num">120+</span><span class="lbl">Staff</span></div>
      <div class="auth-stat"><span class="num">40+</span><span class="lbl">Spaces</span></div>
      <div class="auth-stat"><span class="num">Free</span><span class="lbl">Access</span></div>
    </div>
  </div>

  <!-- ═══ RIGHT FORM PANEL ═══ -->
  <div class="auth-form-panel">
    <div class="auth-form-box">

      <div style="margin-bottom:28px">
        <h2 style="margin-bottom:6px">Sign in to KCA Chat</h2>
        <p class="subtitle">Student ID <strong>or</strong> KCA email — no role selection needed</p>
      </div>

      <?php if ($error): ?>
      <div class="error-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= sanitize($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" id="login-form" novalidate>

        <div class="form-group">
          <label for="student_id">Student ID or Email</label>
          <input
            type="text"
            id="student_id"
            name="student_id"
            class="form-control"
            placeholder="KCA/2023/001 or you@students.kcau.ac.ke"
            value="<?= sanitize($studentId) ?>"
            required
            autofocus
            autocomplete="username"
            spellcheck="false"
          >
          <div class="id-hint" id="id-hint">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Enter your KCA Student ID <strong>or</strong> university email — the system detects your role automatically
          </div>
        </div>

        <div class="form-group">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <label for="password" style="margin:0">Password</label>
            <a href="pages/forgot_password.php" style="font-size:.78rem;color:var(--kca-navy);font-weight:600;text-decoration:none">Forgot password?</a>
          </div>
          <div style="position:relative">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="Enter your password"
              required
              autocomplete="current-password"
              style="padding-right:46px"
            >
            <button type="button" class="pw-toggle" onclick="togglePw()" title="Show/hide password" tabindex="-1">
              <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
          <input type="checkbox" name="remember" id="remember" style="width:15px;height:15px;accent-color:var(--kca-navy)">
          <label for="remember" style="font-size:.82rem;color:var(--gray-600);cursor:pointer;user-select:none">Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg login-cta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10,17 15,12 10,7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
          </svg>
          Sign In
        </button>
      </form>

      <div class="divider-text">or</div>

      <!-- Demo accounts -->
      <div class="demo-accounts">
        <p>Quick Demo — Password: <code style="color:var(--kca-navy)">password</code></p>
        <div class="demo-row">
          <span class="label">Admin</span>
          <span class="val">ADMIN001</span>
          <button class="demo-fill" onclick="fillDemo('ADMIN001')">Use</button>
        </div>
        <div class="demo-row">
          <span class="label">Staff</span>
          <span class="val">STF/001</span>
          <button class="demo-fill" onclick="fillDemo('STF/001')">Use</button>
        </div>
        <div class="demo-row">
          <span class="label">Student</span>
          <span class="val">KCA/2023/001</span>
          <button class="demo-fill" onclick="fillDemo('KCA/2023/001')">Use</button>
        </div>
      </div>

      <div class="form-link" style="margin-top:22px">
        New to KCA Chat? <a href="pages/register.php">Create an account</a>
      </div>

      <p style="font-size:.72rem;color:var(--gray-400);text-align:center;margin-top:18px;line-height:1.6">
        By signing in you agree to KCA University's
        <a href="#" style="color:var(--kca-navy);text-decoration:none">Terms of Use</a>
        and <a href="#" style="color:var(--kca-navy);text-decoration:none">Privacy Policy</a>.
      </p>
    </div>
  </div>
</div>

<script>
function togglePw() {
  const f   = document.getElementById('password');
  const ico = document.getElementById('eye-icon');
  const show = f.type === 'password';
  f.type = show ? 'text' : 'password';
  ico.innerHTML = show
    ? `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`
    : `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
}

function fillDemo(id) {
  document.getElementById('student_id').value = id;
  document.getElementById('password').value   = 'password';
  document.getElementById('login-form').submit();
}

document.getElementById('login-form').addEventListener('submit', function(e) {
  const id = document.getElementById('student_id').value.trim();
  const pw = document.getElementById('password').value;
  document.getElementById('student_id').classList.toggle('error', !id);
  document.getElementById('password').classList.toggle('error', !pw);
  if (!id || !pw) e.preventDefault();
});
</script>
</body>
</html>
