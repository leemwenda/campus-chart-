<?php
require_once __DIR__ . '/../config/auth.php';
$pageTitle    = 'Home Feed';
$pageSubtitle = 'What is happening on campus';
$activeNav    = 'feed';
require_once __DIR__ . '/../includes/header.php';

// Active feed banners (injected between posts)
$feedBanners = [];
try {
    $feedBanners = DB::rows(
        "SELECT * FROM banners WHERE is_active=1 AND placement='feed'
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY RAND() LIMIT 2"
    );
} catch(Exception $e) { $feedBanners = []; }
$bannerInsertAfter = 4; // insert first banner after 4th post
$bannerIdx = 0;

// Fetch posts (from spaces user is a member of + own posts)
$posts = DB::rows(
    "SELECT p.*, u.full_name, u.role as user_role, u.id as author_id, u.avatar,
            s.name as space_name, s.id as space_id,
            (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = p.id AND pr.reaction='like') as like_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count,
            (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = p.id AND pr.user_id = ? AND pr.reaction='like') as user_liked
     FROM posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN spaces s ON s.id = p.space_id
     WHERE p.space_id IS NULL
        OR p.space_id IN (SELECT space_id FROM space_members WHERE user_id = ?)
     ORDER BY p.is_pinned DESC, p.created_at DESC
     LIMIT 30",
    [$user['id'], $user['id']]
);

// Filter tag from URL
$filterTag = $_GET['tag'] ?? 'all';
?>

<div class="content-grid">
  <!-- ── LEFT: FEED ── -->
  <div>

    <!-- Compose -->
    <div class="card compose-card">
      <div class="card-body">
        <div class="compose-top">
          <div class="avatar avatar-sm" style="background:<?= $avatarBg ?>">
            <?php if ($user['avatar']): ?><img src="<?= UPLOAD_URL . sanitize($user['avatar']) ?>" alt=""><?php else: ?><?= $initials ?><?php endif; ?>
          </div>
          <a href="<?= SITE_URL ?>/pages/post_create.php" class="compose-fake-input">
            What is on your mind, <?= sanitize(explode(' ', $user['full_name'])[0]) ?>?
          </a>
        </div>
        <div class="compose-actions">
          <a href="<?= SITE_URL ?>/pages/post_create.php?type=photo" class="compose-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
            Photo
          </a>
          <a href="<?= SITE_URL ?>/pages/post_create.php?type=file" class="compose-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            Attachment
          </a>
          <a href="<?= SITE_URL ?>/pages/post_create.php?type=event" class="compose-action-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Event
          </a>
          <a href="<?= SITE_URL ?>/pages/post_create.php" class="compose-action-btn primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Post
          </a>
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar" id="feed-filter-bar">
      <a href="?tag=all"       class="chip <?= $filterTag==='all'?'active':'' ?>" data-filter="all">All Posts</a>
      <a href="?tag=academic"  class="chip <?= $filterTag==='academic'?'active':'' ?>" data-filter="academic">Academic</a>
      <a href="?tag=social"    class="chip <?= $filterTag==='social'?'active':'' ?>" data-filter="social">Social</a>
      <a href="?tag=administrative" class="chip <?= $filterTag==='administrative'?'active':'' ?>" data-filter="administrative">Administrative</a>
      <a href="?tag=urgent"    class="chip <?= $filterTag==='urgent'?'active':'' ?>" data-filter="urgent">Urgent</a>
    </div>

    <!-- Posts -->
    <?php
    $filteredPosts = $filterTag === 'all' ? $posts : array_filter($posts, fn($p) => $p['tag'] === $filterTag);
    if (empty($filteredPosts)): ?>
      <div class="card">
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
          <p>No posts yet. Be the first to share something!</p>
        </div>
      </div>
    <?php else:
    $postLoopCount = 0;
    foreach ($filteredPosts as $post):
      $pInitials = avatarInitials($post['full_name']);
      $pColor    = avatarColor($post['author_id']);
      $postLoopCount++;
    ?>
    <div class="card post-card" data-post-id="<?= $post['id'] ?>">
      <div class="post-header">
        <div class="avatar avatar-sm" style="background:<?= $pColor ?>">
          <?php if ($post['avatar']): ?><img src="<?= UPLOAD_URL . sanitize($post['avatar']) ?>" alt=""><?php else: ?><?= $pInitials ?><?php endif; ?>
        </div>
        <div class="post-meta">
          <div class="post-author">
            <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= $post['author_id'] ?>"><?= sanitize($post['full_name']) ?></a>
          </div>
          <div class="post-sub">
            <?php if ($post['space_name']): ?>
              in <a href="<?= SITE_URL ?>/pages/space.php?id=<?= $post['space_id'] ?>"><?= sanitize($post['space_name']) ?></a> &middot;
            <?php endif; ?>
            <?= timeAgo($post['created_at']) ?>
          </div>
        </div>
        <span class="post-tag tag-<?= sanitize($post['tag']) ?>"><?= ucfirst(sanitize($post['tag'])) ?></span>
        <?php if ($post['is_pinned']): ?>
          <span style="font-size:.72rem;color:var(--kca-gold);font-weight:700;margin-left:4px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
            Pinned
          </span>
        <?php endif; ?>
      </div>

      <div class="post-body">
        <?= nl2br(sanitize($post['content'])) ?>
      </div>

      <div class="post-footer">
        <button class="react-btn <?= $post['user_liked'] ? 'liked' : '' ?>"
                onclick="toggleReaction(<?= $post['id'] ?>, 'like', this)">
          <svg viewBox="0 0 24 24" fill="<?= $post['user_liked'] ? 'var(--kca-navy)' : 'none' ?>" stroke="currentColor" stroke-width="1.8">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
          <span class="react-count"><?= $post['like_count'] ?></span> Likes
        </button>

        <button class="react-btn" onclick="toggleComments(<?= $post['id'] ?>)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span class="react-count"><?= $post['comment_count'] ?></span> Comments
        </button>

        <div class="post-spacer"></div>

        <?php if ($post['author_id'] === $user['id'] || in_array($user['role'], ['admin','staff'])): ?>
          <a href="<?= SITE_URL ?>/pages/post_edit.php?id=<?= $post['id'] ?>" style="font-size:.75rem;color:var(--gray-400);text-decoration:none;margin-right:8px">Edit</a>
        <?php endif; ?>
        <button class="comment-toggle" onclick="toggleComments(<?= $post['id'] ?>)"><?= $post['comment_count'] ?> comments</button>
      </div>

      <!-- Comments Section (hidden by default) -->
      <div class="comments-section" id="comments-<?= $post['id'] ?>" style="display:none">
        <div class="comments-list">
          <!-- Loaded via JS on toggle -->
        </div>
        <form class="comment-form" onsubmit="submitComment(this, <?= $post['id'] ?>); return false;">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <div class="avatar avatar-sm" style="background:<?= $avatarBg ?>; flex-shrink:0">
            <?php if ($user['avatar']): ?><img src="<?= UPLOAD_URL . sanitize($user['avatar']) ?>" alt=""><?php else: ?><?= $initials ?><?php endif; ?>
          </div>
          <input type="text" class="comment-input" placeholder="Write a comment..." autocomplete="off">
          <button type="submit" class="comment-submit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9"/></svg>
          </button>
        </form>
      </div>
    </div>
    <?php
      // Inject banner after every 4th post
      if (!empty($feedBanners) && $postLoopCount % 4 === 0 && isset($feedBanners[intdiv($postLoopCount,4)-1])):
        $bn = $feedBanners[intdiv($postLoopCount,4)-1];
        DB::query("UPDATE banners SET views = views + 1 WHERE id = ?", [$bn['id']]);
    ?>
    <div class="card" style="overflow:hidden;border:1.5px solid rgba(201,168,76,.4);border-radius:14px;margin-bottom:16px">
      <div style="background:linear-gradient(135deg,rgba(201,168,76,.07),rgba(0,48,135,.04));padding:7px 14px;border-bottom:1px solid rgba(201,168,76,.15);display:flex;align-items:center;gap:6px">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--kca-gold)" stroke-width="2" width="11" height="11"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
        <span style="font-size:.65rem;font-weight:700;color:var(--kca-gold);letter-spacing:.08em;text-transform:uppercase">Sponsored</span>
      </div>
      <img src="<?= UPLOAD_URL . sanitize($bn['image']) ?>" alt="<?= sanitize($bn['title']) ?>"
           style="width:100%;max-height:200px;object-fit:cover;display:block">
      <div style="padding:14px 16px">
        <div style="font-weight:700;font-size:.92rem;color:var(--text-primary);margin-bottom:4px"><?= sanitize($bn['title']) ?></div>
        <?php if ($bn['description']): ?>
          <div style="font-size:.81rem;color:var(--text-muted);margin-bottom:10px;line-height:1.5"><?= sanitize($bn['description']) ?></div>
        <?php endif; ?>
        <?php if ($bn['link_url']): ?>
          <a href="<?= sanitize($bn['link_url']) ?>" target="_blank" rel="noopener"
             class="btn btn-outline btn-sm" style="border-color:var(--kca-gold);color:var(--kca-gold)">
            <?= sanitize($bn['link_label'] ?: 'Learn More') ?> →
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endforeach; endif; ?>
  </div>

  <!-- ── RIGHT SIDEBAR ── -->
  <div class="right-sidebar">
    <!-- Upcoming Events -->
    <div class="card widget">
      <div class="card-header">
        <h3>Upcoming Events</h3>
        <a href="<?= SITE_URL ?>/pages/events.php" class="card-action">See all</a>
      </div>
      <div class="card-body" style="padding-top:8px;padding-bottom:8px">
        <?php
        $upcomingEvents = DB::rows(
            "SELECT e.*, (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id=e.id AND r.status='going') as attendee_count
             FROM events e WHERE e.event_date >= CURDATE() AND e.is_active=1
             ORDER BY e.event_date ASC LIMIT 4"
        );
        foreach ($upcomingEvents as $ev): ?>
        <a href="<?= SITE_URL ?>/pages/events.php#event-<?= $ev['id'] ?>" class="widget-item" style="text-decoration:none">
          <div class="event-date-badge">
            <div class="day"><?= date('j', strtotime($ev['event_date'])) ?></div>
            <div class="mon"><?= date('M', strtotime($ev['event_date'])) ?></div>
          </div>
          <div>
            <div class="item-title"><?= sanitize($ev['title']) ?></div>
            <div class="item-meta">
              <?= date('g:i A', strtotime($ev['start_time'])) ?> &middot; <?= sanitize($ev['location'] ?? 'TBD') ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($upcomingEvents)): ?><p class="text-muted text-sm" style="padding:8px 0">No upcoming events</p><?php endif; ?>
      </div>
    </div>

    <!-- Active Spaces -->
    <div class="card widget">
      <div class="card-header">
        <h3>My Spaces</h3>
        <a href="<?= SITE_URL ?>/pages/spaces.php" class="card-action">Browse</a>
      </div>
      <div class="card-body" style="padding-top:8px;padding-bottom:8px">
        <?php
        $activeSpaces = DB::rows(
            "SELECT s.*, (SELECT COUNT(*) FROM space_members sm2 WHERE sm2.space_id=s.id) as member_count,
                    (SELECT COUNT(*) FROM posts p WHERE p.space_id=s.id AND p.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)) as new_posts
             FROM spaces s JOIN space_members sm ON sm.space_id=s.id
             WHERE sm.user_id=? AND s.is_active=1 ORDER BY new_posts DESC LIMIT 4",
            [$user['id']]
        );
        $spaceTypeColors2 = ['academic'=>'#003087','social'=>'#0A7C59','administrative'=>'#8B6914','co-curricular'=>'#4527A0'];
        foreach ($activeSpaces as $sp): ?>
        <a href="<?= SITE_URL ?>/pages/space.php?id=<?= $sp['id'] ?>" class="widget-item" style="text-decoration:none">
          <div class="item-icon" style="background:rgba(0,48,135,.08)">
            <svg viewBox="0 0 24 24" fill="none" stroke="var(--kca-navy)" stroke-width="1.8" width="17" height="17"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          </div>
          <div>
            <div class="item-title"><?= sanitize($sp['name']) ?></div>
            <div class="item-meta"><?= $sp['member_count'] ?> members<?= $sp['new_posts'] > 0 ? ' &middot; ' . $sp['new_posts'] . ' new' : '' ?></div>
          </div>
        </a>
        <?php endforeach;
        if (empty($activeSpaces)): ?><p class="text-muted text-sm" style="padding:8px 0">No spaces joined yet. <a href="<?= SITE_URL ?>/pages/spaces.php">Browse spaces</a></p><?php endif; ?>
      </div>
    </div>

    <!-- Online Members -->
    <div class="card widget">
      <div class="card-header"><h3>Online Now</h3></div>
      <div class="card-body" style="padding-top:8px;padding-bottom:8px">
        <?php
        $onlineUsers = DB::rows(
            "SELECT id, full_name, role, avatar FROM users
             WHERE is_online=1 AND id != ? AND is_active=1 LIMIT 6",
            [$user['id']]
        );
        foreach ($onlineUsers as $ou): ?>
        <div class="online-user">
          <div class="avatar avatar-sm" style="background:<?= avatarColor($ou['id']) ?>">
            <?php if ($ou['avatar']): ?><img src="<?= UPLOAD_URL . sanitize($ou['avatar']) ?>" alt=""><?php else: ?><?= avatarInitials($ou['full_name']) ?><?php endif; ?>
          </div>
          <div style="flex:1">
            <div style="font-size:.83rem;font-weight:600"><?= sanitize($ou['full_name']) ?></div>
            <div style="font-size:.73rem;color:var(--gray-400)"><?= ucfirst($ou['role']) ?></div>
          </div>
          <span class="status-dot online"></span>
        </div>
        <?php endforeach;
        if (empty($onlineUsers)): ?><p class="text-muted text-sm" style="padding:8px 0">No one else online right now</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
