<?php
require_once __DIR__ . '/../config/auth.php';
$pageTitle    = 'Create Post';
$pageSubtitle = 'Share with your campus community';
$activeNav    = 'feed';
require_once __DIR__ . '/../includes/header.php';

$mySpacesForPost = DB::rows(
    "SELECT s.id, s.name FROM spaces s JOIN space_members sm ON sm.space_id=s.id WHERE sm.user_id=? AND s.is_active=1 ORDER BY s.name",
    [$user['id']]
);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $tag     = $_POST['tag'] ?? 'academic';
    $spaceId = (int)($_POST['space_id'] ?? 0) ?: null;
    if (!$content) {
        $error = 'Post content cannot be empty.';
    } else {
        $validTags = ['academic','social','administrative','urgent','event','announcement'];
        if (!in_array($tag, $validTags)) $tag = 'academic';
        DB::insert("INSERT INTO posts (user_id,space_id,content,tag) VALUES (?,?,?,?)", [$user['id'],$spaceId,$content,$tag]);
        redirect('pages/feed.php');
    }
}
?>
<div style="max-width:640px;margin:0 auto">
  <div class="page-header" style="margin-bottom:20px">
    <h2>New Post</h2>
    <a href="<?= SITE_URL ?>/pages/feed.php" class="btn btn-ghost btn-sm">Cancel</a>
  </div>

  <?php if ($error): ?>
    <div style="background:rgba(196,43,43,.08);border:1px solid rgba(196,43,43,.2);border-radius:8px;padding:12px 14px;font-size:.85rem;color:#C42B2B;margin-bottom:16px"><?= sanitize($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap:11px">
        <div class="avatar avatar-sm" style="background:<?= $avatarBg ?>"><?= $initials ?></div>
        <div>
          <div style="font-weight:700;font-size:.9rem"><?= sanitize($user['full_name']) ?></div>
          <div style="font-size:.77rem;color:var(--gray-400)"><?= ucfirst($user['role']) ?></div>
        </div>
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div class="card-body">
        <div class="form-group">
          <textarea name="content" rows="6" class="form-control" placeholder="What is on your mind? Share an update, ask a question, or make an announcement..." required style="resize:vertical;border-radius:8px;font-size:.95rem"><?= sanitize($_POST['content'] ?? '') ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label>Category</label>
            <select name="tag" class="form-control">
              <option value="academic">Academic</option>
              <option value="social">Social</option>
              <option value="administrative">Administrative</option>
              <option value="urgent">Urgent</option>
              <option value="event">Event</option>
              <?php if (in_array($user['role'],['admin','staff'])): ?>
                <option value="announcement">Announcement</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Post to Space (optional)</label>
            <select name="space_id" class="form-control">
              <option value="">Campus-wide (no specific space)</option>
              <?php foreach ($mySpacesForPost as $sp): ?>
                <option value="<?= $sp['id'] ?>"><?= sanitize($sp['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer" style="display:flex;justify-content:flex-end;gap:10px">
        <a href="<?= SITE_URL ?>/pages/feed.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9"/></svg>
          Publish Post
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
