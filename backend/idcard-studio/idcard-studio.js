(function (window, document) {
    'use strict';

    var config = window.ID_CARD_STUDIO_CONFIG;
    var renderer = window.SchoolLiftIdCardRenderer;
    var geometry = window.SchoolLiftIdCardGeometry;
    var app = document.getElementById('idstudio-app');
    if (!config || !renderer || !geometry || !app || !window.fabric) {
        showFatal(
            'The local ID Card Studio browser libraries could not be loaded. Reload the page or contact support.'
        );
        return;
    }

    var canvas = new fabric.Canvas('idstudio-canvas', {
        preserveObjectStacking: true,
        selection: true,
        stopContextMenu: true,
        fireRightClick: true,
        allowTouchScrolling: false,
        uniformScaling: true,
        uniScaleKey: 'none'
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
        history: {entries: [], index: -1},
        revision: 0,
        gesture: false,
        referenceId: '',
        selectionOrder: [],
        tool: 'select',
        multiSelect: false,
        nodeIndex: 0,
        drawNodes: [],
        styleClipboard: null
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
    state.loading = true;
    Promise.all(
        ['front', 'back'].map(function (side) {
            return renderer
                .upgrade(state.documents[side], {
                    widthMm: state.widthMm,
                    heightMm: state.heightMm,
                    assets: state.assets,
                    bindings: config.sampleData || {}
                })
                .then(function (doc) {
                    logicalUnits(doc.objects).forEach(function (unit) {
                        geometry.fit(unit, state.widthMm, state.heightMm);
                    });
                    state.documents[side] = doc;
                });
        })
    )
        .then(function () {
            return renderSide('front');
        })
        .then(function () {
            seedHistory();
            initialiseAdvancedUi();
            app.setAttribute('data-studio-ready', '1');
            setSaveState('Draft loaded');
            window.requestAnimationFrame(fitCanvasToViewport);
        })
        .catch(function (error) {
            showFatal(error.message);
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
            button.addEventListener('click', function () {
                closePanel(true);
            });
        });

        elements.title.addEventListener('change', function () {
            pushHistory();
            markDirty();
        });
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
        elements.grid.addEventListener('change', function () {
            canvas.requestRenderAll();
        });
        [
            elements.printPaper,
            elements.printMargin,
            elements.printGap,
            elements.printCrop,
            elements.printDuplex
        ].forEach(function (input) {
            input.addEventListener('change', function () {
                collectPrintSettings();
                pushHistory();
                markDirty();
            });
        });

        Object.keys(propertyInputs).forEach(function (key) {
            var input = propertyInputs[key];
            if (!input) {
                return;
            }
            input.addEventListener('change', applyProperties);
        });

        elements.assetInput.addEventListener('change', uploadAsset);
        document.addEventListener('keydown', keyboardShortcut);
        document.addEventListener('keyup', keyboardKeyup);
        elements.scroll.addEventListener('mousedown', beginPan);
        elements.scroll.addEventListener('wheel', zoomWithWheel, {passive: false});
        document.addEventListener('mousemove', movePan);
        document.addEventListener('mouseup', endPan);
        elements.scroll.addEventListener(
            'pointerdown',
            function (event) {
                if (event.pointerType !== 'mouse' && state.tool === 'pan') {
                    beginPan(event);
                    elements.scroll.setPointerCapture(event.pointerId);
                }
            },
            {passive: false}
        );
        document.addEventListener('pointermove', function (event) {
            if (event.pointerType !== 'mouse') {
                movePan(event);
            }
        });
        ['pointerup', 'pointercancel'].forEach(function (name) {
            document.addEventListener(name, function (event) {
                if (event.pointerType !== 'mouse') {
                    endPan();
                }
            });
        });
        window.addEventListener('resize', scheduleWorkspaceResize);
        window.addEventListener('orientationchange', scheduleWorkspaceResize);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleWorkspaceResize);
        }
        if (window.ResizeObserver) {
            state.workspaceObserver = new window.ResizeObserver(function () {
                scheduleCanvasFit();
            });
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
        if (!isPanelCollapsible(name)) {
            return;
        }
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
            window.setTimeout(function () {
                closeButton.focus();
            }, 210);
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
            button.setAttribute(
                'aria-expanded',
                isPanelCollapsible(name) && openPanel === name ? 'true' : 'false'
            );
        });
    }

    function setViewportHeight() {
        var viewportHeight = window.visualViewport
            ? window.visualViewport.height
            : window.innerHeight;
        if (viewportHeight > 0) {
            document.documentElement.style.setProperty(
                '--idstudio-viewport-height',
                Math.round(viewportHeight) + 'px'
            );
            var page = document.querySelector('.idstudio-page');
            var top =
                page && window.innerWidth > 767 ? Math.max(0, page.getBoundingClientRect().top) : 0;
            document.documentElement.style.setProperty(
                '--idstudio-page-height',
                Math.max(180, Math.floor(viewportHeight - top)) + 'px'
            );
        }
    }

    function scheduleWorkspaceResize() {
        setViewportHeight();
        syncResponsivePanels();
        scheduleCanvasFit();
    }

    function scheduleCanvasFit() {
        window.clearTimeout(state.resizeTimer);
        if (state.zoomMode !== 'fit' || app.getAttribute('data-studio-ready') !== '1') {
            return;
        }
        state.resizeTimer = window.setTimeout(fitCanvasToViewport, 80);
    }

    function bindCanvas() {
        canvas.on('selection:created', selectionChanged);
        canvas.on('selection:updated', selectionChanged);
        canvas.on('selection:cleared', updateSelectionPanel);
        canvas.on('object:moving', function (event) {
            snapObject(event);
            keepInside(event.target);
        });
        canvas.on('mouse:down:before', function () {
            state.pointerSelection = canvas.getActiveObjects().slice();
        });
        canvas.on('mouse:down', function (event) {
            if (
                state.tool !== 'select' ||
                !event.target ||
                !event.target.studioData ||
                !event.target.studioData.locked ||
                event.e.button === 2
            ) {
                return;
            }
            var extend = event.e.shiftKey || state.multiSelect;
            if (extend && state.pointerSelection.length) {
                canvas.setActiveObject(
                    state.pointerSelection.length === 1
                        ? state.pointerSelection[0]
                        : new fabric.ActiveSelection(state.pointerSelection, {canvas: canvas})
                );
            }
            chooseObject(event.target, extend);
        });
        canvas.on('before:transform', function (event) {
            state.gesture = true;
            state.validTransform = transformState(event.transform.target);
            clearTimeout(state.autosaveTimer);
        });
        canvas.on('object:scaling', constrainTransform);
        canvas.on('object:rotating', constrainTransform);
        canvas.on('object:modified', function () {
            objectChanged();
            if (!state.nodeObject) {
                commitDocument(selectedIds());
            }
        });
        canvas.on('mouse:up', function () {
            state.gesture = false;
            state.smartGuides = [];
            canvas.requestRenderAll();
        });
        canvas.on('after:render', function () {
            drawGrid();
            drawSmartGuides();
            drawReference();
            drawPenPreview();
        });
    }

    function selectionChanged(event) {
        if (state.expandingSelection) {
            return;
        }
        var active = canvas.getActiveObject();
        if (state.nodeObject && active !== state.nodeObject) {
            exitNodeEdit();
        }
        var selected = canvas.getActiveObjects().slice(),
            groups = selected
                .map(function (o) {
                    return o.studioGroup;
                })
                .filter(Boolean);
        var extra = canvas.getObjects().filter(function (o) {
            return groups.includes(o.studioGroup) && !selected.includes(o);
        });
        if (extra.length) {
            var order = state.selectionOrder.slice();
            state.expandingSelection = true;
            canvas.discardActiveObject();
            canvas.setActiveObject(
                new fabric.ActiveSelection(selected.concat(extra), {canvas: canvas})
            );
            state.selectionOrder = order;
            state.expandingSelection = false;
            active = canvas.getActiveObject();
            canvas.requestRenderAll();
        }
        selected = canvas.getActiveObjects();
        state.selectionOrder = state.selectionOrder.filter(function (id) {
            return selected.some(function (o) {
                return o.studioId === id;
            });
        });
        ((event && event.selected) || selected).forEach(function (o) {
            if (!state.selectionOrder.includes(o.studioId)) {
                state.selectionOrder.push(o.studioId);
            }
        });
        if (selected.length > 1) {
            state.referenceId = state.selectionOrder[state.selectionOrder.length - 1];
        }
        if (active && active.type === 'activeSelection') {
            active.setControlsVisibility({mt: false, mb: false, ml: false, mr: false});
            active.lockSkewingX = active.lockSkewingY = true;
        }
        var target = document.getElementById('idstudio-align-target');
        if (target && !state.restoringSelection) {
            target.value = selected.length > 1 ? 'reference' : 'card';
        }
        updateSelectionPanel();
    }

    function renderSide(side) {
        state.side = side;
        state.loading = true;
        exitNodeEdit();
        updateSideButtons();
        return renderer
            .render(canvas, state.documents[side], {
                widthMm: state.widthMm,
                heightMm: state.heightMm,
                bindings: config.sampleData || {},
                assets: state.assets
            })
            .then(function () {
                state.loading = false;
                setZoom(state.zoom, state.zoomMode);
                updateLayers();
                updateSelectionPanel();
                elements.sideStatus.textContent = side === 'front' ? 'Front side' : 'Back side';
                if (state.zoomMode === 'fit') {
                    window.requestAnimationFrame(fitCanvasToViewport);
                }
            })
            .catch(function (error) {
                state.loading = false;
                showMessage(error.message || 'The design could not be rendered.', 'danger');
            });
    }

    function switchSide(side) {
        if (state.loading || state.drawNodes.length) {
            return;
        }
        if ((side !== 'front' && side !== 'back') || side === state.side) {
            return;
        }
        captureCurrent(true);
        renderSide(side);
    }

    function captureCurrent(push) {
        if (state.loading) {
            return state.documents[state.side];
        }
        state.documents[state.side] = renderer.toCanonical(
            canvas,
            state.side,
            state.documents[state.side].background
        );
        if (push) {
            pushHistory(state.side);
        }
        return state.documents[state.side];
    }

    function seedHistory() {
        state.history = {entries: [snapshot()], index: 0};
    }

    function snapshot() {
        return JSON.stringify({
            documents: state.documents,
            widthMm: state.widthMm,
            heightMm: state.heightMm,
            print: state.print,
            title: elements.title.value
        });
    }

    function pushHistory() {
        var history = state.history;
        var value = snapshot();
        if (history.entries[history.index] === value) {
            return;
        }
        history.entries = history.entries.slice(0, history.index + 1);
        history.entries.push(value);
        if (history.entries.length > 101) {
            history.entries.shift();
        }
        history.index = history.entries.length - 1;
    }

    function undo() {
        if (state.loading) {
            return;
        }
        captureCurrent(false);
        var history = state.history;
        if (history.index <= 0) {
            return;
        }
        history.index -= 1;
        restoreSnapshot(history.entries[history.index]);
    }

    function redo() {
        if (state.loading) {
            return;
        }
        var history = state.history;
        if (history.index >= history.entries.length - 1) {
            return;
        }
        history.index += 1;
        restoreSnapshot(history.entries[history.index]);
    }

    function restoreSnapshot(value) {
        var saved = JSON.parse(value);
        state.documents = saved.documents;
        state.widthMm = saved.widthMm;
        state.heightMm = saved.heightMm;
        state.print = saved.print;
        elements.title.value = saved.title;
        elements.width.value = state.widthMm;
        elements.height.value = state.heightMm;
        elements.dimensionsStatus.textContent = state.widthMm + ' × ' + state.heightMm + ' mm';
        initialisePrintSettings();
        renderSide(state.side);
        markDirty();
    }

    function objectChanged() {
        if (state.loading) {
            return;
        }
        state.gesture = false;
        captureCurrent(true);
        updateLayers();
        updateSelectionPanel();
        markDirty();
    }

    function markDirty() {
        state.revision++;
        state.dirty = true;
        setSaveState('Unsaved changes');
        clearTimeout(state.autosaveTimer);
        state.autosaveTimer = window.setTimeout(function () {
            saveDraft(true).catch(function () {});
        }, 2500);
    }

    function addObject(type) {
        if (state.loading) {
            return;
        }
        var rounded = type === 'rounded-rect';
        if (type === 'rounded-rect') {
            type = 'rect';
        }
        if (type === 'text') {
            addCanonical({
                id: nextId('text'),
                type: 'text',
                binding: '',
                text: 'Double-click to edit',
                prefix: '',
                suffix: '',
                x: 8,
                y: 8,
                width: Math.min(40, state.widthMm - 16),
                height: 7,
                rotation: 0,
                opacity: 1,
                visible: true,
                locked: false,
                fontFamily: 'Arial',
                fontSize: 3,
                fontWeight: 'normal',
                fontStyle: 'normal',
                align: 'left',
                fill: '#111827',
                lineHeight: 1.16,
                charSpacing: 0
            });
            return;
        }
        if (type === 'qr') {
            addQr('attendance.credential');
            return;
        }
        if (type === 'barcode') {
            var idBinding =
                config.subjectType === 'staff' ? 'staff.employee_id' : 'student.admission_no';
            addCanonical(codeObject('barcode', idBinding));
            return;
        }
        var width = type === 'line' ? 30 : 22;
        var height = type === 'line' ? 0.8 : 15;
        addCanonical({
            id: nextId(type),
            type: type,
            x: 8,
            y: 8,
            width: width,
            height: height,
            rotation: 0,
            opacity: 1,
            visible: true,
            locked: false,
            fill: type === 'line' ? 'transparent' : '#e2e8f0',
            stroke: '#64748b',
            strokeWidth: 0.25,
            radius: rounded ? 3 : 0
        });
    }

    function addTextBinding(binding) {
        addCanonical({
            id: nextId(binding.split('.').pop()),
            type: 'text',
            binding: binding,
            text: '',
            prefix: '',
            suffix: '',
            x: 8,
            y: 8,
            width: Math.min(45, state.widthMm - 16),
            height: 7,
            rotation: 0,
            opacity: 1,
            visible: true,
            locked: false,
            fontFamily: 'Arial',
            fontSize: 3,
            fontWeight: 'normal',
            fontStyle: 'normal',
            align: 'left',
            fill: '#111827',
            lineHeight: 1.16,
            charSpacing: 0
        });
    }

    function addImageBinding(binding) {
        addCanonical({
            id: nextId(binding.split('.').pop()),
            type: 'image',
            binding: binding,
            assetId: null,
            x: 8,
            y: 8,
            width: 24,
            height: 27,
            rotation: 0,
            opacity: 1,
            visible: true,
            locked: false,
            fit: 'cover',
            radius: 2,
            stroke: '#cbd5e1',
            strokeWidth: 0.25
        });
    }

    function addQr(binding) {
        addCanonical(codeObject('qr', binding));
    }

    function codeObject(type, binding) {
        return {
            id: nextId(type),
            type: type,
            binding: binding,
            x: 8,
            y: 8,
            width: type === 'qr' ? 18 : 32,
            height: type === 'qr' ? 18 : 12,
            rotation: 0,
            opacity: 1,
            visible: true,
            locked: false,
            foreground: '#111827',
            background: '#ffffff',
            label: ''
        };
    }

    function addAssetObject(asset) {
        addCanonical({
            id: nextId('image'),
            type: 'image',
            binding: '',
            assetId: parseInt(asset.id, 10),
            x: 8,
            y: 8,
            width: 28,
            height: 22,
            rotation: 0,
            opacity: 1,
            visible: true,
            locked: false,
            fit: 'contain',
            radius: 0,
            stroke: 'transparent',
            strokeWidth: 0
        });
    }

    function addCanonical(object) {
        if (state.loading) {
            return Promise.resolve();
        }
        if (canvas.getObjects().length >= 150) {
            showMessage('A side can contain up to 150 objects.', 'info');
            return Promise.resolve();
        }
        geometry.fit([object], state.widthMm, state.heightMm);
        state.loading = true;
        return renderer
            .objectToFabric(object, {
                schemaVersion: 2,
                bindings: config.sampleData || {},
                assets: state.assets
            })
            .then(function (fabricObject) {
                canvas.add(fabricObject);
                canvas.setActiveObject(fabricObject);
                canvas.requestRenderAll();
                state.loading = false;
                objectChanged();
            })
            .catch(function (error) {
                state.loading = false;
                showMessage(error.message || 'The object could not be added.', 'danger');
            });
    }

    function nextId(prefix) {
        var base =
            String(prefix || 'object')
                .toLowerCase()
                .replace(/[^a-z0-9_-]+/g, '-')
                .replace(/^-|-$/g, '') || 'object';
        var used = {};
        canvas.getObjects().forEach(function (object) {
            used[object.studioId] = true;
        });
        var index = 1;
        while (used[base + '-' + index]) {
            index += 1;
        }
        return base + '-' + index;
    }

    function updateSelectionPanel() {
        updateReferenceLabel();
        var active = canvas.getActiveObject();
        var single = active && active.type !== 'activeSelection';
        elements.properties.hidden = !single;
        elements.noSelection.hidden = !!single;
        elements.selectionStatus.textContent = active
            ? active.type === 'activeSelection'
                ? active.getObjects().length + ' objects selected'
                : active.studioId || 'Object selected'
            : 'Nothing selected';
        if (!single) {
            updateLayers();
            return;
        }
        var data = active.studioData || {};
        propertyInputs.name.value = active.studioId || '';
        propertyInputs.x.value = round(active.left / renderer.PX_PER_MM, 2);
        propertyInputs.y.value = round(active.top / renderer.PX_PER_MM, 2);
        propertyInputs.width.value = round(
            Math.abs(active.width * active.scaleX) / renderer.PX_PER_MM,
            4
        );
        propertyInputs.height.value = round(
            Math.abs(active.height * active.scaleY) / renderer.PX_PER_MM,
            4
        );
        propertyInputs.rotation.value = round(active.angle || 0, 1);
        propertyInputs.opacity.value = round(active.opacity, 2);
        var isText = active.studioType === 'text';
        var isShape =
            renderer.shapeTypes.concat(['image', 'text']).indexOf(active.studioType) !== -1;
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
            propertyInputs.shapeFill.parentElement.hidden =
                isText || ['image', 'line'].includes(active.studioType);
            propertyInputs.strokeWidth.parentElement.hidden = active.studioType === 'line';
            document.getElementById('idstudio-no-fill').parentElement.hidden = [
                'image',
                'line'
            ].includes(active.studioType);
            propertyInputs.radius.parentElement.hidden = !['rect', 'image'].includes(
                active.studioType
            );
            propertyInputs.shapeFill.value = normaliseHex(active.studioData.fill, '#e2e8f0');
            propertyInputs.stroke.value = normaliseHex(active.studioData.stroke, '#64748b');
            propertyInputs.strokeWidth.value = number(active.studioData.strokeWidth, 0);
            propertyInputs.radius.value = number(active.studioData.radius, 0);
            propertyInputs.fit.value = active.studioData.fit || 'cover';
        }
        updateLayers();
        syncAdvancedProperties(active);
    }

    function applyProperties() {
        if (state.loading) {
            return;
        }
        var active = canvas.getActiveObject();
        if (!active || active.type === 'activeSelection') {
            return;
        }
        if (active.studioData.locked) {
            return;
        }
        captureCurrent(false);
        var data = state.documents[state.side].objects.find(function (o) {
            return o.id === active.studioId;
        });
        var safeId = String(propertyInputs.name.value || '')
            .replace(/[^A-Za-z0-9_-]/g, '')
            .replace(/^[^A-Za-z0-9]+/, '')
            .slice(0, 64);
        if (safeId && !idUsedByAnother(safeId, active)) {
            if (state.referenceId === data.id) {
                state.referenceId = safeId;
            }
            data.id = safeId;
        }
        data.x = number(propertyInputs.x.value, data.x);
        data.y = number(propertyInputs.y.value, data.y);
        var minimum = ['path', 'line'].includes(data.type) ? 0.1 : 0.5;
        data.width = clamp(
            number(propertyInputs.width.value, data.width),
            minimum,
            Math.hypot(state.widthMm, state.heightMm)
        );
        data.height = clamp(
            number(propertyInputs.height.value, data.height),
            minimum,
            Math.hypot(state.widthMm, state.heightMm)
        );
        data.rotation = clamp(number(propertyInputs.rotation.value, data.rotation), -360, 360);
        data.opacity = clamp(number(propertyInputs.opacity.value, 1), 0, 1);
        if (data.type === 'text') {
            data.text = propertyInputs.text.value.slice(0, 500);
            data.binding = propertyInputs.binding.value;
            data.fontFamily = propertyInputs.font.value;
            data.fontSize = clamp(number(propertyInputs.fontSize.value, 3), 1.5, 20);
            data.fontWeight = propertyInputs.bold.checked ? 'bold' : 'normal';
            data.fontStyle = propertyInputs.italic.checked ? 'italic' : 'normal';
            data.align = propertyInputs.align.value;
            data.fill = propertyInputs.fill.value;
            data.lineHeight = clamp(number(propertyInputs.lineHeight.value, 1.16), 0.7, 3);
            data.charSpacing = clamp(number(propertyInputs.charSpacing.value, 0), -200, 1000);
        }
        if (renderer.shapeTypes.concat(['image', 'text']).includes(data.type)) {
            data.fill = document.getElementById('idstudio-no-fill').checked
                ? 'transparent'
                : data.type === 'text'
                  ? propertyInputs.fill.value
                  : propertyInputs.shapeFill.value;
            data.stroke = document.getElementById('idstudio-no-stroke').checked
                ? 'transparent'
                : propertyInputs.stroke.value;
            data.strokeWidth = clamp(number(propertyInputs.strokeWidth.value, 0), 0, 5);
            data.radius = clamp(
                number(propertyInputs.radius.value, 0),
                0,
                data.type === 'image' ? 50 : 30
            );
            data.fit = propertyInputs.fit.value;
        }
        readAdvancedProperties(data);
        geometry.fit([data], state.widthMm, state.heightMm);
        commitDocument([data.id]);
    }

    function commitDocument(ids) {
        var invalid = ['front', 'back'].some(function (side) {
            return state.documents[side].objects.some(function (o) {
                var minimum = ['path', 'line'].includes(o.type) ? 0.1 : 0.5;
                return (
                    o.width < minimum - 0.000001 ||
                    o.height < minimum - 0.000001 ||
                    !geometry.inside(o, state.widthMm, state.heightMm)
                );
            });
        });
        if (invalid) {
            restoreSnapshot(state.history.entries[state.history.index]);
            showMessage(
                'This change would make an object too small or place its border outside the card. Keep a larger card/frame, or reduce the selection before trying again.',
                'info'
            );
            return Promise.resolve(false);
        }
        pushHistory();
        markDirty();
        var order = state.selectionOrder.slice(),
            reference = state.referenceId;
        return renderSide(state.side).then(function () {
            state.restoringSelection = true;
            var selected = canvas.getObjects().filter(function (o) {
                return (ids || []).includes(o.studioId);
            });
            if (selected.length) {
                canvas.setActiveObject(
                    selected.length === 1
                        ? selected[0]
                        : new fabric.ActiveSelection(selected, {canvas: canvas})
                );
            }
            state.selectionOrder = order.filter(function (id) {
                return (ids || []).includes(id);
            });
            state.referenceId = reference;
            state.restoringSelection = false;
            updateSelectionPanel();
            canvas.requestRenderAll();
        });
    }

    function idUsedByAnother(id, current) {
        return canvas.getObjects().some(function (object) {
            return object !== current && object.studioId === id;
        });
    }

    function updateLayers() {
        while (elements.layers.firstChild) {
            elements.layers.removeChild(elements.layers.firstChild);
        }
        var active = canvas.getActiveObject();
        canvas
            .getObjects()
            .slice()
            .reverse()
            .forEach(function (object) {
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
                visibility.innerHTML =
                    '<i class="fa ' +
                    (object.visible === false ? 'fa-eye-slash' : 'fa-eye') +
                    '"></i>';
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
                lock.innerHTML =
                    '<i class="fa ' +
                    (object.selectable === false ? 'fa-lock' : 'fa-unlock') +
                    '"></i>';
                lock.addEventListener('click', function (event) {
                    event.stopPropagation();
                    setLocked(object, object.selectable !== false);
                    objectChanged();
                });
                item.appendChild(icon);
                item.appendChild(name);
                item.appendChild(visibility);
                item.appendChild(lock);
                var reference = document.createElement('button');
                reference.type = 'button';
                reference.textContent = '◎';
                reference.title = 'Set as reference';
                reference.setAttribute('aria-label', 'Set ' + object.studioId + ' as reference');
                reference.addEventListener('click', function (event) {
                    event.stopPropagation();
                    state.referenceId = object.studioId;
                    document.getElementById('idstudio-align-target').value = 'reference';
                    updateReferenceLabel();
                    canvas.requestRenderAll();
                });
                item.appendChild(reference);
                item.tabIndex = 0;
                item.addEventListener('keydown', function (event) {
                    if (event.target === item && (event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault();
                        event.stopPropagation();
                        chooseObject(object, event.shiftKey || state.multiSelect);
                    }
                });
                item.addEventListener('click', function (event) {
                    chooseObject(object, event.shiftKey || state.multiSelect);
                });
                elements.layers.appendChild(item);
            });
    }

    function runAction(action) {
        if (
            state.loading &&
            ![
                'save',
                'actions',
                'commands',
                'shortcuts',
                'zoom-in',
                'zoom-out',
                'zoom-fit'
            ].includes(action)
        ) {
            return;
        }
        if (advancedAction(action)) {
            return;
        }
        if (action === 'undo') {
            undo();
            return;
        }
        if (action === 'redo') {
            redo();
            return;
        }
        if (action === 'save') {
            saveDraft(false).catch(function () {});
            return;
        }
        if (action === 'publish') {
            publish();
            return;
        }
        if (action === 'use-legacy') {
            useLegacyRenderer();
            return;
        }
        if (action === 'delete') {
            deleteSelection();
            return;
        }
        if (action === 'duplicate') {
            duplicateSelection();
            return;
        }
        if (action === 'lock') {
            toggleSelectionLock();
            return;
        }
        if (action === 'layer-up') {
            moveLayer(true);
            return;
        }
        if (action === 'layer-down') {
            moveLayer(false);
            return;
        }
        if (action === 'group') {
            groupSelection();
            return;
        }
        if (action === 'ungroup') {
            ungroupSelection();
            return;
        }
        if (action === 'distribute-horizontal') {
            distributeSelection('horizontal');
            return;
        }
        if (action === 'distribute-vertical') {
            distributeSelection('vertical');
            return;
        }
        if (action === 'zoom-in') {
            setZoom(Math.min(2.5, state.zoom + 0.1), 'manual');
            return;
        }
        if (action === 'zoom-out') {
            setZoom(Math.max(0.25, state.zoom - 0.1), 'manual');
            return;
        }
        if (action === 'zoom-fit') {
            fitCanvasToViewport();
            return;
        }
        if (action === 'preset-landscape') {
            setDimensions(85.6, 53.98);
            return;
        }
        if (action === 'preset-portrait') {
            setDimensions(53.98, 85.6);
            return;
        }
        if (action === 'export-png') {
            exportPng();
            return;
        }
        if (action === 'export-pdf') {
            exportExactPdf();
            return;
        }
        if (action === 'export-a4') {
            exportA4();
            return;
        }
        if (action === 'print') {
            printCard();
        }
    }

    function selectedIds() {
        return canvas.getActiveObjects().map(function (o) {
            return o.studioId;
        });
    }
    function recordsFor(ids) {
        return state.documents[state.side].objects.filter(function (o) {
            return ids.includes(o.id);
        });
    }
    function deleteSelection() {
        if (state.loading) {
            return;
        }
        var ids = selectedIds();
        captureCurrent(false);
        state.documents[state.side].objects = state.documents[state.side].objects.filter(
            function (o) {
                return !ids.includes(o.id) || o.locked;
            }
        );
        commitDocument([]);
    }
    function copySelection(cut) {
        captureCurrent(false);
        state.clipboard = renderer.clone(recordsFor(selectedIds()));
        if (cut) {
            deleteSelection();
        }
    }
    function pasteSelection() {
        if (state.loading || !state.clipboard || !state.clipboard.length) {
            return;
        }
        captureCurrent(false);
        if (state.documents[state.side].objects.length + state.clipboard.length > 150) {
            showMessage('A side can contain up to 150 objects.', 'info');
            return;
        }
        var copies = renderer.clone(state.clipboard),
            groups = {},
            ids = [],
            used = state.documents[state.side].objects.map(function (o) {
                return o.id;
            });
        copies.forEach(function (o) {
            var base = o.type + '-copy',
                i = 1;
            while (used.includes(base + '-' + i)) {
                i++;
            }
            o.id = base + '-' + i;
            used.push(o.id);
            ids.push(o.id);
            o.x += 2;
            o.y += 2;
            o.locked = false;
            if (o.group) {
                groups[o.group] =
                    groups[o.group] || 'group-' + Date.now() + '-' + Object.keys(groups).length;
                o.group = groups[o.group];
            }
        });
        geometry.fit(copies, state.widthMm, state.heightMm);
        state.documents[state.side].objects = state.documents[state.side].objects.concat(copies);
        commitDocument(ids);
    }
    function duplicateSelection() {
        copySelection(false);
        pasteSelection();
    }
    function toggleSelectionLock() {
        if (state.loading) {
            return;
        }
        var ids = selectedIds();
        captureCurrent(false);
        var records = recordsFor(ids);
        var lock = records.some(function (o) {
            return !o.locked;
        });
        records.forEach(function (o) {
            o.locked = lock;
        });
        commitDocument(ids);
    }
    function setLocked(object, locked) {
        object.set({
            selectable: !locked,
            evented: true,
            lockMovementX: locked,
            lockMovementY: locked,
            lockRotation: locked,
            lockScalingX: locked,
            lockScalingY: locked
        });
        object.studioData.locked = locked;
    }
    function moveLayer(forward, extreme) {
        if (state.loading) {
            return;
        }
        var ids = selectedIds();
        captureCurrent(false);
        var objects = state.documents[state.side].objects;
        if (extreme) {
            var selected = objects.filter(function (o) {
                return ids.includes(o.id) && !o.locked;
            });
            var rest = objects.filter(function (o) {
                return !selected.includes(o);
            });
            state.documents[state.side].objects = forward
                ? rest.concat(selected)
                : selected.concat(rest);
        } else {
            var order = forward ? objects.slice().reverse() : objects.slice();
            order.forEach(function (o) {
                if (!ids.includes(o.id) || o.locked) {
                    return;
                }
                var i = objects.indexOf(o),
                    j = i + (forward ? 1 : -1);
                if (j >= 0 && j < objects.length && !ids.includes(objects[j].id)) {
                    var other = objects[j];
                    objects[j] = o;
                    objects[i] = other;
                }
            });
        }
        commitDocument(ids);
    }
    function groupSelection() {
        if (state.loading) {
            return;
        }
        var ids = selectedIds();
        captureCurrent(false);
        var records = recordsFor(ids);
        if (
            records.length < 2 ||
            records.some(function (o) {
                return o.locked;
            })
        ) {
            showMessage('Select at least two unlocked objects to group.', 'info');
            return;
        }
        var id = 'group-' + Date.now();
        records.forEach(function (o) {
            o.group = id;
        });
        commitDocument(ids);
    }
    function ungroupSelection() {
        var ids = selectedIds();
        captureCurrent(false);
        recordsFor(ids).forEach(function (o) {
            if (!o.locked) {
                o.group = '';
            }
        });
        commitDocument(ids);
    }
    function logicalUnits(records) {
        var units = {},
            order = [];
        records.forEach(function (o) {
            var key = o.group || o.id;
            if (!units[key]) {
                units[key] = [];
                order.push(key);
            }
            units[key].push(o);
        });
        return order.map(function (key) {
            return units[key];
        });
    }
    function arrange(mode, distribution) {
        if (state.loading) {
            return;
        }
        var ids = selectedIds();
        captureCurrent(false);
        var originals = state.documents[state.side].objects,
            objects = renderer.clone(originals);
        var selected = objects.filter(function (o) {
                return ids.includes(o.id);
            }),
            units = logicalUnits(selected);
        if (!units.length) {
            return;
        }
        var target = document.getElementById('idstudio-align-target').value;
        var reference = objects.find(function (o) {
            return o.id === state.referenceId;
        });
        if (distribution) {
            if (units.length < 3) {
                showMessage('Select at least three objects or groups to distribute.', 'info');
                return;
            }
            var horizontal = mode === 'horizontal',
                pos = horizontal ? 'left' : 'top',
                size = horizontal ? 'width' : 'height';
            units.sort(function (a, b) {
                return geometry.union(a)[pos] - geometry.union(b)[pos];
            });
            var first = geometry.union(units[0]),
                last = geometry.union(units[units.length - 1]);
            var gap =
                    (last[pos] +
                        last[size] -
                        first[pos] -
                        units.reduce(function (sum, u) {
                            return sum + geometry.union(u)[size];
                        }, 0)) /
                    (units.length - 1),
                cursor = first[pos];
            units.forEach(function (unit, index) {
                var box = geometry.union(unit),
                    delta = cursor - box[pos];
                if (index > 0 && index < units.length - 1) {
                    unit.forEach(function (o) {
                        if (horizontal) {
                            o.x += delta;
                        } else {
                            o.y += delta;
                        }
                    });
                }
                cursor += box[size] + gap;
            });
        } else {
            if (target === 'reference' && !reference) {
                showMessage('Choose an object as the alignment reference.', 'info');
                return;
            }
            var refUnit = reference
                ? objects.filter(function (o) {
                      return reference.group ? o.group === reference.group : o.id === reference.id;
                  })
                : [];
            var box =
                target === 'card'
                    ? {
                          left: 0,
                          top: 0,
                          right: state.widthMm,
                          bottom: state.heightMm,
                          width: state.widthMm,
                          height: state.heightMm
                      }
                    : geometry.union(target === 'reference' ? refUnit : selected);
            units.forEach(function (unit) {
                if (
                    target === 'reference' &&
                    unit.some(function (o) {
                        return refUnit.some(function (r) {
                            return r.id === o.id;
                        });
                    })
                ) {
                    return;
                }
                var b = geometry.union(unit),
                    dx = 0,
                    dy = 0;
                if (mode === 'left') {
                    dx = box.left - b.left;
                }
                if (mode === 'right') {
                    dx = box.right - b.right;
                }
                if (mode === 'center' || mode === 'both') {
                    dx = box.left + box.width / 2 - b.left - b.width / 2;
                }
                if (mode === 'top') {
                    dy = box.top - b.top;
                }
                if (mode === 'bottom') {
                    dy = box.bottom - b.bottom;
                }
                if (mode === 'middle' || mode === 'both') {
                    dy = box.top + box.height / 2 - b.top - b.height / 2;
                }
                unit.forEach(function (o) {
                    o.x += dx;
                    o.y += dy;
                });
            });
        }
        var invalid = objects.some(function (o, i) {
            return (
                !geometry.inside(o, state.widthMm, state.heightMm) ||
                (o.locked &&
                    (Math.abs(o.x - originals[i].x) > 0.000001 ||
                        Math.abs(o.y - originals[i].y) > 0.000001))
            );
        });
        if (invalid) {
            showMessage(
                'This alignment would cross the card edge or move a locked object. Choose another reference or resize the selection.',
                'info'
            );
            return;
        }
        state.documents[state.side].objects = objects;
        commitDocument(ids);
    }
    function distributeSelection(axis) {
        arrange(axis, true);
    }
    function alignSelection(mode) {
        arrange(mode, false);
    }
    function liveRecords(target) {
        var ids =
            target.type === 'activeSelection'
                ? target.getObjects().map(function (o) {
                      return o.studioId;
                  })
                : [target.studioId];
        return renderer
            .toCanonical(canvas, state.side, state.documents[state.side].background)
            .objects.filter(function (o) {
                return ids.includes(o.id);
            });
    }
    function transformState(object) {
        var value = {};
        ['left', 'top', 'scaleX', 'scaleY', 'angle', 'flipX', 'flipY'].forEach(function (k) {
            value[k] = object[k];
        });
        return value;
    }
    function transformFits(object) {
        return liveRecords(object).every(function (o) {
            var minimum = ['line', 'path'].includes(o.type) ? 0.1 : 0.5;
            return (
                !o.locked &&
                o.width >= minimum &&
                o.height >= minimum &&
                geometry.inside(o, state.widthMm, state.heightMm)
            );
        });
    }
    function constrainTransform(event) {
        var object = event.target,
            wanted = transformState(object),
            previous = state.validTransform;
        if (!previous) {
            keepInside(object);
            return;
        }
        if (transformFits(object)) {
            state.validTransform = wanted;
            return;
        }
        if (event.transform && event.transform.action === 'rotate') {
            object.set(previous);
        } else {
            var lo = 0,
                hi = 1;
            for (var i = 0; i < 25; i++) {
                var t = (lo + hi) / 2,
                    next = {};
                ['left', 'top', 'scaleX', 'scaleY', 'angle'].forEach(function (k) {
                    next[k] = previous[k] + (wanted[k] - previous[k]) * t;
                });
                object.set(next);
                object.setCoords();
                if (transformFits(object)) {
                    lo = t;
                } else {
                    hi = t;
                }
            }
            ['left', 'top', 'scaleX', 'scaleY', 'angle'].forEach(function (k) {
                object[k] = previous[k] + (wanted[k] - previous[k]) * lo;
            });
        }
        object.setCoords();
        state.validTransform = transformState(object);
    }
    function snapObject(event) {
        state.smartGuides = [];
        if (!elements.snap.checked) {
            return;
        }
        var object = event.target,
            records = liveRecords(object),
            b = geometry.union(records),
            unit = renderer.PX_PER_MM;
        var threshold = 6 / (state.zoom * unit),
            dx = Math.round(b.left) - b.left,
            dy = Math.round(b.top) - b.top;
        var candidates = [
            {
                left: 0,
                top: 0,
                right: state.widthMm,
                bottom: state.heightMm,
                width: state.widthMm,
                height: state.heightMm
            }
        ];
        var ids = records.map(function (o) {
            return o.id;
        });
        renderer
            .toCanonical(canvas, state.side, state.documents[state.side].background)
            .objects.forEach(function (o) {
                if (!ids.includes(o.id) && o.visible !== false) {
                    candidates.push(geometry.bounds(o));
                }
            });
        var bestX = threshold,
            bestY = threshold;
        candidates.forEach(function (c) {
            [c.left, c.right, c.left + c.width / 2].forEach(function (x) {
                [b.left, b.right, b.left + b.width / 2].forEach(function (from) {
                    if (Math.abs(x - from) < bestX) {
                        dx = x - from;
                        bestX = Math.abs(dx);
                        state.smartGuides = state.smartGuides.filter(function (g) {
                            return g.axis !== 'x';
                        });
                        state.smartGuides.push({axis: 'x', value: x * unit});
                    }
                });
            });
            [c.top, c.bottom, c.top + c.height / 2].forEach(function (y) {
                [b.top, b.bottom, b.top + b.height / 2].forEach(function (from) {
                    if (Math.abs(y - from) < bestY) {
                        dy = y - from;
                        bestY = Math.abs(dy);
                        state.smartGuides = state.smartGuides.filter(function (g) {
                            return g.axis !== 'y';
                        });
                        state.smartGuides.push({axis: 'y', value: y * unit});
                    }
                });
            });
        });
        object.left += dx * unit;
        object.top += dy * unit;
        object.setCoords();
    }
    function keepInside(object) {
        var records = liveRecords(object),
            b = geometry.union(records),
            unit = renderer.PX_PER_MM;
        if (
            records.some(function (o) {
                return o.locked;
            }) &&
            state.validTransform
        ) {
            object.set(state.validTransform);
            object.setCoords();
            return;
        }
        if (b.width > state.widthMm || b.height > state.heightMm) {
            var initial = transformState(object),
                lo = 0,
                hi = 1;
            for (var i = 0; i < 30; i++) {
                var mid = (lo + hi) / 2;
                object.scaleX = initial.scaleX * mid;
                object.scaleY = initial.scaleY * mid;
                object.setCoords();
                var trial = geometry.union(liveRecords(object));
                if (trial.width <= state.widthMm && trial.height <= state.heightMm) {
                    lo = mid;
                } else {
                    hi = mid;
                }
            }
            object.scaleX = initial.scaleX * lo;
            object.scaleY = initial.scaleY * lo;
            object.setCoords();
            b = geometry.union(liveRecords(object));
        }
        object.left +=
            (b.left < 0 ? -b.left : b.right > state.widthMm ? state.widthMm - b.right : 0) * unit;
        object.top +=
            (b.top < 0 ? -b.top : b.bottom > state.heightMm ? state.heightMm - b.bottom : 0) * unit;
        object.setCoords();
    }

    function drawGrid() {
        if (!elements.grid.checked || state.loading) {
            return;
        }
        var context = canvas.getContext();
        var zoom = state.zoom;
        var spacing = renderer.PX_PER_MM * 5 * zoom;
        var width = state.widthMm * renderer.PX_PER_MM * zoom;
        var height = state.heightMm * renderer.PX_PER_MM * zoom;
        context.save();
        context.strokeStyle = 'rgba(37,99,235,.11)';
        context.lineWidth = 1;
        for (var x = spacing; x < width; x += spacing) {
            context.beginPath();
            context.moveTo(x, 0);
            context.lineTo(x, height);
            context.stroke();
        }
        for (var y = spacing; y < height; y += spacing) {
            context.beginPath();
            context.moveTo(0, y);
            context.lineTo(width, y);
            context.stroke();
        }
        context.restore();
    }

    function drawSmartGuides() {
        if (!state.smartGuides.length || state.loading) {
            return;
        }
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
        setDimensions(
            number(elements.width.value, state.widthMm),
            number(elements.height.value, state.heightMm)
        );
    }

    function setDimensions(width, height) {
        if (state.loading) {
            return;
        }
        width = clamp(width, 40, 220);
        height = clamp(height, 40, 220);
        captureCurrent(false);
        var ratioX = width / state.widthMm;
        var ratioY = height / state.heightMm;
        ['front', 'back'].forEach(function (side) {
            logicalUnits(state.documents[side].objects).forEach(function (unit) {
                var box = geometry.union(unit),
                    cx = (box.left + box.right) / 2,
                    cy = (box.top + box.bottom) / 2,
                    factor = Math.min(ratioX, ratioY);
                unit.forEach(function (object) {
                    object.x = cx * ratioX + (object.x - cx) * factor;
                    object.y = cy * ratioY + (object.y - cy) * factor;
                    object.width *= factor;
                    object.height *= factor;
                    if (object.type === 'text') {
                        object.fontSize = clamp(object.fontSize * factor, 1.5, 20);
                    }
                });
                geometry.fit(unit, width, height);
            });
        });
        state.widthMm = width;
        state.heightMm = height;
        elements.width.value = round(width, 2);
        elements.height.value = round(height, 2);
        elements.dimensionsStatus.textContent = round(width, 2) + ' × ' + round(height, 2) + ' mm';
        commitDocument([]);
    }

    function setZoom(value, mode) {
        state.zoom = clamp(value, 0.25, 2.5);
        if (mode) {
            state.zoomMode = mode;
        }
        var baseWidth = Math.round(state.widthMm * renderer.PX_PER_MM);
        var baseHeight = Math.round(state.heightMm * renderer.PX_PER_MM);
        canvas.setDimensions({
            width: Math.round(baseWidth * state.zoom),
            height: Math.round(baseHeight * state.zoom)
        });
        canvas.setViewportTransform([state.zoom, 0, 0, state.zoom, 0, 0]);
        elements.stage.style.width = Math.round(baseWidth * state.zoom) + 'px';
        elements.stage.style.height = Math.round(baseHeight * state.zoom) + 'px';
        elements.safeArea.style.inset = Math.round(3 * renderer.PX_PER_MM * state.zoom) + 'px';
        elements.bleedArea.style.inset =
            '-' + Math.round(3 * renderer.PX_PER_MM * state.zoom) + 'px';
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
        if (availableWidth <= 0 || availableHeight <= 0 || baseWidth <= 0 || baseHeight <= 0) {
            return;
        }

        var fit = Math.min(availableWidth / baseWidth, availableHeight / baseHeight, 1.25);
        fit = Math.max(0.25, Math.floor(fit * 20) / 20);
        setZoom(fit, 'fit');
        window.requestAnimationFrame(function () {
            elements.scroll.scrollLeft = Math.max(
                0,
                (elements.scroll.scrollWidth - elements.scroll.clientWidth) / 2
            );
            elements.scroll.scrollTop = Math.max(
                0,
                (elements.scroll.scrollHeight - elements.scroll.clientHeight) / 2
            );
        });
    }

    function zoomWithWheel(event) {
        if (!event.ctrlKey && !event.metaKey) {
            return;
        }
        event.preventDefault();
        var delta = event.deltaY;
        if (event.deltaMode === 1) {
            delta *= 16;
        }
        if (event.deltaMode === 2) {
            delta *= elements.scroll.clientHeight;
        }
        var nextZoom = clamp(state.zoom * Math.exp(-delta * 0.0015), 0.25, 2.5);
        nextZoom = Math.round(nextZoom * 100) / 100;
        zoomAtViewportPoint(nextZoom, event.clientX, event.clientY);
    }

    function zoomAtViewportPoint(nextZoom, clientX, clientY) {
        var bounds = elements.scroll.getBoundingClientRect();
        var pointerX = clientX - bounds.left;
        var pointerY = clientY - bounds.top;
        var designX =
            (elements.scroll.scrollLeft + pointerX - elements.stage.offsetLeft) / state.zoom;
        var designY =
            (elements.scroll.scrollTop + pointerY - elements.stage.offsetTop) / state.zoom;

        setZoom(nextZoom, 'manual');
        window.requestAnimationFrame(function () {
            elements.scroll.scrollLeft =
                elements.stage.offsetLeft + designX * state.zoom - pointerX;
            elements.scroll.scrollTop = elements.stage.offsetTop + designY * state.zoom - pointerY;
        });
    }

    function saveDraft(automatic) {
        clearTimeout(state.autosaveTimer);
        if (state.loading || state.gesture || state.drawNodes.length) {
            if (automatic) {
                state.autosaveTimer = window.setTimeout(function () {
                    saveDraft(true).catch(function () {});
                }, 500);
                return Promise.resolve(false);
            }
            if (state.drawNodes.length) {
                var error = new Error('Finish or cancel the current path before saving.');
                showMessage(error.message, 'info');
                return Promise.reject(error);
            }
            return new Promise(function (resolve) {
                window.setTimeout(resolve, 100);
            }).then(function () {
                return saveDraft(automatic);
            });
        }
        if (state.savePromise) {
            return state.savePromise.then(function () {
                return state.dirty ? saveDraft(automatic) : true;
            });
        }
        if (!state.dirty && automatic) {
            return Promise.resolve(true);
        }
        captureCurrent(false);
        collectPrintSettings();
        setSaveState(automatic ? 'Autosaving…' : 'Saving…');
        var sentRevision = state.revision;

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

        state.savePromise = request(config.endpoints.save, form)
            .then(function (response) {
                if (response.status !== 'saved') {
                    throw new Error(response.message || 'The draft was not saved.');
                }
                state.checksum = response.checksum;
                state.dirty = state.revision !== sentRevision;
                setSaveState(
                    state.dirty
                        ? 'Saving newer changes…'
                        : 'Saved ' + new Date().toLocaleTimeString()
                );
                return true;
            })
            .catch(function (error) {
                setSaveState('Save failed');
                showMessage(error.message, error.status === 409 ? 'warning' : 'danger');
                throw error;
            })
            .finally(function () {
                state.savePromise = null;
            });
        return state.savePromise.then(function () {
            return state.dirty ? saveDraft(automatic) : true;
        });
    }

    function publish() {
        saveDraft(false)
            .then(function () {
                if (
                    !window.confirm(
                        'Publish this design for card generation? The previous published version will remain in history.'
                    )
                ) {
                    return;
                }
                var form = new FormData();
                form.append('studio_csrf', config.csrf);
                form.append('expected_checksum', state.checksum);
                setSaveState('Publishing…');
                var publishedRevision = state.revision;
                return request(config.endpoints.publish, form).then(function (response) {
                    if (response.status !== 'published') {
                        throw new Error(response.message || 'The design was not published.');
                    }
                    state.checksum = response.draft_checksum;
                    config.publishedVersionId = response.published_version_id;
                    state.dirty = state.revision !== publishedRevision;
                    setSaveState(
                        state.dirty ? 'Published; newer draft edits pending' : 'Published'
                    );
                    showMessage(
                        'Design published. Student/staff generation will use this version.',
                        'success'
                    );
                    if (state.dirty) {
                        return saveDraft(true);
                    }
                });
            })
            .catch(function (error) {
                setSaveState('Publish failed');
                if (!elements.alert.classList.contains('is-visible')) {
                    showMessage(error.message, 'danger');
                }
            });
    }

    function useLegacyRenderer() {
        if (
            !window.confirm(
                'Use the unchanged legacy template for future generation? All studio versions will be retained.'
            )
        ) {
            return;
        }
        var form = new FormData();
        form.append('studio_csrf', config.csrf);
        form.append('expected_published_version_id', config.publishedVersionId || '');
        request(config.endpoints.useLegacy, form)
            .then(function (response) {
                if (response.status !== 'legacy') {
                    throw new Error(response.message || 'The legacy renderer was not restored.');
                }
                state.dirty = false;
                showMessage(response.message, 'success');
                window.setTimeout(function () {
                    window.location.href = window.location.href.replace(
                        /\/editor\/(student|staff)\/\d+.*$/,
                        '/index/' + config.subjectType
                    );
                }, 900);
            })
            .catch(function (error) {
                showMessage(error.message, 'danger');
            });
    }

    function uploadAsset() {
        if (!elements.assetInput.files || !elements.assetInput.files[0]) {
            return;
        }
        var form = new FormData();
        form.append('studio_csrf', config.csrf);
        form.append('asset', elements.assetInput.files[0]);
        setSaveState('Uploading image…');
        request(config.endpoints.upload, form)
            .then(function (response) {
                if (response.status !== 'uploaded') {
                    throw new Error(response.message || 'Upload failed.');
                }
                config.assets.unshift(response.asset);
                state.assets[response.asset.id] = response.asset.url;
                renderAssets(config.assets);
                elements.assetInput.value = '';
                setSaveState('Image uploaded');
                addAssetObject(response.asset);
            })
            .catch(function (error) {
                setSaveState('Upload failed');
                showMessage(error.message, 'danger');
            });
    }

    function renderAssets(assets) {
        while (elements.assets.firstChild) {
            elements.assets.removeChild(elements.assets.firstChild);
        }
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
            button.addEventListener('click', function () {
                addAssetObject(asset);
            });
            elements.assets.appendChild(button);
        });
    }

    function exportPng() {
        renderOffscreen(state.side)
            .then(function (result) {
                var multiplier = 300 / (renderer.PX_PER_MM * 25.4);
                var dataUrl = result.canvas.toDataURL({
                    format: 'png',
                    multiplier: multiplier,
                    enableRetinaScaling: false
                });
                downloadDataUrl(dataUrl, safeTitle() + '-' + state.side + '-300dpi.png');
                result.dispose();
            })
            .catch(exportError);
    }

    function exportExactPdf() {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            showMessage('The local PDF library is unavailable.', 'danger');
            return;
        }
        renderOffscreen(state.side)
            .then(function (result) {
                var image = result.canvas.toDataURL({
                    format: 'png',
                    multiplier: 2.5,
                    enableRetinaScaling: false
                });
                var pdf = new window.jspdf.jsPDF({
                    orientation: state.widthMm >= state.heightMm ? 'landscape' : 'portrait',
                    unit: 'mm',
                    format: [state.widthMm, state.heightMm],
                    compress: true
                });
                pdf.addImage(image, 'PNG', 0, 0, state.widthMm, state.heightMm, undefined, 'FAST');
                pdf.save(safeTitle() + '-' + state.side + '.pdf');
                result.dispose();
            })
            .catch(exportError);
    }

    function exportA4() {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            showMessage('The local PDF library is unavailable.', 'danger');
            return;
        }
        captureCurrent(false);
        collectPrintSettings();
        var sides = state.print.duplex ? ['front', 'back'] : [state.side];
        Promise.all(sides.map(renderOffscreen))
            .then(function (renders) {
                var orientation =
                    state.print.orientation === 'landscape' ? 'landscape' : 'portrait';
                var pageWidth = orientation === 'landscape' ? 297 : 210;
                var pageHeight = orientation === 'landscape' ? 210 : 297;
                var pdf = new window.jspdf.jsPDF({
                    orientation: orientation,
                    unit: 'mm',
                    format: 'a4',
                    compress: true
                });
                renders.forEach(function (result, sideIndex) {
                    if (sideIndex > 0) {
                        pdf.addPage('a4', orientation);
                    }
                    var image = result.canvas.toDataURL({
                        format: 'png',
                        multiplier: 2.2,
                        enableRetinaScaling: false
                    });
                    drawA4Grid(pdf, image, pageWidth, pageHeight, sideIndex === 1);
                    result.dispose();
                });
                pdf.save(safeTitle() + '-a4-grid.pdf');
            })
            .catch(exportError);
    }

    function drawA4Grid(pdf, image, pageWidth, pageHeight, reverse) {
        var margin = clamp(number(state.print.marginMm, 8), 0, 30);
        var gap = clamp(number(state.print.gapMm, 4), 0, 30);
        var columns = Math.max(
            1,
            Math.floor((pageWidth - 2 * margin + gap) / (state.widthMm + gap))
        );
        var rows = Math.max(
            1,
            Math.floor((pageHeight - 2 * margin + gap) / (state.heightMm + gap))
        );
        for (var row = 0; row < rows; row += 1) {
            for (var column = 0; column < columns; column += 1) {
                var actualColumn =
                    reverse && state.print.flip === 'long-edge' ? columns - column - 1 : column;
                var x = margin + actualColumn * (state.widthMm + gap);
                var y = margin + row * (state.heightMm + gap);
                pdf.addImage(image, 'PNG', x, y, state.widthMm, state.heightMm, undefined, 'FAST');
                if (state.print.cropMarks) {
                    drawCropMarks(pdf, x, y, state.widthMm, state.heightMm);
                }
            }
        }
    }

    function drawCropMarks(pdf, x, y, width, height) {
        var length = 2;
        pdf.setDrawColor(80);
        pdf.setLineWidth(0.15);
        pdf.line(x - length, y, x - 0.4, y);
        pdf.line(x, y - length, x, y - 0.4);
        pdf.line(x + width + 0.4, y, x + width + length, y);
        pdf.line(x + width, y - length, x + width, y - 0.4);
        pdf.line(x - length, y + height, x - 0.4, y + height);
        pdf.line(x, y + height + 0.4, x, y + height + length);
        pdf.line(x + width + 0.4, y + height, x + width + length, y + height);
        pdf.line(x + width, y + height + 0.4, x + width, y + height + length);
    }

    function printCard() {
        var popup = window.open('', '_blank');
        if (!popup) {
            showMessage('Allow pop-ups to print the card.', 'warning');
            return;
        }
        popup.opener = null;
        renderOffscreen(state.side)
            .then(function (result) {
                var image = result.canvas.toDataURL({
                    format: 'png',
                    multiplier: 2.5,
                    enableRetinaScaling: false
                });
                var safeImage = image.replace(/"/g, '&quot;');
                popup.document.write(
                    '<!doctype html><html><head><title>Print ID Card</title><style>@page{size:' +
                        state.widthMm +
                        'mm ' +
                        state.heightMm +
                        'mm;margin:0}html,body{margin:0;padding:0}img{display:block;width:' +
                        state.widthMm +
                        'mm;height:' +
                        state.heightMm +
                        'mm}</style></head><body><img src="' +
                        safeImage +
                        '"><script>window.onload=function(){window.print()}<\/script></body></html>'
                );
                popup.document.close();
                result.dispose();
            })
            .catch(function (error) {
                popup.close();
                exportError(error);
            });
    }

    function renderOffscreen(side) {
        captureCurrent(false);
        var node = document.createElement('canvas');
        var offscreen = new fabric.StaticCanvas(node, {renderOnAddRemove: false});
        return renderer
            .render(offscreen, state.documents[side], {
                widthMm: state.widthMm,
                heightMm: state.heightMm,
                bindings: config.sampleData || {},
                assets: state.assets
            })
            .then(function () {
                return {
                    canvas: offscreen,
                    dispose: function () {
                        offscreen.dispose();
                    }
                };
            });
    }

    function initialiseAdvancedUi() {
        document.querySelectorAll('[data-tool]').forEach(function (button) {
            button.addEventListener('click', function () {
                setTool(button.dataset.tool);
                closePanelAfterToolUse();
            });
        });
        document.querySelectorAll('[data-advanced-prop]').forEach(function (input) {
            input.addEventListener('change', applyProperties);
        });
        document.querySelectorAll('[data-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                showInspectorTab(button.dataset.tab);
            });
            button.addEventListener('keydown', function (event) {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                var tabs = Array.from(document.querySelectorAll('[data-tab]'));
                tabs[
                    (tabs.indexOf(button) + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) %
                        tabs.length
                ].click();
                document.querySelector('[data-tab][aria-selected="true"]').focus();
            });
        });
        document.getElementById('idstudio-align-target').addEventListener('change', function () {
            updateReferenceLabel();
            canvas.requestRenderAll();
        });
        document
            .getElementById('idstudio-shape-search')
            .addEventListener('input', function (event) {
                var term = event.target.value.toLowerCase();
                document.querySelectorAll('.idstudio-tool-grid button').forEach(function (button) {
                    button.hidden = !button.textContent.toLowerCase().includes(term);
                });
            });
        canvas._isSelectionKeyPressed = function (event) {
            return state.multiSelect || event.shiftKey;
        };
        canvas.upperCanvasEl.addEventListener('contextmenu', function (event) {
            event.preventDefault();
            openObjectMenu(event);
        });
        canvas.upperCanvasEl.addEventListener('pointerdown', function (event) {
            if (event.pointerType === 'mouse' || state.tool !== 'select') {
                return;
            }
            state.touchStart = {x: event.clientX, y: event.clientY};
            state.longPress = window.setTimeout(function () {
                openObjectMenu(event);
            }, 600);
        });
        canvas.upperCanvasEl.addEventListener('pointermove', function (event) {
            if (
                state.touchStart &&
                Math.hypot(event.clientX - state.touchStart.x, event.clientY - state.touchStart.y) >
                    8
            ) {
                clearTimeout(state.longPress);
            }
        });
        ['pointerup', 'pointercancel'].forEach(function (name) {
            canvas.upperCanvasEl.addEventListener(name, function () {
                clearTimeout(state.longPress);
                state.touchStart = null;
            });
        });
        canvas.on('mouse:dblclick', function (event) {
            if (
                event.target &&
                event.target.studioType === 'text' &&
                !event.target.studioData.locked
            ) {
                showInspectorTab('properties');
                if (window.innerWidth < 1200) {
                    togglePanel('properties');
                }
                window.setTimeout(function () {
                    propertyInputs.text.focus();
                    propertyInputs.text.select();
                }, 230);
            }
        });
        canvas.on('mouse:down', drawDown);
        canvas.on('mouse:move', drawMove);
        canvas.on('mouse:up', drawUp);
        canvas.on('selection:cleared', function () {
            if (!state.loading) {
                exitNodeEdit();
                state.selectionOrder = [];
            }
        });
        updateReferenceLabel();
    }

    function showInspectorTab(tab) {
        document.querySelectorAll('[data-tab]').forEach(function (button) {
            button.setAttribute('aria-selected', String(button.dataset.tab === tab));
            button.tabIndex = button.dataset.tab === tab ? 0 : -1;
        });
        document.querySelectorAll('.idstudio-tab-pane').forEach(function (pane) {
            pane.hidden = pane.id !== 'idstudio-tab-' + tab;
        });
    }
    function updateReferenceLabel() {
        var label = document.getElementById('idstudio-reference-label'),
            target = document.getElementById('idstudio-align-target');
        if (label && target) {
            label.textContent =
                'Align to: ' +
                (target.value === 'reference'
                    ? state.referenceId || 'choose a reference'
                    : target.value === 'bounds'
                      ? 'Selection bounds'
                      : 'Card');
        }
    }
    function drawReference() {
        if (state.loading || canvas.getActiveObjects().length < 2) {
            return;
        }
        var object = canvas.getObjects().find(function (o) {
            return o.studioId === state.referenceId;
        });
        if (!object) {
            return;
        }
        var matrix = fabric.util.multiplyTransformMatrices(
            canvas.viewportTransform,
            object.calcTransformMatrix()
        );
        var points = [
                [-0.5, -0.5],
                [0.5, -0.5],
                [0.5, 0.5],
                [-0.5, 0.5]
            ],
            ctx = canvas.getContext();
        ctx.save();
        ctx.strokeStyle = '#d97706';
        ctx.lineWidth = 2;
        ctx.setLineDash([5, 3]);
        ctx.beginPath();
        points.forEach(function (p, i) {
            var q = fabric.util.transformPoint(
                new fabric.Point(p[0] * object.width, p[1] * object.height),
                matrix
            );
            if (i === 0) {
                ctx.moveTo(q.x, q.y);
            } else {
                ctx.lineTo(q.x, q.y);
            }
        });
        ctx.closePath();
        ctx.stroke();
        ctx.restore();
    }
    function chooseObject(object, extend) {
        if (state.loading) {
            return;
        }
        var selected = extend ? canvas.getActiveObjects().slice() : [];
        canvas.discardActiveObject();
        var unit = object.studioGroup
            ? canvas.getObjects().filter(function (o) {
                  return o.studioGroup === object.studioGroup;
              })
            : [object];
        if (
            extend &&
            unit.every(function (o) {
                return selected.includes(o);
            })
        ) {
            selected = selected.filter(function (o) {
                return !unit.includes(o);
            });
        } else {
            unit.forEach(function (o) {
                if (!selected.includes(o)) {
                    selected.push(o);
                }
            });
        }
        if (selected.length) {
            canvas.setActiveObject(
                selected.length === 1
                    ? selected[0]
                    : new fabric.ActiveSelection(selected, {canvas: canvas})
            );
        }
        state.selectionOrder = selected.map(function (o) {
            return o.studioId;
        });
        state.referenceId = state.selectionOrder[state.selectionOrder.length - 1] || '';
        updateSelectionPanel();
        canvas.requestRenderAll();
    }
    function syncAdvancedProperties(active) {
        var data = active.studioData,
            shape = data.shape || {},
            shadow = data.shadow || {};
        document.getElementById('idstudio-no-fill').checked = data.fill === 'transparent';
        document.getElementById('idstudio-no-stroke').checked =
            !data.stroke || data.stroke === 'transparent';
        document.querySelectorAll('[data-for-shape]').forEach(function (label) {
            label.hidden = label.dataset.forShape !== data.type;
        });
        [
            ['sides', 6],
            ['points', 5],
            ['innerRadius', 0.45],
            ['head', 0.35],
            ['shaft', 0.4]
        ].forEach(function (p) {
            document.getElementById('idstudio-shape-' + p[0]).value = number(shape[p[0]], p[1]);
        });
        document.getElementById('idstudio-appearance').hidden = ['qr', 'barcode'].includes(
            data.type
        );
        document.getElementById('idstudio-shadow-enabled').checked = !!data.shadow;
        document.getElementById('idstudio-shadow-color').value = normaliseHex(
            shadow.color,
            '#000000'
        );
        [
            ['opacity', 0.3],
            ['blur', 1],
            ['offsetX', 1],
            ['offsetY', 1]
        ].forEach(function (p) {
            document.getElementById('idstudio-shadow-' + p[0]).value = number(shadow[p[0]], p[1]);
        });
        document.getElementById('idstudio-curve-actions').hidden = !renderer.shapeTypes.includes(
            data.type
        );
        document.querySelector('[data-action="convert-curves"]').hidden = data.type === 'path';
        document.querySelector('[data-action="node-edit"]').hidden = data.type !== 'path';
        document.getElementById('idstudio-node-actions').hidden = !state.nodeObject;
        document
            .querySelectorAll(
                '#idstudio-properties input,#idstudio-properties select,#idstudio-properties textarea'
            )
            .forEach(function (input) {
                input.disabled = !!data.locked;
            });
    }
    function readAdvancedProperties(data) {
        var ranges = {
            sides: [3, 32, 6],
            points: [3, 32, 5],
            innerRadius: [0.05, 0.95, 0.45],
            head: [0.1, 0.9, 0.35],
            shaft: [0.1, 0.9, 0.4]
        };
        if (['polygon', 'star', 'arrow'].includes(data.type)) {
            data.shape = {};
            Object.keys(ranges).forEach(function (key) {
                var r = ranges[key],
                    value = clamp(
                        number(document.getElementById('idstudio-shape-' + key).value, r[2]),
                        r[0],
                        r[1]
                    );
                data.shape[key] = ['sides', 'points'].includes(key) ? Math.round(value) : value;
            });
        }
        if (
            !['qr', 'barcode'].includes(data.type) &&
            document.getElementById('idstudio-shadow-enabled').checked
        ) {
            data.shadow = {color: document.getElementById('idstudio-shadow-color').value};
            [
                ['opacity', 0, 1, 0.3],
                ['blur', 0, 10, 1],
                ['offsetX', -10, 10, 1],
                ['offsetY', -10, 10, 1]
            ].forEach(function (p) {
                data.shadow[p[0]] = clamp(
                    number(document.getElementById('idstudio-shadow-' + p[0]).value, p[3]),
                    p[1],
                    p[2]
                );
            });
        } else {
            delete data.shadow;
        }
    }
    function setTool(tool) {
        if (state.loading) {
            return;
        }
        if (tool === 'node') {
            enterNodeEdit();
            return;
        }
        exitNodeEdit();
        state.tool = tool;
        state.drawNodes = [];
        state.drawing = false;
        state.gesture = false;
        canvas.selection = tool === 'select';
        canvas.skipTargetFind = tool !== 'select';
        canvas.defaultCursor =
            tool === 'pan' ? 'grab' : tool === 'select' ? 'default' : 'crosshair';
        if (tool !== 'select') {
            canvas.discardActiveObject();
        }
        document.querySelectorAll('[data-tool]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(button.dataset.tool === tool));
        });
        document.getElementById('idstudio-tool-hint').textContent =
            tool === 'pen'
                ? 'Click corners; drag for curves. Enter finishes, Escape cancels.'
                : tool === 'freehand'
                  ? 'Draw a stroke, then Finish (Enter) or Cancel (Escape).'
                  : tool === 'pan'
                    ? 'Drag the design area to pan. V returns to Select.'
                    : 'Shift-select another object to use it as the alignment reference.';
        document.getElementById('idstudio-draw-actions').hidden = !['pen', 'freehand'].includes(
            tool
        );
        canvas.requestRenderAll();
    }
    function advancedAction(action) {
        if (action === 'actions' || action === 'commands' || action === 'shortcuts') {
            openCommandMenu(action);
            return true;
        }
        if (action === 'multi-select') {
            state.multiSelect = !state.multiSelect;
            document
                .querySelector('[data-action="multi-select"]')
                .setAttribute('aria-pressed', String(state.multiSelect));
            return true;
        }
        if (action === 'copy' || action === 'cut') {
            copySelection(action === 'cut');
            return true;
        }
        if (action === 'paste') {
            pasteSelection();
            return true;
        }
        if (action === 'layer-front' || action === 'layer-back') {
            moveLayer(action === 'layer-front', true);
            return true;
        }
        if (action === 'set-reference') {
            var ids = selectedIds();
            state.referenceId = state.contextTarget || ids[ids.length - 1] || state.referenceId;
            document.getElementById('idstudio-align-target').value = 'reference';
            updateReferenceLabel();
            canvas.requestRenderAll();
            return true;
        }
        if (action === 'select-all') {
            canvas.discardActiveObject();
            canvas.setActiveObject(
                new fabric.ActiveSelection(
                    canvas.getObjects().filter(function (o) {
                        return o.visible;
                    }),
                    {canvas: canvas}
                )
            );
            canvas.requestRenderAll();
            return true;
        }
        if (action === 'copy-style') {
            var a = canvas.getActiveObject();
            if (a && a.studioData) {
                state.styleClipboard = renderer.clone(a.studioData);
            }
            return true;
        }
        if (
            action === 'paste-style' ||
            action === 'flip-horizontal' ||
            action === 'flip-vertical'
        ) {
            var selected = selectedIds();
            captureCurrent(false);
            var keys = [
                'fill',
                'stroke',
                'strokeWidth',
                'opacity',
                'shadow',
                'fontFamily',
                'fontSize',
                'fontWeight',
                'fontStyle',
                'align',
                'lineHeight',
                'charSpacing'
            ];
            recordsFor(selected)
                .filter(function (o) {
                    return !o.locked && !['qr', 'barcode'].includes(o.type);
                })
                .forEach(function (o) {
                    if (action === 'paste-style' && state.styleClipboard) {
                        keys.forEach(function (key) {
                            if (key in state.styleClipboard) {
                                o[key] = renderer.clone(state.styleClipboard[key]);
                            } else if (key === 'shadow') {
                                delete o.shadow;
                            }
                        });
                    } else if (action !== 'paste-style') {
                        var key = action === 'flip-horizontal' ? 'flipX' : 'flipY';
                        o[key] = !o[key];
                    }
                });
            logicalUnits(recordsFor(selected)).forEach(function (unit) {
                if (
                    !unit.some(function (o) {
                        return o.locked;
                    })
                ) {
                    geometry.fit(unit, state.widthMm, state.heightMm);
                }
            });
            commitDocument(selected);
            return true;
        }
        if (action === 'convert-curves') {
            convertCurves();
            return true;
        }
        if (action === 'node-edit') {
            enterNodeEdit();
            return true;
        }
        if (action.indexOf('node-') === 0 || action === 'path-closed') {
            editNode(action);
            return true;
        }
        if (action === 'finish-path') {
            finishPath();
            return true;
        }
        if (action === 'cancel-path') {
            setTool('select');
            return true;
        }
        if (action.indexOf('align-') === 0) {
            alignSelection(action.slice(6));
            return true;
        }
        if (action.indexOf('add-') === 0) {
            addObject(action.slice(4));
            return true;
        }
        if (action.indexOf('tool-') === 0) {
            setTool(action.slice(5));
            return true;
        }
        return false;
    }

    function commandEntries() {
        var selected = canvas.getActiveObjects(),
            single = selected.length === 1 ? selected[0] : null,
            any = selected.length > 0,
            editable = selected.some(function (o) {
                return !o.studioData.locked;
            }),
            path = single && single.studioType === 'path';
        return [
            ['copy', 'Copy', 'Ctrl/Cmd+C', any],
            ['cut', 'Cut', 'Ctrl/Cmd+X', editable],
            ['paste', 'Paste', 'Ctrl/Cmd+V', !!(state.clipboard && state.clipboard.length)],
            ['duplicate', 'Duplicate', 'Ctrl/Cmd+D', editable],
            ['delete', 'Delete', 'Delete', editable],
            ['group', 'Group', 'Ctrl/Cmd+G', selected.length > 1],
            ['ungroup', 'Ungroup', 'Ctrl/Cmd+Shift+G', any],
            ['lock', 'Lock / unlock', '', any],
            ['layer-up', 'Bring forward', '', editable],
            ['layer-down', 'Send backward', '', editable],
            ['layer-front', 'Bring to front', '', editable],
            ['layer-back', 'Send to back', '', editable],
            ['set-reference', 'Set as reference', '', any],
            ['copy-style', 'Copy style', '', !!single],
            ['paste-style', 'Paste style', '', editable && !!state.styleClipboard],
            ['align-left', 'Align left', '', any],
            ['align-center', 'Align horizontal centre', '', any],
            ['align-right', 'Align right', '', any],
            ['align-top', 'Align top', '', any],
            ['align-middle', 'Align vertical centre', '', any],
            ['align-bottom', 'Align bottom', '', any],
            ['align-both', 'Align centre both', '', any],
            ['distribute-horizontal', 'Distribute horizontally', '', selected.length >= 3],
            ['distribute-vertical', 'Distribute vertically', '', selected.length >= 3],
            [
                'convert-curves',
                'Convert to curves',
                '',
                single && !path && renderer.shapeTypes.includes(single.studioType) && editable
            ],
            ['node-edit', 'Edit nodes', 'N', path && editable],
            ['node-insert', 'Insert node', '', !!state.nodeObject],
            ['node-delete', 'Delete node', 'Delete', !!state.nodeObject],
            ['node-smooth', 'Smooth node', '', !!state.nodeObject],
            ['node-corner', 'Corner node', '', !!state.nodeObject],
            ['node-straight', 'Straight segment', '', !!state.nodeObject],
            ['node-curve', 'Curved segment', '', !!state.nodeObject],
            ['path-closed', 'Open / close path', '', !!state.nodeObject],
            ['flip-horizontal', 'Flip horizontal', '', editable],
            ['flip-vertical', 'Flip vertical', '', editable]
        ];
    }
    function closeCommandMenu(restore) {
        if (!state.menu) {
            return;
        }
        var focus = state.menuPreviousFocus;
        state.menu.remove();
        state.menu = null;
        state.contextTarget = '';
        document.removeEventListener('pointerdown', menuOutside, true);
        if (restore && focus && focus.focus) {
            focus.focus();
        }
    }
    function menuOutside(event) {
        if (state.menu && !state.menu.contains(event.target)) {
            closeCommandMenu(false);
        }
    }
    function openObjectMenu(event) {
        if (state.loading || !['select', 'node'].includes(state.tool)) {
            return;
        }
        var target = canvas.findTarget(event, true),
            selected = canvas.getActiveObjects();
        if (target && target.type !== 'activeSelection' && !selected.includes(target)) {
            chooseObject(target, false);
        }
        openCommandMenu('actions', event.clientX, event.clientY, target && target.studioId);
    }
    function openCommandMenu(kind, x, y, contextId) {
        closeCommandMenu(false);
        state.menuPreviousFocus = document.activeElement;
        state.contextTarget = contextId || '';
        var menu = document.createElement('div');
        menu.className = 'idstudio-command-menu';
        menu.setAttribute('role', 'dialog');
        menu.setAttribute(
            'aria-label',
            kind === 'shortcuts' ? 'Keyboard shortcuts' : 'Object actions and commands'
        );
        menu.tabIndex = -1;
        var header = document.createElement('header'),
            title = document.createElement('strong'),
            close = document.createElement('button');
        title.textContent = kind === 'shortcuts' ? 'Shortcuts' : 'Actions & commands';
        close.textContent = 'Close';
        close.type = 'button';
        close.addEventListener('click', function () {
            closeCommandMenu(true);
        });
        header.append(title, close);
        menu.appendChild(header);
        var search = document.createElement('input');
        search.type = 'search';
        search.placeholder = 'Search commands…';
        search.setAttribute('aria-label', 'Search commands');
        menu.appendChild(search);
        var list = document.createElement('div');
        list.className = 'idstudio-command-list';
        list.setAttribute('role', 'menu');
        menu.appendChild(list);
        var entries = commandEntries();
        if (kind !== 'actions') {
            entries = entries.concat([
                ['save', 'Save draft', 'Ctrl/Cmd+S', true],
                ['undo', 'Undo', 'Ctrl/Cmd+Z', true],
                ['redo', 'Redo', 'Ctrl/Cmd+Shift+Z / Y', true],
                ['select-all', 'Select all', 'Ctrl/Cmd+A', true],
                ['tool-select', 'Select', 'V', true],
                ['tool-pan', 'Pan', 'H / Space-drag', true],
                ['add-text', 'Text', 'T', true],
                ['add-rect', 'Rectangle', 'R', true],
                ['add-ellipse', 'Ellipse', 'O', true],
                ['tool-pen', 'Pen', 'P', true],
                ['tool-freehand', 'Freehand', '', true],
                ['finish-path', 'Finish drawing', 'Enter', state.drawNodes.length > 1],
                ['cancel-path', 'Cancel / deselect', 'Escape', true],
                ['zoom-fit', 'Fit canvas', 'Ctrl/Cmd+0', true],
                ['export-png', 'Export PNG', '', true],
                ['export-pdf', 'Export exact PDF', '', true],
                ['export-a4', 'Export A4 PDF', '', true],
                ['print', 'Print', '', true]
            ]);
            [
                'star',
                'triangle',
                'diamond',
                'polygon',
                'arrow',
                'rounded-rect',
                'line',
                'qr',
                'barcode'
            ].forEach(function (type) {
                entries.push(['add-' + type, 'Add ' + type, '', true]);
            });
        }
        function renderEntries() {
            list.replaceChildren();
            entries
                .filter(function (e) {
                    return (e[1] + ' ' + e[2]).toLowerCase().includes(search.value.toLowerCase());
                })
                .forEach(function (e) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.setAttribute('role', 'menuitem');
                    button.disabled = !e[3];
                    button.dataset.command = e[0];
                    var name = document.createElement('span'),
                        key = document.createElement('kbd');
                    name.textContent = e[1];
                    key.textContent = e[2];
                    button.append(name, key);
                    button.addEventListener('click', function () {
                        var reference = state.contextTarget;
                        closeCommandMenu(true);
                        state.contextTarget = reference;
                        runAction(e[0]);
                        state.contextTarget = '';
                    });
                    list.appendChild(button);
                });
        }
        search.addEventListener('input', renderEntries);
        renderEntries();
        menu.addEventListener('keydown', function (event) {
            event.stopPropagation();
            if (event.key === 'Escape') {
                event.preventDefault();
                closeCommandMenu(true);
                return;
            }
            var buttons = Array.from(list.querySelectorAll('button:not(:disabled)'));
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                var i = buttons.indexOf(document.activeElement),
                    next =
                        event.key === 'Home'
                            ? 0
                            : event.key === 'End'
                              ? buttons.length - 1
                              : (i +
                                    (event.key === 'ArrowDown' ? 1 : buttons.length - 1) +
                                    buttons.length) %
                                buttons.length;
                if (buttons[next]) {
                    buttons[next].focus();
                }
            }
            if (event.key === 'Tab') {
                var focusable = Array.from(menu.querySelectorAll('input,button:not(:disabled)'));
                var index = focusable.indexOf(document.activeElement);
                if (
                    (!event.shiftKey && index === focusable.length - 1) ||
                    (event.shiftKey && index === 0)
                ) {
                    event.preventDefault();
                    focusable[event.shiftKey ? focusable.length - 1 : 0].focus();
                }
            }
        });
        document.body.appendChild(menu);
        state.menu = menu;
        var rect = menu.getBoundingClientRect();
        menu.style.left =
            Math.max(
                8,
                Math.min(
                    x === undefined ? (window.innerWidth - rect.width) / 2 : x,
                    window.innerWidth - rect.width - 8
                )
            ) + 'px';
        menu.style.top =
            Math.max(8, Math.min(y === undefined ? 80 : y, window.innerHeight - rect.height - 8)) +
            'px';
        search.focus();
        document.addEventListener('pointerdown', menuOutside, true);
    }

    function convertCurves() {
        var active = canvas.getActiveObject();
        if (
            !active ||
            active.type === 'activeSelection' ||
            active.studioData.locked ||
            !renderer.shapeTypes.includes(active.studioType) ||
            active.studioType === 'path'
        ) {
            return;
        }
        captureCurrent(false);
        var data = recordsFor([active.studioId])[0];
        data.nodes = geometry.shapeNodes(data);
        if (data.type === 'line') {
            data.fill = data.stroke;
            data.stroke = 'transparent';
            data.strokeWidth = 0;
        }
        data.type = 'path';
        data.closed = true;
        delete data.shape;
        delete data.radius;
        commitDocument([data.id]).then(enterNodeEdit);
    }
    function exitNodeEdit() {
        if (state.nodeObject) {
            state.nodeObject.controls = fabric.Object.prototype.controls;
            state.nodeObject.hasBorders = true;
            state.nodeObject.lockMovementX = state.nodeObject.lockMovementY =
                !!state.nodeObject.studioData.locked;
            state.nodeObject = null;
        }
        var panel = document.getElementById('idstudio-node-actions');
        if (panel) {
            panel.hidden = true;
        }
        if (state.tool === 'node') {
            state.tool = 'select';
            canvas.selection = true;
            canvas.skipTargetFind = false;
        }
    }
    function enterNodeEdit() {
        var object = canvas.getActiveObject();
        if (!object || object.studioType !== 'path' || object.studioData.locked) {
            showMessage(
                'Select an editable curve first. Geometric shapes can be converted using Actions → Convert to curves.',
                'info'
            );
            return;
        }
        exitNodeEdit();
        state.nodeObject = object;
        state.tool = 'node';
        state.nodeIndex = Math.min(state.nodeIndex, object.studioData.nodes.length - 1);
        canvas.skipTargetFind = false;
        canvas.selection = false;
        object.hasBorders = false;
        object.lockMovementX = object.lockMovementY = true;
        buildNodeControls(object);
        syncAdvancedProperties(object);
        canvas.requestRenderAll();
        document.getElementById('idstudio-tool-hint').textContent =
            'Drag nodes and handles inside the frame. Actions includes node commands. Escape leaves node editing.';
    }
    function buildNodeControls(object) {
        var controls = {};
        object.studioData.nodes.forEach(function (node, index) {
            ['point', 'in', 'out'].forEach(function (kind) {
                if (kind !== 'point' && !node[kind]) {
                    return;
                }
                controls[index + '-' + kind] = new fabric.Control({
                    cursorStyle: 'crosshair',
                    actionName: 'editNode',
                    sizeX: kind === 'point' ? 10 : 7,
                    sizeY: kind === 'point' ? 10 : 7,
                    touchSizeX: 22,
                    touchSizeY: 22,
                    positionHandler: function (dim, matrix, target) {
                        var n = target.studioData.nodes[index],
                            p = kind === 'point' ? n : n[kind];
                        return fabric.util.transformPoint(
                            new fabric.Point(
                                (p.x - 0.5) * target.width,
                                (p.y - 0.5) * target.height
                            ),
                            fabric.util.multiplyTransformMatrices(
                                canvas.viewportTransform,
                                target.calcTransformMatrix()
                            )
                        );
                    },
                    mouseDownHandler: function () {
                        state.nodeIndex = index;
                        updateNodeStatus();
                        return true;
                    },
                    actionHandler: function (event, transform, x, y) {
                        state.nodeIndex = index;
                        var target = transform.target,
                            n = target.studioData.nodes[index],
                            p = fabric.util.transformPoint(
                                new fabric.Point(x, y),
                                fabric.util.invertTransform(target.calcTransformMatrix())
                            );
                        var nx = clamp(p.x / target.width + 0.5, 0, 1),
                            ny = clamp(p.y / target.height + 0.5, 0, 1);
                        if (kind === 'point') {
                            var dx = nx - n.x,
                                dy = ny - n.y,
                                factor = 1;
                            if (n.mode === 'smooth') {
                                ['in', 'out'].forEach(function (k) {
                                    if (n[k]) {
                                        var vx = n[k].x - n.x,
                                            vy = n[k].y - n.y;
                                        if (vx > 0) {
                                            factor = Math.min(factor, (1 - nx) / vx);
                                        }
                                        if (vx < 0) {
                                            factor = Math.min(factor, nx / -vx);
                                        }
                                        if (vy > 0) {
                                            factor = Math.min(factor, (1 - ny) / vy);
                                        }
                                        if (vy < 0) {
                                            factor = Math.min(factor, ny / -vy);
                                        }
                                    }
                                });
                            }
                            ['in', 'out'].forEach(function (k) {
                                if (n[k]) {
                                    n[k].x =
                                        n.mode === 'smooth'
                                            ? nx + (n[k].x - n.x) * factor
                                            : clamp(n[k].x + dx, 0, 1);
                                    n[k].y =
                                        n.mode === 'smooth'
                                            ? ny + (n[k].y - n.y) * factor
                                            : clamp(n[k].y + dy, 0, 1);
                                }
                            });
                            n.x = nx;
                            n.y = ny;
                        } else {
                            n[kind] = {x: nx, y: ny};
                            if (n.mode === 'smooth') {
                                var opposite = kind === 'in' ? 'out' : 'in',
                                    vx = nx - n.x,
                                    vy = ny - n.y,
                                    f = 1;
                                if (vx > 0) {
                                    f = Math.min(f, n.x / vx);
                                }
                                if (vx < 0) {
                                    f = Math.min(f, (1 - n.x) / -vx);
                                }
                                if (vy > 0) {
                                    f = Math.min(f, n.y / vy);
                                }
                                if (vy < 0) {
                                    f = Math.min(f, (1 - n.y) / -vy);
                                }
                                n[opposite] = {x: n.x - vx * f, y: n.y - vy * f};
                            }
                        }
                        target.dirty = true;
                        state.nodeChanged = true;
                        updateNodeStatus();
                        return true;
                    },
                    render: function (ctx, left, top) {
                        ctx.save();
                        ctx.fillStyle =
                            kind === 'point'
                                ? index === state.nodeIndex
                                    ? '#d97706'
                                    : '#2563eb'
                                : '#fff';
                        ctx.strokeStyle = '#2563eb';
                        ctx.lineWidth = 1.5;
                        ctx.beginPath();
                        ctx.arc(left, top, kind === 'point' ? 5 : 3.5, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.stroke();
                        ctx.restore();
                    }
                });
            });
        });
        object.controls = controls;
        object.setCoords();
        updateNodeStatus();
    }
    function updateNodeStatus() {
        var el = document.getElementById('idstudio-node-status');
        if (el && state.nodeObject) {
            el.textContent =
                'Node ' +
                (state.nodeIndex + 1) +
                ' of ' +
                state.nodeObject.studioData.nodes.length +
                ' · ' +
                state.nodeObject.studioData.nodes[state.nodeIndex].mode;
        }
    }
    function midpoint(a, b) {
        return {x: (a.x + b.x) / 2, y: (a.y + b.y) / 2};
    }
    function editNode(action) {
        var object = state.nodeObject;
        if (!object || state.loading) {
            return;
        }
        var data = object.studioData,
            nodes = data.nodes,
            i = state.nodeIndex,
            n = nodes[i],
            next = nodes[(i + 1) % nodes.length],
            prev = nodes[(i + nodes.length - 1) % nodes.length];
        if (
            !data.closed &&
            i === nodes.length - 1 &&
            ['node-straight', 'node-curve'].includes(action)
        ) {
            showMessage('Select a node with a following segment to edit.', 'info');
            return;
        }
        if (action === 'node-delete') {
            if (nodes.length <= 2) {
                showMessage('A path needs at least two nodes.', 'info');
                return;
            }
            nodes.splice(i, 1);
            state.nodeIndex = Math.min(i, nodes.length - 1);
        }
        if (action === 'node-insert') {
            if (nodes.length >= 256) {
                showMessage('A curve supports up to 256 nodes.', 'info');
                return;
            }
            if (!data.closed && i === nodes.length - 1) {
                showMessage('Select a node with a following segment to split.', 'info');
                return;
            }
            if (n.out || next.in) {
                var a = midpoint(n, n.out || n),
                    b = midpoint(n.out || n, next.in || next),
                    c = midpoint(next.in || next, next),
                    d = midpoint(a, b),
                    e = midpoint(b, c),
                    m = midpoint(d, e);
                n.out = a;
                next.in = c;
                m.in = d;
                m.out = e;
                m.mode = 'smooth';
                nodes.splice(i + 1, 0, m);
            } else {
                nodes.splice(i + 1, 0, Object.assign(midpoint(n, next), {mode: 'corner'}));
            }
            state.nodeIndex = i + 1;
        }
        if (action === 'node-smooth') {
            n.mode = 'smooth';
            var dx = (next.x - prev.x) / 6,
                dy = (next.y - prev.y) / 6,
                f = 1;
            if (dx) {
                f = Math.min(f, Math.min(n.x, 1 - n.x) / Math.abs(dx));
            }
            if (dy) {
                f = Math.min(f, Math.min(n.y, 1 - n.y) / Math.abs(dy));
            }
            n.in = {x: n.x - dx * f, y: n.y - dy * f};
            n.out = {x: n.x + dx * f, y: n.y + dy * f};
        }
        if (action === 'node-corner') {
            n.mode = 'corner';
        }
        if (action === 'node-straight') {
            delete n.out;
            delete next.in;
            n.mode = next.mode = 'corner';
        }
        if (action === 'node-curve') {
            n.out = {x: n.x + (next.x - n.x) / 3, y: n.y + (next.y - n.y) / 3};
            next.in = {x: n.x + ((next.x - n.x) * 2) / 3, y: n.y + ((next.y - n.y) * 2) / 3};
            n.mode = next.mode = 'corner';
        }
        if (action === 'path-closed') {
            data.closed = !data.closed;
        }
        buildNodeControls(object);
        object.dirty = true;
        objectChanged();
        canvas.requestRenderAll();
    }
    function drawPointer(event) {
        var p = canvas.getPointer(event.e);
        return {
            x: clamp(p.x / renderer.PX_PER_MM, 0.125, state.widthMm - 0.125),
            y: clamp(p.y / renderer.PX_PER_MM, 0.125, state.heightMm - 0.125),
            mode: 'corner'
        };
    }
    function drawDown(event) {
        if (!['pen', 'freehand'].includes(state.tool) || state.spaceDown || event.e.button === 2) {
            return;
        }
        if (state.tool === 'pen' && state.drawNodes.length >= 256) {
            showMessage('Finish this path: the 256-node limit has been reached.', 'info');
            return;
        }
        if (state.tool === 'freehand' && state.drawNodes.length) {
            return;
        }
        state.gesture = true;
        state.drawing = true;
        clearTimeout(state.autosaveTimer);
        state.drawNodes.push(drawPointer(event));
        canvas.requestRenderAll();
    }
    function drawMove(event) {
        if (!state.drawing) {
            return;
        }
        var p = drawPointer(event),
            n = state.drawNodes[state.drawNodes.length - 1];
        if (state.tool === 'freehand') {
            if (Math.hypot(p.x - n.x, p.y - n.y) > 0.15 && state.drawNodes.length < 4096) {
                state.drawNodes.push(p);
            }
        } else if (state.tool === 'pen') {
            var dx = p.x - n.x,
                dy = p.y - n.y,
                f = 1;
            if (dx) {
                f = Math.min(f, Math.min(n.x - 0.125, state.widthMm - 0.125 - n.x) / Math.abs(dx));
            }
            if (dy) {
                f = Math.min(f, Math.min(n.y - 0.125, state.heightMm - 0.125 - n.y) / Math.abs(dy));
            }
            if (Math.hypot(dx, dy) > 0.2) {
                n.out = {x: n.x + dx * f, y: n.y + dy * f};
                n.in = {x: n.x - dx * f, y: n.y - dy * f};
                n.mode = 'smooth';
            }
        }
        canvas.requestRenderAll();
    }
    function drawUp() {
        state.drawing = false;
        if (state.nodeChanged) {
            state.nodeChanged = false;
            objectChanged();
            if (state.nodeObject) {
                buildNodeControls(state.nodeObject);
            }
        }
    }
    function finishPath() {
        if (state.drawNodes.length < 2) {
            showMessage('Add at least two points, or Cancel to leave drawing.', 'info');
            return;
        }
        var nodes = renderer.clone(state.drawNodes),
            tolerance = 0.15;
        if (state.tool === 'freehand') {
            var original = nodes;
            do {
                nodes = geometry.simplify(original, tolerance);
                tolerance *= 1.5;
            } while (nodes.length > 256);
        }
        var points = [];
        nodes.forEach(function (n) {
            points.push(n);
            if (n.in) {
                points.push(n.in);
            }
            if (n.out) {
                points.push(n.out);
            }
        });
        var left = Math.min.apply(
                null,
                points.map(function (p) {
                    return p.x;
                })
            ),
            top = Math.min.apply(
                null,
                points.map(function (p) {
                    return p.y;
                })
            );
        var width = Math.max(
                0.1,
                Math.max.apply(
                    null,
                    points.map(function (p) {
                        return p.x;
                    })
                ) - left
            ),
            height = Math.max(
                0.1,
                Math.max.apply(
                    null,
                    points.map(function (p) {
                        return p.y;
                    })
                ) - top
            );
        nodes.forEach(function (n) {
            var all = [n];
            if (n.in) {
                all.push(n.in);
            }
            if (n.out) {
                all.push(n.out);
            }
            all.forEach(function (p) {
                p.x = (p.x - left) / width;
                p.y = (p.y - top) / height;
            });
        });
        setTool('select');
        addCanonical({
            id: nextId('path'),
            type: 'path',
            x: left + width / 2,
            y: top + height / 2,
            width: width,
            height: height,
            rotation: 0,
            opacity: 1,
            visible: true,
            locked: false,
            fill: 'transparent',
            stroke: '#111827',
            strokeWidth: 0.25,
            nodes: nodes,
            closed: false
        }).then(enterNodeEdit);
    }
    function drawPenPreview() {
        var ctx = canvas.getContext();
        if (state.nodeObject) {
            ctx.save();
            ctx.strokeStyle = '#94a3b8';
            ctx.lineWidth = 1;
            var object = state.nodeObject,
                matrix = fabric.util.multiplyTransformMatrices(
                    canvas.viewportTransform,
                    object.calcTransformMatrix()
                );
            object.studioData.nodes.forEach(function (n) {
                var p = fabric.util.transformPoint(
                    new fabric.Point((n.x - 0.5) * object.width, (n.y - 0.5) * object.height),
                    matrix
                );
                ['in', 'out'].forEach(function (k) {
                    if (n[k]) {
                        var q = fabric.util.transformPoint(
                            new fabric.Point(
                                (n[k].x - 0.5) * object.width,
                                (n[k].y - 0.5) * object.height
                            ),
                            matrix
                        );
                        ctx.beginPath();
                        ctx.moveTo(p.x, p.y);
                        ctx.lineTo(q.x, q.y);
                        ctx.stroke();
                    }
                });
            });
            ctx.restore();
        }
        if (!state.drawNodes.length) {
            return;
        }
        ctx.save();
        ctx.scale(state.zoom * renderer.PX_PER_MM, state.zoom * renderer.PX_PER_MM);
        ctx.strokeStyle = '#2563eb';
        ctx.lineWidth = 0.25;
        ctx.beginPath();
        state.drawNodes.forEach(function (n, i) {
            if (!i) {
                ctx.moveTo(n.x, n.y);
            } else {
                var prev = state.drawNodes[i - 1];
                if (prev.out || n.in) {
                    var a = prev.out || prev,
                        b = n.in || n;
                    ctx.bezierCurveTo(a.x, a.y, b.x, b.y, n.x, n.y);
                } else {
                    ctx.lineTo(n.x, n.y);
                }
            }
        });
        ctx.stroke();
        ctx.restore();
    }

    function keyboardShortcut(event) {
        if (state.menu) {
            return;
        }
        var target = event.target,
            editing =
                target &&
                (/INPUT|TEXTAREA|SELECT/.test(target.tagName) || target.isContentEditable);
        var key = event.key.toLowerCase(),
            command = event.ctrlKey || event.metaKey;
        if (command && key === 's') {
            event.preventDefault();
            if (editing) {
                target.blur();
            }
            saveDraft(false).catch(function () {});
            return;
        }
        if (editing || state.loading) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            if (state.openPanel) {
                closePanel(true);
            } else {
                setTool('select');
                canvas.discardActiveObject();
                canvas.requestRenderAll();
            }
            return;
        }
        if ((event.shiftKey && event.key === 'F10') || event.key === 'ContextMenu') {
            event.preventDefault();
            openCommandMenu('actions');
            return;
        }
        if (key === '?' || event.key === 'F1') {
            event.preventDefault();
            openCommandMenu('shortcuts');
            return;
        }
        if (command && key === 'k') {
            event.preventDefault();
            openCommandMenu('commands');
            return;
        }
        if (event.key === 'Enter' && state.drawNodes.length) {
            event.preventDefault();
            finishPath();
            return;
        }
        if (!command && (event.code === 'Space' || event.key === ' ')) {
            state.spaceDown = true;
            canvas.skipTargetFind = true;
            elements.scroll.classList.add('is-pan-ready');
            event.preventDefault();
            return;
        }
        if (state.drawNodes.length) {
            return;
        }
        if (command) {
            var actions = {
                s: 'save',
                z: event.shiftKey ? 'redo' : 'undo',
                y: 'redo',
                d: 'duplicate',
                c: 'copy',
                x: 'cut',
                v: 'paste',
                a: 'select-all',
                g: event.shiftKey ? 'ungroup' : 'group',
                0: 'zoom-fit'
            };
            if (actions[key]) {
                event.preventDefault();
                runAction(actions[key]);
            }
            return;
        }
        if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            state.nodeObject ? editNode('node-delete') : deleteSelection();
            return;
        }
        if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
            var selected = canvas.getActiveObject();
            if (!selected || state.nodeObject) {
                return;
            }
            event.preventDefault();
            if (
                canvas.getActiveObjects().some(function (o) {
                    return o.studioData.locked;
                })
            ) {
                showMessage('Unlock the selected objects before moving them.', 'info');
                return;
            }
            var amount = (event.shiftKey ? 5 : 1) * renderer.PX_PER_MM;
            if (event.key === 'ArrowLeft') {
                selected.left -= amount;
            }
            if (event.key === 'ArrowRight') {
                selected.left += amount;
            }
            if (event.key === 'ArrowUp') {
                selected.top -= amount;
            }
            if (event.key === 'ArrowDown') {
                selected.top += amount;
            }
            keepInside(selected);
            selected.setCoords();
            canvas.requestRenderAll();
            objectChanged();
            return;
        }
        if (event.altKey) {
            return;
        }
        var tools = {v: 'select', h: 'pan', p: 'pen', n: 'node'},
            add = {t: 'text', r: 'rect', o: 'ellipse'};
        if (tools[key]) {
            event.preventDefault();
            setTool(tools[key]);
        }
        if (add[key]) {
            event.preventDefault();
            setTool('select');
            addObject(add[key]);
        }
    }

    function keyboardKeyup(event) {
        if (event.code === 'Space' || event.key === ' ') {
            state.spaceDown = false;
            canvas.skipTargetFind = !['select', 'node'].includes(state.tool);
            if (!state.panning) {
                elements.scroll.classList.remove('is-pan-ready');
            }
        }
    }

    function beginPan(event) {
        if (!state.spaceDown && state.tool !== 'pan' && event.button !== 1) {
            return;
        }
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
        if (!state.panning || !state.panOrigin) {
            return;
        }
        elements.scroll.scrollLeft = state.panOrigin.left - (event.clientX - state.panOrigin.x);
        elements.scroll.scrollTop = state.panOrigin.top - (event.clientY - state.panOrigin.y);
    }

    function endPan() {
        if (!state.panning) {
            return;
        }
        state.panning = false;
        state.panOrigin = null;
        canvas.selection = state.tool === 'select';
        elements.scroll.classList.remove('is-panning');
        if (!state.spaceDown) {
            elements.scroll.classList.remove('is-pan-ready');
        }
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
        return fetch(url, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            return response
                .json()
                .catch(function () {
                    throw responseError(
                        'The server returned an unreadable response.',
                        response.status
                    );
                })
                .then(function (payload) {
                    if (!response.ok) {
                        throw responseError(
                            payload.message || 'The request failed.',
                            response.status
                        );
                    }
                    return payload;
                });
        });
    }

    function responseError(message, status) {
        var error = new Error(message);
        error.status = status;
        return error;
    }

    function setSaveState(message) {
        elements.saveState.textContent = message;
    }

    function showMessage(message, type) {
        elements.alert.className = 'alert alert-' + (type || 'info') + ' idstudio-alert is-visible';
        elements.alert.textContent = message;
        window.clearTimeout(elements.alert._hideTimer);
        elements.alert._hideTimer = window.setTimeout(function () {
            elements.alert.classList.remove('is-visible');
        }, 8000);
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
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportError(error) {
        showMessage(error.message || 'The export could not be created.', 'danger');
    }

    function safeTitle() {
        return (
            (elements.title.value || 'id-card')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '') || 'id-card'
        );
    }

    function layerIcon(type) {
        if (type === 'text') {
            return 'fa fa-font';
        }
        if (type === 'image') {
            return 'fa fa-picture-o';
        }
        if (type === 'qr') {
            return 'fa fa-qrcode';
        }
        if (type === 'barcode') {
            return 'fa fa-barcode';
        }
        if (type === 'ellipse') {
            return 'fa fa-circle-thin';
        }
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
})(window, document);
