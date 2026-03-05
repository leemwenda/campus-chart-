<?php
// pages/notifications.php
$pageTitle    = 'Notifications';
$pageSubtitle = 'Your activity & alerts';
$activeNav    = 'notifications';
require_once __DIR__ . '/../includes/header.php';

// Mark all as read if requested
if (isset($_GET['mark_all'])) {
    DB::query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$user['id']]);
    header('Location: notifications.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$validFilters = ['all','post_like','comment','message','event_reminder','new_post','announcement','space_invite'];
if (!in_array($filter, $validFilters)) $filter = 'all';

$whereExtra = $filter !== 'all' ? " AND n.type = '$filter'" : '';

$notifRows = DB::rows(
    "SELECT n.*, u.full_name AS actor_name, u.id AS actor_id_val
     FROM notifications n
     LEFT JOIN users u ON u.id = n.actor_id
     WHERE n.user_id = ? $whereExtra
     ORDER BY n.created_at DESC LIMIT 60",
    [$user['id']]
);

$totalUnread = DB::count("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$user['id']]);

// Icon/color map
$iconMap = [
    'post_like'      => ['#FDF6E3','#8B6914', '<path fill="#8B6914" stroke="none" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>'],
    'comment'        => ['rgba(0,48,135,.08)','#003087', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
    'new_post'       => ['rgba(0,48,135,.08)','#003087', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'],
    'event_reminder' => ['rgba(74,39,160,.1)','#4527A0', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
    'space_invite'   => ['rgba(10,124,89,.1)','#0A7C59', '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'],
    'message'        => ['rgba(0,84,166,.1)','#0054A6', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
    'announcement'   => ['rgba(196,43,43,.1)','#C42B2B', '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'],
    'follow'         => ['rgba(0,48,135,.08)','#003087', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
];

$filterLabels = [
    'all'            => 'All',
    'post_like'      => 'Likes',
    'comment'        => 'Comments',
    'message'        => 'Messages',
    'event_reminder' => 'Events',
    'new_post'       => 'Posts',
    'announcement'   => 'Announcements',
];
?>

<div class="content-grid wide">
  <div>
    <!-- Header bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
      <div>
        <h2 style="font-family:var(--font-serif);font-size:1.2rem;color:var(--kca-navy)">Notifications</h2>
        <?php if ($totalUnread > 0): ?>
          <p style="font-size:.82rem;color:var(--gray-400);margin-top:3px"><?= $totalUnread ?> unread</p>
        <?php endif; ?>
      </div>
      <?php if ($totalUnread > 0): ?>
        <a href="notifications.php?mark_all=1" class="btn btn-ghost btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20,6 9,17 4,12"/></svg>
          Mark all read
        </a>
      <?php endif; ?>
    </div>

    <!-- Filter chips -->
    <div class="filter-bar" style="margin-bottom:20px">
      <?php foreach ($filterLabels as $key => $label): ?>
        <a href="notifications.php?filter=<?= $key ?>"
           class="chip <?= $filter===$key?'active':'' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Notifications list -->
    <div class="card" style="overflow:hidden">
      <?php if (empty($notifRows)): ?>
        <div class="notif-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            <line x1="2" y1="2" x2="22" y2="22" stroke-width="1.5"/>
          </svg>
          <h3>No notifications<?= $filter !== 'all' ? ' in this category' : ' yet' ?></h3>
          <p>Interact with posts, join events, and connect with the KCA community to see your activity here.</p>
          <?php if ($filter !== 'all'): ?>
            <a href="notifications.php" class="btn btn-outline btn-sm" style="margin-top:16px">Show all notifications</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach ($notifRows as $n):
          [$bg, $color, $iconPath] = $iconMap[$n['type']] ?? ['var(--gray-100)','var(--gray-500)','<circle cx="12" cy="12" r="10"/>'];
        ?>
        <div class="notif-page-item <?= $n['is_read'] ? '' : 'unread' ?>" onclick="readAndGo(<?= $n['id'] ?>, this, '<?= sanitize($n['link'] ?? '') ?>')">
          <div class="notif-page-icon" style="background:<?= $bg ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2"><?= $iconPath ?></svg>
          </div>
          <div class="notif-page-text" style="flex:1">
            <div class="main"><?= sanitize($n['message']) ?></div>
            <?php if ($n['actor_name']): ?>
              <div style="font-size:.75rem;color:var(--gray-400);margin-top:2px">
                <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= $n['actor_id_val'] ?>" style="color:var(--kca-navy);font-weight:600;text-decoration:none">
                  <?= sanitize($n['actor_name']) ?>
                </a>
              </div>
            <?php endif; ?>
            <div class="time"><?= timeAgo($n['created_at']) ?></div>
          </div>
          <?php if (!$n['is_read']): ?>
            <div style="width:8px;height:8px;border-radius:50%;background:var(--kca-navy);flex-shrink:0;margin-top:6px"></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$extraJs = "
function readAndGo(id, el, link) {
  el.classList.remove('unread');
  fetch('".SITE_URL."/api/notifications.php?action=mark_read&id=' + id, {
    method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}
  });
  if (link) window.location.href = link;
}
";
require_once __DIR__ . '/../includes/footer.php';
