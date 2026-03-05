<?php
require_once __DIR__ . '/../config/auth.php';
$user = requireLogin(); // ensure $user is loaded before any DB queries
$spaceId = (int)($_GET['id'] ?? 0);
if (!$spaceId) redirect('pages/spaces.php');

$space = DB::row("SELECT * FROM spaces WHERE id=? AND is_active=1", [$spaceId]);
if (!$space) redirect('pages/spaces.php');

$isMember = (bool)DB::row("SELECT id FROM space_members WHERE space_id=? AND user_id=?", [$spaceId, $user['id']]);
$memberCount = DB::count("SELECT COUNT(*) FROM space_members WHERE space_id=?", [$spaceId]);
$myRole = $isMember ? (DB::row("SELECT role FROM space_members WHERE space_id=? AND user_id=?", [$spaceId, $user['id']])['role'] ?? 'member') : null;
$isSpaceAdmin = $myRole === 'admin' || $user['role'] === 'admin';

$tab = $_GET['tab'] ?? 'discuss';
$pageTitle = sanitize($space['name']);
$pageSubtitle = 'Space';
$activeNav = 'spaces';

// Load discussion posts for this space
$posts = DB::rows(
    "SELECT p.*, u.full_name, u.avatar, u.role as author_role,
            (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id=p.id AND pr.reaction='like') as like_count,
            (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id=p.id AND pr.reaction='like' AND pr.user_id=?) as user_liked,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id) as comment_count
     FROM posts p JOIN users u ON u.id=p.user_id
     WHERE p.space_id=? ORDER BY p.is_pinned DESC, p.created_at DESC LIMIT 40",
    [$user['id'], $spaceId]
);

// Load members
$members = DB::rows(
    "SELECT u.id, u.full_name, u.avatar, u.role, u.course, u.is_online, sm.role as space_role, sm.joined_at
     FROM space_members sm JOIN users u ON u.id=sm.user_id
     WHERE sm.space_id=? ORDER BY sm.role DESC, u.full_name ASC",
    [$spaceId]
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Space Header Banner -->
<div class="card" style="margin-bottom:18px;overflow:hidden;padding:0">
  <!-- Banner -->
  <div style="height:140px;position:relative;overflow:hidden">
    <?php
    $bannerColors = [
      'academic'       => 'linear-gradient(135deg,#003087 0%,#0057B8 100%)',
      'club'           => 'linear-gradient(135deg,#0A7C59 0%,#0d9e72 100%)',
      'administrative' => 'linear-gradient(135deg,#8B6914 0%,#C9A84C 100%)',
      'co-curricular'  => 'linear-gradient(135deg,#4527A0 0%,#7C3AED 100%)',
      'professional'   => 'linear-gradient(135deg,#8B1A1A 0%,#C42B2B 100%)',
    ];
    $bg = $bannerColors[$space['type']] ?? $bannerColors['academic'];
    ?>
    <div style="width:100%;height:100%;background:<?= $bg ?>;position:relative">
      <?php if ($space['banner']): ?>
      <img src="<?= UPLOAD_URL . sanitize($space['banner']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;opacity:.5">
      <?php endif; ?>
      <!-- Pattern overlay -->
      <div style="position:absolute;inset:0;opacity:.06;background-image:repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%);background-size:12px 12px"></div>
    </div>
    <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.65) 0%,transparent 60%)"></div>
    <div style="position:absolute;top:12px;right:12px">
      <span style="background:rgba(255,255,255,.18);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.3);border-radius:99px;padding:4px 10px;font-size:.68rem;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.05em">
        <?= ucfirst($space['type']) ?>
      </span>
    </div>
    <div style="position:absolute;bottom:14px;left:18px;right:18px;display:flex;align-items:flex-end;gap:12px">
      <div style="width:52px;height:52px;border-radius:12px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,.3);flex-shrink:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--kca-navy)" stroke-width="1.8" width="24" height="24">
          <?php
          $icons = [
            'academic'       => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
            'club'           => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'administrative' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'co-curricular'  => '<circle cx="12" cy="5" r="3"/><path d="M6.5 8a6 6 0 0 0-3.5 5.5V17h3v3h12v-3h3v-3.5A6 6 0 0 0 17.5 8"/><path d="M12 8v9"/>',
            'professional'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'social'         => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
          ];
          echo $icons[$space['type']] ?? $icons['academic'];
          ?>
        </svg>
      </div>
      <div>
        <div style="color:#fff;font-family:var(--font-serif);font-size:1.05rem;font-weight:700;line-height:1.2"><?= sanitize($space['name']) ?></div>
        <div style="color:rgba(255,255,255,.75);font-size:.72rem;margin-top:2px">
          <?= $memberCount ?> member<?= $memberCount !== 1 ? 's' : '' ?>
          <?php if ($space['school']): ?> &middot; <?= sanitize($space['school']) ?><?php endif; ?>
        </div>
      </div>
      <div style="margin-left:auto;display:flex;gap:8px">
        <?php if ($isSpaceAdmin): ?>
        <a href="<?= SITE_URL ?>/admin/index.php?tab=spaces&edit=<?= $spaceId ?>" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);backdrop-filter:blur(4px)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Manage
        </a>
        <?php endif; ?>
        <button class="btn btn-sm <?= $isMember ? 'btn-outline' : 'btn-primary' ?>"
                style="<?= $isMember ? 'background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.4)' : '' ?>"
                onclick="toggleSpace(<?= $spaceId ?>, this)" id="space-join-btn">
          <?php if ($isMember): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20,6 9,17 4,12"/></svg>
          Joined
          <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Join Space
          <?php endif; ?>
        </button>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="space-tabs" style="padding:0 18px;margin:0">
    <button class="space-tab-btn <?= $tab==='discuss'?'active':'' ?>" onclick="switchTab('discuss')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" style="vertical-align:middle;margin-right:4px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Discussions
    </button>
    <button class="space-tab-btn <?= $tab==='members'?'active':'' ?>" onclick="switchTab('members')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" style="vertical-align:middle;margin-right:4px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Members <span style="color:var(--gray-400);font-weight:400">(<?= $memberCount ?>)</span>
    </button>
    <button class="space-tab-btn <?= $tab==='about'?'active':'' ?>" onclick="switchTab('about')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" style="vertical-align:middle;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      About
    </button>
  </div>
</div>

<!-- TAB: DISCUSSIONS -->
<div id="tab-discuss" class="tab-panel <?= $tab!=='members'&&$tab!=='about'?'':'hidden' ?>">

  <?php if ($isMember): ?>
  <!-- Compose box -->
  <div class="card" style="margin-bottom:14px;padding:16px">
    <div style="display:flex;gap:12px;align-items:flex-start">
      <div class="avatar avatar-sm" style="background:<?= avatarColor($user['id']) ?>;flex-shrink:0">
        <?php if ($user['avatar']): ?><img src="<?= UPLOAD_URL . sanitize($user['avatar']) ?>" alt=""><?php else: ?><?= avatarInitials($user['full_name']) ?><?php endif; ?>
      </div>
      <div style="flex:1">
        <textarea id="space-post-input" placeholder="Share something with this space..." rows="2"
          style="width:100%;resize:none;border:1.5px solid var(--gray-200);border-radius:10px;padding:10px 14px;font-size:.875rem;font-family:var(--font-ui);outline:none;transition:border-color .15s;background:var(--gray-50)"
          onfocus="this.style.borderColor='var(--kca-navy)'" onblur="this.style.borderColor='var(--gray-200)'"></textarea>
        <div style="display:flex;gap:8px;margin-top:8px;justify-content:flex-end">
          <select id="space-post-tag" class="form-control" style="width:auto;padding:6px 10px;font-size:.8rem">
            <option value="academic">Academic</option>
            <option value="social">Social</option>
            <option value="announcement">Announcement</option>
            <option value="event">Event</option>
            <option value="urgent">Urgent</option>
          </select>
          <button class="btn btn-primary btn-sm" onclick="submitSpacePost(<?= $spaceId ?>)">Post</button>
        </div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="margin-bottom:14px;padding:16px;text-align:center;color:var(--gray-400)">
    <p style="font-size:.875rem">Join this space to post and participate in discussions.</p>
    <button class="btn btn-primary btn-sm" style="margin-top:10px" onclick="toggleSpace(<?= $spaceId ?>, document.getElementById('space-join-btn'))">Join Space</button>
  </div>
  <?php endif; ?>

  <!-- Posts -->
  <div id="space-posts-list">
  <?php if (empty($posts)): ?>
  <div class="card"><div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <p>No discussions yet. Be the first to post!</p>
  </div></div>
  <?php else: foreach ($posts as $post): ?>
  <div class="card post-card" style="margin-bottom:12px;padding:16px" id="post-<?= $post['id'] ?>">
    <div style="display:flex;gap:10px;align-items:flex-start">
      <div class="avatar avatar-sm" style="background:<?= avatarColor($post['user_id']) ?>;flex-shrink:0">
        <?php if ($post['avatar']): ?><img src="<?= UPLOAD_URL . sanitize($post['avatar']) ?>" alt=""><?php else: ?><?= avatarInitials($post['full_name']) ?><?php endif; ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span style="font-weight:700;font-size:.87rem;color:var(--gray-700)"><?= sanitize($post['full_name']) ?></span>
          <?php if ($post['is_pinned']): ?>
          <span style="font-size:.65rem;background:rgba(201,168,76,.15);color:var(--kca-gold);padding:2px 6px;border-radius:99px;font-weight:700">Pinned</span>
          <?php endif; ?>
          <span style="font-size:.7rem;color:var(--gray-400);margin-left:auto"><?= timeAgo($post['created_at']) ?></span>
        </div>
        <div class="tag-badge tag-<?= $post['tag'] ?>" style="display:inline-block;margin:4px 0;font-size:.65rem;padding:2px 8px;border-radius:99px;font-weight:700;text-transform:uppercase;letter-spacing:.04em"><?= $post['tag'] ?></div>
        <p style="font-size:.875rem;line-height:1.65;color:var(--gray-700);margin-top:6px"><?= nl2br(sanitize($post['content'])) ?></p>
        <div style="display:flex;gap:14px;margin-top:12px;padding-top:10px;border-top:1px solid var(--gray-100)">
          <button onclick="toggleReaction(<?= $post['id'] ?>, 'like', this)" class="react-btn <?= $post['user_liked'] ? 'liked' : '' ?>" style="background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:5px;font-size:.8rem;color:<?= $post['user_liked'] ? 'var(--kca-navy)' : 'var(--gray-400)' ?>;font-weight:600;transition:color .15s">
            <svg viewBox="0 0 24 24" fill="<?= $post['user_liked'] ? 'var(--kca-navy)' : 'none' ?>" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span class="react-count"><?= $post['like_count'] ?></span>
          </button>
          <button onclick="toggleComments(<?= $post['id'] ?>)" style="background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:5px;font-size:.8rem;color:var(--gray-400);font-weight:600">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <?= $post['comment_count'] ?> comment<?= $post['comment_count'] !== 1 ? 's' : '' ?>
          </button>
        </div>
        <!-- Comments section -->
        <div id="comments-<?= $post['id'] ?>" style="display:none;margin-top:10px">
          <div class="comments-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:10px"></div>
          <?php if ($isMember): ?>
          <form onsubmit="submitComment(this, <?= $post['id'] ?>); return false" style="display:flex;gap:8px">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <div class="avatar" style="width:26px;height:26px;border-radius:50%;background:<?= avatarColor($user['id']) ?>;font-size:.6rem;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0">
              <?= avatarInitials($user['full_name']) ?>
            </div>
            <input class="comment-input" placeholder="Write a comment..." style="flex:1;border:1.5px solid var(--gray-200);border-radius:99px;padding:6px 14px;font-size:.8rem;outline:none;font-family:var(--font-ui);background:var(--gray-50)" onfocus="this.style.borderColor='var(--kca-navy)'" onblur="this.style.borderColor='var(--gray-200)'">
            <button type="submit" class="btn btn-primary btn-sm" style="border-radius:99px;padding:6px 14px">Reply</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>
  </div>
</div>

<!-- TAB: MEMBERS -->
<div id="tab-members" class="tab-panel <?= $tab==='members'?'':'hidden' ?>">
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
    <?php foreach ($members as $m): ?>
    <div class="card" style="padding:16px;display:flex;align-items:center;gap:12px">
      <div style="position:relative;flex-shrink:0">
        <div class="avatar avatar-sm" style="background:<?= avatarColor($m['id']) ?>">
          <?php if ($m['avatar']): ?><img src="<?= UPLOAD_URL . sanitize($m['avatar']) ?>" alt=""><?php else: ?><?= avatarInitials($m['full_name']) ?><?php endif; ?>
        </div>
        <span style="position:absolute;bottom:-2px;right:-2px" class="<?= $m['is_online'] ? 'online-dot' : 'offline-dot' ?>"></span>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:.83rem;color:var(--gray-700);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($m['full_name']) ?></div>
        <div style="font-size:.7rem;color:var(--gray-400)"><?= ucfirst($m['space_role']) ?> &middot; <?= ucfirst($m['role']) ?></div>
        <?php if ($m['course']): ?>
        <div style="font-size:.67rem;color:var(--gray-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($m['course']) ?></div>
        <?php endif; ?>
      </div>
      <?php if ($m['id'] !== $user['id']): ?>
      <button onclick="startConversation(<?= $m['id'] ?>)" title="Send message" style="background:none;border:1px solid var(--gray-200);border-radius:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--gray-400);transition:all .15s;flex-shrink:0" onmouseover="this.style.background='var(--kca-navy)';this.style.color='#fff';this.style.borderColor='var(--kca-navy)'" onmouseout="this.style.background='none';this.style.color='var(--gray-400)';this.style.borderColor='var(--gray-200)'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- TAB: ABOUT -->
<div id="tab-about" class="tab-panel <?= $tab==='about'?'':'hidden' ?>">
  <div class="card" style="padding:20px">
    <h3 style="font-family:var(--font-serif);color:var(--kca-navy);margin-bottom:12px"><?= sanitize($space['name']) ?></h3>
    <p style="font-size:.875rem;color:var(--gray-600);line-height:1.7;margin-bottom:16px"><?= nl2br(sanitize($space['description'])) ?></p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
      <div style="background:var(--gray-50);border-radius:10px;padding:12px">
        <div style="font-size:.68rem;color:var(--gray-400);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Type</div>
        <div style="font-size:.85rem;font-weight:600;color:var(--gray-700)"><?= ucfirst($space['type']) ?></div>
      </div>
      <div style="background:var(--gray-50);border-radius:10px;padding:12px">
        <div style="font-size:.68rem;color:var(--gray-400);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Members</div>
        <div style="font-size:.85rem;font-weight:600;color:var(--gray-700)"><?= $memberCount ?></div>
      </div>
      <?php if ($space['school']): ?>
      <div style="background:var(--gray-50);border-radius:10px;padding:12px">
        <div style="font-size:.68rem;color:var(--gray-400);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">School</div>
        <div style="font-size:.85rem;font-weight:600;color:var(--gray-700)"><?= sanitize($space['school']) ?></div>
      </div>
      <?php endif; ?>
      <div style="background:var(--gray-50);border-radius:10px;padding:12px">
        <div style="font-size:.68rem;color:var(--gray-400);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Privacy</div>
        <div style="font-size:.85rem;font-weight:600;color:var(--gray-700)"><?= $space['is_private'] ? 'Private' : 'Open' ?></div>
      </div>
    </div>
  </div>
</div>

<style>
.hidden { display: none !important; }
.tag-academic { background:rgba(0,48,135,.1);color:var(--kca-navy); }
.tag-social { background:rgba(10,124,89,.1);color:#0A7C59; }
.tag-announcement { background:rgba(201,168,76,.1);color:#8B6914; }
.tag-event { background:rgba(69,39,160,.1);color:#4527A0; }
.tag-urgent { background:rgba(196,43,43,.1);color:#C42B2B; }
.react-btn.liked svg { fill: var(--kca-navy); }
</style>

<?php
$extraJs = "
function switchTab(name) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
  document.querySelectorAll('.space-tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.remove('hidden');
  event.currentTarget.classList.add('active');
}

function submitSpacePost(spaceId) {
  const input = document.getElementById('space-post-input');
  const tag   = document.getElementById('space-post-tag').value;
  const text  = input.value.trim();
  if (!text) { Toast.show('Write something first!', 'error'); return; }
  const fd = new FormData();
  fd.append('content', text);
  fd.append('tag', tag);
  fd.append('space_id', spaceId);
  fetch(window.BASE_URL + '/api/posts.php', { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        Toast.show('Posted!', 'success');
        input.value = '';
        setTimeout(() => location.reload(), 600);
      } else Toast.show(d.message || 'Error posting', 'error');
    })
    .catch(() => Toast.show('Network error', 'error'));
}
";
require_once __DIR__ . '/../includes/footer.php';
?>
