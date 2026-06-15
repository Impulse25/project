(function(){
    function cssVar(name, fallback){
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }
    function themeColors(){
        return {
            text: cssVar('--text', '#dbeafe'),
            muted: cssVar('--muted', '#8fa3bf'),
            grid: cssVar('--line', 'rgba(148,163,184,.12)'),
            blue: '#2f7df6', blue2: '#1d5fd0', green: '#22c55e', light: '#cbd5e1', orange: '#f59e0b', red: '#ef4444', yellow: '#eab308'
        };
    }

    if (typeof Chart === 'undefined') {
        window.addEventListener('load', () => {
            document.querySelectorAll('.chart-wrap,.chart-wrap-lg').forEach(el => {
                if (!el.querySelector('canvas')) return;
                const msg = document.createElement('div');
                msg.style.color = cssVar('--muted', '#9fb0c7');
                msg.style.padding = '24px';
                msg.textContent = 'Для отображения диаграмм подключите интернет или локальный Chart.js.';
                el.appendChild(msg);
            });
        });
        return;
    }

    Chart.defaults.font.family = 'Inter, Arial, sans-serif';
    Chart.defaults.plugins.legend.labels.boxWidth = 28;

    function makeGradient(ctx, h){
        const g = ctx.createLinearGradient(0,0,0,h || 260);
        g.addColorStop(0, '#2f7df6');
        g.addColorStop(1, '#1454c8');
        return g;
    }
    function commonScales(){
        const c = themeColors();
        return { x:{ grid:{ color:c.grid }, ticks:{ color:c.muted } }, y:{ beginAtZero:true, grid:{ color:c.grid }, ticks:{ color:c.muted } } };
    }
    function commonLegend(){
        const c = themeColors();
        return { labels:{ color:c.text } };
    }

    window.SVGTKCharts = {
        doughnut(id, labels, data){
            const el = document.getElementById(id); if(!el) return null;
            const c = themeColors();
            return new Chart(el, {
                type:'doughnut',
                data:{ labels, datasets:[{ data, backgroundColor:[c.green, c.blue, c.orange, c.red, c.yellow], borderColor:document.documentElement.getAttribute('data-theme') === 'light' ? '#ffffff' : '#e5eefb', borderWidth:2, hoverOffset:6 }] },
                options:{ responsive:true, maintainAspectRatio:false, cutout:'48%', plugins:{ legend:{ position:'top', labels:{ color:c.text } } } }
            });
        },
        bar(id, labels, data, label){
            const el = document.getElementById(id); if(!el) return null;
            const c = themeColors();
            const ctx = el.getContext('2d');
            return new Chart(el, {
                type:'bar',
                data:{ labels, datasets:[{ label: label || 'Значение', data, backgroundColor: makeGradient(ctx, 280), borderColor:'#60a5fa', borderWidth:1, borderRadius:3 }] },
                options:{ responsive:true, maintainAspectRatio:false, scales:commonScales(), plugins:{ legend:commonLegend() } }
            });
        },
        groupedBar(id, labels, present, total){
            const el = document.getElementById(id); if(!el) return null;
            const c = themeColors();
            return new Chart(el, {
                type:'bar',
                data:{ labels, datasets:[
                    { label:'Посещено занятий', data:present, backgroundColor:c.green, borderColor:'#86efac', borderWidth:1, borderRadius:3 },
                    { label:'Всего занятий', data:total, backgroundColor:c.light, borderColor:'#e2e8f0', borderWidth:1, borderRadius:3 }
                ]},
                options:{ responsive:true, maintainAspectRatio:false, scales:commonScales(), plugins:{ legend:commonLegend() } }
            });
        },
        line(id, labels, data, label){
            const el = document.getElementById(id); if(!el) return null;
            const c = themeColors();
            return new Chart(el, {
                type:'line',
                data:{ labels, datasets:[{ label: label || 'Динамика', data, borderColor:c.green, backgroundColor:'rgba(34,197,94,.14)', fill:true, tension:.35, pointRadius:4 }] },
                options:{ responsive:true, maintainAspectRatio:false, scales:commonScales(), plugins:{ legend:commonLegend() } }
            });
        }
    };

    window.exportTableToExcel = function(tableId, filename){
        const table = document.getElementById(tableId);
        if(!table) return;
        const html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>' + table.outerHTML + '</body></html>';
        const blob = new Blob(['\ufeff' + html], {type:'application/vnd.ms-excel;charset=utf-8;'});
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename || 'svgtk-report.xls';
        link.click();
        URL.revokeObjectURL(link.href);
    };

})();
