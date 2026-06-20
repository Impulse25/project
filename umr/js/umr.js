const html = document.documentElement;
html.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
document.getElementById('themeToggle')?.addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
});
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
  document.getElementById('mainWrapper')?.classList.toggle('sidebar-collapsed');
});