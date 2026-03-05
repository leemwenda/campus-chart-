<?php
// Reusable space card partial — $sp must be set by parent
$typeColors = [
    'academic'       => ['bg'=>'rgba(0,48,135,.08)',      'text'=>'#003087', 'badge'=>'Academic'],
    'club'           => ['bg'=>'rgba(10,124,89,.08)',      'text'=>'#0A7C59', 'badge'=>'Club'],
    'administrative' => ['bg'=>'rgba(139,105,20,.08)',    'text'=>'#8B6914', 'badge'=>'Official'],
    'co-curricular'  => ['bg'=>'rgba(69,39,160,.08)',     'text'=>'#4527A0', 'badge'=>'Activity'],
    'professional'   => ['bg'=>'rgba(196,43,43,.08)',     'text'=>'#C42B2B', 'badge'=>'Professional'],
];
$tc = $typeColors[$sp['type']] ?? $typeColors['academic'];
$memberRole = null;
if ($sp['is_member']) {
    $mr = DB::row("SELECT role FROM space_members WHERE space_id=? AND user_id=?", [$sp['id'], $user['id']]);
    $memberRole = $mr['role'] ?? 'member';
}
?>
<div class="card space-card" id="space-<?= $sp['id'] ?>">
  <!-- Banner -->
  <div class="space-banner type-<?= $sp['type'] ?>" style="position:relative;overflow:hidden">
    <div class="space-banner-pattern"></div>
    <?php if ($sp['school']): ?>
    <div style="position:absolute;bottom:8px;left:10px;font-size:.65rem;font-weight:700;color:rgba(255,255,255,.7);letter-spacing:.05em;text-transform:uppercase">
      <?php
      $schoolShorts = ['School of Technology'=>'SoT','School of Business'=>'SoB','School of Education, Arts & Social Sciences'=>'SEASS'];
      echo $schoolShorts[$sp['school']] ?? $sp['school'];
      ?>
    </div>
    <?php endif; ?>
    <div style="position:absolute;top:8px;right:8px">
      <span style="background:rgba(255,255,255,.2);backdrop-filter:blur(4px);border-radius:99px;padding:3px 8px;font-size:.63rem;font-weight:700;color:var(--white);letter-spacing:.04em;border:1px solid rgba(255,255,255,.25)">
        <?= $tc['badge'] ?>
      </span>
    </div>
  </div>

  <div class="space-card-body">
    <a href="<?= SITE_URL ?>/pages/space_view.php?id=<?= $sp['id'] ?>" style="text-decoration:none;color:inherit">
      <div class="space-card-name"><?= sanitize($sp['name']) ?></div>
    </a>
    <div class="space-card-desc" style="-webkit-line-clamp:2"><?= sanitize($sp['description']) ?></div>

    <div style="display:flex;align-items:center;gap:10px;margin-top:8px;padding-top:8px;border-top:1px solid var(--gray-100)">
      <span class="space-members" style="flex:1">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span id="member-count-<?= $sp['id'] ?>"><?= $sp['member_count'] ?></span>
      </span>

      <?php if ($sp['recent_posts'] > 0): ?>
      <span style="font-size:.7rem;color:var(--success);font-weight:600">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle"><circle cx="12" cy="12" r="10"/></svg>
        <?= $sp['recent_posts'] ?> new
      </span>
      <?php endif; ?>

      <?php if ($sp['is_member'] && $memberRole === 'admin'): ?>
      <span style="font-size:.67rem;font-weight:700;color:var(--kca-gold);background:rgba(201,168,76,.12);padding:2px 7px;border-radius:99px">Admin</span>
      <?php elseif ($sp['is_member'] && $memberRole === 'moderator'): ?>
      <span style="font-size:.67rem;font-weight:700;color:var(--info);background:rgba(0,84,166,.1);padding:2px 7px;border-radius:99px">Mod</span>
      <?php endif; ?>

      <button class="join-btn <?= $sp['is_member'] ? 'joined' : '' ?>"
              id="join-btn-<?= $sp['id'] ?>"
              onclick="toggleSpace(<?= $sp['id'] ?>, this)">
        <?php if ($sp['is_member']): ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20,6 9,17 4,12"/></svg>
        Joined
        <?php else: ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Join
        <?php endif; ?>
      </button>
    </div>
  </div>
</div>
