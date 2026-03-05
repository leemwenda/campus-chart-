<?php
require_once __DIR__ . '/../config/auth.php';
$user = requireRole('admin', 'staff');
$pageTitle    = 'Analytics';
$pageSubtitle = 'Platform insights and statistics';
$activeNav    = 'analytics';
require_once __DIR__ . '/../includes/header.php';

// Stats
$totalUsers   = DB::count("SELECT COUNT(*) FROM users WHERE is_active=1");
$totalPosts   = DB::count("SELECT COUNT(*) FROM posts");
$totalEvents  = DB::count("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()");
$totalRsvps   = DB::count("SELECT COUNT(*) FROM event_rsvps");
$totalSpaces  = DB::count("SELECT COUNT(*) FROM spaces WHERE is_active=1");
$totalMsgs    = DB::count("SELECT COUNT(*) FROM messages");
$onlineNow    = DB::count("SELECT COUNT(*) FROM users WHERE is_online=1");
$postsToday   = DB::count("SELECT COUNT(*) FROM posts WHERE DATE(created_at)=CURDATE()");

// Daily active (last 14 days)
$dailyData = DB::rows(
    "SELECT DATE(created_at) as day, COUNT(DISTINCT user_id) as cnt
     FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at) ORDER BY day ASC"
);

// Top spaces
$topSpaces = DB::rows(
    "SELECT s.name, COUNT(p.id) as post_count, COUNT(DISTINCT sm.user_id) as member_count
     FROM spaces s LEFT JOIN posts p ON p.space_id=s.id LEFT JOIN space_members sm ON sm.space_id=s.id
     WHERE s.is_active=1 GROUP BY s.id ORDER BY post_count DESC LIMIT 5"
);
$maxPosts = max(array_column($topSpaces, 'post_count') ?: [1]);

// Post breakdown by tag
$tagBreakdown = DB::rows("SELECT tag, COUNT(*) as cnt FROM posts GROUP BY tag ORDER BY cnt DESC");
$totalTagPosts = array_sum(array_column($tagBreakdown, 'cnt')) ?: 1;

// User role breakdown
$roleBreakdown = DB::rows("SELECT role, COUNT(*) as cnt FROM users WHERE is_active=1 GROUP BY role");
?>

<div class="stats-row">
  <?php $stats = [
    ['Total Users','people',        $totalUsers, 'stat-card card gold-border-top'],
    ['Active Posts','file-text',    $totalPosts, 'stat-card card'],
    ['Upcoming Events','calendar',  $totalEvents,'stat-card card'],
    ['Total Spaces','globe',        $totalSpaces,'stat-card card'],
    ['Total RSVPs','check-circle',  $totalRsvps, 'stat-card card'],
    ['Messages Sent','message-circle',$totalMsgs,'stat-card card'],
    ['Online Now','radio',          $onlineNow,  'stat-card card'],
    ['Posts Today','edit',          $postsToday, 'stat-card card'],
  ];
  $svgs = [
    'people'         => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'file-text'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    'calendar'       => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'globe'          => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
    'check-circle'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/>',
    'message-circle' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'radio'          => '<circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/>',
    'edit'           => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
  ];
  foreach ($stats as [$label, $icon, $value, $cls]): ?>
  <div class="<?= $cls ?>">
    <div class="stat-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $svgs[$icon] ?></svg>
    </div>
    <div class="stat-value"><?= number_format($value) ?></div>
    <div class="stat-label"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">
  <!-- Bar chart: daily posts last 14 days -->
  <div class="card chart-card">
    <div class="card-header"><h3>Daily Posts — Last 14 Days</h3></div>
    <div class="chart-wrap">
      <div class="bar-chart" id="bar-chart">
        <?php
        // Fill in missing days
        $days = [];
        for ($i=13; $i>=0; $i--) { $days[date('Y-m-d', strtotime("-$i days"))] = 0; }
        foreach ($dailyData as $d) { $days[$d['day']] = (int)$d['cnt']; }
        $maxVal = max(array_values($days) ?: [1]);
        foreach ($days as $date => $cnt):
          $pct = $maxVal > 0 ? round($cnt / $maxVal * 100) : 0;
          $isToday = $date === date('Y-m-d');
        ?>
        <div class="bar-col">
          <div class="bar-fill <?= $isToday?'gold':'' ?>" style="height:0%" data-target="<?= $pct ?>" title="<?= date('M j', strtotime($date)) ?>: <?= $cnt ?> posts"></div>
          <span class="bar-lbl"><?= date('j', strtotime($date)) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Post category breakdown -->
  <div class="card chart-card">
    <div class="card-header"><h3>Posts by Category</h3></div>
    <div class="card-body">
      <?php
      $tagColors = ['academic'=>'#003087','social'=>'#0A7C59','administrative'=>'#8B6914','urgent'=>'#C42B2B','event'=>'#4527A0','announcement'=>'#0054A6'];
      foreach ($tagBreakdown as $tb):
        $pct = round($tb['cnt']/$totalTagPosts*100);
        $color = $tagColors[$tb['tag']] ?? '#003087';
      ?>
      <div class="progress-row">
        <div class="progress-label">
          <span style="display:flex;align-items:center;gap:6px">
            <span style="width:10px;height:10px;border-radius:2px;background:<?= $color ?>;display:inline-block;flex-shrink:0"></span>
            <?= ucfirst($tb['tag']) ?>
          </span>
          <span><?= $tb['cnt'] ?> (<?= $pct ?>%)</span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" style="width:0%;background:<?= $color ?>" data-target="<?= $pct ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <!-- Top spaces -->
  <div class="card chart-card">
    <div class="card-header"><h3>Top Spaces by Posts</h3></div>
    <div class="card-body">
      <?php foreach ($topSpaces as $sp):
        $pct = $maxPosts > 0 ? round($sp['post_count']/$maxPosts*100) : 0;
      ?>
      <div class="progress-row">
        <div class="progress-label">
          <span><?= sanitize($sp['name']) ?></span>
          <span><?= $sp['post_count'] ?> posts &middot; <?= $sp['member_count'] ?> members</span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" style="width:0%" data-target="<?= $pct ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- User role breakdown -->
  <div class="card chart-card">
    <div class="card-header"><h3>Users by Role</h3></div>
    <div class="card-body">
      <?php
      $roleColors = ['student'=>'#003087','staff'=>'#8B6914','admin'=>'#C42B2B'];
      $totalRoleUsers = array_sum(array_column($roleBreakdown, 'cnt')) ?: 1;
      foreach ($roleBreakdown as $rb):
        $pct = round($rb['cnt']/$totalRoleUsers*100);
        $color = $roleColors[$rb['role']] ?? '#003087';
      ?>
      <div class="progress-row">
        <div class="progress-label">
          <span style="display:flex;align-items:center;gap:6px">
            <span style="width:10px;height:10px;border-radius:2px;background:<?= $color ?>;display:inline-block"></span>
            <?= ucfirst($rb['role']) ?>
          </span>
          <span><?= $rb['cnt'] ?> (<?= $pct ?>%)</span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" style="width:0%;background:<?= $color ?>" data-target="<?= $pct ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="divider"></div>
      <div style="text-align:center;font-size:.82rem;color:var(--gray-400)">Total: <?= $totalUsers ?> active users</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
