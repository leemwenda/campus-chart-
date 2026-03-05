<?php
require_once __DIR__ . '/../config/auth.php';
$pageTitle    = 'Messages';
$pageSubtitle = 'Direct conversations';
$activeNav    = 'messages';
require_once __DIR__ . '/../includes/header.php';

// ── Get all conversations for current user ────────────────────────────────
// Fixed query: explicitly exclude self-conversations and deduplicate
$conversations = DB::rows(
    "SELECT
        c.id,
        cp2.user_id  AS other_user_id,
        u.full_name,
        u.avatar,
        u.is_online,
        (SELECT content  FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
        (SELECT sent_at  FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) AS last_time,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count
     FROM conversations c
     JOIN conversation_participants cp  ON cp.conversation_id  = c.id AND cp.user_id  = ?
     JOIN conversation_participants cp2 ON cp2.conversation_id = c.id AND cp2.user_id != ?
     JOIN users u ON u.id = cp2.user_id
     WHERE u.id != ?
     GROUP BY c.id, cp2.user_id
     ORDER BY last_time DESC, c.id DESC",
    [$user['id'], $user['id'], $user['id'], $user['id']]
);

// ── Active conversation ───────────────────────────────────────────────────
$activeConv   = (int)($_GET['conv'] ?? ($conversations[0]['id'] ?? 0));
$activeThread = null;
$messages     = [];

if ($activeConv) {
    // Verify current user is actually a participant (security check)
    $isParticipant = DB::row(
        "SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?",
        [$activeConv, $user['id']]
    );
    if ($isParticipant) {
        $activeThread = null;
        foreach ($conversations as $c) {
            if ($c['id'] === $activeConv) { $activeThread = $c; break; }
        }
        // If conv exists but other person isn't in our list (edge case), fetch them
        if (!$activeThread) {
            $other = DB::row(
                "SELECT cp2.user_id AS other_user_id, u.full_name, u.avatar, u.is_online
                 FROM conversation_participants cp2
                 JOIN users u ON u.id = cp2.user_id
                 WHERE cp2.conversation_id = ? AND cp2.user_id != ?
                 LIMIT 1",
                [$activeConv, $user['id']]
            );
            if ($other) {
                $activeThread = array_merge($other, [
                    'id' => $activeConv,
                    'last_message' => null,
                    'last_time' => null,
                    'unread_count' => 0,
                ]);
            }
        }
        if ($activeThread) {
            $messages = DB::rows(
                "SELECT m.*, u.full_name, u.id AS sender_id_real, u.avatar
                 FROM messages m
                 JOIN users u ON u.id = m.sender_id
                 WHERE m.conversation_id = ?
                 ORDER BY m.sent_at ASC",
                [$activeConv]
            );
            // Mark incoming messages as read
            DB::query(
                "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?",
                [$activeConv, $user['id']]
            );
        }
    }
}

// ── Suggested contacts (people you follow or staff) for "New Message" ──────
$suggested = DB::rows(
    "SELECT u.id, u.full_name, u.avatar, u.role, u.is_online
     FROM users u
     LEFT JOIN follows f ON f.following_id = u.id AND f.follower_id = ?
     WHERE u.id != ? AND u.is_active = 1
     ORDER BY f.id DESC, u.role ASC, u.full_name ASC
     LIMIT 30",
    [$user['id'], $user['id']]
);
?>

<style>
/* ── MESSAGES LAYOUT ── */
.messages-layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 16px;
  height: calc(100vh - var(--topbar-h) - 32px);
  min-height: 500px;
}
.msg-list  { display: flex; flex-direction: column; overflow: hidden; }
.chat-panel{ display: flex; flex-direction: column; overflow: hidden; }

/* ── THREAD LIST ── */
.msg-list-header {
  padding: 16px 16px 10px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.msg-list-header h3 {
  font-family: var(--font-serif);
  font-size: .95rem; font-weight: 700;
  color: var(--text-primary); margin-bottom: 10px;
  display: flex; align-items: center; justify-content: space-between;
}
.new-msg-btn {
  width: 28px; height: 28px; border-radius: 7px;
  background: var(--kca-navy); color: var(--white);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; border: none; flex-shrink: 0;
  transition: background var(--transition);
}
.new-msg-btn:hover { background: var(--kca-navy-mid); }
.new-msg-btn svg   { width: 14px; height: 14px; }

.conv-search-wrap {
  display: flex; align-items: center; gap: 7px;
  background: var(--surface-2); border: 1.5px solid var(--border);
  border-radius: 99px; padding: 7px 12px;
}
.conv-search-wrap svg   { color: var(--text-muted); flex-shrink: 0; width: 14px; height: 14px; }
.conv-search-wrap input {
  background: none; border: none; outline: none;
  font-size: .8rem; width: 100%; font-family: var(--font-ui);
  color: var(--text-primary);
}

.msg-list-body { flex: 1; overflow-y: auto; }

.msg-thread {
  display: flex; align-items: center; gap: 11px;
  padding: 12px 16px; text-decoration: none;
  border-bottom: 1px solid var(--border);
  transition: background var(--transition);
  position: relative;
}
.msg-thread:hover  { background: var(--surface-2); }
.msg-thread.active {
  background: rgba(0,48,135,.06);
  border-left: 3px solid var(--kca-navy);
}
.thread-info { flex: 1; min-width: 0; }
.thread-name {
  font-size: .85rem; font-weight: 600;
  color: var(--text-primary); white-space: nowrap;
  overflow: hidden; text-overflow: ellipsis;
}
.thread-preview {
  font-size: .75rem; color: var(--text-muted);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-top: 2px;
}
.thread-time { font-size: .68rem; color: var(--text-muted); white-space: nowrap; }
.unread-badge {
  background: var(--kca-navy); color: var(--white);
  font-size: .65rem; font-weight: 700;
  padding: 2px 6px; border-radius: 99px;
}

/* ── CHAT WINDOW ── */
.chat-header {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 18px; border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.chat-name   { font-weight: 700; font-size: .9rem; color: var(--text-primary); }
.chat-status { font-size: .73rem; margin-top: 1px; }
.chat-status.online  { color: #22c55e; }
.chat-status.offline { color: var(--text-muted); }

.chat-messages {
  flex: 1; overflow-y: auto;
  padding: 16px 18px;
  display: flex; flex-direction: column; gap: 4px;
}

.msg-bubble {
  display: flex; align-items: flex-end; gap: 8px;
  max-width: 72%; margin-bottom: 6px;
}
.msg-bubble.own {
  flex-direction: row-reverse;
  align-self: flex-end;
}
.bubble-content {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 16px 16px 16px 4px;
  padding: 9px 13px;
  font-size: .84rem; line-height: 1.5;
  color: var(--text-primary);
  word-break: break-word;
}
.msg-bubble.own .bubble-content {
  background: var(--kca-navy);
  color: var(--white);
  border-color: var(--kca-navy);
  border-radius: 16px 16px 4px 16px;
}
.bubble-time {
  font-size: .68rem; color: var(--text-muted);
  margin-top: 4px; padding: 0 4px;
  white-space: nowrap;
}
.msg-bubble.own .bubble-time { text-align: right; }

.chat-footer {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; border-top: 1px solid var(--border);
  flex-shrink: 0;
}
.chat-input-field {
  flex: 1; background: var(--surface-2);
  border: 1.5px solid var(--border);
  border-radius: 99px; padding: 10px 18px;
  font-size: .85rem; outline: none;
  font-family: var(--font-ui); color: var(--text-primary);
  transition: border-color var(--transition);
}
.chat-input-field:focus { border-color: var(--kca-navy); }
.send-btn {
  width: 40px; height: 40px; border-radius: 50%;
  background: var(--kca-navy); color: var(--white);
  border: none; cursor: pointer; display: flex;
  align-items: center; justify-content: center;
  flex-shrink: 0; transition: background var(--transition);
}
.send-btn:hover { background: var(--kca-navy-mid); }
.send-btn svg { width: 16px; height: 16px; }

/* ── NEW MESSAGE MODAL ── */
.modal-overlay {
  position: fixed; inset: 0; z-index: 500;
  background: rgba(0,0,0,.4); backdrop-filter: blur(3px);
  display: none; align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: var(--surface); border-radius: 16px;
  width: 100%; max-width: 400px; max-height: 80vh;
  display: flex; flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,.25);
  overflow: hidden;
}
.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 20px; border-bottom: 1px solid var(--border);
}
.modal-header h3 { font-family: var(--font-serif); font-size: 1rem; color: var(--text-primary); }
.modal-close {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted); padding: 4px; border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  transition: color var(--transition);
}
.modal-close:hover { color: var(--text-primary); }
.modal-search {
  padding: 12px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0;
}
.modal-search input {
  width: 100%; padding: 9px 14px;
  background: var(--surface-2); border: 1.5px solid var(--border);
  border-radius: 99px; outline: none; font-size: .84rem;
  font-family: var(--font-ui); color: var(--text-primary);
  transition: border-color var(--transition);
  box-sizing: border-box;
}
.modal-search input:focus { border-color: var(--kca-navy); }
.modal-people { overflow-y: auto; flex: 1; }
.modal-person {
  display: flex; align-items: center; gap: 11px;
  padding: 11px 16px; cursor: pointer;
  border-bottom: 1px solid var(--border);
  transition: background var(--transition);
}
.modal-person:last-child { border-bottom: none; }
.modal-person:hover { background: var(--surface-2); }
.modal-person .pname { font-size: .86rem; font-weight: 600; color: var(--text-primary); }
.modal-person .prole { font-size: .72rem; color: var(--text-muted); margin-top: 1px; }

@media (max-width: 768px) {
  .messages-layout { grid-template-columns: 1fr; height: auto; }
  .msg-list { min-height: 220px; }
}
</style>

<!-- ── NEW MESSAGE MODAL ── -->
<div class="modal-overlay" id="new-msg-modal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>New Message</h3>
      <button class="modal-close" onclick="closeNewMsgModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-search">
      <input type="text" id="modal-search-input" placeholder="Search people..." oninput="filterModalPeople(this.value)">
    </div>
    <div class="modal-people" id="modal-people-list">
      <?php foreach ($suggested as $p): ?>
      <div class="modal-person" onclick="startConversation(<?= $p['id'] ?>)"
           data-name="<?= strtolower(sanitize($p['full_name'])) ?>">
        <div class="avatar avatar-sm" style="background:<?= avatarColor($p['id']) ?>;position:relative;flex-shrink:0">
          <?php if ($p['avatar']): ?>
            <img src="<?= UPLOAD_URL . sanitize($p['avatar']) ?>" alt="">
          <?php else: ?>
            <?= avatarInitials($p['full_name']) ?>
          <?php endif; ?>
          <?php if ($p['is_online']): ?>
          <span style="position:absolute;bottom:0;right:0;width:9px;height:9px;border-radius:50%;background:#22c55e;border:2px solid var(--surface)"></span>
          <?php endif; ?>
        </div>
        <div>
          <div class="pname"><?= sanitize($p['full_name']) ?></div>
          <div class="prole"><?= ucfirst($p['role']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── MESSAGES LAYOUT ── -->
<div class="messages-layout">

  <!-- ══ LEFT: CONVERSATION LIST ══ -->
  <div class="card msg-list">
    <div class="msg-list-header">
      <h3>
        Messages
        <button class="new-msg-btn" onclick="openNewMsgModal()" title="New conversation">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
        </button>
      </h3>
      <div class="conv-search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" placeholder="Search conversations..." id="conv-search">
      </div>
    </div>

    <div class="msg-list-body">
      <?php if (empty($conversations)): ?>
        <div style="padding:32px;text-align:center">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="40" height="40" style="color:var(--gray-300);margin-bottom:10px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <p style="font-size:.82rem;color:var(--text-muted)">No conversations yet</p>
          <button onclick="openNewMsgModal()" class="btn btn-primary btn-sm" style="margin-top:10px">Start a chat</button>
        </div>
      <?php else: ?>
        <?php foreach ($conversations as $conv): ?>
        <a href="?conv=<?= $conv['id'] ?>" class="msg-thread <?= $conv['id']===$activeConv?'active':'' ?>"
           data-name="<?= strtolower(sanitize($conv['full_name'])) ?>">
          <div class="avatar avatar-sm" style="background:<?= avatarColor($conv['other_user_id']) ?>;flex-shrink:0;position:relative">
            <?php if ($conv['avatar']): ?>
              <img src="<?= UPLOAD_URL . sanitize($conv['avatar']) ?>" alt="">
            <?php else: ?>
              <?= avatarInitials($conv['full_name']) ?>
            <?php endif; ?>
            <?php if ($conv['is_online']): ?>
            <span style="position:absolute;bottom:0;right:0;width:8px;height:8px;border-radius:50%;background:#22c55e;border:2px solid var(--surface)"></span>
            <?php endif; ?>
          </div>
          <div class="thread-info">
            <div class="thread-name"><?= sanitize($conv['full_name']) ?></div>
            <div class="thread-preview">
              <?php if ($conv['last_message']): ?>
                <?= sanitize(substr($conv['last_message'], 0, 45)) ?>
              <?php else: ?>
                <em style="color:var(--text-muted)">No messages yet</em>
              <?php endif; ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0">
            <?php if ($conv['last_time']): ?>
              <span class="thread-time"><?= timeAgo($conv['last_time']) ?></span>
            <?php endif; ?>
            <?php if ($conv['unread_count'] > 0): ?>
              <span class="unread-badge"><?= $conv['unread_count'] ?></span>
            <?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ RIGHT: CHAT WINDOW ══ -->
  <div class="card chat-panel">
    <?php if ($activeThread): ?>

    <!-- Chat header -->
    <div class="chat-header">
      <div class="avatar avatar-sm" style="background:<?= avatarColor($activeThread['other_user_id']) ?>;position:relative;flex-shrink:0">
        <?php if ($activeThread['avatar']): ?>
          <img src="<?= UPLOAD_URL . sanitize($activeThread['avatar']) ?>" alt="">
        <?php else: ?>
          <?= avatarInitials($activeThread['full_name']) ?>
        <?php endif; ?>
        <?php if ($activeThread['is_online']): ?>
        <span style="position:absolute;bottom:0;right:0;width:9px;height:9px;border-radius:50%;background:#22c55e;border:2px solid var(--surface)"></span>
        <?php endif; ?>
      </div>
      <div>
        <div class="chat-name"><?= sanitize($activeThread['full_name']) ?></div>
        <div class="chat-status <?= $activeThread['is_online'] ? 'online' : 'offline' ?>">
          <?= $activeThread['is_online'] ? '● Online' : '● Offline' ?>
        </div>
      </div>
      <div style="margin-left:auto;display:flex;gap:6px">
        <a href="<?= SITE_URL ?>/pages/profile.php?id=<?= $activeThread['other_user_id'] ?>"
           class="btn btn-ghost btn-sm">View Profile</a>
      </div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chat-messages">
      <?php if (empty($messages)): ?>
        <div style="text-align:center;color:var(--text-muted);font-size:.84rem;margin:auto;padding:48px 24px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="44" height="44" style="opacity:.3;display:block;margin:0 auto 12px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Say hello to <?= sanitize($activeThread['full_name']) ?>!
        </div>
      <?php endif; ?>
      <?php foreach ($messages as $msg):
        $isOwn = (int)$msg['sender_id'] === (int)$user['id']; ?>
      <div class="msg-bubble <?= $isOwn ? 'own' : '' ?>" data-msg-id="<?= $msg['id'] ?>">
        <?php if (!$isOwn): ?>
        <div class="avatar avatar-sm" style="background:<?= avatarColor($msg['sender_id_real']) ?>;flex-shrink:0">
          <?php if ($msg['avatar']): ?>
            <img src="<?= UPLOAD_URL . sanitize($msg['avatar']) ?>" alt="">
          <?php else: ?>
            <?= avatarInitials($msg['full_name']) ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <div>
          <div class="bubble-content"><?= sanitize($msg['content']) ?></div>
          <div class="bubble-time"><?= date('g:i A', strtotime($msg['sent_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Input -->
    <div class="chat-footer">
      <input type="text" class="chat-input-field" id="chat-input-field"
             placeholder="Type a message..."
             onkeydown="if(event.key==='Enter'&&!event.shiftKey){sendMessage(<?= $activeConv ?>);event.preventDefault()}"
             autocomplete="off">
      <button class="send-btn" onclick="sendMessage(<?= $activeConv ?>)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9"/></svg>
      </button>
    </div>

    <?php else: ?>
    <!-- No conversation selected -->
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;color:var(--text-muted);gap:14px;padding:40px;text-align:center">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="opacity:.25"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <div>
        <p style="font-size:.9rem;font-weight:600;color:var(--text-primary);margin-bottom:5px">Your messages</p>
        <p style="font-size:.82rem">Select a conversation or start a new one</p>
      </div>
      <button onclick="openNewMsgModal()" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Message
      </button>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php
$extraJs = "
// Auto-scroll chat to bottom
const cm = document.getElementById('chat-messages');
if (cm) cm.scrollTop = cm.scrollHeight;

// Start polling for new messages
" . ($activeConv ? "activeConvId = $activeConv; messagePolling = setInterval(() => loadMessages($activeConv), 4000);" : "") . "

// Conversation search filter
const convSearch = document.getElementById('conv-search');
if (convSearch) {
  convSearch.addEventListener('input', () => {
    const q = convSearch.value.toLowerCase().trim();
    document.querySelectorAll('.msg-thread').forEach(t => {
      const name = t.dataset.name || '';
      t.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
  });
}

// New message modal
function openNewMsgModal() {
  document.getElementById('new-msg-modal').classList.add('open');
  setTimeout(() => document.getElementById('modal-search-input')?.focus(), 100);
}
function closeNewMsgModal() {
  document.getElementById('new-msg-modal').classList.remove('open');
  document.getElementById('modal-search-input').value = '';
  filterModalPeople('');
}
function filterModalPeople(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('.modal-person').forEach(p => {
    p.style.display = (!q || p.dataset.name.includes(q)) ? '' : 'none';
  });
}
// Close modal on overlay click
document.getElementById('new-msg-modal').addEventListener('click', function(e) {
  if (e.target === this) closeNewMsgModal();
});
";
require_once __DIR__ . '/../includes/footer.php';
?>
