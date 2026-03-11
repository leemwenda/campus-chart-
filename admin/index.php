<?php
// admin/index.php
$pageTitle    = 'Admin Panel';
$pageSubtitle = 'KCA Chat Administration';
$activeNav    = 'admin';
require_once __DIR__ . '/../includes/header.php';
requireRole('admin');

$tab = $_GET['tab'] ?? 'dashboard';
$msg = $_GET['msg'] ?? '';

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        header('Location: index.php?tab='.$tab.'&msg=csrf_error'); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'change_role') {
        $uid  = (int)$_POST['user_id'];
        $role = in_array($_POST['role'], ['student','staff','admin']) ? $_POST['role'] : 'student';
        DB::query("UPDATE users SET role=? WHERE id=?", [$role, $uid]);
        header('Location: index.php?tab=users&msg=role_updated'); exit;

    } elseif ($action === 'toggle_user') {
        $uid    = (int)$_POST['user_id'];
        $active = (int)$_POST['is_active'];
        DB::query("UPDATE users SET is_active=? WHERE id=?", [$active, $uid]);
        header('Location: index.php?tab=users&msg=user_updated'); exit;

    } elseif ($action === 'pin_post') {
        $pid  = (int)$_POST['post_id'];
        $pin  = (int)$_POST['is_pinned'];
        DB::query("UPDATE posts SET is_pinned=? WHERE id=?", [$pin, $pid]);
        header('Location: index.php?tab=posts&msg=post_updated'); exit;

    } elseif ($action === 'delete_post') {
        $pid = (int)$_POST['post_id'];
        DB::query("DELETE FROM posts WHERE id=?", [$pid]);
        header('Location: index.php?tab=posts&msg=post_deleted'); exit;

    } elseif ($action === 'create_event') {
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $date  = $_POST['event_date'] ?? '';
        $time  = $_POST['start_time'] ?? '08:00';
        $loc   = trim($_POST['location'] ?? '');
        $cat   = $_POST['category'] ?? 'academic';
        $campus= $_POST['campus'] ?? 'Town Campus';
        if ($title && $date) {
            DB::insert(
                "INSERT INTO events (title,description,category,event_date,start_time,location,campus,created_by) VALUES (?,?,?,?,?,?,?,?)",
                [$title, $desc, $cat, $date, $time, $loc, $campus, $user['id']]
            );
            header('Location: index.php?tab=events&msg=event_created'); exit;
        }

    } elseif ($action === 'delete_event') {
        DB::query("DELETE FROM events WHERE id=?", [(int)$_POST['event_id']]);
        header('Location: index.php?tab=events&msg=event_deleted'); exit;

    } elseif ($action === 'create_announcement') {
        $content = trim($_POST['content'] ?? '');
        if ($content) {
            DB::insert(
                "INSERT INTO posts (user_id, content, tag, is_pinned) VALUES (?,?,'announcement',1)",
                [$user['id'], $content]
            );
            // Notify all users
            $allUsers = DB::rows("SELECT id FROM users WHERE id != ? AND is_active=1", [$user['id']]);
            foreach ($allUsers as $u2) {
                DB::insert(
                    "INSERT INTO notifications (user_id, type, message, actor_id) VALUES (?,?,?,?)",
                    [$u2['id'], 'announcement', '📢 Admin announcement: ' . substr($content, 0, 80) . (strlen($content) > 80 ? '...' : ''), $user['id']]
                );
            }
            header('Location: index.php?tab=posts&msg=announcement_sent'); exit;
        }

    } elseif ($action === 'create_space') {
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $type   = $_POST['type'] ?? 'academic';
        $school = trim($_POST['school'] ?? '') ?: null;
        $banner = null;
        // Handle banner upload
        if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg','jpeg','png','webp','gif','bmp','avif','heic'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['banner']['tmp_name']);
            finfo_close($finfo);
            $isImage = in_array($ext, $allowedExts) || strpos($mime, 'image/') === 0;
            if ($isImage) {
                $uploadDir = __DIR__ . '/../assets/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $saveExt = in_array($ext, ['jpg','jpeg']) ? 'jpg' : ($ext ?: 'jpg');
                $fname = 'space_banner_' . time() . '_' . rand(100,999) . '.' . $saveExt;
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . $fname)) {
                    $banner = $fname;
                }
            }
        }
        if ($name) {
            $slug  = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . time();
            $newId = DB::insert(
                "INSERT INTO spaces (name, slug, description, type, school, banner, created_by) VALUES (?,?,?,?,?,?,?)",
                [$name, $slug, $desc, $type, $school, $banner, $user['id']]
            );
            DB::insert("INSERT INTO space_members (space_id, user_id, role) VALUES (?,?,'admin')", [$newId, $user['id']]);
            header('Location: index.php?tab=spaces&msg=space_created'); exit;
        }
    } elseif ($action === 'edit_space') {
        $sid  = (int)$_POST['space_id'];
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? 'academic';
        $school = trim($_POST['school'] ?? '') ?: null;
        $existing = DB::row("SELECT banner FROM spaces WHERE id=?", [$sid]);
        $banner = $existing['banner'];
        if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg','jpeg','png','webp','gif','bmp','avif','heic'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['banner']['tmp_name']);
            finfo_close($finfo);
            $isImage = in_array($ext, $allowedExts) || strpos($mime, 'image/') === 0;
            if ($isImage) {
                $uploadDir = __DIR__ . '/../assets/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $saveExt = in_array($ext, ['jpg','jpeg']) ? 'jpg' : ($ext ?: 'jpg');
                $fname = 'space_banner_' . time() . '_' . rand(100,999) . '.' . $saveExt;
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . $fname)) {
                    if ($banner && file_exists($uploadDir . $banner)) @unlink($uploadDir . $banner);
                    $banner = $fname;
                }
            }
        }
        if ($name && $sid) {
            DB::query("UPDATE spaces SET name=?,description=?,type=?,school=?,banner=? WHERE id=?",
                [$name, $desc, $type, $school, $banner, $sid]);
            header('Location: index.php?tab=spaces&msg=space_updated'); exit;
        }
    } elseif ($action === 'delete_space') {
        $sid = (int)$_POST['space_id'];
        DB::query("UPDATE spaces SET is_active=0 WHERE id=?", [$sid]);
        header('Location: index.php?tab=spaces&msg=space_deleted'); exit;
    }
}

// ── Data for each tab ────────────────────────────────────────────────────────
$stats = [
    'users'   => DB::count("SELECT COUNT(*) FROM users WHERE is_active=1"),
    'posts'   => DB::count("SELECT COUNT(*) FROM posts"),
    'events'  => DB::count("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()"),
    'spaces'  => DB::count("SELECT COUNT(*) FROM spaces WHERE is_active=1"),
    'msgs'    => DB::count("SELECT COUNT(*) FROM messages"),
    'notifs'  => DB::count("SELECT COUNT(*) FROM notifications WHERE is_read=0"),
];

$users  = $posts = $events = $spaces = [];
// Load banners for banners tab
if ($tab === 'banners') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $bAction = $_POST['action'];
        if ($bAction === 'create_banner') {
            $bTitle   = trim($_POST['title'] ?? '');
            $bDesc    = trim($_POST['description'] ?? '');
            $bLink    = trim($_POST['link_url'] ?? '');
            $bLabel   = trim($_POST['link_label'] ?? 'Learn More');
            $bPlace   = $_POST['placement'] ?? 'feed';
            $bStart   = $_POST['start_date'] ?: null;
            $bEnd     = $_POST['end_date'] ?: null;
            $bImg     = null;
            if (!empty($_FILES['banner_image']['name']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $fname = 'banner_ad_' . time() . '_' . rand(100,999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadDir . $fname)) {
                        $bImg = $fname;
                    }
                }
            }
            if ($bTitle && $bImg) {
                DB::insert("INSERT INTO banners (title,description,image,link_url,link_label,placement,start_date,end_date,created_by) VALUES (?,?,?,?,?,?,?,?,?)",
                    [$bTitle,$bDesc,$bImg,$bLink,$bLabel,$bPlace,$bStart,$bEnd,$user['id']]);
                header('Location: index.php?tab=banners&msg=banner_created'); exit;
            }
        } elseif ($bAction === 'toggle_banner') {
            $bid = (int)($_POST['banner_id'] ?? 0);
            $current = DB::row("SELECT is_active FROM banners WHERE id=?", [$bid]);
            if ($current) {
                DB::query("UPDATE banners SET is_active=? WHERE id=?", [$current['is_active'] ? 0 : 1, $bid]);
            }
            header('Location: index.php?tab=banners'); exit;
        } elseif ($bAction === 'delete_banner') {
            $bid = (int)($_POST['banner_id'] ?? 0);
            $brow = DB::row("SELECT image FROM banners WHERE id=?", [$bid]);
            if ($brow && $brow['image']) {
                @unlink(__DIR__ . '/../assets/uploads/' . $brow['image']);
            }
            DB::query("DELETE FROM banners WHERE id=?", [$bid]);
            header('Location: index.php?tab=banners'); exit;
        }
    }
}
$banners = ($tab === 'banners') ? DB::rows("SELECT b.*, u.full_name as creator FROM banners b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.created_at DESC") : [];

if ($tab === 'users') {
    $q    = trim($_GET['q'] ?? '');
    $role = $_GET['role'] ?? '';
    $sql  = "SELECT * FROM users WHERE 1";
    $params = [];
    if ($q)    { $sql .= " AND (full_name LIKE ? OR email LIKE ? OR student_id LIKE ?)"; $params = array_merge($params, ["%$q%","%$q%","%$q%"]); }
    if ($role) { $sql .= " AND role=?"; $params[] = $role; }
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $users = DB::rows($sql, $params);
}
if ($tab === 'posts') {
    $posts = DB::rows(
        "SELECT p.*, u.full_name, s.name as space_name,
                (SELECT COUNT(*) FROM post_reactions r WHERE r.post_id=p.id) as like_count,
                (SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id) as comment_count
         FROM posts p JOIN users u ON u.id=p.user_id
         LEFT JOIN spaces s ON s.id=p.space_id
         ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 60", []
    );
}
if ($tab === 'events') {
    $events = DB::rows("SELECT e.*, u.full_name as creator FROM events e JOIN users u ON u.id=e.created_by ORDER BY e.event_date ASC LIMIT 60", []);
}
if ($tab === 'spaces') {
    $spaces = DB::rows(
        "SELECT s.*, u.full_name as creator,
                (SELECT COUNT(*) FROM space_members sm WHERE sm.space_id=s.id) as member_count
         FROM spaces s JOIN users u ON u.id=s.created_by ORDER BY s.created_at DESC", []
    );
}
?>

<style>
.admin-tab-content { display:none; }
.admin-tab-content.active { display:block; }
</style>

<!-- Admin nav tabs -->
<div class="admin-nav" style="margin-bottom:22px">
  <?php foreach ([
    'dashboard' => ['Dashboard', '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
    'users'     => ['Users',     '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    'posts'     => ['Posts',     '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'],
    'events'    => ['Events',    '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    'spaces'    => ['Spaces',    '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'],
    'announce'  => ['Announce',  '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'],
    'banners'   => ['Banners',   '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>'],
  ] as $key => [$label, $icon]): ?>
    <a href="?tab=<?= $key ?>" class="admin-nav-btn <?= $tab===$key?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $icon ?></svg>
      <?= $label ?>
    </a>
  <?php endforeach; ?>
  <a href="analytics.php" class="admin-nav-btn">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>
    Analytics
  </a>
</div>

<?php if ($msg): ?>
  <?php $msgs = [
    'banner_created'     => ['success', 'Banner created successfully!'],
    'role_updated'       => ['success', 'User role updated.'],
    'user_updated'       => ['success', 'User account updated.'],
    'post_updated'       => ['success', 'Post updated.'],
    'post_deleted'       => ['success', 'Post deleted.'],
    'event_created'      => ['success', 'Event created successfully!'],
    'event_deleted'      => ['success', 'Event deleted.'],
    'space_created'      => ['success', 'Space created!'],
    'announcement_sent'  => ['success', 'Announcement sent to all users!'],
    'csrf_error'         => ['danger',  'Security error. Please try again.'],
  ]; [$mtype, $mtext] = $msgs[$msg] ?? ['info', $msg]; ?>
  <div style="background:rgba(<?= $mtype==='success'?'10,124,89':'196,43,43' ?>,.07);border-left:3px solid var(--<?= $mtype ?>);border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:.84rem;color:var(--<?= $mtype ?>);">
    <?= sanitize($mtext) ?>
  </div>
<?php endif; ?>

<!-- ═══ DASHBOARD ═══ -->
<?php if ($tab === 'dashboard'): ?>
<div class="stats-row" style="margin-bottom:20px">
  <?php foreach ([
    ['Total Users',    $stats['users'],  'var(--kca-navy)',  '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
    ['Total Posts',    $stats['posts'],  'var(--success)',   '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>'],
    ['Upcoming Events',$stats['events'], 'var(--info)',      '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>'],
    ['Active Spaces',  $stats['spaces'], '#4527A0',          '<circle cx="12" cy="12" r="10"/>'],
    ['Messages Sent',  $stats['msgs'],   '#0054A6',          '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
    ['Unread Notifs',  $stats['notifs'], 'var(--warning)',   '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>'],
  ] as [$label, $val, $color, $icon]): ?>
    <div class="card stat-card">
      <div class="stat-icon" style="background:rgba(0,48,135,.08)">
        <svg viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="1.8" width="20" height="20"><?= $icon ?></svg>
      </div>
      <div class="stat-value" style="color:<?= $color ?>"><?= number_format($val) ?></div>
      <div class="stat-label"><?= $label ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;flex-wrap:wrap">
  <!-- Recent users -->
  <div class="card">
    <div class="card-header">
      <h3>Recent Sign-ups</h3>
      <a href="?tab=users" class="card-action">View all</a>
    </div>
    <?php $recentUsers = DB::rows("SELECT * FROM users ORDER BY created_at DESC LIMIT 6", []); ?>
    <table class="admin-table">
      <thead><tr><th>User</th><th>Role</th><th>Joined</th></tr></thead>
      <tbody>
        <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= sanitize($u['full_name']) ?></div>
              <div style="font-size:.75rem;color:var(--gray-400)"><?= sanitize($u['student_id'] ?? $u['email'] ?? '') ?></div>
            </td>
            <td><span class="role-pill pill-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
            <td style="font-size:.78rem;color:var(--gray-400)"><?= date('M j', strtotime($u['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Recent posts -->
  <div class="card">
    <div class="card-header">
      <h3>Recent Posts</h3>
      <a href="?tab=posts" class="card-action">View all</a>
    </div>
    <?php $recentPosts = DB::rows(
      "SELECT p.*, u.full_name FROM posts p JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC LIMIT 6", []
    ); ?>
    <table class="admin-table">
      <thead><tr><th>Post</th><th>Tag</th><th>By</th></tr></thead>
      <tbody>
        <?php foreach ($recentPosts as $p): ?>
          <tr>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?php if ($p['is_pinned']): ?><span class="pin-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 9h6l-5 4 2 7-6-4-6 4 2-7-5-4h6z"/></svg>Pinned</span><?php endif; ?>
              <?= sanitize(substr($p['content'], 0, 60)) ?>...
            </td>
            <td><span class="post-tag tag-<?= $p['tag'] ?>"><?= $p['tag'] ?></span></td>
            <td style="font-size:.78rem;color:var(--gray-400)"><?= sanitize(explode(' ', $p['full_name'])[0]) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<!-- ═══ USERS ═══ -->
<?php elseif ($tab === 'users'): ?>
<div class="card" style="margin-bottom:18px;padding:16px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
    <input type="hidden" name="tab" value="users">
    <input type="text" name="q" value="<?= sanitize($_GET['q'] ?? '') ?>" placeholder="Search by name, ID, email..." class="form-control" style="flex:1;min-width:200px">
    <select name="role" class="form-control" style="width:130px">
      <option value="">All roles</option>
      <option value="student" <?= ($_GET['role']??'')==='student'?'selected':'' ?>>Student</option>
      <option value="staff"   <?= ($_GET['role']??'')==='staff'?'selected':'' ?>>Staff</option>
      <option value="admin"   <?= ($_GET['role']??'')==='admin'?'selected':'' ?>>Admin</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
  </form>
</div>

<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between">
    <h3 style="font-family:var(--font-serif);color:var(--kca-navy);font-size:.95rem"><?= count($users) ?> users found</h3>
  </div>
  <div style="overflow-x:auto">
    <table class="admin-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Student ID</th>
          <th>Course</th>
          <th>Role</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar avatar-sm" style="background:<?= avatarColor($u['id']) ?>;font-size:.72rem">
                <?= $u['avatar'] ? '<img src="'.SITE_URL.'/assets/uploads/'.sanitize($u['avatar']).'" alt="">' : avatarInitials($u['full_name']) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.86rem"><?= sanitize($u['full_name']) ?></div>
                <div style="font-size:.74rem;color:var(--gray-400)"><?= sanitize($u['email'] ?? '') ?></div>
              </div>
            </div>
          </td>
          <td style="font-family:monospace;font-size:.82rem"><?= sanitize($u['student_id'] ?? '—') ?></td>
          <td style="font-size:.78rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($u['course'] ?? '—') ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="change_role">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <select name="role" class="role-select-inline" onchange="this.form.submit()">
                <option value="student" <?= $u['role']==='student'?'selected':'' ?>>Student</option>
                <option value="staff"   <?= $u['role']==='staff'?'selected':'' ?>>Staff</option>
                <option value="admin"   <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
              </select>
            </form>
          </td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:.75rem;font-weight:600;color:<?= $u['is_active']?'var(--success)':'var(--danger)' ?>">
              <span style="width:6px;height:6px;border-radius:50%;background:currentColor"></span>
              <?= $u['is_active'] ? 'Active' : 'Disabled' ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">View</a>
              <?php if ($u['id'] !== $user['id']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                <button type="submit" class="btn btn-sm" style="background:<?= $u['is_active']?'rgba(196,43,43,.1)':'rgba(10,124,89,.1)' ?>;color:<?= $u['is_active']?'var(--danger)':'var(--success)' ?>;border:none"
                        onclick="return confirm('<?= $u['is_active']?'Disable':'Enable' ?> this user?')">
                  <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<!-- ═══ POSTS ═══ -->
<?php elseif ($tab === 'posts'): ?>

<!-- Announcement form -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header">
    <h3>Send Campus-wide Announcement</h3>
    <span style="font-size:.78rem;color:var(--gray-400)">Will be pinned + notified to all users</span>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="create_announcement">
      <div class="form-group">
        <textarea name="content" class="form-control" rows="4" placeholder="Write your campus-wide announcement here..." required></textarea>
      </div>
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Send Announcement
      </button>
    </form>
  </div>
</div>

<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--gray-100)">
    <h3 style="font-family:var(--font-serif);color:var(--kca-navy);font-size:.95rem">All Posts (<?= count($posts) ?>)</h3>
  </div>
  <div style="overflow-x:auto">
    <table class="admin-table">
      <thead><tr><th>Content</th><th>By</th><th>Space</th><th>Tag</th><th>Likes</th><th>Pin</th><th>Delete</th></tr></thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td style="max-width:220px">
            <?php if ($p['is_pinned']): ?><span class="pin-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="10"><path d="M12 2l3 9h6l-5 4 2 7-6-4-6 4 2-7-5-4h6z"/></svg>Pinned</span> <?php endif; ?>
            <span style="font-size:.82rem"><?= sanitize(substr($p['content'], 0, 80)) ?>...</span>
          </td>
          <td style="font-size:.8rem"><?= sanitize(explode(' ', $p['full_name'])[0]) ?></td>
          <td style="font-size:.78rem;color:var(--gray-400)"><?= sanitize($p['space_name'] ?? 'Campus-wide') ?></td>
          <td><span class="post-tag tag-<?= $p['tag'] ?>"><?= $p['tag'] ?></span></td>
          <td style="font-size:.82rem;text-align:center"><?= $p['like_count'] ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="pin_post">
              <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="is_pinned" value="<?= $p['is_pinned'] ? 0 : 1 ?>">
              <button type="submit" class="btn btn-sm" style="background:<?= $p['is_pinned']?'rgba(201,168,76,.2)':'rgba(0,48,135,.08)' ?>;color:<?= $p['is_pinned']?'#8B6914':'var(--kca-navy)' ?>;border:none">
                <?= $p['is_pinned'] ? 'Unpin' : 'Pin' ?>
              </button>
            </form>
          </td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete_post">
              <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-sm" style="background:rgba(196,43,43,.08);color:var(--danger);border:none" onclick="return confirm('Delete this post?')">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<!-- ═══ EVENTS ═══ -->
<?php elseif ($tab === 'events'): ?>

<!-- Create event form -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header"><h3>Create New Event</h3></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="create_event">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label>Event Title <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. KCA Tech Expo 2026" required>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category" class="form-control">
            <option value="academic">Academic</option>
            <option value="social">Social</option>
            <option value="club">Club / Society</option>
            <option value="career">Career</option>
            <option value="exam">Exam / Assessment</option>
            <option value="deadline">Deadline</option>
            <option value="administrative">Administrative</option>
          </select>
        </div>
        <div class="form-group">
          <label>Date <span style="color:var(--danger)">*</span></label>
          <input type="date" name="event_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>Start Time</label>
          <input type="time" name="start_time" class="form-control" value="08:00">
        </div>
        <div class="form-group">
          <label>Location</label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Main Auditorium, Town Campus">
        </div>
        <div class="form-group">
          <label>Campus</label>
          <select name="campus" class="form-control">
            <option>Town Campus</option>
            <option>Western Campus</option>
            <option>Kitengela Campus</option>
            <option>Online</option>
            <option>All Campuses</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Event details, requirements, what to bring..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create Event
      </button>
    </form>
  </div>
</div>

<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--gray-100)">
    <h3 style="font-family:var(--font-serif);color:var(--kca-navy);font-size:.95rem">All Events (<?= count($events) ?>)</h3>
  </div>
  <div style="overflow-x:auto">
    <table class="admin-table">
      <thead><tr><th>Title</th><th>Date</th><th>Category</th><th>Campus</th><th>Created by</th><th>Delete</th></tr></thead>
      <tbody>
        <?php foreach ($events as $e): ?>
        <tr>
          <td style="font-weight:600;font-size:.85rem;max-width:200px"><?= sanitize($e['title']) ?></td>
          <td style="font-size:.8rem"><?= date('M j, Y', strtotime($e['event_date'])) ?></td>
          <td><span class="post-tag tag-<?= $e['category'] === 'exam' ? 'urgent' : ($e['category'] === 'social' ? 'social' : 'academic') ?>"><?= $e['category'] ?></span></td>
          <td style="font-size:.78rem"><?= sanitize($e['campus']) ?></td>
          <td style="font-size:.78rem;color:var(--gray-400)"><?= sanitize($e['creator']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete_event">
              <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
              <button type="submit" class="btn btn-sm" style="background:rgba(196,43,43,.08);color:var(--danger);border:none" onclick="return confirm('Delete this event?')">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<!-- ═══ SPACES ═══ -->
<?php elseif ($tab === 'spaces'):
$editSpaceId = (int)($_GET['edit'] ?? 0);
$editSpace = $editSpaceId ? DB::row("SELECT * FROM spaces WHERE id=?", [$editSpaceId]) : null;
?>

<div class="card" style="margin-bottom:18px">
  <div class="card-header">
    <h3><?= $editSpace ? 'Edit Space: ' . sanitize($editSpace['name']) : 'Create New Space' ?></h3>
    <?php if ($editSpace): ?><a href="?tab=spaces" class="btn btn-ghost btn-sm">+ Create New Instead</a><?php endif; ?>
  </div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="<?= $editSpace ? 'edit_space' : 'create_space' ?>">
      <?php if ($editSpace): ?><input type="hidden" name="space_id" value="<?= $editSpace['id'] ?>"><?php endif; ?>

      <?php if ($editSpace && $editSpace['banner']): ?>
      <div style="margin-bottom:14px">
        <div style="font-size:.78rem;color:var(--gray-500);margin-bottom:6px;font-weight:600">Current Banner</div>
        <img src="<?= UPLOAD_URL . sanitize($editSpace['banner']) ?>" style="width:100%;max-height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--gray-200)">
      </div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div class="form-group" style="margin:0">
          <label>Space Name <span style="color:var(--danger)">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="e.g. BSc IT Year 3 2026" value="<?= sanitize($editSpace['name'] ?? '') ?>" required>
        </div>
        <div class="form-group" style="margin:0">
          <label>Type / Category</label>
          <select name="type" class="form-control">
            <?php foreach (['academic'=>'Academic','club'=>'Club / Society','co-curricular'=>'Co-curricular','administrative'=>'Administrative / Official','professional'=>'Professional Studies'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editSpace['type']??'')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>School / Faculty (optional)</label>
        <select name="school" class="form-control">
          <option value="">All Schools / Not school-specific</option>
          <?php
          $schools = ['School of Technology','School of Business','School of Education, Arts & Social Sciences','Professional Training & Testing Institute'];
          foreach ($schools as $s): ?>
          <option value="<?= $s ?>" <?= ($editSpace['school']??'')===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="What is this space for? Who should join?"><?= sanitize($editSpace['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Banner Image <span style="font-weight:400;color:var(--gray-400)">(any image format — gives the space a custom look)</span></label>
        <input type="file" name="banner" class="form-control" accept="image/*" onchange="previewBanner(this)" style="padding:8px">
        <img id="banner-preview" src="#" alt="" style="display:none;width:100%;max-height:100px;object-fit:cover;border-radius:8px;margin-top:8px;border:1px solid var(--gray-200)">
      </div>
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
          <?= $editSpace ? '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/>' : '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>' ?>
        </svg>
        <?= $editSpace ? 'Save Changes' : 'Create Space' ?>
      </button>
    </form>
  </div>
</div>

<div class="card" style="overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center">
    <h3 style="font-family:var(--font-serif);color:var(--kca-navy);font-size:.95rem">All Spaces (<?= count($spaces) ?>)</h3>
  </div>
  <div style="padding:14px 18px">
    <?php foreach ($spaces as $s): ?>
    <div class="space-manage-card">
      <?php if ($s['banner'] ?? null): ?>
      <img src="<?= UPLOAD_URL . sanitize($s['banner']) ?>" class="space-manage-banner" alt="">
      <?php else: ?>
      <?php
      $bannerBgs = ['academic'=>'linear-gradient(135deg,#003087,#0057B8)','club'=>'linear-gradient(135deg,#0A7C59,#0d9e72)','administrative'=>'linear-gradient(135deg,#8B6914,#C9A84C)','co-curricular'=>'linear-gradient(135deg,#4527A0,#7C3AED)','professional'=>'linear-gradient(135deg,#8B1A1A,#C42B2B)'];
      ?>
      <div class="space-manage-banner" style="background:<?= $bannerBgs[$s['type']] ?? $bannerBgs['academic'] ?>;border-radius:7px"></div>
      <?php endif; ?>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:.87rem;color:var(--gray-700)"><?= sanitize($s['name']) ?></div>
        <div style="font-size:.72rem;color:var(--gray-400)"><?= ucfirst($s['type']) ?> &middot; <?= $s['member_count'] ?> members</div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <a href="?tab=spaces&edit=<?= $s['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
        <a href="<?= SITE_URL ?>/pages/space_view.php?id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm" target="_blank">View</a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Archive this space?')">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="delete_space">
          <input type="hidden" name="space_id" value="<?= $s['id'] ?>">
          <button type="submit" class="btn btn-sm" style="background:rgba(196,43,43,.08);color:var(--danger);border:1px solid rgba(196,43,43,.2)">Archive</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
function previewBanner(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('banner-preview');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<!-- ═══ ANNOUNCE ═══ -->
<?php elseif ($tab === 'announce'): ?>
<div class="card">
  <div class="card-header"><h3>Campus-wide Announcement</h3></div>
  <div class="card-body">
    <p style="font-size:.86rem;color:var(--gray-500);margin-bottom:18px;line-height:1.7">
      Send an announcement to <strong>all KCA Chat users</strong>. It will appear as a pinned post on the campus feed and trigger a notification for every user.
    </p>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="create_announcement">
      <div class="form-group">
        <label>Announcement Content <span style="color:var(--danger)">*</span></label>
        <textarea name="content" class="form-control" rows="6" placeholder="Write your official announcement here. This will be visible to all students, staff and alumni on KCA Chat..." required></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Send this announcement to ALL users?')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Send to All Users
      </button>
    </form>
  </div>
</div>
<?php elseif ($tab === 'banners'): ?>
<!-- ═══ BANNERS / ADVERTISEMENTS ═══ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

  <!-- CREATE BANNER FORM -->
  <div class="card">
    <div class="card-header"><h3>Add New Banner / Advertisement</h3></div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="create_banner">
        <div class="form-group">
          <label>Banner Title <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. KCA Tech Expo 2026" required>
        </div>
        <div class="form-group">
          <label>Description <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
          <textarea name="description" class="form-control" rows="2" placeholder="Short tagline or description..."></textarea>
        </div>
        <div class="form-group">
          <label>Banner Image <span style="color:var(--danger)">*</span></label>
          <input type="file" name="banner_image" class="form-control" accept="image/*" required onchange="previewBannerAd(this)" style="padding:8px">
          <img id="banner-ad-preview" src="#" alt="" style="display:none;width:100%;max-height:120px;object-fit:cover;border-radius:8px;margin-top:8px;border:1px solid var(--gray-200)">
          <div style="font-size:.72rem;color:var(--gray-400);margin-top:4px">Recommended: 1200×400px or wider</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group" style="margin:0">
            <label>Link URL <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
            <input type="url" name="link_url" class="form-control" placeholder="https://...">
          </div>
          <div class="form-group" style="margin:0">
            <label>Button Label</label>
            <input type="text" name="link_label" class="form-control" value="Learn More" placeholder="Learn More">
          </div>
        </div>
        <div class="form-group">
          <label>Placement</label>
          <select name="placement" class="form-control">
            <option value="feed">Home Feed (between posts)</option>
            <option value="sidebar">Sidebar (right panel)</option>
            <option value="events">Events Page</option>
            <option value="spaces">Spaces Page</option>
          </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group" style="margin:0">
            <label>Start Date <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
            <input type="date" name="start_date" class="form-control">
          </div>
          <div class="form-group" style="margin:0">
            <label>End Date <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
            <input type="date" name="end_date" class="form-control">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:8px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Publish Banner
        </button>
      </form>
    </div>
  </div>

  
  <div>
    <div class="card">
      <div class="card-header">
        <h3>All Banners (<?= count($banners) ?>)</h3>
      </div>
      <?php if (empty($banners)): ?>
        <div style="padding:32px;text-align:center;color:var(--gray-400)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40" style="opacity:.4;display:block;margin:0 auto 10px"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
          <p style="font-size:.85rem">No banners yet. Create your first one!</p>
        </div>
      <?php else: ?>
      <?php foreach ($banners as $bn): ?>
      <div style="border-bottom:1px solid var(--gray-100);padding:14px 16px">
        <!-- Banner image preview -->
        <div style="width:100%;height:80px;border-radius:8px;overflow:hidden;margin-bottom:10px;background:var(--gray-100)">
          <img src="<?= UPLOAD_URL . sanitize($bn['image']) ?>" alt=""
               style="width:100%;height:100%;object-fit:cover">
        </div>
        <div style="display:flex;align-items:flex-start;gap:10px">
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:.87rem;color:var(--gray-700)"><?= sanitize($bn['title']) ?></div>
            <div style="font-size:.72rem;margin-top:2px">
              <span class="post-tag tag-<?= $bn['placement'] === 'feed' ? 'academic' : ($bn['placement'] === 'sidebar' ? 'social' : 'administrative') ?>"><?= ucfirst($bn['placement']) ?></span>
              <span style="color:var(--gray-400);margin-left:6px"><?= $bn['views'] ?> views · <?= $bn['clicks'] ?> clicks</span>
            </div>
            <?php if ($bn['start_date'] || $bn['end_date']): ?>
            <div style="font-size:.7rem;color:var(--gray-400);margin-top:3px">
              <?= $bn['start_date'] ? date('M j', strtotime($bn['start_date'])) : 'Now' ?>
              → <?= $bn['end_date'] ? date('M j, Y', strtotime($bn['end_date'])) : 'No end' ?>
            </div>
            <?php endif; ?>
          </div>
          <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0">
            <!-- Toggle active -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="toggle_banner">
              <input type="hidden" name="banner_id" value="<?= $bn['id'] ?>">
              <button type="submit" class="btn btn-sm"
                      style="background:<?= $bn['is_active'] ? 'rgba(10,124,89,.1)' : 'rgba(0,48,135,.07)' ?>;color:<?= $bn['is_active'] ? '#0A7C59' : 'var(--kca-navy)' ?>;border:none;font-size:.72rem;white-space:nowrap">
                <?= $bn['is_active'] ? '✓ Active' : '○ Paused' ?>
              </button>
            </form>
            <!-- Delete -->
            <form method="POST" onsubmit="return confirm('Delete this banner permanently?')" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete_banner">
              <input type="hidden" name="banner_id" value="<?= $bn['id'] ?>">
              <button type="submit" class="btn btn-sm" style="background:rgba(196,43,43,.08);color:var(--danger);border:none;font-size:.72rem">Delete</button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
function previewBannerAd(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('banner-ad-preview');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
