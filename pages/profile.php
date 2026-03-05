<?php
require_once __DIR__ . '/../config/auth.php';
$activeNav = 'members';
$viewId = (int)($_GET['id'] ?? $user['id']);
$profile = DB::row("SELECT * FROM users WHERE id=? AND is_active=1", [$viewId]);
if (!$profile) { redirect('pages/members.php'); }

$pageTitle    = sanitize($profile['full_name']);
$pageSubtitle = 'Profile';
require_once __DIR__ . '/../includes/header.php';

$isOwn     = $profile['id'] === $user['id'];
$pInit     = avatarInitials($profile['full_name']);
$pColor    = avatarColor($profile['id']);
$isFollow  = (bool)DB::row("SELECT id FROM follows WHERE follower_id=? AND following_id=?", [$user['id'], $profile['id']]);
$followers = DB::count("SELECT COUNT(*) FROM follows WHERE following_id=?", [$profile['id']]);
$following = DB::count("SELECT COUNT(*) FROM follows WHERE follower_id=?", [$profile['id']]);
$postCount = DB::count("SELECT COUNT(*) FROM posts WHERE user_id=?", [$profile['id']]);

$profilePosts = DB::rows(
    "SELECT p.*, s.name as space_name,
            (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id=p.id AND pr.reaction='like') as like_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id) as comment_count
     FROM posts p LEFT JOIN spaces s ON s.id=p.space_id
     WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 10",
    [$profile['id']]
);
?>
<div style="max-width:800px;margin:0 auto">
  <!-- Profile header card -->
  <div class="card" style="margin-bottom:20px;overflow:hidden">
    <div style="height:100px;background:linear-gradient(135deg,var(--kca-navy),var(--kca-navy-mid));position:relative">
      <div style="position:absolute;inset:0;background:url('<?= SITE_URL ?>/assets/images/pattern.svg') center/200px repeat;opacity:.06"></div>
      <div class="kca-stripe" style="position:absolute;bottom:0;left:0;right:0;height:3px"></div>
    </div>
    <div class="card-body" style="padding-top:0">
      <div style="display:flex;gap:18px;align-items:flex-end;margin-top:-36px;margin-bottom:16px">
        <div class="avatar avatar-xl" style="background:<?= $pColor ?>;border:4px solid var(--white);box-shadow:var(--shadow)">
          <?php if ($profile['avatar']): ?><img src="<?= UPLOAD_URL . sanitize($profile['avatar']) ?>" alt=""><?php else: ?><?= $pInit ?><?php endif; ?>
        </div>
        <div style="flex:1;padding-bottom:4px">
          <h2 style="font-family:var(--font-serif);font-size:1.3rem;font-weight:700;color:var(--kca-navy)"><?= sanitize($profile['full_name']) ?></h2>
          <p style="font-size:.82rem;color:var(--gray-400)"><?= sanitize($profile['department'] ?? $profile['course'] ?? '') ?></p>
        </div>
        <div style="display:flex;gap:8px;padding-bottom:4px">
          <?php if (!$isOwn): ?>
            <button class="btn btn-<?= $isFollow?'ghost':'primary' ?> btn-sm <?= $isFollow?'following':'' ?>"
                    onclick="toggleFollow(<?= $profile['id'] ?>, this)">
              <?= $isFollow ? 'Following' : '+ Follow' ?>
            </button>
            <button class="btn btn-outline btn-sm" onclick="startConversation(<?= $profile['id'] ?>)">Message</button>
          <?php else: ?>
            <a href="<?= SITE_URL ?>/pages/settings.php" class="btn btn-outline btn-sm">Edit Profile</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($profile['bio']): ?>
        <p style="font-size:.88rem;color:var(--gray-600);margin-bottom:14px;line-height:1.6"><?= sanitize($profile['bio']) ?></p>
      <?php endif; ?>

      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px">
        <span class="role-pill pill-<?= $profile['role'] ?>"><?= ucfirst($profile['role']) ?></span>
        <?php if ($profile['student_id']): ?>
          <span style="font-size:.75rem;color:var(--gray-400);padding:3px 10px;background:var(--gray-100);border-radius:99px"><?= sanitize($profile['student_id']) ?></span>
        <?php endif; ?>
        <?php if ($profile['is_online']): ?>
          <span style="font-size:.75rem;color:var(--success);padding:3px 10px;background:rgba(10,124,89,.1);border-radius:99px;display:flex;align-items:center;gap:4px">
            <span style="width:6px;height:6px;background:var(--success);border-radius:50%;display:inline-block"></span>
            Online
          </span>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:24px;border-top:1px solid var(--gray-100);padding-top:14px">
        <div style="text-align:center">
          <div style="font-family:var(--font-serif);font-weight:700;font-size:1.2rem;color:var(--kca-navy)"><?= $postCount ?></div>
          <div style="font-size:.75rem;color:var(--gray-400)">Posts</div>
        </div>
        <div style="text-align:center">
          <div style="font-family:var(--font-serif);font-weight:700;font-size:1.2rem;color:var(--kca-navy)"><?= $followers ?></div>
          <div style="font-size:.75rem;color:var(--gray-400)">Followers</div>
        </div>
        <div style="text-align:center">
          <div style="font-family:var(--font-serif);font-weight:700;font-size:1.2rem;color:var(--kca-navy)"><?= $following ?></div>
          <div style="font-size:.75rem;color:var(--gray-400)">Following</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Posts -->
  <h3 style="font-family:var(--font-serif);font-size:1rem;font-weight:700;color:var(--kca-navy);margin-bottom:14px">Posts</h3>
  <?php if (empty($profilePosts)): ?>
    <div class="card"><div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
      <p>No posts yet</p>
    </div></div>
  <?php else: foreach ($profilePosts as $p): ?>
  <div class="card post-card" style="margin-bottom:14px">
    <div class="post-header">
      <div class="avatar avatar-sm" style="background:<?= $pColor ?>"><?= $pInit ?></div>
      <div class="post-meta">
        <div class="post-author"><?= sanitize($profile['full_name']) ?></div>
        <div class="post-sub">
          <?php if ($p['space_name']): ?>in <?= sanitize($p['space_name']) ?> &middot; <?php endif; ?>
          <?= timeAgo($p['created_at']) ?>
        </div>
      </div>
      <span class="post-tag tag-<?= $p['tag'] ?>"><?= ucfirst($p['tag']) ?></span>
    </div>
    <div class="post-body"><?= nl2br(sanitize($p['content'])) ?></div>
    <div class="post-footer">
      <span style="font-size:.8rem;color:var(--gray-400);display:flex;align-items:center;gap:5px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <?= $p['like_count'] ?>
      </span>
      <span style="font-size:.8rem;color:var(--gray-400);margin-left:10px;display:flex;align-items:center;gap:5px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <?= $p['comment_count'] ?>
      </span>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
