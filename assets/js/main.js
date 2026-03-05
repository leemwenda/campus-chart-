/* ============================================================
   KCA CHART — Main JavaScript
   ============================================================ */

'use strict';

/* ── MOBILE SIDEBAR ── */
function openSidebar() {
  document.getElementById('sidebar')?.classList.add('open');
  document.getElementById('sidebar-overlay')?.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebar-overlay')?.classList.remove('open');
  document.body.style.overflow = '';
}
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  if (s?.classList.contains('open')) closeSidebar(); else openSidebar();
}

/* ── TOAST NOTIFICATIONS ── */
const Toast = {
  show(message, type = 'default', duration = 3500) {
    const icons = {
      default: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>`,
      success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>`,
      error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
    };
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = (icons[type] || icons.default) + `<span>${message}</span>`;
    container.appendChild(toast);
    requestAnimationFrame(() => { requestAnimationFrame(() => { toast.classList.add('show'); }); });
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }
};

/* ── MODAL ── */
const Modal = {
  open(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); el.addEventListener('click', e => { if(e.target===el) Modal.close(id); }, {once:true}); }
  },
  close(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  }
};
// Close modals on overlay click & Escape
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(m=>m.classList.remove('open')); });

/* ── NOTIFICATIONS PANEL ── */
let notifPanelOpen = false;
function toggleNotifPanel() {
  const panel = document.getElementById('notif-panel');
  if (!panel) return;
  notifPanelOpen = !notifPanelOpen;
  panel.classList.toggle('open', notifPanelOpen);
}
document.addEventListener('click', e => {
  if (notifPanelOpen && !e.target.closest('#notif-panel') && !e.target.closest('[data-toggle-notif]')) {
    notifPanelOpen = false;
    const panel = document.getElementById('notif-panel');
    if (panel) panel.classList.remove('open');
  }
});

/* ── MARK ALL NOTIFICATIONS READ ── */
function markAllNotifRead() {
  fetch(window.BASE_URL+'/api/notifications.php?action=mark_all_read', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        const badge = document.querySelector('#notif-btn .badge');
        if (badge) badge.remove();
        Toast.show('All notifications marked as read', 'success');
      }
    });
}

/* ── POST REACTIONS ── */
function toggleReaction(postId, type, btn) {
  fetch(window.BASE_URL+'/api/posts.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({ action:'react', post_id: postId, reaction: type })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      btn.classList.toggle('liked', d.liked);
      const cnt = btn.querySelector('.react-count');
      if (cnt) cnt.textContent = d.count;
    }
  })
  .catch(() => Toast.show('Could not save reaction', 'error'));
}

/* ── COMMENTS ── */
function toggleComments(postId) {
  const section = document.getElementById('comments-' + postId);
  if (!section) return;
  const isHidden = section.style.display === 'none' || !section.style.display;
  section.style.display = isHidden ? 'block' : 'none';
  if (isHidden && section.dataset.loaded !== '1') {
    loadComments(postId);
  }
}

function loadComments(postId) {
  const section = document.getElementById('comments-' + postId);
  if (!section) return;
  section.dataset.loaded = '1';
  fetch(`${window.BASE_URL}/api/posts.php?action=comments&post_id=${postId}`, {headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        const list = section.querySelector('.comments-list');
        if (!list) return;
        list.innerHTML = d.comments.map(c => commentHTML(c)).join('');
      }
    });
}

function commentHTML(c) {
  return `<div class="comment-item">
    <div class="avatar avatar-sm" style="background:${c.color}">${c.initials}</div>
    <div class="comment-bubble">
      <div class="comment-author">${c.author}</div>
      <div class="comment-text">${escapeHtml(c.content)}</div>
      <div class="text-xs text-muted" style="margin-top:4px">${c.time_ago}</div>
    </div>
  </div>`;
}

function submitComment(form, postId) {
  const input = form.querySelector('.comment-input');
  const text = input.value.trim();
  if (!text) return;
  const csrf = form.querySelector('[name=csrf]')?.value || '';
  fetch(window.BASE_URL+'/api/posts.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({ action:'comment', post_id: postId, content: text, csrf })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      input.value = '';
      const list = document.querySelector(`#comments-${postId} .comments-list`);
      if (list) list.insertAdjacentHTML('beforeend', commentHTML(d.comment));
      // Update count
      const countEl = document.querySelector(`[data-post-id="${postId}"] .comment-toggle`);
      if (countEl && d.count !== undefined) countEl.textContent = d.count + ' comments';
    } else {
      Toast.show(d.message || 'Error posting comment', 'error');
    }
  })
  .catch(() => Toast.show('Network error', 'error'));
}

/* ── COMPOSE POST MODAL OPEN ── */
function openPostModal() { Modal.open('post-modal'); }
function openEventModal() { Modal.open('event-modal'); }

/* ── POST SUBMIT ── */
function submitPost(form) {
  const data = new FormData(form);
  fetch(window.BASE_URL+'/api/posts.php', { method: 'POST', body: data, headers: {'X-Requested-With':'XMLHttpRequest'} })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        Modal.close('post-modal');
        form.reset();
        Toast.show('Post published!', 'success');
        setTimeout(() => location.reload(), 800);
      } else {
        Toast.show(d.message || 'Error publishing post', 'error');
      }
    })
    .catch(() => Toast.show('Network error', 'error'));
  return false;
}

/* ── RSVP ── */
function toggleRSVP(eventId, btn) {
  fetch(window.BASE_URL+'/api/events.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({ action:'rsvp', event_id: eventId })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      btn.classList.toggle('going', d.going);
      btn.textContent = d.going ? 'Going' : 'RSVP Going';
      const countEl = btn.parentNode.querySelector('.attendee-count');
      if (countEl && d.count !== undefined) countEl.textContent = d.count + ' attending';
      Toast.show(d.going ? 'RSVP confirmed!' : 'RSVP removed', d.going ? 'success' : 'default');
    }
  })
  .catch(() => Toast.show('Network error', 'error'));
}

/* ── SPACE JOIN/LEAVE ── */
function toggleSpace(spaceId, btn) {
  fetch(window.BASE_URL+'/api/spaces.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({ action:'toggle', space_id: spaceId })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      btn.classList.toggle('joined', d.joined);
      btn.textContent = d.joined ? 'Joined' : '+ Join';
      const memberEl = btn.closest('.space-card')?.querySelector('.space-members');
      if (memberEl && d.count !== undefined) memberEl.textContent = d.count + ' members';
      Toast.show(d.joined ? `Joined space` : `Left space`, d.joined ? 'success' : 'default');
    }
  })
  .catch(() => Toast.show('Network error', 'error'));
}

/* ── FOLLOW/UNFOLLOW ── */
function toggleFollow(userId, btn) {
  fetch(window.BASE_URL+'/api/members.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({ action:'follow', user_id: userId })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      btn.classList.toggle('following', d.following);
      btn.textContent = d.following ? 'Following' : '+ Follow';
      Toast.show(d.following ? 'Now following!' : 'Unfollowed', d.following ? 'success' : 'default');
    }
  })
  .catch(() => Toast.show('Network error', 'error'));
}

/* ── MESSAGES (Real-time polling) ── */
let messagePolling = null;
let activeConvId = null;

function selectConversation(convId) {
  activeConvId = convId;
  document.querySelectorAll('.msg-thread').forEach(el => el.classList.toggle('active', parseInt(el.dataset.convId) === convId));
  loadMessages(convId);
  if (messagePolling) clearInterval(messagePolling);
  messagePolling = setInterval(() => loadMessages(convId), 4000);
}

function loadMessages(convId) {
  const lastId = document.querySelector('#chat-messages .msg-bubble:last-child')?.dataset?.msgId || 0;
  fetch(`${window.BASE_URL}/api/messages.php?action=load&conv_id=${convId}&after=${lastId}`, {headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r => r.json())
    .then(d => {
      if (d.success && d.messages.length > 0) {
        const container = document.getElementById('chat-messages');
        if (!container) return;
        const wasAtBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 5;
        d.messages.forEach(m => {
          container.insertAdjacentHTML('beforeend', messageBubbleHTML(m));
        });
        if (wasAtBottom) container.scrollTop = container.scrollHeight;
      }
    });
}

function messageBubbleHTML(m) {
  const ownClass = m.is_own ? 'own' : '';
  const avatar = m.is_own ? '' : `<div class="avatar avatar-sm" style="background:${m.color}">${m.initials}</div>`;
  return `<div class="msg-bubble ${ownClass}" data-msg-id="${m.id}">
    ${avatar}
    <div>
      <div class="bubble-content">${escapeHtml(m.content)}</div>
      <div class="bubble-time">${m.time}</div>
    </div>
  </div>`;
}

function sendMessage(convId) {
  const input = document.getElementById('chat-input-field');
  if (!input) return;
  const text = input.value.trim();
  if (!text) return;
  const csrf = document.querySelector('[name=csrf_token]')?.value || '';
  input.value = '';
  fetch(window.BASE_URL+'/api/messages.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({ action:'send', conv_id: convId, content: text, csrf })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      const container = document.getElementById('chat-messages');
      if (container) {
        container.insertAdjacentHTML('beforeend', messageBubbleHTML(d.message));
        container.scrollTop = container.scrollHeight;
      }
    } else {
      Toast.show('Could not send message', 'error');
      input.value = text;
    }
  });
}

function startConversation(userId) {
  fetch(window.BASE_URL+'/api/messages.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
    body: JSON.stringify({ action:'start', user_id: userId })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      window.location.href = window.BASE_URL + '/pages/messages.php?conv=' + d.conv_id;
    }
  });
}

/* ── ANALYTICS CHARTS ── */
function renderBarChart(containerId, data, options = {}) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const max = Math.max(...data.map(d => d.value), 1);
  container.innerHTML = data.map((d, i) => `
    <div class="bar-col">
      <div class="bar-fill ${d.gold ? 'gold' : ''}" style="height:0%" data-target="${Math.round(d.value/max*100)}" title="${d.label}: ${d.value}"></div>
      <span class="bar-lbl">${d.shortLabel || ''}</span>
    </div>
  `).join('');
  setTimeout(() => {
    container.querySelectorAll('.bar-fill').forEach(b => { b.style.height = b.dataset.target + '%'; });
  }, 150);
}

function renderProgressBars() {
  document.querySelectorAll('.progress-fill[data-target]').forEach(el => {
    setTimeout(() => { el.style.width = el.dataset.target + '%'; }, 200);
  });
}

/* ── FILTER CHIPS ── */
function initFilterChips(barSelector, callback) {
  const bar = document.querySelector(barSelector);
  if (!bar) return;
  bar.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', () => {
      bar.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      callback(chip.dataset.filter);
    });
  });
}

/* ── SEARCH (client-side highlight) ── */
function initSearch(inputSelector, itemsSelector, textSelector) {
  const input = document.querySelector(inputSelector);
  if (!input) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    document.querySelectorAll(itemsSelector).forEach(item => {
      const text = item.querySelector(textSelector)?.textContent.toLowerCase() || '';
      item.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
  });
}

/* ── UTILS ── */
function escapeHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatCount(n) {
  if (n >= 1000) return (n/1000).toFixed(1) + 'k';
  return n;
}

/* ── CALENDAR ── */
let _calYear, _calMonth, _calEventDays = [];

function initCalendar(year, month, eventDays) {
  _calYear = year; _calMonth = month; _calEventDays = eventDays;
  renderCalendar();
}

function renderCalendar() {
  const grid = document.getElementById('cal-grid');
  const header = document.getElementById('cal-month-header');
  if (!grid) return;

  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  if (header) header.textContent = monthNames[_calMonth] + ' ' + _calYear;

  grid.innerHTML = '';
  const days = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  days.forEach(d => {
    const el = document.createElement('div');
    el.className='cal-day-hdr'; el.textContent=d; grid.appendChild(el);
  });
  const firstDay = new Date(_calYear, _calMonth, 1).getDay();
  const totalDays = new Date(_calYear, _calMonth+1, 0).getDate();
  const today = new Date();
  for (let i=0;i<firstDay;i++) {
    const el=document.createElement('div'); el.className='cal-day empty'; grid.appendChild(el);
  }
  for (let d=1;d<=totalDays;d++) {
    const el = document.createElement('div');
    let cls = 'cal-day';
    if (today.getFullYear()===_calYear && today.getMonth()===_calMonth && today.getDate()===d) cls+=' today';
    if (_calEventDays.includes(d)) cls+=' has-event';
    el.className=cls; el.textContent=d;
    el.title = _calEventDays.includes(d) ? 'Event(s) on this day' : '';
    grid.appendChild(el);
  }
}

function calPrev() { _calMonth--; if (_calMonth<0){_calMonth=11;_calYear--;} renderCalendar(); }
function calNext() { _calMonth++; if (_calMonth>11){_calMonth=0;_calYear++;} renderCalendar(); }

/* ── ONLOAD ── */
document.addEventListener('DOMContentLoaded', () => {
  // Render progress bars on analytics pages
  renderProgressBars();
  // Chat scroll to bottom
  const chatMsgs = document.getElementById('chat-messages');
  if (chatMsgs) chatMsgs.scrollTop = chatMsgs.scrollHeight;
  // Auto-select first conversation
  const firstThread = document.querySelector('.msg-thread');
  if (firstThread && firstThread.dataset.convId) {
    selectConversation(parseInt(firstThread.dataset.convId));
  }
});
