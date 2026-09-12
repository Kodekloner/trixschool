(function (window, document) {
    'use strict';

    var config = window.ID_CARD_STUDIO_CONFIG;
    var renderer = window.SchoolLiftIdCardRenderer;
    var app = document.getElementById('idstudio-app');
    if (!config || !renderer || !app || !window.fabric) {
        showFatal('The local ID Card Studio browser libraries could not be loaded. Reload the page or contact support.');
        return;
    }

    var canvas = new fabric.Canvas('idstudio-canvas', {
        preserveObjectStacking: true,
        selection: true,
        stopContextMenu: true,
        fireRightClick: true,
        allowTouchScrolling: true
    });
    var state = {
        side: 'front',
        documents: {
            front: renderer.clone(config.front),
            back: renderer.clone(config.back)
        },
        widthMm: number(config.widthMm, 85.6),
        heightMm: number(config.heightMm, 53.98),
        print: renderer.clone(config.printSettings || {}),
        checksum: String(config.checksum || ''),
        assets: {},
        loading: false,
        dirty: false,
        savePromise: null,
        autosaveTimer: null,
        zoom: 1.25,
        zoomMode: 'fit',
        clipboard: null,
        spaceDown: false,
        panning: false,
        panOrigin: null,
        openPanel: '',
        panelTrigger: null,
        resizeTimer: null,
        smartGuides: [],
        history: {
            front: {entries: [], index: -1},
            back: {entries: [], index: -1}
        }
    };

    (config.assets || []).forEach(function (asset) {
        state.assets[asset.id] = asset.url;
    });

    var elements = {
        alert: document.getElementById('idstudio-alert'),
        title: document.getElementById('idstudio-title'),
        width: document.getElementById('idstudio-width'),
        height: document.getElementById('idstudio-height'),
        saveState: document.getElementById('idstudio-save-state'),
        sideStatus: document.getElementById('idstudio-side-status'),
        selectionStatus: document.getElementById('idstudio-selection-status'),
        dimensionsStatus: document.getElementById('idstudio-dimensions-status'),
        stage: document.getElementById('idstudio-stage'),
        safeArea: document.getElementById('idstudio-safe-area'),
        bleedArea: document.getElementById('idstudio-bleed-area'),
        scroll: document.getElementById('idstudio-scroll'),
        zoom: document.getElementById('idstudio-zoom'),
        zoomLabel: document.getElementById('idstudio-zoom-label'),
        grid: document.getElementById('idstudio-grid-toggle'),
        snap: document.getElementById('idstudio-snap-toggle'),
        guides: document.getElementById('idstudio-guides-toggle'),
        bleed: document.getElementById('idstudio-bleed-toggle'),
        properties: document.getElementById('idstudio-properties'),
        noSelection: document.getElementById('idstudio-no-selection'),
        textProperties: document.getElementById('idstudio-text-properties'),
        shapeProperties: document.getElementById('idstudio-shape-properties'),
        imageFitRow: document.getElementById('idstudio-image-fit-row'),
        layers: document.getElementById('idstudio-layers'),
        assets: document.getElementById('idstudio-assets'),
        assetInput: document.getElementById('idstudio-asset-input'),
        printPaper: document.getElementById('idstudio-print-paper'),
        printMargin: document.getElementById('idstudio-print-margin'),
        printGap: document.getElementById('idstudio-print-gap'),
        printCrop: document.getElementById('idstudio-print-crop'),
        printDuplex: document.getElementById('idstudio-print-duplex')
    };

    var propertyInputs = {
        name: document.getElementById('idstudio-object-name'),
        x: document.getElementById('idstudio-prop-x'),
        y: document.getElementById('idstudio-prop-y'),
        width: document.getElementById('idstudio-prop-width'),
        height: document.getElementById('idstudio-prop-height'),
        rotation: document.getElementById('idstudio-prop-rotation'),
        opacity: document.getElementById('idstudio-prop-opacity'),
        text: document.getElementById('idstudio-prop-text'),
        binding: document.getElementById('idstudio-prop-binding'),
        font: document.getElementById('idstudio-prop-font'),
        fontSize: document.getElementById('idstudio-prop-font-size'),
        fill: document.getElementById('idstudio-prop-fill'),
        align: document.getElementById('idstudio-prop-align'),
        bold: document.getElementById('idstudio-prop-bold'),
        italic: document.getElementById('idstudio-prop-italic'),
        lineHeight: document.getElementById('idstudio-prop-line-height'),
        charSpacing: document.getElementById('idstudio-prop-char-spacing'),
        shapeFill: document.getElementById('idstudio-prop-shape-fill'),
        stroke: document.getElementById('idstudio-prop-stroke'),
        strokeWidth: document.getElementById('idstudio-prop-stroke-width'),
        radius: document.getElementById('idstudio-prop-radius'),
        fit: document.getElementById('idstudio-prop-fit')
    };

    setViewportHeight();
    syncResponsivePanels();
    initialiseBindings();
    initialisePrintSettings();
    bindUi();
    bindCanvas();
    renderAssets(config.assets || []);
    seedHistory();
    renderSide('front').then(function () {
        app.setAttribute('data-studio-ready', '1');
        setSaveState('Draft loaded');
        window.requestAnimationFrame(fitCanvasToViewport);
    });

    function bindUi() {
        document.querySelectorAll('[data-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                runAction(button.getAttribute('data-action'));
            });
        });
        document.querySelectorAll('[data-side]').forEach(function (button) {
            button.addEventListener('click', function () {
                switchSide(button.getAttribute('data-side'));
            });
        });
        document.querySelectorAll('[data-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                addObject(button.getAttribute('data-add'));
                closePanelAfterToolUse();
            });
        });
        document.querySelectorAll('[data-add-binding]').forEach(function (button) {
            button.addEventListener('click', function () {
                addTextBinding(button.getAttribute('data-add-binding'));
                closePanelAfterToolUse();
            });
        });
        document.querySelectorAll('[data-add-binding-image]').forEach(function (button) {
            button.addEventListener('click', function () {
                addImageBinding(button.getAttribute('data-add-binding-image'));
                closePanelAfterToolUse();
            });
        });
        document.querySelectorAll('[data-add-binding-qr]').forEach(function (button) {
            button.addEventListener('click', function () {
                addQr(button.getAttribute('data-add-binding-qr'));
                closePanelAfterToolUse();
            });
        });
        document.querySelectorAll('[data-align]').forEach(function (button) {
            button.addEventListener('click', function () {
                alignSelection(button.getAttribute('data-align'));
            });
        });
        document.querySelectorAll('[data-panel-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                togglePanel(button.getAttribute('data-panel-toggle'), button);
            });
        });
        document.querySelectorAll('[data-panel-dismiss]').forEach(function (button) {
            button.addEventListener('click', function () { closePanel(true); });
        });

        elements.title.addEventListener('input', markDirty);
        elements.width.addEventListener('change', changeDimensions);
        elements.height.addEventListener('change', changeDimensions);
        elements.zoom.addEventListener('input', function () {
            setZoom(number(elements.zoom.value, 125) / 100, 'manual');
        });
        elements.guides.addEventListener('change', function () {
            elements.safeArea.classList.toggle('is-hidden', !elements.guides.checked);
        });
        elements.bleed.addEventListener('change', function () {
            elements.bleedArea.classList.toggle('is-hidden', !elements.bleed.checked);
        });
        elements.grid.addEventListener('change', function () { canvas.requestRenderAll(); });
        [elements.printPaper, elements.printMargin, elements.printGap, elements.printCrop, elements.printDuplex].forEach(function (input) {
            input.addEventListener('change', function () {
                collectPrintSettings();
                markDirty();
            });
        });

        Object.keys(propertyInputs).forEach(function (key) {
            var input = propertyInputs[key];
            if (!input) { return; }
            input.addEventListener(input.type === 'checkbox' || input.tagName === 'SELECT' ? 'change' : 'input', applyProperties);
        });

        elements.assetInput.addEventListener('change', uploadAsset);
        document.addEventListener('keydown', keyboardShortcut);
        document.addEventListener('keyup', keyboardKeyup);
        elements.scroll.addEventListener('mousedown', beginPan);
        elements.scroll.addEventListener('wheel', zoomWithWheel, {passive: false});
        document.addEventListener('mousemove', movePan);
        document.addEventListener('mouseup', endPan);
        window.addEventListener('resize', scheduleWorkspaceResize);
        window.addEventListener('orientationchange', scheduleWorkspaceResize);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleWorkspaceResize);
        }
        if (window.ResizeObserver) {
            state.workspaceObserver = new window.ResizeObserver(function () { scheduleCanvasFit(); });
            state.workspaceObserver.observe(elements.scroll);
        }
        window.addEventListener('beforeunload', function (event) {
            if (state.dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    }

    function isPanelCollapsible(name) {
        return name === 'view' ? window.innerWidth <= 767 : window.innerWidth <= 1199;
    }

    function togglePanel(name, trigger) {
        if (!isPanelCollapsible(name)) { return; }
        if (state.openPanel === name) {
            closePanel(true);
            return;
        }
        state.openPanel = name;
        state.panelTrigger = trigger || null;
        app.setAttribute('data-open-panel', name);
        syncResponsivePanels();

        var panel = document.querySelector('[data-studio-panel="' + name + '"]');
        var closeButton = panel ? panel.querySelector('[data-panel-dismiss]') : null;
        if (closeButton) {
            window.setTimeout(function () { closeButton.focus(); }, 210);
        }
    }

    function closePanel(restoreFocus) {
        var trigger = state.panelTrigger;
        state.openPanel = '';
        state.panelTrigger = null;
        app.removeAttribute('data-open-panel');
        syncResponsivePanels();
        if (restoreFocus && trigger && document.documentElement.contains(trigger)) {
            trigger.focus();
        }
    }

    function closePanelAfterToolUse() {
        if (state.openPanel === 'tools') {
            closePanel(false);
        }
    }

    function syncResponsivePanels() {
        var openPanel = app.getAttribute('data-open-panel') || '';
        if (openPanel && !isPanelCollapsible(openPanel)) {
            app.removeAttribute('data-open-panel');
            openPanel = '';
            state.openPanel = '';
            state.panelTrigger = null;
        } else {
            state.openPanel = openPanel;
        }

        document.querySelectorAll('[data-studio-panel]').forEach(function (panel) {
            var name = panel.getAttribute('data-studio-panel');
            if (isPanelCollapsible(name)) {
                panel.setAttribute('aria-hidden', openPanel === name ? 'false' : 'true');
                if (openPanel === name) {
                    panel.removeAttribute('inert');
                } else {
                    panel.setAttribute('inert', '');
                }
            } else {
                panel.removeAttribute('aria-hidden');
                panel.removeAttribute('inert');
            }
        });
        document.querySelectorAll('[data-panel-toggle]').forEach(function (button) {
            var name = button.getAttribute('data-panel-toggle');
            button.setAttribute('aria-expanded', isPanelCollapsible(name) && openPanel === name ? 'true' : 'false');
        });
    }

    function setViewportHeight() {
        var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
        if (viewportHeight > 0) {
            document.documentElement.style.setProperty('--idstudio-viewport-height', Math.round(viewportHeight) + 'px');
        }
    }

    function scheduleWorkspaceResize() {
        setViewportHeight();
        syncResponsivePanels();
        scheduleCanvasFit();
    }

    function scheduleCanvasFit() {
        window.clearTimeout(state.resizeTimer);
        if (state.zoomMode !== 'fit' || app.getAttribute('data-studio-ready') !== '1') { return; }
        state.resizeTimer = window.setTimeout(fitCanvasToViewport, 80);
    }

    function bindCanvas() {
        canvas.on('selection:created', selectionChanged);
        canvas.on('selection:updated', selectionChanged);
        canvas.on('selection:cleared', updateSelectionPanel);
        canvas.on('object:moving', snapObject);
        canvas.on('object:modified', objectChanged);
        canvas.on('mouse:up', function () { state.smartGuides = []; canvas.requestRenderAll(); });
        canvas.on('object:added', function () {
            if (!state.loading) { objectChanged(); }
        });
        canvas.on('object:removed', function () {
            if (!state.loading) { objectChanged(); }
        });
        canvas.on('after:render', function () { drawGrid(); drawSmartGuides(); });
    }

    function selectionChanged() {
        var active = canvas.getActiveObject();
        if (active && active.type !== 'activeSelection' && active.studioGroup) {
            var members = canvas.getObjects().filter(function (object) {
                return object.selectable !== false && object.studioGroup === active.studioGroup;
            });
            if (members.length > 1) {
                canvas.setActiveObject(new fabric.ActiveSelection(members, {canvas: canvas}));
                canvas.requestRenderAll();
            }
        }
        updateSelectionPanel();
    }

    function renderSide(side) {
        state.side = side;
        state.loading = true;
        updateSideButtons();
        return renderer.render(canvas, state.documents[side], {
            widthMm: state.widthMm,
            heightMm: state.heightMm,
            bindings: config.sampleData || {},
            assets: state.assets
        }).then(function () {
            state.loading = false;
            setZoom(state.zoom, state.zoomMode);
            updateLayers();
            updateSelectionPanel();
            elements.sideStatus.textContent = side === 'front' ? 'Front side' : 'Back side';
            if (state.zoomMode === 'fit') {
                window.requestAnimationFrame(fitCanvasToViewport);
            }
        }).catch(function (error) {
            state.loading = false;
            showMessage(error.message || 'The design could not be rendered.', 'danger');
        });
    }

    function switchSide(side) {
        if (side !== 'front' && side !== 'back' || side === state.side) { return; }
        captureCurrent(true);
        renderSide(side);
    }

    function captureCurrent(push) {
        state.documents[state.side] = renderer.toCanonical(
            canvas,
            state.side,
            state.documents[state.side].background
        );
        if (push) { pushHistory(state.side); }
        return state.documents[state.side];
    }

    function seedHistory() {
        ['front', 'back'].forEach(function (side) {
            var snapshot = JSON.stringify(state.documents[side]);
            state.history[side] = {entries: [snapshot], index: 0};
        });
    }

    function pushHistory(side) {
        var history = state.history[side];
        var snapshot = JSON.stringify(state.documents[side]);
        if (history.entries[history.index] === snapshot) { return; }
        history.entries = history.entries.slice(0, history.index + 1);
        history.entries.push(snapshot);
        if (history.entries.length > 101) { history.entries.shift(); }
        history.index = history.entries.length - 1;
    }

    function undo() {
        captureCurrent(false);
        var history = state.history[state.side];
        if (history.index <= 0) { return; }
        history.index -= 1;
        state.documents[state.side] = JSON.parse(history.entries[history.index]);
        renderSide(state.side);
        markDirty();
    }

    function redo() {
        var history = state.history[state.side];
        if (history.index >= history.entries.length - 1) { return; }
        history.index += 1;
        state.documents[state.side] = JSON.parse(history.entries[history.index]);
        renderSide(state.side);
        markDirty();
    }

    function objectChanged() {
        if (state.loading) { return; }
        captureCurrent(true);
        updateLayers();
        updateSelectionPanel();
        markDirty();
    }

    function markDirty() {
        state.dirty = true;
        setSaveState('Unsaved changes');
        clearTimeout(state.autosaveTimer);
        state.autosaveTimer = window.setTimeout(function () { saveDraft(true); }, 2500);
    }

    function addObject(type) {
        if (type === 'text') {
            addCanonical({
                id: nextId('text'), type: 'text', binding: '', text: 'Double-click to edit', prefix: '', suffix: '',
                x: 8, y: 8, width: Math.min(40, state.widthMm - 16), height: 7, rotation: 0,
                opacity: 1, visible: true, locked: false, fontFamily: 'Arial', fontSize: 3,
                fontWeight: 'normal', fontStyle: 'normal', align: 'left', fill: '#111827',
                lineHeight: 1.16, charSpacing: 0
            });
            return;
        }
        if (type === 'qr') { addQr('attendance.credential'); return; }
        if (type === 'barcode') {
            var idBinding = config.subjectType === 'staff' ? 'staff.employee_id' : 'student.admission_no';
            addCanonical(codeObject('barcode', idBinding));
            return;
        }
        var width = type === 'line' ? 30 : 22;
        var height = type === 'line' ? 0.8 : 15;
        addCanonical({
            id: nextId(type), type: type, x: 8, y: 8, width: width, height: height,
            rotation: 0, opacity: 1, visible: true, locked: false,
            fill: type === 'line' ? 'transparent' : '#e2e8f0', stroke: '#64748b',
            strokeWidth: 0.25, radius: type === 'rect' ? 1 : 0
        });
    }

    function addTextBinding(binding) {
        addCanonical({
            id: nextId(binding.split('.').pop()), type: 'text', binding: binding, text: '', prefix: '', suffix: '',
            x: 8, y: 8, width: Math.min(45, state.widthMm - 16), height: 7, rotation: 0,
            opacity: 1, visible: true, locked: false, fontFamily: 'Arial', fontSize: 3,
            fontWeight: 'normal', fontStyle: 'normal', align: 'left', fill: '#111827',
            lineHeight: 1.16, charSpacing: 0
        });
    }

    function addImageBinding(binding) {
        addCanonical({
            id: nextId(binding.split('.').pop()), type: 'image', binding: binding, assetId: null,
            x: 8, y: 8, width: 24, height: 27, rotation: 0, opacity: 1,
            visible: true, locked: false, fit: 'cover', radius: 2,
            stroke: '#cbd5e1', strokeWidth: 0.25
        });
    }

    function addQr(binding) {
        addCanonical(codeObject('qr', binding));
    }

    function codeObject(type, binding) {
        return {
            id: nextId(type), type: type, binding: binding,
            x: 8, y: 8, width: type === 'qr' ? 18 : 32, height: type === 'qr' ? 18 : 12,
            rotation: 0, opacity: 1, visible: true, locked: false,
            foreground: '#111827', background: '#ffffff', label: ''
        };
    }

    function addAssetObject(asset) {
        addCanonical({
            id: nextId('image'), type: 'image', binding: '', assetId: parseInt(asset.id, 10),
            x: 8, y: 8, width: 28, height: 22, rotation: 0, opacity: 1,
            visible: true, locked: false, fit: 'contain', radius: 0,
            stroke: 'transparent', strokeWidth: 0
        });
    }

    function addCanonical(object) {
        object.x = Math.max(0, Math.min(object.x, state.widthMm - object.width));
        object.y = Math.max(0, Math.min(object.y, state.heightMm - object.height));
        renderer.objectToFabric(object, {
            bindings: config.sampleData || {},
            assets: state.assets
        }).then(function (fabricObject) {
            canvas.add(fabricObject);
            canvas.setActiveObject(fabricObject);
            canvas.requestRenderAll();
            objectChanged();
        });
    }

    function nextId(prefix) {
        var base = String(prefix || 'object').toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-|-$/g, '') || 'object';
        var used = {};
        canvas.getObjects().forEach(function (object) { used[object.studioId] = true; });
        var index = 1;
        while (used[base + '-' + index]) { index += 1; }
        return base + '-' + index;
    }

    function updateSelectionPanel() {
        var active = canvas.getActiveObject();
        var single = active && active.type !== 'activeSelection';
        elements.properties.hidden = !single;
        elements.noSelection.hidden = !!single;
        elements.selectionStatus.textContent = active
            ? (active.type === 'activeSelection' ? active.getObjects().length + ' objects selected' : (active.studioId || 'Object selected'))
            : 'Nothing selected';
        if (!single) {
            updateLayers();
            return;
        }
        var data = active.studioData || {};
        propertyInputs.name.value = active.studioId || '';
        propertyInputs.x.value = round(active.left / renderer.PX_PER_MM, 2);
        propertyInputs.y.value = round(active.top / renderer.PX_PER_MM, 2);
        propertyInputs.width.value = round(active.getScaledWidth() / renderer.PX_PER_MM, 2);
        propertyInputs.height.value = round(active.getScaledHeight() / renderer.PX_PER_MM, 2);
        propertyInputs.rotation.value = round(active.angle || 0, 1);
        propertyInputs.opacity.value = round(active.opacity, 2);
        var isText = active.studioType === 'text';
        var isShape = ['rect', 'ellipse', 'line', 'image'].indexOf(active.studioType) !== -1;
        elements.textProperties.hidden = !isText;
        elements.shapeProperties.hidden = !isShape;
        elements.imageFitRow.hidden = active.studioType !== 'image';
        if (isText) {
            propertyInputs.text.value = data.text || '';
            propertyInputs.binding.value = data.binding || '';
            propertyInputs.font.value = active.fontFamily || 'Arial';
            propertyInputs.fontSize.value = round(active.fontSize / renderer.PX_PER_MM, 2);
            propertyInputs.fill.value = normaliseHex(active.fill, '#111827');
            propertyInputs.align.value = active.textAlign || 'left';
            propertyInputs.bold.checked = active.fontWeight === 'bold';
            propertyInputs.italic.checked = active.fontStyle === 'italic';
            propertyInputs.lineHeight.value = round(number(active.lineHeight, 1.16), 2);
            propertyInputs.charSpacing.value = Math.round(number(active.charSpacing, 0));
        }
        if (isShape) {
            propertyInputs.shapeFill.value = normaliseHex(active.studioData.fill, '#e2e8f0');
            propertyInputs.stroke.value = normaliseHex(active.studioData.stroke, '#64748b');
            propertyInputs.strokeWidth.value = number(active.studioData.strokeWidth, 0);
            propertyInputs.radius.value = number(active.studioData.radius, 0);
            propertyInputs.fit.value = active.studioData.fit || 'cover';
        }
        updateLayers();
    }

    function applyProperties() {
        if (state.loading) { return; }
        var active = canvas.getActiveObject();
        if (!active || active.type === 'activeSelection') { return; }
        var safeId = String(propertyInputs.name.value || '').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 64);
        if (safeId && !idUsedByAnother(safeId, active)) {
            active.studioId = safeId;
            active.studioData.id = safeId;
        }
        active.set({
            left: clamp(number(propertyInputs.x.value, 0), 0, state.widthMm) * renderer.PX_PER_MM,
            top: clamp(number(propertyInputs.y.value, 0), 0, state.heightMm) * renderer.PX_PER_MM,
            angle: clamp(number(propertyInputs.rotation.value, 0), -360, 360),
            opacity: clamp(number(propertyInputs.opacity.value, 1), 0, 1)
        });
        var targetWidth = clamp(number(propertyInputs.width.value, 1), .1, state.widthMm) * renderer.PX_PER_MM;
        var targetHeight = clamp(number(propertyInputs.height.value, 1), .1, state.heightMm) * renderer.PX_PER_MM;
        active.scaleX = targetWidth / Math.max(1, active.width);
        active.scaleY = targetHeight / Math.max(1, active.height);
        keepInside(active);

        if (active.studioType === 'text') {
            var data = active.studioData;
            data.text = propertyInputs.text.value.slice(0, 500);
            data.binding = propertyInputs.binding.value;
            data.fontFamily = propertyInputs.font.value;
            data.fontSize = clamp(number(propertyInputs.fontSize.value, 3), 1.5, 20);
            data.fontWeight = propertyInputs.bold.checked ? 'bold' : 'normal';
            data.fontStyle = propertyInputs.italic.checked ? 'italic' : 'normal';
            data.align = propertyInputs.align.value;
            data.fill = propertyInputs.fill.value;
            data.lineHeight = clamp(number(propertyInputs.lineHeight.value, 1.16), .7, 3);
            data.charSpacing = clamp(number(propertyInputs.charSpacing.value, 0), -200, 1000);
            active.set({
                text: renderer.textValue(data, config.sampleData || {}),
                fontFamily: data.fontFamily,
                fontSize: data.fontSize * renderer.PX_PER_MM,
                fontWeight: data.fontWeight,
                fontStyle: data.fontStyle,
                textAlign: data.align,
                fill: data.fill
                ,lineHeight: data.lineHeight
                ,charSpacing: data.charSpacing
            });
        }
        if (['rect', 'ellipse', 'line', 'image'].indexOf(active.studioType) !== -1) {
            var shapeData = active.studioData;
            shapeData.fill = propertyInputs.shapeFill.value;
            shapeData.stroke = propertyInputs.stroke.value;
            shapeData.strokeWidth = clamp(number(propertyInputs.strokeWidth.value, 0), 0, 5);
            shapeData.radius = clamp(number(propertyInputs.radius.value, 0), 0, 50);
            shapeData.fit = propertyInputs.fit.value;
            if (active.studioType !== 'image') {
                active.set({
                    fill: active.studioType === 'line' ? shapeData.stroke : shapeData.fill,
                    stroke: active.studioType === 'line' ? 'transparent' : shapeData.stroke,
                    strokeWidth: shapeData.strokeWidth * renderer.PX_PER_MM,
                    rx: shapeData.radius * renderer.PX_PER_MM,
                    ry: shapeData.radius * renderer.PX_PER_MM
                });
            }
        }
        active.setCoords();
        canvas.requestRenderAll();
        captureCurrent(true);
        updateLayers();
        markDirty();
    }

    function idUsedByAnother(id, current) {
        return canvas.getObjects().some(function (object) { return object !== current && object.studioId === id; });
    }

    function updateLayers() {
        while (elements.layers.firstChild) { elements.layers.removeChild(elements.layers.firstChild); }
        var active = canvas.getActiveObject();
        canvas.getObjects().slice().reverse().forEach(function (object) {
            var item = document.createElement('li');
            item.className = object === active ? 'is-active' : '';
            item.title = object.studioType || object.type;
            var icon = document.createElement('i');
            icon.className = layerIcon(object.studioType);
            var name = document.createElement('span');
            name.className = 'layer-name';
            name.textContent = object.studioId || object.studioType || 'Object';
            var visibility = document.createElement('button');
            visibility.type = 'button';
            visibility.title = object.visible === false ? 'Show layer' : 'Hide layer';
            visibility.innerHTML = '<i class="fa ' + (object.visible === false ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
            visibility.addEventListener('click', function (event) {
                event.stopPropagation();
                object.visible = object.visible === false;
                object.studioData.visible = object.visible;
                canvas.requestRenderAll();
                objectChanged();
            });
            var lock = document.createElement('button');
            lock.type = 'button';
            lock.title = object.selectable === false ? 'Unlock layer' : 'Lock layer';
            lock.innerHTML = '<i class="fa ' + (object.selectable === false ? 'fa-lock' : 'fa-unlock') + '"></i>';
            lock.addEventListener('click', function (event) {
                event.stopPropagation();
                setLocked(object, object.selectable !== false);
                objectChanged();
            });
            item.appendChild(icon);
            item.appendChild(name);
            item.appendChild(visibility);
            item.appendChild(lock);
            item.addEventListener('click', function () {
                if (object.selectable !== false) {
                    canvas.setActiveObject(object);
                    canvas.requestRenderAll();
                    updateSelectionPanel();
                }
            });
            elements.layers.appendChild(item);
        });
    }

    function runAction(action) {
        if (action === 'undo') { undo(); return; }
        if (action === 'redo') { redo(); return; }
        if (action === 'save') { saveDraft(false); return; }
        if (action === 'publish') { publish(); return; }
        if (action === 'use-legacy') { useLegacyRenderer(); return; }
        if (action === 'delete') { deleteSelection(); return; }
        if (action === 'duplicate') { duplicateSelection(); return; }
        if (action === 'lock') { toggleSelectionLock(); return; }
        if (action === 'layer-up') { moveLayer(true); return; }
        if (action === 'layer-down') { moveLayer(false); return; }
        if (action === 'group') { groupSelection(); return; }
        if (action === 'ungroup') { ungroupSelection(); return; }
        if (action === 'distribute-horizontal') { distributeSelection('horizontal'); return; }
        if (action === 'distribute-vertical') { distributeSelection('vertical'); return; }
        if (action === 'zoom-in') { setZoom(Math.min(2.5, state.zoom + .1), 'manual'); return; }
        if (action === 'zoom-out') { setZoom(Math.max(.25, state.zoom - .1), 'manual'); return; }
        if (action === 'zoom-fit') { fitCanvasToViewport(); return; }
        if (action === 'preset-landscape') { setDimensions(85.6, 53.98); return; }
        if (action === 'preset-portrait') { setDimensions(53.98, 85.6); return; }
        if (action === 'export-png') { exportPng(); return; }
        if (action === 'export-pdf') { exportExactPdf(); return; }
        if (action === 'export-a4') { exportA4(); return; }
        if (action === 'print') { printCard(); }
    }

    function deleteSelection() {
        var active = canvas.getActiveObject();
        if (!active) { return; }
        if (active.type === 'activeSelection') {
            active.getObjects().forEach(function (object) { canvas.remove(object); });
        } else {
            canvas.remove(active);
        }
        canvas.discardActiveObject();
        canvas.requestRenderAll();
        objectChanged();
    }

    function duplicateSelection() {
        var active = canvas.getActiveObject();
        if (!active || active.type === 'activeSelection') { return; }
        var data = renderer.clone(active.studioData);
        data.id = nextId(data.type || 'copy');
        data.x = clamp(active.left / renderer.PX_PER_MM + 2, 0, state.widthMm - active.getScaledWidth() / renderer.PX_PER_MM);
        data.y = clamp(active.top / renderer.PX_PER_MM + 2, 0, state.heightMm - active.getScaledHeight() / renderer.PX_PER_MM);
        data.width = active.getScaledWidth() / renderer.PX_PER_MM;
        data.height = active.getScaledHeight() / renderer.PX_PER_MM;
        addCanonical(data);
    }

    function toggleSelectionLock() {
        var active = canvas.getActiveObject();
        if (!active || active.type === 'activeSelection') { return; }
        var willLock = active.selectable !== false;
        setLocked(active, willLock);
        if (willLock) { canvas.discardActiveObject(); }
        canvas.requestRenderAll();
        objectChanged();
    }

    function setLocked(object, locked) {
        object.set({
            selectable: !locked, evented: !locked,
            lockMovementX: locked, lockMovementY: locked,
            lockRotation: locked, lockScalingX: locked, lockScalingY: locked
        });
        object.studioData.locked = locked;
    }

    function moveLayer(forward) {
        var active = canvas.getActiveObject();
        if (!active || active.type === 'activeSelection') { return; }
        if (forward) { canvas.bringForward(active); } else { canvas.sendBackwards(active); }
        canvas.requestRenderAll();
        objectChanged();
    }

    function groupSelection() {
        var active = canvas.getActiveObject();
        if (!active || active.type !== 'activeSelection' || active.getObjects().length < 2) {
            showMessage('Select at least two unlocked objects before grouping.', 'warning');
            return;
        }
        var groupId = nextGroupId();
        active.getObjects().forEach(function (object) {
            object.studioGroup = groupId;
            object.studioData.group = groupId;
        });
        objectChanged();
        showMessage('Objects grouped. Selecting one member selects the whole group.', 'success');
    }

    function ungroupSelection() {
        var active = canvas.getActiveObject();
        if (!active) { return; }
        var objects = active.type === 'activeSelection' ? active.getObjects() : [active];
        var changed = false;
        objects.forEach(function (object) {
            if (object.studioGroup) {
                object.studioGroup = '';
                object.studioData.group = '';
                changed = true;
            }
        });
        if (changed) { objectChanged(); } else { showMessage('The selection is not grouped.', 'info'); }
    }

    function nextGroupId() {
        var used = {};
        canvas.getObjects().forEach(function (object) { if (object.studioGroup) { used[object.studioGroup] = true; } });
        var index = 1;
        while (used['group-' + index]) { index += 1; }
        return 'group-' + index;
    }

    function distributeSelection(axis) {
        var active = canvas.getActiveObject();
        if (!active || active.type !== 'activeSelection' || active.getObjects().length < 3) {
            showMessage('Select at least three unlocked objects to distribute them.', 'warning');
            return;
        }
        var objects = active.getObjects().slice();
        var horizontal = axis === 'horizontal';
        objects.sort(function (a, b) { return horizontal ? a.left - b.left : a.top - b.top; });
        var first = objects[0];
        var last = objects[objects.length - 1];
        var start = horizontal ? first.left : first.top;
        var finish = horizontal ? last.left + last.getScaledWidth() : last.top + last.getScaledHeight();
        var occupied = objects.reduce(function (total, object) {
            return total + (horizontal ? object.getScaledWidth() : object.getScaledHeight());
        }, 0);
        var gap = (finish - start - occupied) / (objects.length - 1);
        var cursor = start;
        objects.forEach(function (object) {
            if (horizontal) { object.left = cursor; cursor += object.getScaledWidth() + gap; }
            else { object.top = cursor; cursor += object.getScaledHeight() + gap; }
            object.setCoords();
        });
        canvas.requestRenderAll();
        objectChanged();
    }

    function alignSelection(mode) {
        var active = canvas.getActiveObject();
        if (!active) { return; }
        var objects = active.type === 'activeSelection' ? active.getObjects() : [active];
        if (objects.length === 1) {
            var object = objects[0];
            if (mode === 'left') { object.left = 0; }
            if (mode === 'center') { object.left = (state.widthMm * renderer.PX_PER_MM - object.getScaledWidth()) / 2; }
            if (mode === 'right') { object.left = state.widthMm * renderer.PX_PER_MM - object.getScaledWidth(); }
            if (mode === 'top') { object.top = 0; }
            if (mode === 'middle') { object.top = (state.heightMm * renderer.PX_PER_MM - object.getScaledHeight()) / 2; }
            if (mode === 'bottom') { object.top = state.heightMm * renderer.PX_PER_MM - object.getScaledHeight(); }
            object.setCoords();
        } else {
            var bounds = active.getBoundingRect(true, true);
            objects.forEach(function (object) {
                if (mode === 'left') { object.left -= object.getBoundingRect(true, true).left - bounds.left; }
                if (mode === 'right') { object.left += bounds.left + bounds.width - (object.getBoundingRect(true, true).left + object.getBoundingRect(true, true).width); }
                if (mode === 'top') { object.top -= object.getBoundingRect(true, true).top - bounds.top; }
                if (mode === 'bottom') { object.top += bounds.top + bounds.height - (object.getBoundingRect(true, true).top + object.getBoundingRect(true, true).height); }
                if (mode === 'center') { object.left += bounds.left + bounds.width / 2 - (object.getBoundingRect(true, true).left + object.getBoundingRect(true, true).width / 2); }
                if (mode === 'middle') { object.top += bounds.top + bounds.height / 2 - (object.getBoundingRect(true, true).top + object.getBoundingRect(true, true).height / 2); }
                object.setCoords();
            });
        }
        canvas.requestRenderAll();
        objectChanged();
    }

    function snapObject(event) {
        if (!elements.snap.checked) { return; }
        var object = event.target;
        var unit = renderer.PX_PER_MM;
        state.smartGuides = [];
        object.left = Math.round(object.left / unit) * unit;
        object.top = Math.round(object.top / unit) * unit;
        var centreX = state.widthMm * unit / 2;
        var centreY = state.heightMm * unit / 2;
        if (Math.abs(object.left + object.getScaledWidth() / 2 - centreX) < unit) {
            object.left = centreX - object.getScaledWidth() / 2;
        }
        if (Math.abs(object.top + object.getScaledHeight() / 2 - centreY) < unit) {
            object.top = centreY - object.getScaledHeight() / 2;
            state.smartGuides.push({axis: 'y', value: centreY});
        }
        if (Math.abs(object.left + object.getScaledWidth() / 2 - centreX) < unit) {
            state.smartGuides.push({axis: 'x', value: centreX});
        }
        var movingEdges = function () {
            return {
                left: object.left, right: object.left + object.getScaledWidth(),
                centerX: object.left + object.getScaledWidth() / 2,
                top: object.top, bottom: object.top + object.getScaledHeight(),
                centerY: object.top + object.getScaledHeight() / 2
            };
        };
        canvas.getObjects().forEach(function (candidate) {
            if (candidate === object || candidate.visible === false) { return; }
            var target = {
                left: candidate.left, right: candidate.left + candidate.getScaledWidth(),
                centerX: candidate.left + candidate.getScaledWidth() / 2,
                top: candidate.top, bottom: candidate.top + candidate.getScaledHeight(),
                centerY: candidate.top + candidate.getScaledHeight() / 2
            };
            var xPairs = [['left', 'left'], ['left', 'right'], ['right', 'left'], ['right', 'right'], ['centerX', 'centerX']];
            var yPairs = [['top', 'top'], ['top', 'bottom'], ['bottom', 'top'], ['bottom', 'bottom'], ['centerY', 'centerY']];
            xPairs.some(function (pair) {
                var moving = movingEdges();
                if (Math.abs(moving[pair[0]] - target[pair[1]]) <= unit) {
                    object.left += target[pair[1]] - moving[pair[0]];
                    state.smartGuides.push({axis: 'x', value: target[pair[1]]});
                    return true;
                }
                return false;
            });
            yPairs.some(function (pair) {
                var moving = movingEdges();
                if (Math.abs(moving[pair[0]] - target[pair[1]]) <= unit) {
                    object.top += target[pair[1]] - moving[pair[0]];
                    state.smartGuides.push({axis: 'y', value: target[pair[1]]});
                    return true;
                }
                return false;
            });
        });
        keepInside(object);
    }

    function keepInside(object) {
        var maxLeft = state.widthMm * renderer.PX_PER_MM - object.getScaledWidth();
        var maxTop = state.heightMm * renderer.PX_PER_MM - object.getScaledHeight();
        object.left = clamp(object.left, 0, Math.max(0, maxLeft));
        object.top = clamp(object.top, 0, Math.max(0, maxTop));
    }

    function drawGrid() {
        if (!elements.grid.checked || state.loading) { return; }
        var context = canvas.getContext();
        var zoom = state.zoom;
        var spacing = renderer.PX_PER_MM * 5 * zoom;
        var width = state.widthMm * renderer.PX_PER_MM * zoom;
        var height = state.heightMm * renderer.PX_PER_MM * zoom;
        context.save();
        context.strokeStyle = 'rgba(37,99,235,.11)';
        context.lineWidth = 1;
        for (var x = spacing; x < width; x += spacing) {
            context.beginPath(); context.moveTo(x, 0); context.lineTo(x, height); context.stroke();
        }
        for (var y = spacing; y < height; y += spacing) {
            context.beginPath(); context.moveTo(0, y); context.lineTo(width, y); context.stroke();
        }
        context.restore();
    }

    function drawSmartGuides() {
        if (!state.smartGuides.length || state.loading) { return; }
        var context = canvas.getContext();
        var zoom = state.zoom;
        context.save();
        context.strokeStyle = 'rgba(225, 29, 72, .9)';
        context.lineWidth = 1;
        context.setLineDash([5, 4]);
        state.smartGuides.forEach(function (guide) {
            context.beginPath();
            if (guide.axis === 'x') {
                context.moveTo(guide.value * zoom, 0);
                context.lineTo(guide.value * zoom, state.heightMm * renderer.PX_PER_MM * zoom);
            } else {
                context.moveTo(0, guide.value * zoom);
                context.lineTo(state.widthMm * renderer.PX_PER_MM * zoom, guide.value * zoom);
            }
            context.stroke();
        });
        context.restore();
    }

    function changeDimensions() {
        setDimensions(number(elements.width.value, state.widthMm), number(elements.height.value, state.heightMm));
    }

    function setDimensions(width, height) {
        width = clamp(width, 40, 220);
        height = clamp(height, 40, 220);
        captureCurrent(false);
        var ratioX = width / state.widthMm;
        var ratioY = height / state.heightMm;
        ['front', 'back'].forEach(function (side) {
            state.documents[side].objects.forEach(function (object) {
                object.x = round(object.x * ratioX, 3);
                object.y = round(object.y * ratioY, 3);
                object.width = round(object.width * ratioX, 3);
                object.height = round(object.height * ratioY, 3);
            });
        });
        state.widthMm = width;
        state.heightMm = height;
        elements.width.value = round(width, 2);
        elements.height.value = round(height, 2);
        elements.dimensionsStatus.textContent = round(width, 2) + ' × ' + round(height, 2) + ' mm';
        seedHistory();
        renderSide(state.side);
        markDirty();
    }

    function setZoom(value, mode) {
        state.zoom = clamp(value, .25, 2.5);
        if (mode) { state.zoomMode = mode; }
        var baseWidth = Math.round(state.widthMm * renderer.PX_PER_MM);
        var baseHeight = Math.round(state.heightMm * renderer.PX_PER_MM);
        canvas.setDimensions({width: Math.round(baseWidth * state.zoom), height: Math.round(baseHeight * state.zoom)});
        canvas.setViewportTransform([state.zoom, 0, 0, state.zoom, 0, 0]);
        elements.stage.style.width = Math.round(baseWidth * state.zoom) + 'px';
        elements.stage.style.height = Math.round(baseHeight * state.zoom) + 'px';
        elements.safeArea.style.inset = Math.round(3 * renderer.PX_PER_MM * state.zoom) + 'px';
        elements.bleedArea.style.inset = '-' + Math.round(3 * renderer.PX_PER_MM * state.zoom) + 'px';
        elements.zoom.value = Math.round(state.zoom * 100);
        elements.zoomLabel.textContent = Math.round(state.zoom * 100) + '%';
        canvas.requestRenderAll();
    }

    function fitCanvasToViewport() {
        var style = window.getComputedStyle(elements.scroll);
        var horizontalPadding = number(style.paddingLeft, 0) + number(style.paddingRight, 0);
        var verticalPadding = number(style.paddingTop, 0) + number(style.paddingBottom, 0);
        var availableWidth = elements.scroll.clientWidth - horizontalPadding - 12;
        var availableHeight = elements.scroll.clientHeight - verticalPadding - 12;
        var baseWidth = state.widthMm * renderer.PX_PER_MM;
        var baseHeight = state.heightMm * renderer.PX_PER_MM;
        if (availableWidth <= 0 || availableHeight <= 0 || baseWidth <= 0 || baseHeight <= 0) { return; }

        var fit = Math.min(availableWidth / baseWidth, availableHeight / baseHeight, 1.25);
        fit = Math.max(.25, Math.floor(fit * 20) / 20);
        setZoom(fit, 'fit');
        window.requestAnimationFrame(function () {
            elements.scroll.scrollLeft = Math.max(0, (elements.scroll.scrollWidth - elements.scroll.clientWidth) / 2);
            elements.scroll.scrollTop = Math.max(0, (elements.scroll.scrollHeight - elements.scroll.clientHeight) / 2);
        });
    }

    function zoomWithWheel(event) {
        if (!event.ctrlKey && !event.metaKey) { return; }
        event.preventDefault();
        var delta = event.deltaY;
        if (event.deltaMode === 1) { delta *= 16; }
        if (event.deltaMode === 2) { delta *= elements.scroll.clientHeight; }
        var nextZoom = clamp(state.zoom * Math.exp(-delta * .0015), .25, 2.5);
        nextZoom = Math.round(nextZoom * 100) / 100;
        zoomAtViewportPoint(nextZoom, event.clientX, event.clientY);
    }

    function zoomAtViewportPoint(nextZoom, clientX, clientY) {
        var bounds = elements.scroll.getBoundingClientRect();
        var pointerX = clientX - bounds.left;
        var pointerY = clientY - bounds.top;
        var designX = (elements.scroll.scrollLeft + pointerX - elements.stage.offsetLeft) / state.zoom;
        var designY = (elements.scroll.scrollTop + pointerY - elements.stage.offsetTop) / state.zoom;

        setZoom(nextZoom, 'manual');
        window.requestAnimationFrame(function () {
            elements.scroll.scrollLeft = elements.stage.offsetLeft + designX * state.zoom - pointerX;
            elements.scroll.scrollTop = elements.stage.offsetTop + designY * state.zoom - pointerY;
        });
    }

    function saveDraft(automatic) {
        clearTimeout(state.autosaveTimer);
        if (state.savePromise) { return state.savePromise; }
        if (!state.dirty && automatic) { return Promise.resolve(true); }
        captureCurrent(false);
        collectPrintSettings();
        setSaveState(automatic ? 'Autosaving…' : 'Saving…');

        var form = new FormData();
        form.append('studio_csrf', config.csrf);
        form.append('expected_published_version_id', config.publishedVersionId || '');
        form.append('expected_checksum', state.checksum);
        form.append('title', elements.title.value);
        form.append('width_mm', state.widthMm);
        form.append('height_mm', state.heightMm);
        form.append('front_json', JSON.stringify(state.documents.front));
        form.append('back_json', JSON.stringify(state.documents.back));
        form.append('print_settings_json', JSON.stringify(state.print));

        state.savePromise = request(config.endpoints.save, form).then(function (response) {
            if (response.status !== 'saved') { throw new Error(response.message || 'The draft was not saved.'); }
            state.checksum = response.checksum;
            state.dirty = false;
            setSaveState('Saved ' + new Date().toLocaleTimeString());
            return true;
        }).catch(function (error) {
            setSaveState('Save failed');
            showMessage(error.message, error.status === 409 ? 'warning' : 'danger');
            throw error;
        }).finally(function () {
            state.savePromise = null;
        });
        return state.savePromise;
    }

    function publish() {
        saveDraft(false).then(function () {
            if (!window.confirm('Publish this design for card generation? The previous published version will remain in history.')) {
                return;
            }
            var form = new FormData();
            form.append('studio_csrf', config.csrf);
            form.append('expected_checksum', state.checksum);
            setSaveState('Publishing…');
            return request(config.endpoints.publish, form).then(function (response) {
                if (response.status !== 'published') { throw new Error(response.message || 'The design was not published.'); }
                state.checksum = response.draft_checksum;
                config.publishedVersionId = response.published_version_id;
                state.dirty = false;
                setSaveState('Published');
                showMessage('Design published. Student/staff generation will use this version.', 'success');
            });
        }).catch(function (error) {
            setSaveState('Publish failed');
            if (!elements.alert.classList.contains('is-visible')) { showMessage(error.message, 'danger'); }
        });
    }

    function useLegacyRenderer() {
        if (!window.confirm('Use the unchanged legacy template for future generation? All studio versions will be retained.')) {
            return;
        }
        var form = new FormData();
        form.append('studio_csrf', config.csrf);
        form.append('expected_published_version_id', config.publishedVersionId || '');
        request(config.endpoints.useLegacy, form).then(function (response) {
            if (response.status !== 'legacy') { throw new Error(response.message || 'The legacy renderer was not restored.'); }
            state.dirty = false;
            showMessage(response.message, 'success');
            window.setTimeout(function () {
                window.location.href = window.location.href.replace(/\/editor\/(student|staff)\/\d+.*$/, '/index/' + config.subjectType);
            }, 900);
        }).catch(function (error) {
            showMessage(error.message, 'danger');
        });
    }

    function uploadAsset() {
        if (!elements.assetInput.files || !elements.assetInput.files[0]) { return; }
        var form = new FormData();
        form.append('studio_csrf', config.csrf);
        form.append('asset', elements.assetInput.files[0]);
        setSaveState('Uploading image…');
        request(config.endpoints.upload, form).then(function (response) {
            if (response.status !== 'uploaded') { throw new Error(response.message || 'Upload failed.'); }
            config.assets.unshift(response.asset);
            state.assets[response.asset.id] = response.asset.url;
            renderAssets(config.assets);
            elements.assetInput.value = '';
            setSaveState('Image uploaded');
            addAssetObject(response.asset);
        }).catch(function (error) {
            setSaveState('Upload failed');
            showMessage(error.message, 'danger');
        });
    }

    function renderAssets(assets) {
        while (elements.assets.firstChild) { elements.assets.removeChild(elements.assets.firstChild); }
        if (!assets.length) {
            var empty = document.createElement('p');
            empty.className = 'text-muted';
            empty.textContent = 'No studio images uploaded.';
            elements.assets.appendChild(empty);
            return;
        }
        assets.forEach(function (asset) {
            var button = document.createElement('button');
            button.type = 'button';
            button.title = asset.name;
            button.textContent = asset.name;
            button.addEventListener('click', function () { addAssetObject(asset); });
            elements.assets.appendChild(button);
        });
    }

    function exportPng() {
        renderOffscreen(state.side).then(function (result) {
            var multiplier = 300 / (renderer.PX_PER_MM * 25.4);
            var dataUrl = result.canvas.toDataURL({format: 'png', multiplier: multiplier, enableRetinaScaling: false});
            downloadDataUrl(dataUrl, safeTitle() + '-' + state.side + '-300dpi.png');
            result.dispose();
        }).catch(exportError);
    }

    function exportExactPdf() {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            showMessage('The local PDF library is unavailable.', 'danger');
            return;
        }
        renderOffscreen(state.side).then(function (result) {
            var image = result.canvas.toDataURL({format: 'png', multiplier: 2.5, enableRetinaScaling: false});
            var pdf = new window.jspdf.jsPDF({
                orientation: state.widthMm >= state.heightMm ? 'landscape' : 'portrait',
                unit: 'mm',
                format: [state.widthMm, state.heightMm],
                compress: true
            });
            pdf.addImage(image, 'PNG', 0, 0, state.widthMm, state.heightMm, undefined, 'FAST');
            pdf.save(safeTitle() + '-' + state.side + '.pdf');
            result.dispose();
        }).catch(exportError);
    }

    function exportA4() {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            showMessage('The local PDF library is unavailable.', 'danger');
            return;
        }
        captureCurrent(false);
        collectPrintSettings();
        var sides = state.print.duplex ? ['front', 'back'] : [state.side];
        Promise.all(sides.map(renderOffscreen)).then(function (renders) {
            var orientation = state.print.orientation === 'landscape' ? 'landscape' : 'portrait';
            var pageWidth = orientation === 'landscape' ? 297 : 210;
            var pageHeight = orientation === 'landscape' ? 210 : 297;
            var pdf = new window.jspdf.jsPDF({orientation: orientation, unit: 'mm', format: 'a4', compress: true});
            renders.forEach(function (result, sideIndex) {
                if (sideIndex > 0) { pdf.addPage('a4', orientation); }
                var image = result.canvas.toDataURL({format: 'png', multiplier: 2.2, enableRetinaScaling: false});
                drawA4Grid(pdf, image, pageWidth, pageHeight, sideIndex === 1);
                result.dispose();
            });
            pdf.save(safeTitle() + '-a4-grid.pdf');
        }).catch(exportError);
    }

    function drawA4Grid(pdf, image, pageWidth, pageHeight, reverse) {
        var margin = clamp(number(state.print.marginMm, 8), 0, 30);
        var gap = clamp(number(state.print.gapMm, 4), 0, 30);
        var columns = Math.max(1, Math.floor((pageWidth - 2 * margin + gap) / (state.widthMm + gap)));
        var rows = Math.max(1, Math.floor((pageHeight - 2 * margin + gap) / (state.heightMm + gap)));
        for (var row = 0; row < rows; row += 1) {
            for (var column = 0; column < columns; column += 1) {
                var actualColumn = reverse && state.print.flip === 'long-edge' ? (columns - column - 1) : column;
                var x = margin + actualColumn * (state.widthMm + gap);
                var y = margin + row * (state.heightMm + gap);
                pdf.addImage(image, 'PNG', x, y, state.widthMm, state.heightMm, undefined, 'FAST');
                if (state.print.cropMarks) { drawCropMarks(pdf, x, y, state.widthMm, state.heightMm); }
            }
        }
    }

    function drawCropMarks(pdf, x, y, width, height) {
        var length = 2;
        pdf.setDrawColor(80);
        pdf.setLineWidth(.15);
        pdf.line(x - length, y, x - .4, y); pdf.line(x, y - length, x, y - .4);
        pdf.line(x + width + .4, y, x + width + length, y); pdf.line(x + width, y - length, x + width, y - .4);
        pdf.line(x - length, y + height, x - .4, y + height); pdf.line(x, y + height + .4, x, y + height + length);
        pdf.line(x + width + .4, y + height, x + width + length, y + height); pdf.line(x + width, y + height + .4, x + width, y + height + length);
    }

    function printCard() {
        renderOffscreen(state.side).then(function (result) {
            var image = result.canvas.toDataURL({format: 'png', multiplier: 2.5, enableRetinaScaling: false});
            var popup = window.open('', '_blank', 'noopener,noreferrer');
            if (!popup) { throw new Error('Allow pop-ups to print the card.'); }
            var safeImage = image.replace(/"/g, '&quot;');
            popup.document.write('<!doctype html><html><head><title>Print ID Card</title><style>@page{size:' + state.widthMm + 'mm ' + state.heightMm + 'mm;margin:0}html,body{margin:0;padding:0}img{display:block;width:' + state.widthMm + 'mm;height:' + state.heightMm + 'mm}</style></head><body><img src="' + safeImage + '"><script>window.onload=function(){window.print()}<\/script></body></html>');
            popup.document.close();
            result.dispose();
        }).catch(exportError);
    }

    function renderOffscreen(side) {
        captureCurrent(false);
        var node = document.createElement('canvas');
        var offscreen = new fabric.StaticCanvas(node, {renderOnAddRemove: false});
        return renderer.render(offscreen, state.documents[side], {
            widthMm: state.widthMm,
            heightMm: state.heightMm,
            bindings: config.sampleData || {},
            assets: state.assets
        }).then(function () {
            return {
                canvas: offscreen,
                dispose: function () { offscreen.dispose(); }
            };
        });
    }

    function keyboardShortcut(event) {
        var target = event.target;
        var editing = target && /INPUT|TEXTAREA|SELECT/.test(target.tagName);
        if (event.key === 'Escape' && state.openPanel) {
            event.preventDefault();
            closePanel(true);
            return;
        }
        if (!editing && (event.code === 'Space' || event.key === ' ')) {
            state.spaceDown = true;
            elements.scroll.classList.add('is-pan-ready');
            event.preventDefault();
            return;
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
            event.preventDefault(); event.shiftKey ? redo() : undo(); return;
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'y') {
            event.preventDefault(); redo(); return;
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault(); saveDraft(false); return;
        }
        if ((event.ctrlKey || event.metaKey) && event.key === '0' && !editing) {
            event.preventDefault(); fitCanvasToViewport(); return;
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') {
            event.preventDefault(); duplicateSelection(); return;
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'c' && !editing) {
            var active = canvas.getActiveObject();
            if (active && active.type !== 'activeSelection') { state.clipboard = renderer.clone(active.studioData); }
            return;
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'v' && !editing && state.clipboard) {
            event.preventDefault();
            var copy = renderer.clone(state.clipboard);
            copy.id = nextId(copy.type || 'copy'); copy.x += 2; copy.y += 2; addCanonical(copy); return;
        }
        if (editing) { return; }
        if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault(); deleteSelection(); return;
        }
        if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(event.key) !== -1) {
            var selected = canvas.getActiveObject();
            if (!selected) { return; }
            event.preventDefault();
            var amount = (event.shiftKey ? 5 : 1) * renderer.PX_PER_MM;
            if (event.key === 'ArrowLeft') { selected.left -= amount; }
            if (event.key === 'ArrowRight') { selected.left += amount; }
            if (event.key === 'ArrowUp') { selected.top -= amount; }
            if (event.key === 'ArrowDown') { selected.top += amount; }
            keepInside(selected); selected.setCoords(); canvas.requestRenderAll(); objectChanged();
        }
    }

    function keyboardKeyup(event) {
        if (event.code === 'Space' || event.key === ' ') {
            state.spaceDown = false;
            if (!state.panning) { elements.scroll.classList.remove('is-pan-ready'); }
        }
    }

    function beginPan(event) {
        if (!state.spaceDown && event.button !== 1) { return; }
        event.preventDefault();
        state.panning = true;
        state.panOrigin = {
            x: event.clientX,
            y: event.clientY,
            left: elements.scroll.scrollLeft,
            top: elements.scroll.scrollTop
        };
        canvas.selection = false;
        elements.scroll.classList.add('is-panning');
    }

    function movePan(event) {
        if (!state.panning || !state.panOrigin) { return; }
        elements.scroll.scrollLeft = state.panOrigin.left - (event.clientX - state.panOrigin.x);
        elements.scroll.scrollTop = state.panOrigin.top - (event.clientY - state.panOrigin.y);
    }

    function endPan() {
        if (!state.panning) { return; }
        state.panning = false;
        state.panOrigin = null;
        canvas.selection = true;
        elements.scroll.classList.remove('is-panning');
        if (!state.spaceDown) { elements.scroll.classList.remove('is-pan-ready'); }
    }

    function collectPrintSettings() {
        state.print = {
            schemaVersion: 1,
            paper: elements.printPaper.value,
            orientation: state.print.orientation || 'portrait',
            marginMm: clamp(number(elements.printMargin.value, 8), 0, 30),
            gapMm: clamp(number(elements.printGap.value, 4), 0, 30),
            cropMarks: elements.printCrop.checked,
            duplex: elements.printDuplex.checked,
            flip: state.print.flip || 'long-edge',
            dpi: 300,
            maxCardsPerPart: clamp(number(state.print.maxCardsPerPart, 100), 1, 250)
        };
    }

    function initialisePrintSettings() {
        elements.printPaper.value = state.print.paper || 'a4';
        elements.printMargin.value = number(state.print.marginMm, 8);
        elements.printGap.value = number(state.print.gapMm, 4);
        elements.printCrop.checked = state.print.cropMarks !== false;
        elements.printDuplex.checked = state.print.duplex === true;
    }

    function initialiseBindings() {
        Object.keys(config.bindings || {}).forEach(function (binding) {
            var option = document.createElement('option');
            option.value = binding;
            option.textContent = config.bindings[binding];
            propertyInputs.binding.appendChild(option);
        });
    }

    function updateSideButtons() {
        document.querySelectorAll('[data-side]').forEach(function (button) {
            var active = button.getAttribute('data-side') === state.side;
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-default', !active);
            button.classList.toggle('active', active);
        });
    }

    function request(url, form) {
        return fetch(url, {method: 'POST', body: form, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) {
                return response.json().catch(function () {
                    throw responseError('The server returned an unreadable response.', response.status);
                }).then(function (payload) {
                    if (!response.ok) { throw responseError(payload.message || 'The request failed.', response.status); }
                    return payload;
                });
            });
    }

    function responseError(message, status) {
        var error = new Error(message); error.status = status; return error;
    }

    function setSaveState(message) {
        elements.saveState.textContent = message;
    }

    function showMessage(message, type) {
        elements.alert.className = 'alert alert-' + (type || 'info') + ' idstudio-alert is-visible';
        elements.alert.textContent = message;
        window.clearTimeout(elements.alert._hideTimer);
        elements.alert._hideTimer = window.setTimeout(function () { elements.alert.classList.remove('is-visible'); }, 8000);
    }

    function showFatal(message) {
        var alert = document.getElementById('idstudio-alert');
        if (alert) {
            alert.className = 'alert alert-danger idstudio-alert is-visible';
            alert.textContent = message;
        }
    }

    function downloadDataUrl(url, filename) {
        var link = document.createElement('a');
        link.href = url; link.download = filename; document.body.appendChild(link); link.click(); document.body.removeChild(link);
    }

    function exportError(error) {
        showMessage(error.message || 'The export could not be created.', 'danger');
    }

    function safeTitle() {
        return (elements.title.value || 'id-card').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'id-card';
    }

    function layerIcon(type) {
        if (type === 'text') { return 'fa fa-font'; }
        if (type === 'image') { return 'fa fa-picture-o'; }
        if (type === 'qr') { return 'fa fa-qrcode'; }
        if (type === 'barcode') { return 'fa fa-barcode'; }
        if (type === 'ellipse') { return 'fa fa-circle-thin'; }
        return 'fa fa-square-o';
    }

    function normaliseHex(value, fallback) {
        return /^#[0-9a-f]{6}$/i.test(String(value || '')) ? value : fallback;
    }

    function number(value, fallback) {
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function clamp(value, minimum, maximum) {
        return Math.min(maximum, Math.max(minimum, value));
    }

    function round(value, precision) {
        var factor = Math.pow(10, precision || 0);
        return Math.round(value * factor) / factor;
    }
}(window, document));
