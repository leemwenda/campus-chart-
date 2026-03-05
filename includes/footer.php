</div><!-- end .app-body -->

<div class="toast-container" id="toast-container"></div>

<script>window.BASE_URL = '<?= SITE_URL ?>';</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
// ── DARK MODE ──
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('kcachart_theme', theme);
  const sun  = document.getElementById('theme-icon-sun');
  const moon = document.getElementById('theme-icon-moon');
  if (sun && moon) {
    sun.style.display  = theme === 'dark' ? 'block' : 'none';
    moon.style.display = theme === 'dark' ? 'none'  : 'block';
  }
}
function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  applyTheme(current === 'dark' ? 'light' : 'dark');
}
// Apply saved theme on load
(function() {
  const saved = localStorage.getItem('kcachart_theme') || 'light';
  applyTheme(saved);
})();
// ── Sidebar toggle for mobile ──
function toggleSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebar-overlay');
  const open     = sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('visible', open);
  document.body.style.overflow = open ? 'hidden' : '';
}

window.addEventListener('resize', () => {
  if (window.innerWidth > 768) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('visible');
    document.body.style.overflow = '';
  }
});

function markNotifRead(id, el) {
  el.classList.remove('unread');
  fetch('<?= SITE_URL ?>/api/notifications.php?action=mark_read&id=' + id, {
    method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}
  });
}

document.getElementById('global-search')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    const q = e.target.value.trim();
    if (q) window.location.href = '<?= SITE_URL ?>/pages/members.php?q=' + encodeURIComponent(q);
  }
});
</script>
<?php if (isset($extraJs)): ?>
<script><?= $extraJs ?></script>
<?php endif; ?>
</body>
</html>
