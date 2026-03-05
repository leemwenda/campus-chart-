<?php
// pages/profile_edit.php
$pageTitle    = 'Edit Profile';
$pageSubtitle = 'Update your KCA Chat profile';
$activeNav    = 'profile';
require_once __DIR__ . '/../includes/header.php';

$error   = '';
$success = '';

// All KCA courses
$courses = [
    '— School of Technology —' => [],
    'BSc Information Technology' => 'SoT',
    'BSc Software Development' => 'SoT',
    'BSc Applied Computing' => 'SoT',
    'BSc Data Science' => 'SoT',
    'BSc Information Security & Forensics' => 'SoT',
    'BSc Gaming & Animation Technology' => 'SoT',
    'Bachelor of Business Information Technology' => 'SoT',
    'Diploma in Information Technology' => 'SoT',
    'Diploma in Business Information Technology' => 'SoT',
    'Certificate in Information Technology' => 'SoT',
    'Certificate in Business Information Technology' => 'SoT',
    '— School of Business —' => [],
    'Bachelor of Commerce' => 'SoB',
    'BSc Actuarial Science' => 'SoB',
    'BSc Forensic Accounting' => 'SoB',
    'BSc Economics & Statistics' => 'SoB',
    'Bachelor of International Business Management' => 'SoB',
    'Bachelor of Procurement & Logistics' => 'SoB',
    'Bachelor of Public Management' => 'SoB',
    'BA in Economics & Business Studies' => 'SoB',
    'MBA (Corporate Management)' => 'SoB',
    'MSc Supply Chain Management' => 'SoB',
    'MSc Commerce' => 'SoB',
    'MSc Development Finance' => 'SoB',
    'PhD in Business Management' => 'SoB',
    'PhD in Finance' => 'SoB',
    'Diploma in Business Management' => 'SoB',
    'Diploma in Procurement & Logistics' => 'SoB',
    'Diploma in Public Management' => 'SoB',
    '— School of Education, Arts & Social Sciences —' => [],
    'BA in Journalism & Digital Media' => 'SEASS',
    'BA in Counselling Psychology' => 'SEASS',
    'BA in Criminology' => 'SEASS',
    'BA in Performing Arts & Film' => 'SEASS',
    'BA in Early Childhood Education' => 'SEASS',
    'Bachelor of Education (Arts)' => 'SEASS',
    'Master of Education (Leadership & Management)' => 'SEASS',
    'Master of Arts in Counselling Psychology' => 'SEASS',
    'Diploma in Journalism & Digital Media' => 'SEASS',
    'Diploma in Film Technology' => 'SEASS',
    'Diploma in Counselling Psychology' => 'SEASS',
    'Diploma in Criminology & Criminal Justice' => 'SEASS',
    '— Professional & Postgraduate —' => [],
    'MSc Data Science' => 'SoT',
    'MSc Information Systems Management' => 'SoT',
    'MSc Data Analytics' => 'SoT',
    'MSc Knowledge Management & Innovation' => 'SoB',
    'PhD in Information Systems' => 'SoT',
    'Postgraduate Diploma in Education' => 'SEASS',
    'CPA' => 'PTTI',
    'ACCA' => 'PTTI',
    'CHRP' => 'PTTI',
    'CPSP-K' => 'PTTI',
    'CISA / CIFA' => 'PTTI',
    'Other' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please try again.';
    } else {
        $fullName   = trim($_POST['full_name'] ?? '');
        $bio        = trim($_POST['bio'] ?? '');
        $course     = trim($_POST['course'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $year       = (int)($_POST['year_of_study'] ?? 1);
        $studyMode  = trim($_POST['study_mode'] ?? 'day');
        $campus     = trim($_POST['campus'] ?? 'Town Campus');
        $newPw      = $_POST['new_password'] ?? '';
        $confirmPw  = $_POST['confirm_password'] ?? '';

        if (!$fullName) {
            $error = 'Full name is required.';
        } elseif ($newPw && strlen($newPw) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPw && $newPw !== $confirmPw) {
            $error = 'Passwords do not match.';
        } else {
            // Handle avatar upload
            $avatarFile = $user['avatar'];
            if (!empty($_FILES['avatar']['name'])) {
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $error = 'Profile picture must be JPG, PNG, GIF, or WEBP.';
                } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    $error = 'Profile picture must be under 2MB.';
                } else {
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
                        // Remove old avatar
                        if ($user['avatar'] && file_exists($uploadDir . $user['avatar'])) {
                            @unlink($uploadDir . $user['avatar']);
                        }
                        $avatarFile = $filename;
                    } else {
                        $error = 'Failed to upload image. Check server permissions.';
                    }
                }
            }

            if (!$error) {
                $updateFields = "full_name=?, bio=?, course=?, department=?, year_of_study=?, study_mode=?, avatar=?";
                $params = [$fullName, $bio, $course, $department, $year, $studyMode, $avatarFile];

                if ($newPw) {
                    $updateFields .= ", password_hash=?";
                    $params[] = password_hash($newPw, PASSWORD_DEFAULT);
                }

                $params[] = $user['id'];
                DB::query("UPDATE users SET $updateFields WHERE id=?", $params);
                $success = 'Profile updated successfully!';
                // Refresh user data
                $user = currentUser();
                $initials = avatarInitials($user['full_name']);
                $avatarBg = avatarColor($user['id']);
            }
        }
    }
}
?>

<div class="content-grid" style="max-width:900px;margin:0 auto">
  <div>

    <?php if ($error): ?>
      <div style="background:rgba(196,43,43,.07);border:1px solid rgba(196,43,43,.18);border-left:3px solid var(--danger);border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:.84rem;color:#B02020;display:flex;gap:10px;align-items:flex-start">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= sanitize($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div style="background:rgba(10,124,89,.07);border:1px solid rgba(10,124,89,.2);border-left:3px solid var(--success);border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:.84rem;color:#0A7C59;display:flex;gap:10px;align-items:center">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>
        <?= sanitize($success) ?>
        <a href="profile.php" style="margin-left:auto;font-weight:700;color:#0A7C59;text-decoration:none">View Profile</a>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <!-- Avatar + Name -->
      <div class="card" style="margin-bottom:18px">
        <div class="card-header"><h3>Profile Picture & Name</h3></div>
        <div class="card-body">
          <div style="display:flex;gap:24px;align-items:center;flex-wrap:wrap">
            <!-- Avatar upload -->
            <label class="avatar-upload-wrap" title="Click to change photo">
              <div class="avatar avatar-xl" style="background:<?= $avatarBg ?>">
                <?php if ($user['avatar']): ?>
                  <img src="<?= SITE_URL ?>/assets/uploads/<?= sanitize($user['avatar']) ?>" alt="" id="avatar-preview">
                <?php else: ?>
                  <span id="avatar-initials"><?= $initials ?></span>
                <?php endif; ?>
              </div>
              <div class="upload-overlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              </div>
              <input type="file" name="avatar" accept="image/*" style="display:none" onchange="previewAvatar(this)">
            </label>

            <div style="flex:1;min-width:200px">
              <div class="form-group">
                <label>Full Name <span style="color:var(--danger)">*</span></label>
                <input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name']) ?>" required placeholder="Your full name">
              </div>
              <p style="font-size:.78rem;color:var(--gray-400)">
                Click the photo to upload a new profile picture. JPG, PNG or WEBP. Max 2MB.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Bio -->
      <div class="card" style="margin-bottom:18px">
        <div class="card-header"><h3>About Me</h3></div>
        <div class="card-body">
          <div class="form-group" style="margin-bottom:0">
            <label>Bio <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
            <textarea name="bio" class="form-control" rows="4" placeholder="Tell the KCA community a bit about yourself — your interests, projects, goals..." style="resize:vertical;line-height:1.6"><?= sanitize($user['bio'] ?? '') ?></textarea>
            <div style="font-size:.74rem;color:var(--gray-400);margin-top:6px">Max 500 characters</div>
          </div>
        </div>
      </div>

      <!-- Academic Details -->
      <div class="card" style="margin-bottom:18px">
        <div class="card-header"><h3>Academic Details</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label>Course / Programme</label>
            <select name="course" class="form-control">
              <option value="">Select your programme</option>
              <?php foreach ($courses as $courseName => $school):
                if (empty($school)): // Section header ?>
                  <option disabled style="font-weight:700;color:var(--gray-500)"><?= $courseName ?></option>
                <?php else: ?>
                  <option value="<?= sanitize($courseName) ?>" <?= ($user['course'] ?? '') === $courseName ? 'selected' : '' ?>>
                    <?= sanitize($courseName) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-group">
              <label>Department / School</label>
              <select name="department" class="form-control">
                <option value="">Select department</option>
                <option value="School of Technology (SoT)" <?= ($user['department']??'')==='School of Technology (SoT)'?'selected':'' ?>>School of Technology (SoT)</option>
                <option value="School of Business (SoB)" <?= ($user['department']??'')==='School of Business (SoB)'?'selected':'' ?>>School of Business (SoB)</option>
                <option value="School of Education, Arts & Social Sciences (SEASS)" <?= ($user['department']??'')==='School of Education, Arts & Social Sciences (SEASS)'?'selected':'' ?>>SEASS</option>
                <option value="Board of Postgraduate Studies" <?= ($user['department']??'')==='Board of Postgraduate Studies'?'selected':'' ?>>Board of Postgraduate Studies</option>
                <option value="KCAU PTTI" <?= ($user['department']??'')==='KCAU PTTI'?'selected':'' ?>>KCAU PTTI</option>
                <option value="ICT Department" <?= ($user['department']??'')==='ICT Department'?'selected':'' ?>>ICT Department</option>
              </select>
            </div>

            <div class="form-group">
              <label>Year of Study</label>
              <select name="year_of_study" class="form-control">
                <?php foreach ([1=>'Year 1',2=>'Year 2',3=>'Year 3',4=>'Year 4',5=>'Alumni / Graduate',6=>'Staff'] as $y => $label): ?>
                  <option value="<?= $y ?>" <?= ($user['year_of_study'] ?? 1) == $y ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
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

      <!-- Password Change -->
      <div class="card" style="margin-bottom:18px">
        <div class="card-header">
          <h3>Change Password</h3>
          <span style="font-size:.78rem;color:var(--gray-400)">Leave blank to keep current password</span>
        </div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-group" style="margin-bottom:0">
              <label>New Password</label>
              <div style="position:relative">
                <input type="password" name="new_password" id="pw1" class="form-control" placeholder="Min. 8 characters" autocomplete="new-password" style="padding-right:44px">
                <button type="button" onclick="togglePw('pw1')" style="position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);display:flex">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" id="pw2" class="form-control" placeholder="Repeat new password" autocomplete="new-password">
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:12px;align-items:center">
        <button type="submit" class="btn btn-primary btn-lg">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg>
          Save Changes
        </button>
        <a href="profile.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>

  </div>

  <!-- Right sidebar -->
  <div class="right-sidebar">
    <div class="card widget">
      <div class="card-header"><h3>Your Profile</h3></div>
      <div class="card-body" style="text-align:center">
        <div class="avatar avatar-lg" style="background:<?= $avatarBg ?>;margin:0 auto 12px">
          <?php if ($user['avatar']): ?>
            <img src="<?= SITE_URL ?>/assets/uploads/<?= sanitize($user['avatar']) ?>" alt="">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div style="font-weight:700;color:var(--gray-700);margin-bottom:3px"><?= sanitize($user['full_name']) ?></div>
        <div style="font-size:.78rem;color:var(--gray-400);margin-bottom:12px"><?= sanitize($user['student_id'] ?? '') ?></div>
        <a href="profile.php" class="btn btn-outline btn-sm btn-full">View public profile</a>
      </div>
    </div>

    <div class="card widget">
      <div class="card-header"><h3>Tips</h3></div>
      <div class="card-body" style="font-size:.82rem;color:var(--gray-500);line-height:1.7">
        <p style="margin-bottom:8px">📸 Add a profile photo so classmates can recognise you.</p>
        <p style="margin-bottom:8px">📝 Write a bio to let others know about your interests and projects.</p>
        <p>🎓 Keep your course and year updated so you appear in the right search filters.</p>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = "
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      let el = document.getElementById('avatar-preview');
      if (!el) {
        const wrap = input.closest('.avatar');
        wrap.innerHTML = '<img id=\"avatar-preview\" style=\"width:100%;height:100%;border-radius:50%;object-fit:cover\">';
        el = document.getElementById('avatar-preview');
      }
      el.src = e.target.result;
      const ini = document.getElementById('avatar-initials');
      if (ini) ini.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function togglePw(id) {
  const f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
}
";
require_once __DIR__ . '/../includes/footer.php';
