<?php
// pages/spaces.php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/kca_data.php';
$pageTitle    = 'Spaces';
$pageSubtitle = 'Campus communities, clubs & academic groups';
$activeNav    = 'spaces';
require_once __DIR__ . '/../includes/header.php';

$typeFilter   = $_GET['type']   ?? 'all';
$schoolFilter = $_GET['school'] ?? 'all';
$myOnly       = isset($_GET['joined']);
$search       = trim($_GET['q'] ?? '');

$params = [$user['id']];
$where  = "s.is_active=1";

if ($typeFilter !== 'all')   { $where .= " AND s.type=?";   $params[] = $typeFilter; }
if ($schoolFilter !== 'all') { $where .= " AND s.school=?"; $params[] = $schoolFilter; }
if ($search)                 { $where .= " AND s.name LIKE ?"; $params[] = "%$search%"; }
if ($myOnly)                 { $where .= " AND EXISTS(SELECT 1 FROM space_members sm_my WHERE sm_my.space_id=s.id AND sm_my.user_id=?)"; $params[] = $user['id']; }

$spaces = DB::rows(
    "SELECT s.*,
            (SELECT COUNT(*) FROM space_members sm2 WHERE sm2.space_id=s.id) as member_count,
            (SELECT COUNT(*) FROM space_members sm3 WHERE sm3.space_id=s.id AND sm3.user_id=?) as is_member,
            (SELECT COUNT(*) FROM posts p WHERE p.space_id=s.id AND p.created_at > DATE_SUB(NOW(),INTERVAL 7 DAY)) as recent_posts
     FROM spaces s WHERE $where ORDER BY s.type ASC, s.name ASC",
    $params
);

// Group by type for display
$grouped = [];
foreach ($spaces as $sp) {
    $grouped[$sp['type']][] = $sp;
}

$typeLabels = [
    'administrative' => ['label' => 'Official & Administrative', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
    'academic'       => ['label' => 'Academic Spaces',           'icon' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>'],
    'club'           => ['label' => 'Clubs & Societies',          'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>'],
    'co-curricular'  => ['label' => 'Co-curricular & Sports',    'icon' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>'],
    'professional'   => ['label' => 'Professional Studies',       'icon' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
];
?>

<div class="page-header">
  <h2>Campus Spaces</h2>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if (in_array($user['role'],['admin','staff'])): ?>
    <a href="<?= SITE_URL ?>/pages/space_create.php" class="btn btn-primary btn-sm">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Create Space
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Search + Filters -->
<div style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);padding:14px 16px;margin-bottom:18px">
  <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="text" name="q" value="<?= sanitize($search) ?>"
           placeholder="Search spaces, clubs, courses..."
           class="form-control" style="flex:1;min-width:180px;padding:8px 12px">
    <select name="school" class="form-control" style="width:auto;padding:8px 12px">
      <option value="all">All Schools</option>
      <?php foreach (KCA_SCHOOLS as $sName => $sData): ?>
      <option value="<?= sanitize($sName) ?>" <?= $schoolFilter===$sName?'selected':'' ?>>
        <?= $sData['short'] ?> — <?= sanitize($sName) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
    <?php if ($search || $schoolFilter !== 'all' || $typeFilter !== 'all'): ?>
    <a href="?" class="btn btn-ghost btn-sm">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="filter-bar">
  <a href="?"                       class="chip <?= $typeFilter==='all'&&!$myOnly?'active':'' ?>">All Spaces</a>
  <a href="?type=academic"          class="chip <?= $typeFilter==='academic'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11" style="vertical-align:middle;margin-right:3px"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/></svg>
    Academic
  </a>
  <a href="?type=club"              class="chip <?= $typeFilter==='club'?'active':'' ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11" style="vertical-align:middle;margin-right:3px"><circle cx="12" cy="12" r="10"/></svg>
    Clubs
  </a>
  <a href="?type=administrative"    class="chip <?= $typeFilter==='administrative'?'active':'' ?>">Official</a>
  <a href="?type=co-curricular"     class="chip <?= $typeFilter==='co-curricular'?'active':'' ?>">Sports & Activities</a>
  <a href="?type=professional"      class="chip <?= $typeFilter==='professional'?'active':'' ?>">Professional</a>
  <a href="?joined=1"               class="chip <?= $myOnly?'active':'' ?>">My Spaces</a>
</div>

<?php if (empty($spaces)): ?>
<div class="card">
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
    <p>No spaces found<?= $search ? " for \"$search\"" : '' ?>.</p>
    <?php if ($myOnly): ?>
    <a href="?" class="btn btn-outline btn-sm" style="margin-top:12px">Browse all spaces</a>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>

<?php if ($typeFilter !== 'all' || $myOnly || $search): ?>
<!-- Flat grid when filtered -->
<div class="spaces-grid">
  <?php foreach ($spaces as $sp): ?>
  <?php include __DIR__ . '/../includes/space_card.php'; ?>
  <?php endforeach; ?>
</div>

<?php else: ?>
<!-- Grouped by type when showing all -->
<?php foreach ($typeLabels as $type => $tlabel): ?>
  <?php if (empty($grouped[$type])) continue; ?>
  <div style="margin-bottom:28px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid var(--gray-100)">
      <div style="width:32px;height:32px;border-radius:8px;background:rgba(0,48,135,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--kca-navy)" stroke-width="1.8" width="16" height="16"><?= $tlabel['icon'] ?></svg>
      </div>
      <div>
        <div style="font-family:var(--font-serif);font-weight:700;font-size:.95rem;color:var(--kca-navy)"><?= $tlabel['label'] ?></div>
        <div style="font-size:.72rem;color:var(--gray-400)"><?= count($grouped[$type]) ?> space<?= count($grouped[$type])!==1?'s':'' ?></div>
      </div>
    </div>
    <div class="spaces-grid">
      <?php foreach ($grouped[$type] as $sp): ?>
      <?php include __DIR__ . '/../includes/space_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
