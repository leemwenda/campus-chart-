<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/kca_data.php';
$pageTitle    = 'Members';
$pageSubtitle = 'KCA University campus directory';
$activeNav    = 'members';
require_once __DIR__ . '/../includes/header.php';

$roleFilter   = $_GET['role']   ?? 'all';
$schoolFilter = $_GET['school'] ?? 'all';
$yearFilter   = $_GET['year']   ?? 'all';
$search       = trim($_GET['q'] ?? '');

$params = [$user['id']];
$where  = "is_active=1 AND id != ?";

if ($roleFilter !== 'all')   { $where .= " AND role=?";          $params[] = $roleFilter; }
if ($schoolFilter !== 'all') { $where .= " AND school=?";        $params[] = $schoolFilter; }
if ($yearFilter !== 'all')   { $where .= " AND year_of_study=?"; $params[] = (int)$yearFilter; }
if ($search) {
    $where .= " AND (full_name LIKE ? OR student_id LIKE ? OR course LIKE ? OR department LIKE ? OR school LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like,$like,$like,$like,$like]);
}

$members = DB::rows(
    "SELECT id, full_name, email, student_id, role, school, department, course,
            year_of_study, study_mode, bio, avatar, is_online, last_seen
     FROM users WHERE $where ORDER BY role ASC, full_name ASC",
    $params
);

$followingIds = array_column(
    DB::rows("SELECT following_id FROM follows WHERE follower_id=?", [$user['id']]),
    'following_id'
);

$totalCount = count($members);

$schoolShorts = [
    'School of Technology'                          => 'SoT',
    'School of Business'                            => 'SoB',
    'School of Education, Arts & Social Sciences'   => 'SEASS',
    'Professional Training & Testing Institute'     => 'PTTI',
    'ICT Department'                                => 'ICT',
];
?>

<style>
  /* ── MEMBERS PAGE LAYOUT ── */
  .members-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px; margin-bottom: 20px;
  }
  .members-header h2 {
    font-family: var(--font-serif); font-size: 1.4rem;
    font-weight: 700; color: var(--text-primary);
  }
  .members-header .count-badge {
    background: var(--kca-navy); color: var(--white);
    font-size: .72rem; font-weight: 700; padding: 3px 10px;
    border-radius: 99px;
  }

  /* ── SEARCH BAR ── */
  .members-search-bar {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 14px 16px;
    margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap;
  }
  .search-input-wrap {
    flex: 1; min-width: 200px; position: relative;
  }
  .search-input-wrap svg {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); width: 15px; height: 15px;
  }
  .search-input-wrap input {
    padding-left: 36px !important;
  }

  /* ── MEMBER GRID ── */
  .members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
  }

  /* ── MEMBER CARD ── */
  .mc {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 0 0 16px;
    display: flex; flex-direction: column; align-items: center;
    text-align: center;
    overflow: hidden;
    transition: box-shadow .2s, transform .2s, border-color .2s;
    position: relative;
  }
  .mc:hover {
    box-shadow: 0 10px 36px rgba(0,48,135,.13);
    transform: translateY(-3px);
    border-color: rgba(0,48,135,.18);
  }

  /* top colour strip */
  .mc-strip {
    width: 100%; height: 52px; flex-shrink: 0;
    position: relative;
  }

  /* avatar centered over strip */
  .mc-avatar-wrap {
    margin-top: -30px;
    position: relative;
    display: inline-flex;
    margin-bottom: 10px;
  }
  .mc-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-serif); font-size: 1.2rem; font-weight: 700;
    color: var(--white);
    border: 3px solid var(--surface);
    box-shadow: 0 3px 14px rgba(0,0,0,.18);
    overflow: hidden; flex-shrink: 0;
  }
  .mc-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .mc-online {
    position: absolute; bottom: 2px; right: 2px;
    width: 12px; height: 12px; border-radius: 50%;
    background: #22c55e; border: 2px solid var(--surface);
  }

  .mc-name {
    font-size: .9rem; font-weight: 700; color: var(--text-primary);
    line-height: 1.2; margin-bottom: 3px; padding: 0 14px;
  }
  .mc-id {
    font-size: .68rem; color: var(--text-muted);
    font-family: monospace; margin-bottom: 8px;
  }

  .mc-badges {
    display: flex; align-items: center; gap: 5px; flex-wrap: wrap;
    justify-content: center; padding: 0 12px; margin-bottom: 8px;
  }
  .mc-role {
    font-size: .67rem; font-weight: 700; padding: 3px 9px;
    border-radius: 99px; text-transform: uppercase; letter-spacing: .04em;
  }
  .mc-role.role-student  { background: rgba(0,48,135,.1);  color: var(--kca-navy); }
  .mc-role.role-staff    { background: rgba(10,124,89,.1); color: #0A7C59; }
  .mc-role.role-admin    { background: rgba(196,43,43,.1); color: #C42B2B; }
  .mc-school {
    font-size: .66rem; font-weight: 600; padding: 3px 9px;
    border-radius: 99px; background: var(--gray-100); color: var(--gray-500);
  }

  .mc-course {
    font-size: .77rem; font-weight: 600; color: var(--text-primary);
    line-height: 1.35; padding: 0 14px; margin-bottom: 3px;
  }
  .mc-meta {
    font-size: .7rem; color: var(--text-muted); margin-bottom: 14px;
  }

  .mc-actions {
    display: flex; gap: 6px; padding: 0 14px; width: 100%; box-sizing: border-box;
  }
  .mc-follow {
    flex: 1; padding: 7px 10px; border-radius: 8px; font-size: .78rem;
    font-weight: 600; cursor: pointer; border: 1.5px solid var(--border);
    background: none; color: var(--kca-navy); transition: all .18s;
  }
  .mc-follow:hover { background: var(--kca-navy); color: var(--white); border-color: var(--kca-navy); }
  .mc-follow.following {
    background: rgba(0,48,135,.07); color: var(--kca-navy); border-color: rgba(0,48,135,.2);
  }
  .mc-follow.following:hover { background: rgba(220,38,38,.08); color: #C42B2B; border-color: rgba(220,38,38,.2); }
  .mc-icon-btn {
    width: 34px; height: 34px; border-radius: 8px; border: 1.5px solid var(--border);
    background: none; cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); transition: all .18s; flex-shrink: 0; text-decoration: none;
  }
  .mc-icon-btn:hover { background: var(--surface-2); border-color: var(--gray-300); color: var(--kca-navy); }
  .mc-icon-btn svg { width: 14px; height: 14px; }

  /* role-based strip gradients */
  .strip-student     { background: linear-gradient(135deg, #003087, #0057B8); }
  .strip-staff       { background: linear-gradient(135deg, #0A7C59, #0d9e72); }
  .strip-admin       { background: linear-gradient(135deg, #8B1A1A, #C42B2B); }
</style>

<!-- ── PAGE HEADER ── -->
<div class="members-header">
  <div>
    <h2>Campus Directory</h2>
    <p style="font-size:.8rem;color:var(--text-muted);margin-top:2px">
      Connect with students, staff, and lecturers at KCA University
    </p>
  </div>
  <span class="count-badge"><?= $totalCount ?> member<?= $totalCount!==1?'s':'' ?></span>
</div>

<!-- ── SEARCH + FILTERS ── -->
<div class="members-search-bar">
  <form method="GET" style="display:contents">
    <div class="search-input-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" value="<?= sanitize($search) ?>"
             placeholder="Search by name, Student ID, course..."
             class="form-control">
    </div>
    <select name="school" class="form-control" style="width:auto;min-width:160px;font-size:.82rem">
      <option value="all">All Schools</option>
      <?php foreach (KCA_SCHOOLS as $sName => $sData): ?>
      <option value="<?= sanitize($sName) ?>" <?= $schoolFilter===$sName?'selected':'' ?>>
        <?= $sData['short'] ?> — <?= sanitize($sName) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select name="year" class="form-control" style="width:auto;font-size:.82rem">
      <option value="all">All Years</option>
      <option value="1" <?= $yearFilter==='1'?'selected':'' ?>>Year 1</option>
      <option value="2" <?= $yearFilter==='2'?'selected':'' ?>>Year 2</option>
      <option value="3" <?= $yearFilter==='3'?'selected':'' ?>>Year 3</option>
      <option value="4" <?= $yearFilter==='4'?'selected':'' ?>>Year 4</option>
      <option value="0" <?= $yearFilter==='0'?'selected':'' ?>>Postgrad</option>
    </select>
    <input type="hidden" name="role" value="<?= sanitize($roleFilter) ?>">
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    <?php if ($search || $schoolFilter!=='all' || $roleFilter!=='all' || $yearFilter!=='all'): ?>
    <a href="?" class="btn btn-ghost btn-sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- ── ROLE CHIPS ── -->
<div class="filter-bar" style="margin-bottom:20px">
  <a href="?<?= http_build_query(array_merge($_GET,['role'=>'all'])) ?>"
     class="chip <?= $roleFilter==='all'?'active':'' ?>">Everyone</a>
  <a href="?<?= http_build_query(array_merge($_GET,['role'=>'student'])) ?>"
     class="chip <?= $roleFilter==='student'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/></svg>
    Students
  </a>
  <a href="?<?= http_build_query(array_merge($_GET,['role'=>'staff'])) ?>"
     class="chip <?= $roleFilter==='staff'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><rect x="2" y="7" width="20" height="14" rx="2"/></svg>
    Staff & Lecturers
  </a>
  <a href="?<?= http_build_query(array_merge($_GET,['role'=>'admin'])) ?>"
     class="chip <?= $roleFilter==='admin'?'active':'' ?>">Admin</a>
</div>

<!-- ── MEMBER GRID ── -->
<?php if (empty($members)): ?>
<div class="card">
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <h3 style="margin:12px 0 6px;font-size:.95rem">No members found</h3>
    <p><?= $search ? "No results for \"".sanitize($search)."\"" : 'Try adjusting your filters.' ?></p>
    <a href="?" class="btn btn-outline btn-sm" style="margin-top:14px">View all members</a>
  </div>
</div>
<?php else: ?>
<div class="members-grid">
  <?php foreach ($members as $m): ?>
  <?php
    $stripClass = 'strip-' . $m['role'];
    $school = $m['school'] ?? '';
    $shortSchool = $schoolShorts[$school] ?? ($school ? substr($school,0,6) : '');
    $isFollowing = in_array($m['id'], $followingIds);
  ?>
  <div class="mc">
    <!-- Colour strip -->
    <div class="mc-strip <?= $stripClass ?>"></div>

    <!-- Avatar -->
    <div class="mc-avatar-wrap">
      <div class="mc-avatar" style="background:<?= avatarColor($m['id']) ?>">
        <?php if ($m['avatar']): ?>
          <img src="<?= UPLOAD_URL . sanitize($m['avatar']) ?>" alt="">
        <?php else: ?>
          <?= avatarInitials($m['full_name']) ?>
        <?php endif; ?>
      </div>
      <?php if ($m['is_online']): ?>
      <span class="mc-online"></span>
      <?php endif; ?>
    </div>

    <!-- Name -->
    <div class="mc-name"><?= sanitize($m['full_name']) ?></div>
    <?php if ($m['student_id']): ?>
    <div class="mc-id"><?= sanitize($m['student_id']) ?></div>
    <?php endif; ?>

    <!-- Badges -->
    <div class="mc-badges">
      <span class="mc-role role-<?= $m['role'] ?>"><?= ucfirst($m['role']) ?></span>
      <?php if ($shortSchool): ?>
      <span class="mc-school"><?= $shortSchool ?></span>
      <?php endif; ?>
    </div>

    <!-- Course -->
    <?php if ($m['course'] || $m['department']): ?>
    <div class="mc-course"><?= sanitize($m['course'] ?? $m['department'] ?? '') ?></div>
    <?php endif; ?>

    <!-- Year + mode -->
    <?php if ($m['role']==='student' && $m['year_of_study'] > 0): ?>
    <div class="mc-meta">Year <?= $m['year_of_study'] ?><?= $m['study_mode'] ? ' · '.ucfirst($m['study_mode']) : '' ?></div>
    <?php elseif ($m['role'] === 'staff'): ?>
    <div class="mc-meta"><?= sanitize(substr($m['department'] ?? '',0,40)) ?></div>
    <?php else: ?>
    <div class="mc-meta" style="height:18px"></div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="mc-actions">
      <button class="mc-follow <?= $isFollowing?'following':'' ?>"
              onclick="toggleFollow(<?= $m['id'] ?>, this)">
        <?= $isFollowing ? 'Following' : '+ Follow' ?>
      </button>
      <button class="mc-icon-btn" onclick="startConversation(<?= $m['id'] ?>)" title="Message">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </button>
      <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= $m['id'] ?>"
         class="mc-icon-btn" title="View profile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
