<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/kca_data.php';
startSession();
if (!empty($_SESSION['user_id'])) redirect('pages/feed.php');

$error = $success = '';
$fields = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'full_name'  => trim($_POST['full_name'] ?? ''),
        'student_id' => strtoupper(trim($_POST['student_id'] ?? '')),
        'email'      => strtolower(trim($_POST['email'] ?? '')),
        'school'     => trim($_POST['school'] ?? ''),
        'course'     => trim($_POST['course'] ?? ''),
        'year'       => (int)($_POST['year_of_study'] ?? 1),
        'study_mode' => $_POST['study_mode'] ?? 'day',
        'password'   => $_POST['password'] ?? '',
        'confirm'    => $_POST['confirm_password'] ?? '',
    ];

    // Validation
    if (!$fields['full_name'] || !$fields['student_id'] || !$fields['password']) {
        $error = 'Full name, Student ID, and password are required.';
    } elseif (!preg_match('/^KCA\/\d{4}\/\d{3,}$|^STF\/\d+$|^ADMIN\d+$/i', $fields['student_id'])) {
        $error = 'Student ID format invalid. Use KCA/YYYY/NNN (e.g. KCA/2024/001).';
    } elseif ($fields['email'] && !in_array(substr(strrchr($fields['email'],'@'),1), KCA_EMAIL_DOMAINS)) {
        $error = 'Email must be a KCA University address (@students.kcau.ac.ke or @kcau.ac.ke).';
    } elseif (strlen($fields['password']) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($fields['password'] !== $fields['confirm']) {
        $error = 'Passwords do not match.';
    } elseif (DB::row("SELECT id FROM users WHERE student_id = ?", [$fields['student_id']])) {
        $error = 'This Student ID is already registered. Try signing in instead.';
    } elseif ($fields['email'] && DB::row("SELECT id FROM users WHERE email = ?", [$fields['email']])) {
        $error = 'This email address is already linked to an account.';
    } else {
        $hash = password_hash($fields['password'], PASSWORD_DEFAULT);
        // Determine department from school
        $dept = null;
        if ($fields['school'] && isset(KCA_SCHOOLS[$fields['school']])) {
            $dept = KCA_SCHOOLS[$fields['school']]['departments'][0] ?? null;
        }
        $newUserId = DB::insert(
            "INSERT INTO users (full_name,student_id,email,password_hash,role,school,department,course,year_of_study,study_mode)
             VALUES (?,?,?,?,'student',?,?,?,?,?)",
            [
                $fields['full_name'], $fields['student_id'],
                $fields['email'] ?: null, $hash,
                $fields['school'] ?: null, $dept,
                $fields['course'] ?: null, $fields['year'],
                $fields['study_mode'],
            ]
        );

        // ── AUTO-PLACEMENT: join relevant spaces ──────────────────────────
        // 1. Always join the General Campus space (id=1 if it exists)
        $autoJoin = [];

        // Find spaces matching user's school
        if ($fields['school']) {
            $schoolSpaces = DB::rows(
                "SELECT id FROM spaces WHERE is_active=1 AND (
                    school LIKE ? OR name LIKE ? OR
                    (type='academic' AND description LIKE ?)
                ) LIMIT 5",
                ["%{$fields['school']}%", "%{$fields['school']}%", "%{$fields['school']}%"]
            );
            foreach ($schoolSpaces as $s) $autoJoin[] = $s['id'];
        }

        // Find spaces matching course keywords
        if ($fields['course']) {
            $courseKeyword = preg_replace('/^(BSc|BA|MSc|PhD|Bachelor|Master|Diploma|Certificate)\s+(in\s+)?/i', '', $fields['course']);
            $courseKeyword = trim(explode('(', $courseKeyword)[0]); // remove parentheses
            if (strlen($courseKeyword) > 4) {
                $courseSpaces = DB::rows(
                    "SELECT id FROM spaces WHERE is_active=1 AND (name LIKE ? OR description LIKE ?) LIMIT 3",
                    ["%$courseKeyword%", "%$courseKeyword%"]
                );
                foreach ($courseSpaces as $s) $autoJoin[] = $s['id'];
            }
        }

        // Always join any "General" / "Campus Feed" / "Announcements" space
        $generalSpaces = DB::rows(
            "SELECT id FROM spaces WHERE is_active=1 AND (
                name LIKE '%general%' OR name LIKE '%campus%' OR
                name LIKE '%announcement%' OR name LIKE '%all students%'
            ) LIMIT 3",
            []
        );
        foreach ($generalSpaces as $s) $autoJoin[] = $s['id'];

        // Insert memberships (deduplicated)
        foreach (array_unique($autoJoin) as $spaceId) {
            $alreadyIn = DB::row("SELECT id FROM space_members WHERE space_id=? AND user_id=?", [$spaceId, $newUserId]);
            if (!$alreadyIn) {
                DB::insert("INSERT INTO space_members (space_id, user_id, role) VALUES (?,?,'member')", [$spaceId, $newUserId]);
            }
        }
        // ─────────────────────────────────────────────────────────────────

        $success = 'Account created! You have been placed in ' . count(array_unique($autoJoin)) . ' space(s) matching your school. Sign in to get started.';
        $fields  = [];
    }
}

$schools    = KCA_SCHOOLS;
$allCourses = getAllKcaCourses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — KCA Chat</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <style>
    .strength-bar { height:4px; border-radius:2px; background:var(--gray-200); margin-top:6px; overflow:hidden; }
    .strength-fill { height:100%; border-radius:2px; width:0; transition:width .3s,background .3s; }
    .id-tip { background:rgba(0,48,135,.05); border:1px solid rgba(0,48,135,.12); border-radius:8px; padding:10px 13px; font-size:.78rem; color:var(--gray-500); margin-top:6px; line-height:1.6; }
    .id-tip code { font-family:monospace; color:var(--kca-navy); font-weight:600; background:rgba(0,48,135,.06); padding:1px 5px; border-radius:3px; }
    .section-label { font-size:.72rem; font-weight:700; color:var(--gray-400); text-transform:uppercase; letter-spacing:.08em; margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--gray-100); }
    .pw-toggle { position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);display:flex;padding:4px;border-radius:4px;transition:color var(--transition); }
    .pw-toggle:hover { color:var(--kca-navy); }
  </style>
</head>
<body>
<div class="auth-page">

  <!-- Brand panel -->
  <div class="auth-brand">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:32px;position:relative;z-index:1">
      <div style="width:48px;height:48px;background:var(--white);border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,.18);flex-shrink:0">
        <span style="font-family:var(--font-serif);font-weight:700;font-size:1rem;color:var(--kca-navy)">KCA</span>
      </div>
      <div>
        <div style="font-family:var(--font-serif);font-weight:700;font-size:1.1rem;color:var(--white)">KCA Chat</div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.55)">KCA University &mdash; Est. 1989</div>
      </div>
    </div>

    <h1 style="font-family:var(--font-serif);font-size:2rem;font-weight:700;color:var(--white);line-height:1.22;margin-bottom:12px;position:relative;z-index:1">
      Join the<br><span style="color:var(--kca-gold-light)">KCA Community</span>
    </h1>
    <p style="font-size:.88rem;color:rgba(255,255,255,.65);line-height:1.7;max-width:300px;position:relative;z-index:1;margin-bottom:28px">
      Register with your official KCA Student ID to access the full campus community — academic spaces, clubs, events, and direct messaging.
    </p>

    <!-- Schools list -->
    <div style="position:relative;z-index:1;width:100%;max-width:340px">
      <div style="font-size:.72rem;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px">3 Schools · 40+ Programmes</div>
      <?php foreach ($schools as $schoolName => $schoolData): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:9px 14px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:9px;margin-bottom:6px">
        <span style="font-size:.72rem;font-weight:700;color:var(--kca-gold-light);background:rgba(201,168,76,.15);padding:2px 8px;border-radius:99px;flex-shrink:0"><?= $schoolData['short'] ?></span>
        <span style="font-size:.8rem;color:rgba(255,255,255,.8)"><?= $schoolName ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="position:relative;z-index:1;display:flex;gap:28px;margin-top:auto;padding-top:20px;border-top:1px solid rgba(255,255,255,.1)">
      <div class="auth-stat"><span class="num">2,400+</span><span class="lbl">Students</span></div>
      <div class="auth-stat"><span class="num">30+</span><span class="lbl">Clubs</span></div>
      <div class="auth-stat"><span class="num">3</span><span class="lbl">Campuses</span></div>
    </div>
  </div>

  <!-- Form panel -->
  <div class="auth-form-panel">
    <div class="auth-form-box" style="max-width:480px">

      <h2 style="margin-bottom:4px">Create your account</h2>
      <p class="subtitle">
        Already registered? <a href="<?= SITE_URL ?>/" style="color:var(--kca-navy);font-weight:600">Sign in</a>
      </p>

      <?php if ($error): ?>
        <div style="background:rgba(196,43,43,.07);border:1px solid rgba(196,43,43,.18);border-left:3px solid var(--danger);border-radius:8px;padding:12px 14px;font-size:.84rem;color:#B02020;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= sanitize($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div style="background:rgba(10,124,89,.07);border:1px solid rgba(10,124,89,.2);border-left:3px solid var(--success);border-radius:8px;padding:16px;margin-bottom:20px">
          <div style="font-weight:700;color:var(--success);margin-bottom:4px">Account created!</div>
          <div style="font-size:.86rem;color:var(--gray-600)"><?= sanitize($success) ?></div>
          <a href="<?= SITE_URL ?>/" class="btn btn-primary btn-sm" style="margin-top:12px;display:inline-flex">Sign in now</a>
        </div>
      <?php endif; ?>

      <form method="POST" id="reg-form" novalidate>

        <!-- PERSONAL INFO -->
        <div style="margin-bottom:20px">
          <div class="section-label">Personal Information</div>
          <div class="form-group">
            <label>Full Name <span style="color:var(--danger)">*</span></label>
            <input type="text" name="full_name" class="form-control"
                   placeholder="e.g. Brian Otieno Achieng"
                   value="<?= sanitize($fields['full_name'] ?? '') ?>" required autocomplete="name">
          </div>
        </div>

        <!-- KCA CREDENTIALS -->
        <div style="margin-bottom:20px">
          <div class="section-label">KCA University Credentials</div>

          <div class="form-group">
            <label>Student ID <span style="color:var(--danger)">*</span></label>
            <input type="text" name="student_id" id="student_id_reg" class="form-control"
                   placeholder="KCA/2024/001"
                   value="<?= sanitize($fields['student_id'] ?? '') ?>"
                   required autocomplete="off" spellcheck="false"
                   style="font-family:monospace;letter-spacing:.04em;font-size:.95rem"
                   oninput="this.value=this.value.toUpperCase()">
            <div class="id-tip">
              Format: <code>KCA/YYYY/NNN</code> &nbsp;e.g. <code>KCA/2024/001</code> or <code>KCA/2023/145</code><br>
              This is your primary login ID — enter it exactly as issued by the Registrar.
            </div>
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label>KCA Email Address
              <span style="font-weight:400;color:var(--gray-400)">(optional but recommended)</span>
            </label>
            <input type="email" name="email" class="form-control"
                   placeholder="you@students.kcau.ac.ke"
                   value="<?= sanitize($fields['email'] ?? '') ?>"
                   autocomplete="email">
            <div style="font-size:.74rem;color:var(--gray-400);margin-top:5px">
              Must end with <code style="font-family:monospace;color:var(--kca-navy)">@students.kcau.ac.ke</code> or <code style="font-family:monospace;color:var(--kca-navy)">@kcau.ac.ke</code>
            </div>
          </div>
        </div>

        <!-- ACADEMIC DETAILS -->
        <div style="margin-bottom:20px">
          <div class="section-label">Academic Details</div>

          <div class="form-group">
            <label>School <span style="color:var(--danger)">*</span></label>
            <select name="school" id="school-select" class="form-control" onchange="loadCourses(this.value)" required>
              <option value="">-- Select your school --</option>
              <?php foreach ($schools as $schoolName => $schoolData): ?>
              <option value="<?= sanitize($schoolName) ?>"
                      <?= ($fields['school'] ?? '') === $schoolName ? 'selected' : '' ?>>
                <?= sanitize($schoolName) ?> (<?= $schoolData['short'] ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Programme / Course <span style="color:var(--danger)">*</span></label>
            <select name="course" id="course-select" class="form-control" required>
              <option value="">-- Select school first --</option>
              <?php if (!empty($fields['school'])): ?>
                <?php foreach ($schools[$fields['school']]['courses'] ?? [] as $c): ?>
                <option value="<?= sanitize($c) ?>" <?= ($fields['course'] ?? '') === $c ? 'selected' : '' ?>>
                  <?= sanitize($c) ?>
                </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group" style="margin-bottom:0">
              <label>Year of Study</label>
              <select name="year_of_study" class="form-control">
                <?php foreach (KCA_YEAR_OPTIONS as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($fields['year'] ?? 1) == $val ? 'selected' : '' ?>>
                  <?= $label ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label>Study Mode</label>
              <select name="study_mode" class="form-control">
                <option value="day"      <?= ($fields['study_mode']??'day')==='day'?'selected':'' ?>>Day</option>
                <option value="evening"  <?= ($fields['study_mode']??'')==='evening'?'selected':'' ?>>Evening</option>
                <option value="weekend"  <?= ($fields['study_mode']??'')==='weekend'?'selected':'' ?>>Weekend</option>
                <option value="distance" <?= ($fields['study_mode']??'')==='distance'?'selected':'' ?>>Distance Learning</option>
                <option value="online"   <?= ($fields['study_mode']??'')==='online'?'selected':'' ?>>Online</option>
              </select>
            </div>
          </div>
        </div>

        <!-- PASSWORD -->
        <div style="margin-bottom:22px">
          <div class="section-label">Set Password</div>

          <div class="form-group">
            <label>Password <span style="color:var(--danger)">*</span></label>
            <div style="position:relative">
              <input type="password" name="password" id="pw1" class="form-control"
                     placeholder="Min. 8 characters — mix letters, numbers, symbols"
                     required autocomplete="new-password" style="padding-right:44px"
                     oninput="checkStrength(this.value)">
              <button type="button" class="pw-toggle" onclick="togglePw('pw1')">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
            <div style="font-size:.72rem;color:var(--gray-400);margin-top:4px" id="strength-label"></div>
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label>Confirm Password <span style="color:var(--danger)">*</span></label>
            <div style="position:relative">
              <input type="password" name="confirm_password" id="pw2" class="form-control"
                     placeholder="Repeat your password"
                     required autocomplete="new-password" style="padding-right:44px">
              <button type="button" class="pw-toggle" onclick="togglePw('pw2')">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          Create My Account
        </button>

        <p style="font-size:.72rem;color:var(--gray-400);text-align:center;margin-top:14px;line-height:1.6">
          By registering you agree to KCA University's
          <a href="#" style="color:var(--kca-navy);text-decoration:none">Terms of Use</a> and
          <a href="#" style="color:var(--kca-navy);text-decoration:none">Privacy Policy</a>.
        </p>
      </form>
    </div>
  </div>
</div>

<script>
// Courses by school — generated from PHP
const schoolCourses = <?= json_encode(array_map(fn($s) => $s['courses'], $schools)) ?>;

function loadCourses(school) {
  const sel = document.getElementById('course-select');
  sel.innerHTML = '<option value="">-- Select your programme --</option>';
  const courses = schoolCourses[school] || [];
  courses.forEach(c => {
    const o = document.createElement('option');
    o.value = c; o.textContent = c;
    sel.appendChild(o);
  });
}

function togglePw(id) {
  const f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
}

function checkStrength(pw) {
  const fill  = document.getElementById('strength-fill');
  const label = document.getElementById('strength-label');
  let score = 0;
  if (pw.length >= 8)          score++;
  if (/[A-Z]/.test(pw))        score++;
  if (/[0-9]/.test(pw))        score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const levels = [
    {pct:'0%',   color:'',                    text:''},
    {pct:'25%',  color:'var(--danger)',        text:'Weak — add uppercase and numbers'},
    {pct:'50%',  color:'var(--warning)',       text:'Fair — add a symbol to strengthen'},
    {pct:'75%',  color:'var(--kca-gold)',      text:'Good'},
    {pct:'100%', color:'var(--success)',       text:'Strong password'},
  ];
  const l = levels[score];
  fill.style.width = l.pct;
  fill.style.background = l.color;
  label.textContent = l.text;
  label.style.color = l.color;
}
</script>
</body>
</html>
