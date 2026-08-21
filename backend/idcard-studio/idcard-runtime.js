(function (window, document) {
    'use strict';

    var config = window.ID_CARD_RUNTIME_CONFIG;
    var renderer = window.SchoolLiftIdCardRenderer;
    window.IDCARD_STUDIO_RENDER_READY = false;
    window.IDCARD_STUDIO_RENDER_ERROR = '';

    if (!config || !renderer || !window.fabric) {
        fail('The local ID card renderer could not be loaded.');
        return;
    }

    var canvases = [];
    var rendered = {front: [], back: []};
    var cancelled = false;
    var statusNode = document.getElementById('studio-runtime-status');
    var progressNode = document.getElementById('studio-runtime-progress');
    var cancelButton = document.querySelector('[data-runtime-action="cancel"]');
    var jobs = [];

    (config.cards || []).forEach(function (card, index) {
        jobs.push(renderCard('front', index, card, index));
    });
    if (config.printSettings && config.printSettings.duplex) {
        (config.cards || []).slice().reverse().forEach(function (card, index) {
            jobs.push(renderCard('back', index, card, (config.cards || []).length - index - 1));
        });
    }

    Promise.all(jobs).then(function () {
        window.IDCARD_STUDIO_RENDER_READY = true;
        document.body.setAttribute('data-idcard-render-ready', '1');
        setProgress(100, (config.cards || []).length + ' card' + ((config.cards || []).length === 1 ? '' : 's') + ' ready.');
        bindToolbar();
    }).catch(function (error) {
        fail(error && error.message ? error.message : 'The cards could not be rendered.');
    });

    function renderCard(side, index, card, originalIndex) {
        var node = document.getElementById('studio-card-' + side + '-' + index);
        if (!node) { return Promise.resolve(); }
        var canvas = new fabric.StaticCanvas(node, {renderOnAddRemove: false});
        canvases.push(canvas);
        rendered[side][originalIndex] = canvas;
        var documentData = withoutUnissuedCredential(config[side], card.bindings || {});
        return renderer.render(canvas, documentData, {
            widthMm: config.widthMm,
            heightMm: config.heightMm,
            bindings: card.bindings || {},
            assets: config.assets || {}
        });
    }

    /** Never print a dummy QR when a trusted credential has not been issued. */
    function withoutUnissuedCredential(documentData, bindings) {
        var copy = renderer.clone(documentData);
        copy.objects = (copy.objects || []).filter(function (object) {
            if ((object.type === 'qr' || object.type === 'barcode') && object.binding === 'attendance.credential') {
                return typeof bindings[object.binding] === 'string' && bindings[object.binding].length > 0;
            }
            if (object.type === 'image' && object.binding) {
                return typeof bindings[object.binding] === 'string' && bindings[object.binding].length > 0;
            }
            return true;
        });
        return copy;
    }

    function bindToolbar() {
        document.querySelectorAll('[data-runtime-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-runtime-action');
                if (action === 'print') { window.print(); }
                if (action === 'png') { exportPng(); }
                if (action === 'exact-pdf') { exportExactPdf(); }
                if (action === 'a4-pdf') { exportA4Parts(); }
                if (action === 'cancel') { cancelled = true; setProgress(progressNode.value, 'Cancelling after the current PDF part…'); }
                if (action === 'close') { closeView(); }
            });
        });
    }

    function exportPng() {
        if (!rendered.front[0]) { return; }
        var multiplier = 300 / (renderer.PX_PER_MM * 25.4);
        download(rendered.front[0].toDataURL({format: 'png', multiplier: multiplier, enableRetinaScaling: false}), fileTitle() + '-front-300dpi.png');
    }

    function exportExactPdf() {
        if (!window.jspdf || !window.jspdf.jsPDF || !rendered.front[0]) {
            fail('The local PDF library could not be loaded.');
            return;
        }
        var orientation = config.widthMm >= config.heightMm ? 'landscape' : 'portrait';
        var pdf = new window.jspdf.jsPDF({orientation: orientation, unit: 'mm', format: [config.widthMm, config.heightMm], compress: true});
        addCanvas(pdf, rendered.front[0], 0, 0, config.widthMm, config.heightMm);
        if (config.printSettings && config.printSettings.duplex && rendered.back[0]) {
            pdf.addPage([config.widthMm, config.heightMm], orientation);
            addCanvas(pdf, rendered.back[0], 0, 0, config.widthMm, config.heightMm);
        }
        pdf.save(fileTitle() + '-exact-card.pdf');
    }

    /**
     * Split large real-record batches into deterministic numbered PDFs. Each
     * part is completed and downloaded before the next part starts, so the
     * operator can cancel without corrupting an already-created file.
     */
    function exportA4Parts() {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            fail('The local PDF library could not be loaded.');
            return;
        }
        var count = (config.cards || []).length;
        if (!count) { return; }
        cancelled = false;
        cancelButton.disabled = false;
        var limit = Math.max(1, Math.min(250, parseInt((config.printSettings || {}).maxCardsPerPart, 10) || 100));
        var parts = [];
        for (var offset = 0; offset < count; offset += limit) {
            parts.push({start: offset, end: Math.min(count, offset + limit)});
        }
        var index = 0;
        function nextPart() {
            if (cancelled || index >= parts.length) {
                cancelButton.disabled = true;
                setProgress(cancelled ? Math.round(index / parts.length * 100) : 100, cancelled ? 'Export cancelled.' : 'A4 PDF export complete.');
                return;
            }
            var part = parts[index];
            setProgress(Math.round(index / parts.length * 100), 'Building PDF part ' + (index + 1) + ' of ' + parts.length + '…');
            window.setTimeout(function () {
                buildA4Part(part.start, part.end, index + 1, parts.length);
                index += 1;
                setProgress(Math.round(index / parts.length * 100), 'Downloaded PDF part ' + index + ' of ' + parts.length + '.');
                window.setTimeout(nextPart, 40);
            }, 40);
        }
        nextPart();
    }

    function buildA4Part(start, end, partNumber, totalParts) {
        var settings = config.printSettings || {};
        var orientation = settings.orientation === 'landscape' ? 'landscape' : 'portrait';
        var pageWidth = orientation === 'landscape' ? 297 : 210;
        var pageHeight = orientation === 'landscape' ? 210 : 297;
        var margin = clamp(parseFloat(settings.marginMm) || 0, 0, 30);
        var gap = clamp(parseFloat(settings.gapMm) || 0, 0, 30);
        var columns = Math.max(1, Math.floor((pageWidth - margin * 2 + gap) / (config.widthMm + gap)));
        var rows = Math.max(1, Math.floor((pageHeight - margin * 2 + gap) / (config.heightMm + gap)));
        var perPage = columns * rows;
        var pdf = new window.jspdf.jsPDF({orientation: orientation, unit: 'mm', format: 'a4', compress: true});
        var firstPage = true;
        for (var pageStart = start; pageStart < end; pageStart += perPage) {
            if (!firstPage) { pdf.addPage('a4', orientation); }
            firstPage = false;
            var pageEnd = Math.min(end, pageStart + perPage);
            drawCardPage(pdf, rendered.front, pageStart, pageEnd, columns, margin, gap, false);
            if (settings.duplex) {
                pdf.addPage('a4', orientation);
                drawCardPage(pdf, rendered.back, pageStart, pageEnd, columns, margin, gap, true);
            }
        }
        var suffix = totalParts > 1 ? '-part-' + pad(partNumber) + '-of-' + pad(totalParts) : '';
        pdf.save(fileTitle() + '-a4' + suffix + '.pdf');
    }

    function drawCardPage(pdf, source, start, end, columns, margin, gap, back) {
        var settings = config.printSettings || {};
        var count = end - start;
        for (var slot = 0; slot < count; slot += 1) {
            var row = Math.floor(slot / columns);
            var column = slot % columns;
            var actualRow = back && settings.flip === 'short-edge' ? Math.floor((count - 1 - slot) / columns) : row;
            var actualColumn = back && settings.flip !== 'short-edge' ? columns - 1 - column : column;
            var x = margin + actualColumn * (config.widthMm + gap);
            var y = margin + actualRow * (config.heightMm + gap);
            addCanvas(pdf, source[start + slot], x, y, config.widthMm, config.heightMm);
            if (settings.cropMarks) { cropMarks(pdf, x, y, config.widthMm, config.heightMm); }
        }
    }

    function addCanvas(pdf, canvas, x, y, width, height) {
        if (!canvas) { return; }
        var multiplier = 300 / (renderer.PX_PER_MM * 25.4);
        pdf.addImage(canvas.toDataURL({format: 'png', multiplier: multiplier, enableRetinaScaling: false}), 'PNG', x, y, width, height, undefined, 'FAST');
    }

    function cropMarks(pdf, x, y, width, height) {
        var length = 2;
        pdf.setDrawColor(80); pdf.setLineWidth(.15);
        pdf.line(x - length, y, x - .4, y); pdf.line(x, y - length, x, y - .4);
        pdf.line(x + width + .4, y, x + width + length, y); pdf.line(x + width, y - length, x + width, y - .4);
        pdf.line(x - length, y + height, x - .4, y + height); pdf.line(x, y + height + .4, x, y + height + length);
        pdf.line(x + width + .4, y + height, x + width + length, y + height); pdf.line(x + width, y + height + .4, x + width, y + height + length);
    }

    function setProgress(value, message) {
        if (progressNode) { progressNode.value = value; }
        if (statusNode) { statusNode.textContent = message; }
    }

    function download(url, name) {
        var link = document.createElement('a');
        link.href = url; link.download = name;
        document.body.appendChild(link); link.click(); document.body.removeChild(link);
    }

    function closeView() {
        if (window.parent !== window && window.frameElement && window.frameElement.parentNode) {
            window.frameElement.parentNode.removeChild(window.frameElement);
        } else {
            window.close();
        }
    }

    function fileTitle() {
        return String(config.title || 'id-card').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'id-card';
    }

    function pad(number) { return ('0' + number).slice(-2); }
    function clamp(value, minimum, maximum) { return Math.min(maximum, Math.max(minimum, value)); }

    function fail(message) {
        window.IDCARD_STUDIO_RENDER_ERROR = message;
        var error = document.createElement('div');
        error.className = 'studio-runtime-error';
        error.textContent = message;
        document.body.insertBefore(error, document.body.firstChild);
        document.body.setAttribute('data-idcard-render-ready', 'error');
        setProgress(0, message);
    }
}(window, document));
