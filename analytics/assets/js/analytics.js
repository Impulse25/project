(function(){
  function applyTheme(theme){
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    localStorage.setItem('svgtkAnalyticsTheme', theme);
  }
  applyTheme(localStorage.getItem('theme') || localStorage.getItem('svgtkAnalyticsTheme') || 'light');
  document.getElementById('themeToggle')?.addEventListener('click', function(){
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
  });
  const sidebar = document.getElementById('sidebar');
  const main = document.getElementById('mainWrapper');
  document.getElementById('sidebarToggle')?.addEventListener('click', function(){
    setTimeout(()=>main?.classList.toggle('sidebar-collapsed', sidebar?.classList.contains('collapsed')), 0);
  });

  const data = window.analyticsChartData || {};
  const css = getComputedStyle(document.documentElement);
  const text = css.getPropertyValue('--color-text').trim() || '#0f172a';
  const muted = css.getPropertyValue('--color-text-muted').trim() || '#64748b';
  const line = css.getPropertyValue('--color-border').trim() || '#d8e1ee';
  const primary = css.getPropertyValue('--color-primary').trim() || '#2563eb';

  function canvas(id){ const c=document.getElementById(id); if(!c) return null; const dpr=window.devicePixelRatio||1; const rect=c.getBoundingClientRect(); c.width=Math.max(320, rect.width)*dpr; c.height=(c.getAttribute('height')||230)*dpr; const ctx=c.getContext('2d'); ctx.scale(dpr,dpr); return {c,ctx,w:c.width/dpr,h:c.height/dpr}; }
  function drawNoData(ctx,w,h){ ctx.fillStyle=muted; ctx.font='14px Arial'; ctx.textAlign='center'; ctx.fillText('Нет данных для графика', w/2, h/2); }
  function donut(id, values, labels){ const o=canvas(id); if(!o) return; const {ctx,w,h}=o; const total=values.reduce((a,b)=>a+b,0); if(!total){drawNoData(ctx,w,h);return;} const colors=['#16a34a','#2563eb','#f59e0b','#dc2626']; let start=-Math.PI/2; const cx=w/2, cy=h/2-8, r=Math.min(w,h)*.34; values.forEach((v,i)=>{ const a=v/total*Math.PI*2; ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,r,start,start+a); ctx.closePath(); ctx.fillStyle=colors[i]; ctx.fill(); start+=a; }); ctx.globalCompositeOperation='destination-out'; ctx.beginPath(); ctx.arc(cx,cy,r*.48,0,Math.PI*2); ctx.fill(); ctx.globalCompositeOperation='source-over'; labels.forEach((l,i)=>{ const x=w/2-70+i*48, y=h-22; ctx.fillStyle=colors[i]; ctx.fillRect(x,y,12,12); ctx.strokeStyle=text; ctx.strokeRect(x,y,12,12); ctx.fillStyle=text; ctx.font='13px Arial'; ctx.textAlign='left'; ctx.fillText(l,x+18,y+11); }); }
  function bars(id, labels, values, maxVal){ const o=canvas(id); if(!o) return; const {ctx,w,h}=o; if(!values.length || values.every(v=>!v)){drawNoData(ctx,w,h);return;} const pad={l:42,r:16,t:18,b:36}; const chartW=w-pad.l-pad.r, chartH=h-pad.t-pad.b; ctx.strokeStyle=line; ctx.fillStyle=muted; ctx.font='12px Arial'; ctx.textAlign='right'; for(let i=0;i<=5;i++){ const y=pad.t+chartH-(chartH*i/5); ctx.beginPath(); ctx.moveTo(pad.l,y); ctx.lineTo(w-pad.r,y); ctx.stroke(); ctx.fillText(Math.round(maxVal*i/5), pad.l-8, y+4); } const gap=16; const bw=Math.max(18,(chartW-gap*(values.length+1))/values.length); values.forEach((v,i)=>{ const x=pad.l+gap+i*(bw+gap); const bh=Math.max(0,Math.min(chartH, v/maxVal*chartH)); const y=pad.t+chartH-bh; const grd=ctx.createLinearGradient(0,y,0,pad.t+chartH); grd.addColorStop(0,primary); grd.addColorStop(1,'#0ea5e9'); ctx.fillStyle=grd; ctx.beginPath(); ctx.roundRect(x,y,bw,bh,7); ctx.fill(); ctx.fillStyle=text; ctx.font='12px Arial'; ctx.textAlign='center'; ctx.fillText(labels[i]||'', x+bw/2, h-12); }); }
  if (CanvasRenderingContext2D.prototype.roundRect === undefined) { CanvasRenderingContext2D.prototype.roundRect=function(x,y,w,h,r){this.moveTo(x+r,y);this.lineTo(x+w-r,y);this.quadraticCurveTo(x+w,y,x+w,y+r);this.lineTo(x+w,y+h-r);this.quadraticCurveTo(x+w,y+h,x+w-r,y+h);this.lineTo(x+r,y+h);this.quadraticCurveTo(x,y+h,x,y+h-r);this.lineTo(x,y+r);this.quadraticCurveTo(x,y,x+r,y);return this;}; }
  donut('gradesDonut', Object.values(data.gradeDistribution||{}), Object.keys(data.gradeDistribution||{}));
  bars('courseGradeChart', data.courseLabels||[], data.courseGradeData||[], 100);
  bars('courseAttendanceChart', data.courseLabels||[], data.courseAttendanceData||[], 100);
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
