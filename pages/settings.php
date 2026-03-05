<?php
// pages/settings.php
$pageTitle    = 'Settings';
$pageSubtitle = 'Manage your account preferences';
$activeNav    = 'settings';
require_once __DIR__ . '/../includes/header.php';

$tab     = $_GET['tab'] ?? 'profile';
$success = '';
$error   = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── PROFILE (name, bio, course, photo) ──────────────────────────────
        if ($action === 'profile') {
            $fullName   = trim($_POST['full_name'] ?? '');
            $bio        = trim(substr($_POST['bio'] ?? '', 0, 500));
            $course     = trim($_POST['course'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $year       = (int)($_POST['year_of_study'] ?? 1);
            $studyMode  = trim($_POST['study_mode'] ?? 'day');

            if (!$fullName) {
                $error = 'Full name cannot be empty.';
            } else {
                $avatarFile = $user['avatar'];

                // Handle avatar upload
                if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['avatar']['name'];
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    // Accept any common image format
                    $allowedExts = ['jpg','jpeg','png','gif','webp','bmp','tiff','tif','heic','heif','avif'];
                    // Also detect by MIME type as fallback
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
                    finfo_close($finfo);
                    $imageMimes = ['image/jpeg','image/png','image/gif','image/webp','image/bmp','image/tiff','image/heic','image/avif','image/x-ms-bmp'];
                    if (!in_array($ext, $allowedExts) && !in_array($mime, $imageMimes)) {
                        $error = 'Please upload an image file (JPG, PNG, WEBP, etc.)';
                    } else {
                        $uploadDir = __DIR__ . '/../assets/uploads/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        // Normalise extension: use jpg for jpegs, keep others as-is
                        $saveExt = in_array($ext, ['jpg','jpeg']) ? 'jpg' : ($ext ?: 'jpg');
                        $newFile = 'avatar_' . $user['id'] . '_' . time() . '.' . $saveExt;
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $newFile)) {
                            // Delete old avatar
                            if ($user['avatar'] && file_exists($uploadDir . $user['avatar'])) {
                                @unlink($uploadDir . $user['avatar']);
                            }
                            $avatarFile = $newFile;
                        } else {
                            $error = 'Upload failed — please check that assets/uploads/ is writable.';
                        }
                    }
                }

                // Remove photo if requested
                if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if ($user['avatar'] && file_exists($uploadDir . $user['avatar'])) {
                        @unlink($uploadDir . $user['avatar']);
                    }
                    $avatarFile = null;
                }

                if (!$error) {
                    DB::query(
                        "UPDATE users SET full_name=?, bio=?, course=?, department=?, year_of_study=?, study_mode=?, avatar=? WHERE id=?",
                        [$fullName, $bio, $course, $department, $year, $studyMode, $avatarFile, $user['id']]
                    );
                    $success = 'Profile updated successfully!';
                    $user = currentUser();
                    $initials = avatarInitials($user['full_name']);
                    $avatarBg = avatarColor($user['id']);
                }
            }
        }

        // ── PASSWORD ─────────────────────────────────────────────────────────
        if ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new1    = $_POST['new_password'] ?? '';
            $new2    = $_POST['confirm_password'] ?? '';
            $fullRow = DB::row("SELECT password_hash FROM users WHERE id=?", [$user['id']]);

            if (!password_verify($current, $fullRow['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new1) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($new1 !== $new2) {
                $error = 'New passwords do not match.';
            } else {
                DB::query("UPDATE users SET password_hash=? WHERE id=?", [password_hash($new1, PASSWORD_DEFAULT), $user['id']]);
                $success = 'Password changed successfully!';
            }
        }

        // ── EMAIL ────────────────────────────────────────────────────────────
        if ($action === 'email') {
            $newEmail = strtolower(trim($_POST['email'] ?? ''));
            $domain   = substr(strrchr($newEmail, '@'), 1);
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (!in_array($domain, ['students.kcau.ac.ke','kcau.ac.ke','kca.ac.ke'])) {
                $error = 'Must be a KCA University email (@students.kcau.ac.ke or @kcau.ac.ke).';
            } elseif ($newEmail !== $user['email'] && DB::row("SELECT id FROM users WHERE email=? AND id!=?", [$newEmail, $user['id']])) {
                $error = 'This email is already linked to another account.';
            } else {
                DB::query("UPDATE users SET email=? WHERE id=?", [$newEmail, $user['id']]);
                $success = 'Email updated!';
                $user = currentUser();
            }
        }
    }
}

// Available courses for dropdown
require_once __DIR__ . '/../includes/kca_data.php';
$schools = KCA_SCHOOLS;
?>

<div style="max-width:820px;margin:0 auto">

  <!-- Tab Nav -->
  <div style="display:flex;gap:4px;margin-bottom:24px;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);padding:6px;flex-wrap:wrap">
    <?php foreach ([
      'profile'  => ['Profile & Photo',    '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
      'account'  => ['Account',            '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
      'password' => ['Password',           '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
    ] as $tKey => [$tLabel, $tIcon]): ?>
      <a href="?tab=<?= $tKey ?>" style="flex:1;min-width:120px;display:flex;align-items:center;justify-content:center;gap:7px;padding:9px 14px;border-radius:7px;font-size:.84rem;font-weight:600;text-decoration:none;transition:all .15s;<?= $tab===$tKey ? 'background:var(--kca-navy);color:var(--white)' : 'color:var(--gray-500)' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15"><?= $tIcon ?></svg>
        <?= $tLabel ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($success): ?>
    <div style="background:rgba(10,124,89,.07);border:1px solid rgba(10,124,89,.2);border-left:3px solid var(--success);border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:.86rem;color:var(--success);display:flex;align-items:center;gap:10px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20,6 9,17 4,12"/></svg>
      <?= sanitize($success) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="background:rgba(196,43,43,.07);border:1px solid rgba(196,43,43,.18);border-left:3px solid var(--danger);border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:.84rem;color:var(--danger);display:flex;align-items:center;gap:10px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= sanitize($error) ?>
    </div>
  <?php endif; ?>


  <?php if ($tab === 'profile'): ?>
  <!-- ═══ PROFILE TAB ═══ -->
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="profile">
    <input type="hidden" name="remove_avatar" id="remove_avatar_input" value="0">

    <!-- Profile Picture -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><h3>Profile Picture</h3></div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">

          <!-- Current avatar with click-to-change -->
          <div style="position:relative;flex-shrink:0">
            <div class="avatar avatar-xl" id="avatar-display" style="background:<?= $avatarBg ?>;cursor:pointer" onclick="document.getElementById('avatar-file').click()" title="Click to change photo">
              <?php if ($user['avatar']): ?>
                <img src="<?= UPLOAD_URL . sanitize($user['avatar']) ?>" alt="" id="avatar-img" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
              <?php else: ?>
                <span id="avatar-initials-display"><?= $initials ?></span>
              <?php endif; ?>
            </div>
            <!-- Camera overlay badge -->
            <div onclick="document.getElementById('avatar-file').click()" style="position:absolute;bottom:2px;right:2px;width:26px;height:26px;background:var(--kca-navy);border-radius:50%;border:2px solid white;display:flex;align-items:center;justify-content:center;cursor:pointer" title="Change photo">
              <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" width="13" height="13"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            </div>
          </div>

          <div>
            <input type="file" name="avatar" id="avatar-file" accept="image/*" style="display:none" onchange="previewPhoto(this)">
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('avatar-file').click()" style="margin-bottom:8px">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              Upload New Photo
            </button>
            <?php if ($user['avatar']): ?>
              <br>
              <button type="button" class="btn btn-ghost btn-sm" onclick="removePhoto()" style="color:var(--danger);border-color:rgba(196,43,43,.3)">Remove Photo</button>
            <?php endif; ?>
            <p style="font-size:.76rem;color:var(--gray-400);margin-top:8px;line-height:1.6">
              JPG, PNG, WEBP, GIF and more — any file size<br>
              Any photo size works — it will be cropped to a circle
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Basic Info -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><h3>Personal Information</h3></div>
      <div class="card-body">
        <div class="form-group">
          <label>Full Name <span style="color:var(--danger)">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name']) ?>" required placeholder="Your full name">
        </div>
        <div class="form-group">
          <label>Bio <span style="font-weight:400;color:var(--gray-400)">(optional · max 500 chars)</span></label>
          <textarea name="bio" class="form-control" rows="3" placeholder="Tell the campus community about yourself — your interests, skills, or goals..." style="resize:vertical"><?= sanitize($user['bio'] ?? '') ?></textarea>
          <div style="font-size:.72rem;color:var(--gray-400);margin-top:4px" id="bio-counter"><?= strlen($user['bio'] ?? '') ?>/500</div>
        </div>
      </div>
    </div>

    <!-- Academic Info -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><h3>Academic Details</h3></div>
      <div class="card-body">
        <div class="form-group">
          <label>School / Faculty</label>
          <select name="department" id="dept-select" class="form-control" onchange="loadSettingsCourses(this.value)">
            <option value="">Select school</option>
            <?php foreach ($schools as $sName => $sData): ?>
              <option value="<?= sanitize($sName) ?>" <?= ($user['department']??'')===$sName?'selected':'' ?>>
                <?= sanitize($sName) ?> (<?= $sData['short'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Programme / Course</label>
          <select name="course" id="course-select-settings" class="form-control">
            <option value="">Select school first</option>
            <?php
            // Pre-populate if user has a school
            $userSchool = $user['department'] ?? '';
            if ($userSchool && isset($schools[$userSchool])):
              foreach ($schools[$userSchool]['courses'] as $c):
            ?>
              <option value="<?= sanitize($c) ?>" <?= ($user['course']??'')===$c?'selected':'' ?>><?= sanitize($c) ?></option>
            <?php endforeach; endif; ?>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="margin-bottom:0">
            <label>Year of Study</label>
            <select name="year_of_study" class="form-control">
              <?php foreach (KCA_YEAR_OPTIONS as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= ($user['year_of_study']??1)==$val?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label>Study Mode</label>
            <select name="study_mode" class="form-control">
              <option value="day"      <?= ($user['study_mode']??'day')==='day'?'selected':'' ?>>Day</option>
              <option value="evening"  <?= ($user['study_mode']??'')==='evening'?'selected':'' ?>>Evening</option>
              <option value="weekend"  <?= ($user['study_mode']??'')==='weekend'?'selected':'' ?>>Weekend</option>
              <option value="distance" <?= ($user['study_mode']??'')==='distance'?'selected':'' ?>>Distance Learning</option>
              <option value="online"   <?= ($user['study_mode']??'')==='online'?'selected':'' ?>>Online</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center">
      <button type="submit" class="btn btn-primary btn-lg">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/></svg>
        Save Changes
      </button>
      <a href="<?= SITE_URL ?>/pages/profile.php" class="btn btn-ghost">View My Profile</a>
    </div>
  </form>


  <?php elseif ($tab === 'account'): ?>
  <!-- ═══ ACCOUNT TAB ═══ -->

  <!-- Read-only info card -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>Account Information</h3></div>
    <div class="card-body">
      <div style="display:grid;gap:14px">
        <?php foreach ([
          ['Student ID',   $user['student_id'] ?? '—',          'Your permanent KCA Student ID. Cannot be changed.'],
          ['Role',         ucfirst($user['role']),               'Assigned by the university. Contact admin to change.'],
          ['Member since', date('F j, Y', strtotime($user['created_at'] ?? 'now')), ''],
        ] as [$lbl, $val, $hint]): ?>
        <div style="display:flex;align-items:flex-start;gap:16px;padding:12px 0;border-bottom:1px solid var(--gray-100)">
          <div style="width:140px;flex-shrink:0;font-size:.82rem;font-weight:600;color:var(--gray-500)"><?= $lbl ?></div>
          <div>
            <div style="font-size:.9rem;color:var(--gray-700);font-weight:500"><?= sanitize($val) ?></div>
            <?php if ($hint): ?><div style="font-size:.74rem;color:var(--gray-400);margin-top:2px"><?= $hint ?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Email change -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <h3>Email Address</h3>
      <span style="font-size:.76rem;color:var(--gray-400)">Must be a KCA University email</span>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="email">
        <div class="form-group">
          <label>Current Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?= sanitize($user['email'] ?? '') ?>"
                 placeholder="you@students.kcau.ac.ke">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Update Email</button>
      </form>
    </div>
  </div>

  <!-- Danger zone -->
  <div class="card" style="border-color:rgba(196,43,43,.2)">
    <div class="card-header" style="background:rgba(196,43,43,.03)">
      <h3 style="color:var(--danger)">Danger Zone</h3>
    </div>
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div>
          <div style="font-weight:600;font-size:.88rem;color:var(--gray-700)">Sign out of all sessions</div>
          <div style="font-size:.78rem;color:var(--gray-400);margin-top:2px">This will log you out everywhere</div>
        </div>
        <a href="<?= SITE_URL ?>/pages/logout.php" class="btn btn-sm" style="background:rgba(196,43,43,.08);color:var(--danger);border:1px solid rgba(196,43,43,.2)">Sign Out</a>
      </div>
    </div>
  </div>


  <?php elseif ($tab === 'password'): ?>
  <!-- ═══ PASSWORD TAB ═══ -->
  <div class="card">
    <div class="card-header"><h3>Change Password</h3></div>
    <div class="card-body">
      <form method="POST" style="max-width:440px">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="password">

        <div class="form-group">
          <label>Current Password <span style="color:var(--danger)">*</span></label>
          <div style="position:relative">
            <input type="password" name="current_password" id="cpw" class="form-control" required autocomplete="current-password" placeholder="Your current password" style="padding-right:44px">
            <button type="button" onclick="tpw('cpw')" style="position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label>New Password <span style="color:var(--danger)">*</span></label>
          <div style="position:relative">
            <input type="password" name="new_password" id="npw" class="form-control" required autocomplete="new-password" placeholder="Min. 8 characters" style="padding-right:44px" oninput="pwStrength(this.value)">
            <button type="button" onclick="tpw('npw')" style="position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div style="height:4px;border-radius:2px;background:var(--gray-200);margin-top:6px;overflow:hidden"><div id="pw-bar" style="height:100%;border-radius:2px;width:0;transition:width .3s,background .3s"></div></div>
          <div id="pw-label" style="font-size:.72rem;margin-top:4px;color:var(--gray-400)"></div>
        </div>

        <div class="form-group">
          <label>Confirm New Password <span style="color:var(--danger)">*</span></label>
          <div style="position:relative">
            <input type="password" name="confirm_password" id="cpw2" class="form-control" required autocomplete="new-password" placeholder="Repeat new password" style="padding-right:44px">
            <button type="button" onclick="tpw('cpw2')" style="position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Change Password
        </button>
      </form>

      <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-100)">
        <div style="font-size:.82rem;font-weight:600;color:var(--gray-600);margin-bottom:8px">Password tips</div>
        <ul style="font-size:.78rem;color:var(--gray-400);line-height:1.8;padding-left:16px">
          <li>At least 8 characters long</li>
          <li>Mix uppercase, lowercase, numbers and symbols</li>
          <li>Don't reuse passwords from other sites</li>
          <li>Never share your password with anyone — including KCA staff</li>
        </ul>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php
$schoolsJson = json_encode(array_map(fn($s) => $s['courses'], $schools));
$extraJs = "
const settingsSchoolCourses = $schoolsJson;
function loadSettingsCourses(school) {
  const sel = document.getElementById('course-select-settings');
  sel.innerHTML = '<option value=\"\">Select programme</option>';
  const courses = settingsSchoolCourses[school] || [];
  courses.forEach(c => {
    const o = document.createElement('option');
    o.value = c; o.textContent = c; sel.appendChild(o);
  });
}
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const d = document.getElementById('avatar-display');
      d.innerHTML = '<img src=\"' + e.target.result + '\" style=\"width:100%;height:100%;border-radius:50%;object-fit:cover\">';
      document.getElementById('remove_avatar_input').value = '0';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function removePhoto() {
  if (!confirm('Remove your profile photo?')) return;
  const d = document.getElementById('avatar-display');
  d.innerHTML = '<span>" . $initials . "</span>';
  document.getElementById('remove_avatar_input').value = '1';
  const inp = document.getElementById('avatar-file');
  if (inp) inp.value = '';
}
function tpw(id) {
  const f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
}
function pwStrength(pw) {
  let s = 0;
  if (pw.length >= 8) s++;
  if (/[A-Z]/.test(pw)) s++;
  if (/[0-9]/.test(pw)) s++;
  if (/[^A-Za-z0-9]/.test(pw)) s++;
  const bar = document.getElementById('pw-bar');
  const lbl = document.getElementById('pw-label');
  const lvl = [['0%','',''],['25%','var(--danger)','Weak'],['50%','var(--warning)','Fair — add uppercase & numbers'],['75%','var(--kca-gold)','Good'],['100%','var(--success)','Strong']];
  if (bar) { bar.style.width = lvl[s][0]; bar.style.background = lvl[s][1]; }
  if (lbl) { lbl.textContent = lvl[s][2]; lbl.style.color = lvl[s][1]; }
}
// Bio character counter
const bioTa = document.querySelector('[name=bio]');
const bioCnt = document.getElementById('bio-counter');
if (bioTa && bioCnt) {
  bioTa.addEventListener('input', () => {
    bioCnt.textContent = bioTa.value.length + '/500';
    if (bioTa.value.length > 480) bioCnt.style.color = 'var(--warning)';
    if (bioTa.value.length >= 500) bioCnt.style.color = 'var(--danger)';
  });
}
";
require_once __DIR__ . '/../includes/footer.php';
?>
