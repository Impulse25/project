(function(){
  const saved = localStorage.getItem('svgtkAnalyticsTheme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  document.getElementById('themeBtn')?.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('svgtkAnalyticsTheme', next);
  });
})();
function exportTable(){
  const table = document.getElementById('reportTable');
  if(!table){ return; }
  const html = '<html><head><meta charset="utf-8"></head><body>' + table.outerHTML + '</body></html>';
  const blob = new Blob([html], {type:'application/vnd.ms-excel'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'svgtk_analytics_report.xls';
  a.click();
  URL.revokeObjectURL(a.href);
}
