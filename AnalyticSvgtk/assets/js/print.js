(function(){
    'use strict';

    function removeElements(root){
        root.querySelectorAll('.sidebar,.topbar,.actions,.no-print,script,.theme-toggle-btn').forEach(function(el){ el.remove(); });
        root.querySelectorAll('button').forEach(function(btn){ btn.remove(); });
    }

    function forceChartResize(){
        if (!window.Chart) return;
        try {
            Object.keys(Chart.instances || {}).forEach(function(key){
                if (Chart.instances[key] && typeof Chart.instances[key].resize === 'function') {
                    Chart.instances[key].resize();
                    Chart.instances[key].update('none');
                }
            });
        } catch(e) {}
    }

    function replaceChartsWithImages(cloneRoot, sourceRoot){
        var sourceCanvases = Array.prototype.slice.call((sourceRoot || document).querySelectorAll('canvas'));
        cloneRoot.querySelectorAll('canvas').forEach(function(canvas, index){
            try{
                var original = null;
                if (canvas.id) {
                    original = document.getElementById(canvas.id);
                }
                if (!original) {
                    original = sourceCanvases[index];
                }
                if (!original || !original.width || !original.height) {
                    return;
                }

                var img = document.createElement('img');
                img.className = 'print-chart-image';
                img.alt = 'Диаграмма отчёта';
                img.src = original.toDataURL('image/png', 1.0);
                canvas.parentNode.replaceChild(img, canvas);
            }catch(e){
                var box = document.createElement('div');
                box.className = 'print-chart-fallback';
                box.textContent = 'Диаграмма недоступна для печати';
                canvas.parentNode.replaceChild(box, canvas);
            }
        });
    }

    function waitForImages(doc, callback){
        var images = Array.prototype.slice.call(doc.images || []);
        if (!images.length) { callback(); return; }
        var left = images.length;
        var done = function(){
            left -= 1;
            if (left <= 0) callback();
        };
        images.forEach(function(img){
            if (img.complete) { done(); return; }
            img.onload = done;
            img.onerror = done;
        });
        setTimeout(callback, 1200);
    }

    function buildPrintHtml(contentHtml){
        return '<!doctype html><html lang="ru"><head><meta charset="UTF-8">' +
            '<title>Печать отчёта — СВГТК Портал</title>' +
            '<style>' +
            '@page{size:A4;margin:10mm;}' +
            '*{box-sizing:border-box;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}' +
            'html,body{margin:0;padding:0;background:#fff!important;color:#111827!important;font-family:Arial,"Times New Roman",sans-serif;font-size:11pt;line-height:1.25;}' +
            '.print-page{width:100%;max-width:190mm;margin:0 auto;background:#fff;color:#111827;}' +
            '.print-header{text-align:center;font-size:9pt;font-weight:700;margin:0 0 8px;color:#111827;}' +
            '.page-head{margin:0 0 10px!important;}' +
            '.page-title,.dashboard-title{font-size:20pt!important;line-height:1.15!important;margin:0 0 4px!important;color:#111827!important;font-weight:800;}' +
            '.page-subtitle,.dashboard-user{font-size:10pt!important;color:#334155!important;margin:0!important;}' +
            '.print-only{display:block!important;margin:0 0 8px!important;color:#111827!important;}' +
            '.print-only h2{font-size:14pt!important;margin:0 0 3px!important;color:#111827!important;}' +
            '.print-only p{font-size:9pt!important;margin:0 0 8px!important;color:#334155!important;}' +
            '.criteria,.kpi-grid{display:grid!important;grid-template-columns:repeat(3,1fr)!important;gap:5mm!important;margin:0 0 6mm!important;}' +
            '.grid-3,.grid-2,.dashboard-charts-only{display:grid!important;grid-template-columns:1fr!important;gap:6mm!important;margin:0!important;}' +
            '.criteria-card,.kpi-card,.chart-card,.table-card,.report-card,.profile-card,.risk-card{background:#fff!important;border:1px solid #cbd5e1!important;border-radius:5px!important;box-shadow:none!important;color:#111827!important;overflow:hidden!important;break-inside:avoid!important;page-break-inside:avoid!important;margin:0!important;}' +
            '.criteria-card,.kpi-card,.profile-card,.risk-card{padding:7px 9px!important;}' +
            '.criteria-title,.kpi-title{font-size:8.5pt!important;color:#64748b!important;margin:0 0 2px!important;text-transform:none!important;letter-spacing:0!important;}' +
            '.criteria-value,.kpi-value{font-size:13pt!important;color:#111827!important;font-weight:800!important;margin:0!important;}' +
            '.kpi-note{font-size:8.5pt!important;color:#475569!important;margin-top:2px!important;}' +
            '.kpi-icon,.status-icon,.year-badge{display:none!important;}' +
            '.card-head,.table-header{padding:7px 9px!important;background:#f8fafc!important;border-bottom:1px solid #cbd5e1!important;color:#111827!important;font-weight:800!important;font-size:10.5pt!important;}' +
            '.card-body{padding:7px!important;min-height:0!important;}' +
            '.chart-wrap,.chart-wrap-lg{height:auto!important;min-height:0!important;display:block!important;text-align:center!important;}' +
            '.print-chart-image{display:block!important;width:auto!important;max-width:165mm!important;max-height:105mm!important;height:auto!important;margin:2mm auto!important;object-fit:contain!important;}' +
            '.print-chart-fallback{padding:20px;text-align:center;border:1px dashed #cbd5e1;color:#64748b;}' +
            '.table-responsive{overflow:visible!important;width:100%!important;}' +
            'table{width:100%!important;border-collapse:collapse!important;margin:0!important;page-break-inside:auto!important;}' +
            'thead{display:table-header-group!important;}' +
            'tr{break-inside:avoid!important;page-break-inside:avoid!important;}' +
            'th,td{border:1px solid #cbd5e1!important;padding:5px 6px!important;color:#111827!important;background:#fff!important;font-size:8.5pt!important;text-align:left!important;}' +
            'th{background:#f1f5f9!important;font-weight:800!important;}' +
            '.badge,.attendance-badge,.grade-pill{display:inline-block!important;padding:3px 6px!important;border-radius:4px!important;font-weight:700!important;border:1px solid transparent!important;}' +
            '.badge-success{background:#dcfce7!important;color:#166534!important;border-color:#86efac!important;}' +
            '.badge-warning{background:#fef3c7!important;color:#92400e!important;border-color:#facc15!important;}' +
            '.badge-danger{background:#fee2e2!important;color:#991b1b!important;border-color:#fca5a5!important;}' +
            '.badge-blue{background:#dbeafe!important;color:#1e40af!important;border-color:#93c5fd!important;}' +
            'a{color:inherit!important;text-decoration:none!important;}' +
            '</style></head><body><div class="print-page"><div class="print-header">СВГТК Портал</div>' + contentHtml + '</div></body></html>';
    }

    window.printReport = function(){
        forceChartResize();
        var source = document.querySelector('main.content') || document.body;
        var clone = source.cloneNode(true);
        removeElements(clone);
        replaceChartsWithImages(clone, source);

        var win = window.open('', '_blank', 'width=900,height=700');
        if (!win) {
            window.print();
            return;
        }
        win.document.open();
        win.document.write(buildPrintHtml(clone.innerHTML));
        win.document.close();
        win.focus();
        waitForImages(win.document, function(){
            setTimeout(function(){
                win.print();
                setTimeout(function(){ win.close(); }, 900);
            }, 250);
        });
    };
})();
