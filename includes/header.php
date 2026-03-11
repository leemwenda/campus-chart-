<?php

require_once __DIR__ . '/../config/auth.php';
$user = requireLogin();
$notifCount = unreadNotifCount($user['id']);
$msgCount   = unreadMessageCount($user['id']);
$initials   = avatarInitials($user['full_name']);
$avatarBg   = avatarColor($user['id']);
$csrf       = csrfToken();

// Fetch user's joined spaces for sidebar
$mySpaces = DB::rows(
    "SELECT s.id, s.name, s.type FROM spaces s
     JOIN space_members sm ON sm.space_id = s.id
     WHERE sm.user_id = ? AND s.is_active = 1 ORDER BY s.name LIMIT 8",
    [$user['id']]
);

$spaceTypeColors = [
    'academic'       => '#003087',
    'club'           => '#0A7C59',
    'social'         => '#0A7C59',
    'administrative' => '#8B6914',
    'co-curricular'  => '#4527A0',
    'professional'   => '#C42B2B',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- ⚡ Anti-flash: apply saved theme BEFORE page renders -->
  <script>
    (function(){
      var t = localStorage.getItem('kcachart_theme') || 'light';
      document.documentElement.setAttribute('data-theme', t);
      // Prevent white flash by setting bg immediately
      if (t === 'dark') {
        document.documentElement.style.background = '#0f1117';
      }
    })();
  </script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($pageTitle ?? 'Home') ?> — KCA Chat</title>
  <meta name="description" content="KCA University Campus Community Platform">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <meta name="csrf-token" content="<?= $csrf ?>">
</head>
<body>
<input type="hidden" name="csrf_token" id="csrf_token" value="<?= $csrf ?>">


<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>


<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo-mark">
      <span class="logo-fallback">KCA</span>
    </div>
    <div class="sidebar-brand-text">
      <div class="name">KCA Chat</div>
      <div class="tagline">KCA University</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>

    <a href="<?= SITE_URL ?>/pages/feed.php" class="nav-link <?= ($activeNav==='feed')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      <span class="link-text">Home Feed</span>
    </a>

    <a href="<?= SITE_URL ?>/pages/spaces.php" class="nav-link <?= ($activeNav==='spaces')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
      <span class="link-text">Spaces</span>
    </a>

    <a href="<?= SITE_URL ?>/pages/events.php" class="nav-link <?= ($activeNav==='events')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span class="link-text">Events</span>
    </a>

    <a href="<?= SITE_URL ?>/pages/messages.php" class="nav-link <?= ($activeNav==='messages')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span class="link-text">Messages</span>
      <?php if ($msgCount > 0): ?>
        <span class="nav-badge"><?= $msgCount ?></span>
      <?php endif; ?>
    </a>

    <a href="<?= SITE_URL ?>/pages/members.php" class="nav-link <?= ($activeNav==='members')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span class="link-text">Members</span>
    </a>

    <a href="<?= SITE_URL ?>/pages/notifications.php" class="nav-link <?= ($activeNav==='notifications')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span class="link-text">Notifications</span>
      <?php if ($notifCount > 0): ?>
        <span class="nav-badge"><?= $notifCount ?></span>
      <?php endif; ?>
    </a>

    <a href="<?= SITE_URL ?>/pages/settings.php" class="nav-link <?= ($activeNav==='settings')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span class="link-text">Settings</span>
    </a>

    <?php if ($user['role'] === 'admin' || $user['role'] === 'staff'): ?>
    <a href="<?= SITE_URL ?>/admin/analytics.php" class="nav-link <?= ($activeNav==='analytics')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span class="link-text">Analytics</span>
    </a>
    <?php endif; ?>

    <?php if ($user['role'] === 'admin'): ?>
    <a href="<?= SITE_URL ?>/admin/index.php" class="nav-link <?= ($activeNav==='admin')?'active':'' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span class="link-text">Admin Panel</span>
    </a>
    <?php endif; ?>

    <?php if (!empty($mySpaces)): ?>
    <div class="nav-section" style="margin-top:12px">My Spaces</div>
    <?php foreach ($mySpaces as $sp):
      $spColor = $spaceTypeColors[$sp['type']] ?? '#003087'; ?>
    <a href="<?= SITE_URL ?>/pages/space_view.php?id=<?= $sp['id'] ?>" class="nav-link space-link <?= ($activeNav==='space-'.$sp['id'])?'active':'' ?>">
      <span class="sidebar-space-dot" style="background:<?= $spColor ?>"></span>
      <span class="link-text"><?= sanitize($sp['name']) ?></span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <a href="<?= SITE_URL ?>/pages/profile.php" class="sidebar-user">
    <div class="avatar avatar-sm" style="background:<?= $avatarBg ?>">
      <?php if ($user['avatar']): ?>
        <img src="<?= UPLOAD_URL . sanitize($user['avatar']) ?>" alt="">
      <?php else: ?>
        <?= $initials ?>
      <?php endif; ?>
    </div>
    <div>
      <div class="user-name"><?= sanitize($user['full_name']) ?></div>
      <div class="user-role"><?= ucfirst($user['role']) ?></div>
    </div>
  </a>
  <a href="<?= SITE_URL ?>/pages/logout.php" class="sidebar-logout" title="Log out" onclick="return confirm('Log out?')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Log out
  </a>
</aside>


<header class="topbar">
  <div class="topbar-kca-stripe"></div>

  <!-- Hamburger for mobile -->
  <button class="hamburger-btn" id="sidebar-toggle" onclick="toggleSidebar()" title="Menu" aria-label="Open menu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>

  <div class="topbar-title">
    <h1><?= sanitize($pageTitle ?? 'KCA Chat') ?></h1>
    <p><?= sanitize($pageSubtitle ?? 'KCA University Campus Community') ?></p>
  </div>

  <div class="search-wrap">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
    <input type="text" placeholder="Search campus..." id="global-search" autocomplete="off">
  </div>

  <div class="topbar-actions">
    <!-- Dark/Light mode toggle -->
    <button class="theme-toggle" id="theme-toggle-btn" onclick="toggleTheme()" title="Toggle dark/light mode">
      <svg id="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg id="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>

    <button class="icon-btn" data-toggle-notif onclick="toggleNotifPanel()" title="Notifications">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if ($notifCount > 0): ?>
        <span class="badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
      <?php endif; ?>
    </button>

    <a href="<?= SITE_URL ?>/pages/post_create.php" class="icon-btn" title="Create post">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </a>

    <a href="<?= SITE_URL ?>/pages/profile.php" class="avatar-btn" style="background:<?= $avatarBg ?>">
      <?php if ($user['avatar']): ?>
        <img src="<?= UPLOAD_URL . sanitize($user['avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
      <?php else: ?>
        <?= $initials ?>
      <?php endif; ?>
    </a>
  </div>
</header>


<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>


<nav class="bottom-nav" role="navigation" aria-label="Mobile navigation">
  <a href="<?= SITE_URL ?>/pages/feed.php" class="bottom-nav-item <?= ($activeNav==='feed')?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    Feed
  </a>
  <a href="<?= SITE_URL ?>/pages/spaces.php" class="bottom-nav-item <?= ($activeNav==='spaces')?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    Spaces
  </a>
  <a href="<?= SITE_URL ?>/pages/events.php" class="bottom-nav-item <?= ($activeNav==='events')?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    Events
  </a>
  <a href="<?= SITE_URL ?>/pages/messages.php" class="bottom-nav-item <?= ($activeNav==='messages')?'active':'' ?>">
    <?php if ($msgCount > 0): ?>
      <span class="bottom-nav-badge"><?= min($msgCount,9) ?></span>
    <?php endif; ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    Messages
  </a>
  <a href="<?= SITE_URL ?>/pages/notifications.php" class="bottom-nav-item <?= ($activeNav==='notifications')?'active':'' ?>">
    <?php if ($notifCount > 0): ?>
      <span class="bottom-nav-badge"><?= min($notifCount,9) ?></span>
    <?php endif; ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    Alerts
  </a>
</nav>

<!--Notification panel (hidden by default) -->
<div class="notif-panel" id="notif-panel">
  <div class="notif-panel-header">
    <h3>Notifications</h3>
    <button class="notif-mark-all" onclick="markAllNotifRead()">Mark all read</button>
  </div>
  <div class="notif-list" id="notif-list-body">
    <?php
    $notifs = DB::rows(
        "SELECT n.*, u.full_name as actor_name FROM notifications n
         LEFT JOIN users u ON u.id = n.actor_id
         WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 20",
        [$user['id']]
    );
    if (empty($notifs)): ?>
      <div class="empty-state" style="padding:32px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <p>No notifications yet</p>
      </div>
    <?php else:
      $notifIcons = [
        'post_like'     => ['#FDF6E3', '#8B6914'],
        'comment'       => ['rgba(0,48,135,.08)', '#003087'],
        'new_post'      => ['rgba(0,48,135,.08)', '#003087'],
        'event_reminder'=> ['rgba(74,39,160,.1)', '#4527A0'],
        'space_invite'  => ['rgba(10,124,89,.1)', '#0A7C59'],
        'message'       => ['rgba(0,84,166,.1)', '#0054A6'],
        'announcement'  => ['rgba(196,43,43,.1)', '#C42B2B'],
        'follow'         => ['rgba(0,48,135,.08)', '#003087'],
      ];
      foreach ($notifs as $n):
        [$bg, $color] = $notifIcons[$n['type']] ?? ['var(--gray-100)', 'var(--gray-500)'];
    ?>
    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" onclick="markNotifRead(<?= $n['id'] ?>, this)">
      <div class="notif-icon" style="background:<?= $bg ?>">
        <?php if ($n['type'] === 'post_like'): ?>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <?php elseif ($n['type'] === 'comment'): ?>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <?php elseif ($n['type'] === 'event_reminder'): ?>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?php elseif ($n['type'] === 'message'): ?>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <?php elseif ($n['type'] === 'follow'): ?>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        <?php else: ?>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php endif; ?>
      </div>
      <div class="notif-text">
        <div class="main"><?= sanitize($n['message']) ?></div>
        <div class="time"><?= timeAgo($n['created_at']) ?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ════════ MAIN CONTENT WRAPPER ════════ -->
<div class="app-body">
