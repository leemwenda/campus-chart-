<?php
require_once __DIR__ . '/../config/auth.php';
$pageTitle    = 'Events & Academic Calendar';
$pageSubtitle = '2026 KCA University Academic Calendar';
$activeNav    = 'events';
require_once __DIR__ . '/../includes/header.php';

$catFilter    = $_GET['cat']    ?? 'all';
$campusFilter = $_GET['campus'] ?? 'all';
$showPast     = isset($_GET['past']);
$search       = trim($_GET['q'] ?? '');

$params = [$user['id']];
$where  = "e.is_active=1";
$where .= $showPast ? " AND e.event_date < CURDATE()" : " AND e.event_date >= CURDATE()";
if ($catFilter !== 'all')    { $where .= " AND e.category=?";  $params[] = $catFilter; }
if ($campusFilter !== 'all') { $where .= " AND e.campus=?";    $params[] = $campusFilter; }
if ($search)                 { $where .= " AND e.title LIKE ?"; $params[] = "%$search%"; }

$events = DB::rows(
    "SELECT e.*,
            (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id=e.id AND r.status='going') as attendee_count,
            (SELECT COUNT(*) FROM event_rsvps r WHERE r.event_id=e.id AND r.user_id=? AND r.status='going') as user_going,
            s.name as space_name
     FROM events e LEFT JOIN spaces s ON s.id=e.space_id
     WHERE $where
     ORDER BY e.event_date ASC, e.start_time ASC",
    $params
);

// Calendar: all upcoming events for this month's dot markers
$today = new DateTime();
$calYear  = (int)$today->format('Y');
$calMonth = (int)$today->format('n') - 1; // 0-indexed for JS
$allUpcoming = DB::rows("SELECT event_date FROM events WHERE is_active=1 AND event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 31 DAY)", []);
$eventDays = array_unique(array_map(fn($e) => (int)date('j', strtotime($e['event_date'])), $allUpcoming));

$catColors = [
    'exam'           => ['bg'=>'rgba(196,43,43,.08)',    'text'=>'#C42B2B',  'border'=>'#C42B2B',  'label'=>'Exam'],
    'academic'       => ['bg'=>'rgba(0,48,135,.07)',     'text'=>'#003087',  'border'=>'#003087',  'label'=>'Academic'],
    'administrative' => ['bg'=>'rgba(139,105,20,.08)',   'text'=>'#8B6914',  'border'=>'#C9A84C',  'label'=>'Official'],
    'career'         => ['bg'=>'rgba(10,124,89,.08)',    'text'=>'#0A7C59',  'border'=>'#0A7C59',  'label'=>'Career'],
    'club'           => ['bg'=>'rgba(69,39,160,.08)',    'text'=>'#4527A0',  'border'=>'#4527A0',  'label'=>'Club'],
    'social'         => ['bg'=>'rgba(0,84,166,.07)',     'text'=>'#0054A6',  'border'=>'#0054A6',  'label'=>'Social'],
    'deadline'       => ['bg'=>'rgba(196,43,43,.06)',    'text'=>'#C42B2B',  'border'=>'#C42B2B',  'label'=>'Deadline'],
];

// Group events by month
$grouped = [];
foreach ($events as $ev) {
    $month = date('F Y', strtotime($ev['event_date']));
    $grouped[$month][] = $ev;
}
?>

<div class="page-header">
  <div>
    <h2>Campus Events & Calendar</h2>
    <div style="font-size:.78rem;color:var(--gray-400);margin-top:2px">
      2026 KCA University Academic Calendar — <?= count($events) ?> event<?= count($events)!==1?'s':'' ?>
      <?= $catFilter!=='all'?'in selected category':($showPast?'(past)':'upcoming') ?>
    </div>
  </div>
  <div style="display:flex;gap:8px">
    <?php if (in_array($user['role'],['admin','staff'])): ?>
    <a href="<?= SITE_URL ?>/pages/event_create.php" class="btn btn-primary btn-sm">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Event
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Search & campus filter -->
<div style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px">
  <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="text" name="q" value="<?= sanitize($search) ?>"
           placeholder="Search events..." class="form-control" style="flex:1;min-width:160px;padding:8px 12px">
    <select name="campus" class="form-control" style="width:auto;padding:8px 10px;font-size:.82rem">
      <option value="all">All Campuses</option>
      <option value="Town Campus"      <?= $campusFilter==='Town Campus'?'selected':'' ?>>Town Campus (Nairobi)</option>
      <option value="Western Campus"   <?= $campusFilter==='Western Campus'?'selected':'' ?>>Western Campus (Kisumu)</option>
      <option value="Kitengela Campus" <?= $campusFilter==='Kitengela Campus'?'selected':'' ?>>Kitengela Campus</option>
      <option value="Online"           <?= $campusFilter==='Online'?'selected':'' ?>>Online</option>
      <option value="All Campuses"     <?= $campusFilter==='All Campuses'?'selected':'' ?>>All Campuses</option>
    </select>
    <input type="hidden" name="cat" value="<?= sanitize($catFilter) ?>">
    <button type="submit" class="btn btn-outline btn-sm">Search</button>
    <?php if ($search || $campusFilter!=='all'): ?>
    <a href="?cat=<?= urlencode($catFilter) ?>" class="btn btn-ghost btn-sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Category chips -->
<div class="filter-bar">
  <a href="?cat=all"           class="chip <?= $catFilter==='all'&&!$showPast?'active':'' ?>">All</a>
  <a href="?cat=exam"          class="chip <?= $catFilter==='exam'?'active':'' ?>" style="<?= $catFilter==='exam'?'background:#C42B2B;border-color:#C42B2B;color:white':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11" style="vertical-align:middle;margin-right:3px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
    Exams & CATs
  </a>
  <a href="?cat=academic"      class="chip <?= $catFilter==='academic'?'active':'' ?>">Academic</a>
  <a href="?cat=career"        class="chip <?= $catFilter==='career'?'active':'' ?>">Career</a>
  <a href="?cat=club"          class="chip <?= $catFilter==='club'?'active':'' ?>">Clubs</a>
  <a href="?cat=social"        class="chip <?= $catFilter==='social'?'active':'' ?>">Social</a>
  <a href="?cat=administrative" class="chip <?= $catFilter==='administrative'?'active':'' ?>">Official</a>
  <a href="?past=1"            class="chip <?= $showPast?'active':'' ?>">Past Events</a>
</div>

<div class="content-grid">
  <!-- Events list -->
  <div>
    <?php if (empty($events)): ?>
    <div class="card">
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <p>No <?= $showPast?'past':'' ?> events found<?= $catFilter!=='all'?" in this category":'' ?>.</p>
        <a href="?" class="btn btn-outline btn-sm" style="margin-top:12px">View all upcoming events</a>
      </div>
    </div>
    <?php else: ?>

    <?php foreach ($grouped as $monthLabel => $monthEvents): ?>
    <div style="margin-bottom:24px">
      <!-- Month header -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <div style="font-size:.7rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.08em;white-space:nowrap"><?= $monthLabel ?></div>
        <div style="flex:1;height:1px;background:var(--gray-200)"></div>
        <div style="font-size:.7rem;color:var(--gray-400)"><?= count($monthEvents) ?> event<?= count($monthEvents)!==1?'s':'' ?></div>
      </div>

      <?php foreach ($monthEvents as $ev):
        $cc = $catColors[$ev['category']] ?? $catColors['academic'];
        $eventDate = strtotime($ev['event_date']);
        $isPast = $eventDate < strtotime('today');
        $isToday = date('Y-m-d', $eventDate) === date('Y-m-d');
      ?>
      <div class="card event-card" id="event-<?= $ev['id'] ?>"
           style="<?= $isPast?'opacity:.7':'' ?>;border-left:3px solid <?= $cc['border'] ?>;margin-bottom:10px">
        <!-- Date badge -->
        <div class="event-date-badge" style="background:<?= $cc['bg'] ?>;border:1px solid <?= $cc['border'] ?>30">
          <div class="day" style="color:<?= $cc['text'] ?>"><?= date('j', $eventDate) ?></div>
          <div class="mon" style="color:<?= $cc['text'] ?>"><?= date('M', $eventDate) ?></div>
          <?php if ($isToday): ?>
          <div style="font-size:.55rem;font-weight:700;color:var(--white);background:var(--danger);border-radius:99px;padding:1px 5px;margin-top:2px">TODAY</div>
          <?php endif; ?>
        </div>

        <div class="event-info" style="flex:1;min-width:0">
          <div style="display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap;margin-bottom:4px">
            <div class="event-title" style="flex:1"><?= sanitize($ev['title']) ?></div>
            <span style="font-size:.67rem;font-weight:700;color:<?= $cc['text'] ?>;background:<?= $cc['bg'] ?>;border:1px solid <?= $cc['border'] ?>30;padding:2px 8px;border-radius:99px;flex-shrink:0">
              <?= $cc['label'] ?>
            </span>
          </div>

          <div class="event-desc" style="-webkit-line-clamp:2"><?= sanitize($ev['description'] ?? '') ?></div>

          <div class="event-meta">
            <span class="event-meta-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="12" height="12"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
              <?= date('g:i A', strtotime($ev['start_time'])) ?>
              <?= $ev['end_time'] ? ' – ' . date('g:i A', strtotime($ev['end_time'])) : '' ?>
            </span>
            <?php if ($ev['location']): ?>
            <span class="event-meta-item">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="12" height="12"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <?= sanitize($ev['location']) ?>
            </span>
            <?php endif; ?>
            <?php if ($ev['campus'] && $ev['campus'] !== 'All Campuses'): ?>
            <span class="event-meta-item" style="color:var(--kca-gold)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="12" height="12"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
              <?= sanitize($ev['campus']) ?>
            </span>
            <?php elseif ($ev['campus'] === 'All Campuses'): ?>
            <span class="event-meta-item" style="color:var(--kca-gold)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="12" height="12"><circle cx="12" cy="12" r="10"/></svg>
              All Campuses
            </span>
            <?php endif; ?>
          </div>

          <?php if (!$isPast): ?>
          <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
            <button class="rsvp-btn <?= $ev['user_going'] ? 'going' : '' ?>"
                    onclick="toggleRSVP(<?= $ev['id'] ?>, this)">
              <?php if ($ev['user_going']): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><polyline points="20,6 9,17 4,12"/></svg>
              Going
              <?php else: ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              RSVP
              <?php endif; ?>
            </button>
            <span class="attendee-count text-muted text-sm">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="vertical-align:middle"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <span id="attendee-<?= $ev['id'] ?>"><?= $ev['attendee_count'] ?></span> attending
            </span>
          </div>
          <?php else: ?>
          <div style="font-size:.74rem;color:var(--gray-400);margin-top:8px;font-style:italic">
            <?= $ev['attendee_count'] ?> attended
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Right sidebar -->
  <div>
    <!-- Mini calendar -->
    <div class="card widget">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding-bottom:8px">
        <button onclick="calPrev()" style="background:none;border:none;cursor:pointer;color:var(--gray-400);padding:4px;border-radius:4px;line-height:1;transition:color .15s" onmouseover="this.style.color='var(--kca-navy)'" onmouseout="this.style.color='var(--gray-400)'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="15,18 9,12 15,6"/></svg>
        </button>
        <h3 id="cal-month-header" style="font-size:.85rem"><?= date('F Y') ?></h3>
        <button onclick="calNext()" style="background:none;border:none;cursor:pointer;color:var(--gray-400);padding:4px;border-radius:4px;line-height:1;transition:color .15s" onmouseover="this.style.color='var(--kca-navy)'" onmouseout="this.style.color='var(--gray-400)'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="9,18 15,12 9,6"/></svg>
        </button>
      </div>
      <div class="card-body" style="padding-top:0">
        <div id="cal-grid" class="cal-grid"></div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:10px;padding-top:8px;border-top:1px solid var(--gray-100)">
          <div style="display:flex;align-items:center;gap:4px;font-size:.7rem;color:var(--gray-400)">
            <div style="width:5px;height:5px;border-radius:50%;background:var(--kca-gold)"></div> Has event
          </div>
          <div style="display:flex;align-items:center;gap:4px;font-size:.7rem;color:var(--gray-400)">
            <div style="width:14px;height:14px;border-radius:4px;background:var(--kca-navy)"></div> Today
          </div>
        </div>
      </div>
    </div>

    <!-- Trimester timeline -->
    <div class="card widget" style="margin-top:16px">
      <div class="card-header"><h3>Jan–Apr 2026 Trimester</h3></div>
      <div class="card-body" style="padding-top:8px;padding-bottom:8px">
        <?php
        $trimesterMilestones = [
            ['2026-02-23', 'CAT 1 begins',          'exam'],
            ['2026-03-06', '2nd Tech Expo',          'academic'],
            ['2026-03-09', 'CAT 2',                  'exam'],
            ['2026-03-16', 'Coursework marks',       'academic'],
            ['2026-03-23', 'Final Exams begin',      'exam'],
            ['2026-04-17', 'Final Exams end',        'exam'],
        ];
        $todayStr = date('Y-m-d');
        foreach ($trimesterMilestones as [$date, $label, $type]): 
            $isPast = $date < $todayStr;
            $isNext = !$isPast;
            $cc2 = $catColors[$type] ?? $catColors['academic'];
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--gray-100)">
          <div style="width:8px;height:8px;border-radius:50%;background:<?= $isPast?'var(--gray-300)':$cc2['border'] ?>;flex-shrink:0"></div>
          <span style="font-size:.8rem;flex:1;color:<?= $isPast?'var(--gray-400)':'var(--gray-700)' ?>"><?= $label ?></span>
          <span style="font-size:.72rem;color:<?= $isPast?'var(--gray-300)':'var(--gray-500)' ?>;white-space:nowrap"><?= date('M j', strtotime($date)) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- My RSVPs -->
    <div class="card widget" style="margin-top:16px">
      <div class="card-header"><h3>My RSVPs</h3></div>
      <div class="card-body" style="padding-top:8px;padding-bottom:8px">
        <?php
        $myRsvps = DB::rows(
            "SELECT e.title, e.event_date, e.category, er.status FROM event_rsvps er
             JOIN events e ON e.id=er.event_id WHERE er.user_id=? AND e.event_date>=CURDATE()
             ORDER BY e.event_date ASC LIMIT 6",
            [$user['id']]
        );
        foreach ($myRsvps as $r):
            $rc = $catColors[$r['category']] ?? $catColors['academic'];
        ?>
        <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--gray-100)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="<?= $rc['border'] ?>" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>
          <span style="font-size:.8rem;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($r['title']) ?></span>
          <span style="font-size:.7rem;color:var(--gray-400);white-space:nowrap"><?= date('M j', strtotime($r['event_date'])) ?></span>
        </div>
        <?php endforeach;
        if (empty($myRsvps)): ?>
        <p class="text-muted text-sm" style="padding:8px 0">No RSVPs yet. Browse events and click RSVP!</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Legend -->
    <div class="card widget" style="margin-top:16px">
      <div class="card-header"><h3>Event Types</h3></div>
      <div class="card-body" style="padding-top:8px;padding-bottom:8px">
        <?php foreach ($catColors as $type => $cc): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:5px 0">
          <div style="width:10px;height:10px;border-radius:2px;background:<?= $cc['border'] ?>;flex-shrink:0"></div>
          <span style="font-size:.78rem;color:var(--gray-600)"><?= $cc['label'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = "initCalendar($calYear, $calMonth, " . json_encode($eventDays) . ");";
require_once __DIR__ . '/../includes/footer.php';
?>
