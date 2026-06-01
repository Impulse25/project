/**
 * assets/app.js
 * Общий JS для всех страниц портала:
 *   - переключение темы (light / dark)
 *   - сворачивание / разворачивание сайдбара
 */

(function () {
  // ── Тема ──────────────────────────────────────────────────────────────────
  const html = document.documentElement;
  html.setAttribute('data-theme', localStorage.getItem('theme') || 'light');

  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
    });
  }

  // ── Сайдбар ───────────────────────────────────────────────────────────────
  const sidebar     = document.getElementById('sidebar');
  const mainWrapper = document.getElementById('mainWrapper');
  const sidebarToggle = document.getElementById('sidebarToggle');

  if (sidebar && sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      if (mainWrapper) mainWrapper.classList.toggle('sidebar-collapsed');
    });
  }
})();
