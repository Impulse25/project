</div><!-- /portal-page-content -->
</div><!-- /portal-main-wrapper -->

<script>
const portalSidebar = document.getElementById('portalSidebar');
const portalMainWrapper = document.getElementById('portalMainWrapper');
document.getElementById('portalSidebarToggle').addEventListener('click', () => {
  portalSidebar.classList.toggle('collapsed');
  portalMainWrapper.classList.toggle('sidebar-collapsed');
  localStorage.setItem('sidebar_collapsed', portalSidebar.classList.contains('collapsed') ? '1' : '0');
});
if (localStorage.getItem('sidebar_collapsed') === '1') {
  portalSidebar.classList.add('collapsed');
  portalMainWrapper.classList.add('sidebar-collapsed');
}

const html = document.documentElement;
html.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
document.getElementById('portalThemeToggle').addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
});

function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape')
    document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
});

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const root = btn.closest('[data-tabs]') || document;
    root.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    root.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    const t = document.getElementById(btn.dataset.tab);
    if (t) t.classList.add('active');
  });
});
</script>
</body>
</html>