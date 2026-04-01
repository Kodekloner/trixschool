<?php
$builder_type = isset($builder_type) ? $builder_type : 'student';
$builder_card = isset($builder_card) ? $builder_card : null;
$dimensions = get_id_card_dimension_config($builder_card);
$layout_json_value = isset($builder_card->layout_json) ? sanitize_id_card_layout_json($builder_card->layout_json, $builder_type, $dimensions['portrait']) : sanitize_id_card_layout_json('', $builder_type, $dimensions['portrait']);
$layout_defaults_portrait = get_default_id_card_layout($builder_type, true);
$layout_defaults_landscape = get_default_id_card_layout($builder_type, false);
$preview_id = $builder_type . '_id_card_designer';
$layout_input_id = $builder_type . '_layout_json';
$preview_card = $builder_card ? clone $builder_card : new stdClass();
$preview_card->enable_admission_no = 1;
$preview_card->enable_student_name = 1;
$preview_card->enable_class = 1;
$preview_card->enable_fathers_name = 1;
$preview_card->enable_mothers_name = 1;
$preview_card->enable_address = 1;
$preview_card->enable_phone = 1;
$preview_card->enable_dob = 1;
$preview_card->enable_blood_group = 1;
$preview_card->enable_name = 1;
$preview_card->enable_staff_id = 1;
$preview_card->enable_staff_department = 1;
$preview_card->enable_designation = 1;
$preview_card->enable_date_of_joining = 1;
$preview_card->enable_staff_phone = 1;
$preview_card->enable_staff_dob = 1;
$preview_card->enable_permanent_address = 1;
$preview_card->photo_style = isset($preview_card->photo_style) ? $preview_card->photo_style : 'round';

if ($builder_type === 'staff') {
    $preview_payload = array(
        'name' => 'Staff Name',
        'employee_id' => 'STF-9000',
        'designation' => 'Administrator',
        'department' => 'Admin',
        'father_name' => 'Father Name',
        'mother_name' => 'Mother Name',
        'date_of_joining' => date('d-m-Y', strtotime('2020-01-01')),
        'phone' => '0800 000 0000',
        'dob' => date('d-m-Y', strtotime('1990-01-01')),
        'address' => 'No. 1 Street Name, City',
        'staff_id' => 1,
        'photo_url' => get_staff_photo_url(''),
    );
    $checkbox_map = array(
        'name' => 'enable_name',
        'designation' => 'enable_designation',
        'staff_id' => 'enable_staff_id',
        'department' => 'enable_department',
        'father_name' => 'enable_fathers_name',
        'mother_name' => 'enable_staff_mother_name',
        'date_of_joining' => 'enable_date_of_joining',
        'phone' => 'enable_staff_phone',
        'dob' => 'enable_staff_dob',
        'address' => 'enable_staff_permanent_address',
    );
} else {
    $preview_payload = array(
        'student_name' => 'Student Name',
        'admission_no' => 'ADM-1001',
        'class_section' => 'Class 6 - A',
        'father_name' => 'Father Name',
        'mother_name' => 'Mother Name',
        'address' => 'No. 1 Street Name, City',
        'phone' => '0800 000 0000',
        'dob' => '25-06-2006',
        'blood_group' => 'A+',
        'student_session_id' => 1,
        'photo_url' => get_student_image_url('', 'male'),
    );
    $checkbox_map = array(
        'student_name' => 'enable_student_name',
        'admission_no' => 'enable_admission_no',
        'class' => 'enable_class',
        'father_name' => 'enable_father_name',
        'mother_name' => 'enable_mother_name',
        'address' => 'enable_address',
        'phone' => 'enable_phone',
        'dob' => 'enable_dob',
        'blood_group' => 'enable_blood_group',
    );
}
?>
<?php $this->load->view('admin/idcard/_shared_styles'); ?>
<style type="text/css">
    .id-card-designer-shell {
        border: 1px solid #d2d6de;
        border-radius: 8px;
        background: #f9fafb;
        padding: 12px;
        margin-bottom: 15px;
        position: relative;
    }

    .id-card-designer-shell .help-block {
        margin-bottom: 8px;
    }

    .id-card-designer-preview {
        padding: 14px;
        background: linear-gradient(180deg, #ffffff, #eef2f7);
        border-radius: 10px;
        overflow: auto;
        min-height: 520px;
    }

    .id-card-designer-stage {
        position: relative;
        min-width: 100%;
        min-height: 100%;
    }

    .id-card-designer-zoom-target {
        transform-origin: top left;
    }

    .id-card-designer-shell.is-large-window {
        position: fixed;
        top: 50%;
        left: 50%;
        width: min(1200px, calc(100vw - 36px));
        height: min(92vh, 920px);
        transform: translate(-50%, -50%);
        z-index: 1060;
        margin: 0;
        background: rgba(248, 250, 252, 0.98);
        box-shadow: 0 24px 48px rgba(15, 23, 42, 0.28);
        display: flex;
        flex-direction: column;
    }

    .id-card-designer-shell.is-large-window .id-card-designer-preview {
        min-height: 0;
        flex: 1 1 auto;
        overflow: auto;
    }

    .id-card-designer-shell.is-large-window .id-card-designer-stage {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 20px 24px 36px;
        box-sizing: border-box;
    }

    .id-card-designer-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        z-index: 1050;
    }

    .id-card-designer-overlay.active {
        display: block;
    }

    .designer-element {
        cursor: move;
        user-select: none;
        overflow: visible;
        background: transparent !important;
        border: 1px dashed rgba(37, 99, 235, 0.7) !important;
        border-radius: 8px !important;
        box-shadow: none !important;
    }

    .designer-element.hidden-by-toggle {
        display: none !important;
    }

    .designer-element.id-card-panel,
    .designer-element.id-card-heading,
    .designer-element.id-card-qr,
    .designer-element.id-card-signature,
    .designer-element.id-card-brand,
    .designer-element.id-card-photo {
        background: transparent !important;
    }

    .designer-element.id-card-heading {
        color: #0f172a !important;
        text-transform: none;
        letter-spacing: 0;
    }

    .designer-element.id-card-qr {
        padding: 4px !important;
    }

    .designer-resize-handle {
        position: absolute;
        background: #2563eb;
        border: 1px solid #ffffff;
        border-radius: 999px;
        z-index: 15;
        opacity: 0.9;
    }

    .designer-resize-handle.edge-e {
        top: calc(50% - 5px);
        right: -3px;
        width: 6px;
        height: 10px;
        cursor: ew-resize;
    }

    .designer-resize-handle.edge-s {
        left: calc(50% - 5px);
        bottom: -3px;
        width: 10px;
        height: 6px;
        cursor: ns-resize;
    }

    .designer-resize-handle.corner-se {
        right: -3px;
        bottom: -3px;
        width: 7px;
        height: 7px;
        cursor: nwse-resize;
    }

    .card-size-grid .form-group {
        margin-bottom: 10px;
    }

    .card-size-group {
        clear: both;
        display: block;
        width: 100%;
    }

    .card-size-group > label:first-child {
        display: block;
        float: none;
        clear: both;
        width: 100%;
        margin-bottom: 8px;
    }

    .card-size-group > .req {
        display: inline-block;
        margin-left: 2px;
        vertical-align: top;
    }

    .id-card-designer-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        gap: 10px;
        flex-wrap: wrap;
    }

    .id-card-designer-meta {
        font-size: 12px;
        color: #6b7280;
    }

    .id-card-designer-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .id-card-designer-toolbar input[type="range"] {
        width: 180px;
    }

    .id-card-designer-zoom-value {
        min-width: 52px;
        font-size: 12px;
        font-weight: 700;
        text-align: center;
        color: #334155;
    }
</style>

<div class="form-group card-size-group">
    <label>Card Size</label>
    <div class="row card-size-grid">
        <div class="col-xs-12">
            <div class="form-group">
                <label for="<?php echo $builder_type; ?>_card_orientation">Orientation</label>
                <select class="form-control" id="<?php echo $builder_type; ?>_card_orientation">
                    <option value="portrait" <?php echo $dimensions['portrait'] ? 'selected' : ''; ?>>Vertical</option>
                    <option value="landscape" <?php echo !$dimensions['portrait'] ? 'selected' : ''; ?>>Horizontal</option>
                </select>
            </div>
        </div>
        <div class="col-xs-12">
            <div class="form-group">
                <label class="sr-only" for="<?php echo $builder_type; ?>_card_unit">Unit</label>
                <select class="form-control" name="card_unit" id="<?php echo $builder_type; ?>_card_unit">
                    <option value="in" <?php echo $dimensions['unit'] === 'in' ? 'selected' : ''; ?>>Inches</option>
                    <option value="mm" <?php echo $dimensions['unit'] === 'mm' ? 'selected' : ''; ?>>Millimetres</option>
                    <option value="cm" <?php echo $dimensions['unit'] === 'cm' ? 'selected' : ''; ?>>Centimetres</option>
                    <option value="px" <?php echo $dimensions['unit'] === 'px' ? 'selected' : ''; ?>>Pixels</option>
                </select>
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                <label for="<?php echo $builder_type; ?>_card_width">Width</label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="card_width" id="<?php echo $builder_type; ?>_card_width" value="<?php echo set_value('card_width', format_id_card_measurement($dimensions['width'])); ?>" placeholder="Width">
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                <label for="<?php echo $builder_type; ?>_card_height">Height</label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="card_height" id="<?php echo $builder_type; ?>_card_height" value="<?php echo set_value('card_height', format_id_card_measurement($dimensions['height'])); ?>" placeholder="Height">
            </div>
        </div>
    </div>
    <span class="help-block">Vertical default is 2.1 inches by 3.3 inches. Horizontal default is 3.3 inches by 2.1 inches.</span>
</div>

<div class="form-group">
    <label>Photo Shape</label>
    <select class="form-control" name="photo_style" id="<?php echo $builder_type; ?>_photo_style">
        <option value="round" <?php echo (!isset($builder_card->photo_style) || $builder_card->photo_style === 'round') ? 'selected' : ''; ?>>Round</option>
        <option value="square" <?php echo (isset($builder_card->photo_style) && $builder_card->photo_style === 'square') ? 'selected' : ''; ?>>Square</option>
    </select>
</div>

<div class="form-group">
    <label>Layout Designer</label>
    <div class="id-card-designer-overlay" data-designer-overlay="<?php echo $preview_id; ?>"></div>
    <div class="id-card-designer-shell">
        <div class="id-card-designer-actions">
            <div class="id-card-designer-meta">Drag items in the preview to change their position. The saved layout is used for print and preview.</div>
            <div class="id-card-designer-toolbar">
                <button type="button" class="btn btn-default btn-xs" data-zoom-out="<?php echo $preview_id; ?>">-</button>
                <input type="range" min="80" max="320" step="10" value="170" data-zoom-range="<?php echo $preview_id; ?>">
                <button type="button" class="btn btn-default btn-xs" data-zoom-in="<?php echo $preview_id; ?>">+</button>
                <span class="id-card-designer-zoom-value" data-zoom-value="<?php echo $preview_id; ?>">170%</span>
                <button type="button" class="btn btn-default btn-xs" data-reset-layout="<?php echo $preview_id; ?>">Reset Positions</button>
                <button type="button" class="btn btn-primary btn-xs" data-toggle-large="<?php echo $preview_id; ?>">Open Large Window</button>
            </div>
        </div>
        <div class="id-card-designer-preview">
            <div class="id-card-designer-stage" data-designer-stage="<?php echo $preview_id; ?>">
                <div class="id-card-designer-zoom-target" data-zoom-target="<?php echo $preview_id; ?>">
                    <div id="<?php echo $preview_id; ?>">
                        <?php
                        if ($builder_type === 'staff') {
                            $this->load->view('admin/idcard/_staff_card_item', array('card' => $preview_card, 'staff' => $preview_payload, 'designer_mode' => true));
                        } else {
                            $this->load->view('admin/idcard/_student_card_item', array('card' => $preview_card, 'student' => $preview_payload, 'designer_mode' => true));
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" name="layout_json" id="<?php echo $layout_input_id; ?>" value="<?php echo html_escape($layout_json_value); ?>">
</div>

<script type="text/javascript">
if (!window.initIdCardDesigner) {
    window.initIdCardDesigner = function (config) {
        var wrapper = document.getElementById(config.previewId);
        if (!wrapper) {
            return;
        }

        var card = wrapper.querySelector('.id-card-canvas');
        var background = wrapper.querySelector('.id-card-bg');
        var widthInput = document.getElementById(config.widthInputId);
        var heightInput = document.getElementById(config.heightInputId);
        var orientationInput = document.getElementById(config.orientationInputId);
        var unitInput = document.getElementById(config.unitInputId);
        var photoStyleInput = document.getElementById(config.photoStyleInputId);
        var layoutInput = document.getElementById(config.layoutInputId);
        var shell = wrapper.closest('.id-card-designer-shell');
        var overlay = document.querySelector('[data-designer-overlay="' + config.previewId + '"]');
        var stage = document.querySelector('[data-designer-stage="' + config.previewId + '"]');
        var zoomTarget = document.querySelector('[data-zoom-target="' + config.previewId + '"]');
        var zoomRange = document.querySelector('[data-zoom-range="' + config.previewId + '"]');
        var zoomValue = document.querySelector('[data-zoom-value="' + config.previewId + '"]');
        var zoomIn = document.querySelector('[data-zoom-in="' + config.previewId + '"]');
        var zoomOut = document.querySelector('[data-zoom-out="' + config.previewId + '"]');
        var toggleLarge = document.querySelector('[data-toggle-large="' + config.previewId + '"]');
        var resetButton = document.querySelector('[data-reset-layout="' + config.previewId + '"]');
        var form = layoutInput ? layoutInput.form : null;
        var headerColorInput = form ? form.querySelector('input[name="header_color"]') : null;
        var backgroundInput = form ? form.querySelector('input[name="background_image"]') : null;
        var titleNode = card.querySelector('[data-element="title"]');
        var elements = {};
        var layout = JSON.parse(layoutInput.value || '{}');
        var zoom = zoomRange ? percent(zoomRange.value, 170) / 100 : 1.7;

        card.querySelectorAll('.designer-element').forEach(function (node) {
            elements[node.getAttribute('data-element')] = node;
        });

        Object.keys(elements).forEach(function (key) {
            ['edge-e', 'edge-s', 'corner-se'].forEach(function (handleType) {
                if (!elements[key].querySelector('.designer-resize-handle.' + handleType)) {
                    var handle = document.createElement('span');
                    handle.className = 'designer-resize-handle ' + handleType;
                    handle.setAttribute('data-direction', handleType);
                    elements[key].appendChild(handle);
                }
            });
        });

        function percent(value, fallback) {
            var parsed = parseFloat(value);
            return isNaN(parsed) ? fallback : parsed;
        }

        function clamp(value, min, max) {
            return Math.min(max, Math.max(min, value));
        }

        function currentDefaults() {
            var width = percent(widthInput.value, 2.1);
            var height = percent(heightInput.value, 3.3);
            return height >= width ? JSON.parse(JSON.stringify(config.defaultsPortrait)) : JSON.parse(JSON.stringify(config.defaultsLandscape));
        }

        function ensureLayout() {
            var defaults = currentDefaults();
            Object.keys(defaults).forEach(function (key) {
                if (!layout[key]) {
                    layout[key] = defaults[key];
                }
                ['x', 'y', 'w', 'h'].forEach(function (axis) {
                    layout[key][axis] = percent(layout[key][axis], defaults[key][axis]);
                });
            });
        }

        function renderLayout() {
            ensureLayout();
            Object.keys(elements).forEach(function (key) {
                if (!layout[key]) {
                    return;
                }
                var node = elements[key];
                node.style.left = layout[key].x + '%';
                node.style.top = layout[key].y + '%';
                node.style.width = layout[key].w + '%';
                node.style.height = layout[key].h + '%';
            });
            layoutInput.value = JSON.stringify(layout);
        }

        function syncSize() {
            var width = percent(widthInput.value, 2.1);
            var height = percent(heightInput.value, 3.3);
            var unit = unitInput.value || 'in';
            card.style.width = width + unit;
            card.style.height = height + unit;
            syncZoom();
            syncOrientationFromSize();
        }

        function syncOrientationFromSize() {
            if (!orientationInput) {
                return;
            }
            var width = percent(widthInput.value, 2.1);
            var height = percent(heightInput.value, 3.3);
            orientationInput.value = height >= width ? 'portrait' : 'landscape';
        }

        function applyOrientationDefaults() {
            if (!orientationInput) {
                return;
            }
            if (orientationInput.value === 'landscape') {
                widthInput.value = '3.3';
                heightInput.value = '2.1';
            } else {
                widthInput.value = '2.1';
                heightInput.value = '3.3';
            }
            layout = currentDefaults();
            syncSize();
            renderLayout();
        }

        function syncPhotoShape() {
            var photo = card.querySelector('[data-element="photo"] img');
            if (!photo) {
                return;
            }
            photo.classList.remove('round', 'square');
            photo.classList.add(photoStyleInput.value === 'square' ? 'square' : 'round');
        }

        function syncHeaderColor() {
            if (titleNode && headerColorInput) {
                titleNode.style.background = headerColorInput.value || '#1f3c88';
            }
        }

        function syncBackgroundPreview() {
            if (!backgroundInput || !background) {
                return;
            }

            if (!backgroundInput.files || !backgroundInput.files[0]) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function (event) {
                background.style.backgroundImage = 'url(' + event.target.result + ')';
            };
            reader.readAsDataURL(backgroundInput.files[0]);
        }

        function syncZoom() {
            if (!zoomTarget || !stage) {
                return;
            }
            zoomTarget.style.transform = 'scale(' + zoom + ')';
            stage.style.width = (card.offsetWidth * zoom) + 'px';
            stage.style.height = (card.offsetHeight * zoom) + 'px';
            if (zoomValue) {
                zoomValue.textContent = Math.round(zoom * 100) + '%';
            }
            if (zoomRange) {
                zoomRange.value = Math.round(zoom * 100);
            }
        }

        function syncToggles() {
            Object.keys(config.checkboxMap).forEach(function (elementKey) {
                var checkbox = document.getElementById(config.checkboxMap[elementKey]);
                var node = elements[elementKey];
                if (!node || !checkbox) {
                    return;
                }
                node.classList.toggle('hidden-by-toggle', !checkbox.checked);
            });
        }

        function minimumSize(key) {
            if (key === 'qr') {
                return {w: 22, h: 16};
            }

            return {w: 8, h: 6};
        }

        var activeDrag = null;

        function startDrag(event) {
            var handle = event.target.closest('.designer-resize-handle');
            var source = event.target.closest('.designer-element');
            if (!source) {
                return;
            }
            event.preventDefault();
            var key = source.getAttribute('data-element');
            var point = event.touches ? event.touches[0] : event;
            activeDrag = {
                key: key,
                startX: point.clientX,
                startY: point.clientY,
                mode: handle ? 'resize' : 'move',
                direction: handle ? handle.getAttribute('data-direction') : '',
                originX: layout[key].x,
                originY: layout[key].y,
                originW: layout[key].w,
                originH: layout[key].h
            };
        }

        function moveDrag(event) {
            if (!activeDrag) {
                return;
            }
            event.preventDefault();
            var point = event.touches ? event.touches[0] : event;
            var rect = card.getBoundingClientRect();
            var dx = ((point.clientX - activeDrag.startX) / rect.width) * 100;
            var dy = ((point.clientY - activeDrag.startY) / rect.height) * 100;
            var box = layout[activeDrag.key];
            if (activeDrag.mode === 'resize') {
                var minSize = minimumSize(activeDrag.key);
                if (activeDrag.direction === 'edge-e' || activeDrag.direction === 'corner-se') {
                    box.w = clamp(activeDrag.originW + dx, minSize.w, 100 - box.x);
                }
                if (activeDrag.direction === 'edge-s' || activeDrag.direction === 'corner-se') {
                    box.h = clamp(activeDrag.originH + dy, minSize.h, 100 - box.y);
                }
            } else {
                box.x = clamp(activeDrag.originX + dx, 0, 100 - box.w);
                box.y = clamp(activeDrag.originY + dy, 0, 100 - box.h);
            }
            renderLayout();
        }

        function stopDrag() {
            activeDrag = null;
        }

        card.addEventListener('mousedown', startDrag);
        card.addEventListener('touchstart', startDrag, {passive: false});
        document.addEventListener('mousemove', moveDrag);
        document.addEventListener('touchmove', moveDrag, {passive: false});
        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchend', stopDrag);

        [widthInput, heightInput, unitInput].forEach(function (input) {
            input.addEventListener('input', syncSize);
            input.addEventListener('change', syncSize);
        });
        if (orientationInput) {
            orientationInput.addEventListener('change', applyOrientationDefaults);
        }
        photoStyleInput.addEventListener('change', syncPhotoShape);
        if (headerColorInput) {
            headerColorInput.addEventListener('input', syncHeaderColor);
            headerColorInput.addEventListener('change', syncHeaderColor);
        }
        if (backgroundInput) {
            backgroundInput.addEventListener('change', syncBackgroundPreview);
        }
        Object.keys(config.checkboxMap).forEach(function (elementKey) {
            var checkbox = document.getElementById(config.checkboxMap[elementKey]);
            if (checkbox) {
                checkbox.addEventListener('change', syncToggles);
            }
        });
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                layout = currentDefaults();
                renderLayout();
            });
        }

        if (zoomRange) {
            zoomRange.addEventListener('input', function () {
                zoom = percent(this.value, 170) / 100;
                syncZoom();
            });
        }

        if (zoomIn) {
            zoomIn.addEventListener('click', function () {
                zoom = Math.min(3.2, zoom + 0.1);
                syncZoom();
            });
        }

        if (zoomOut) {
            zoomOut.addEventListener('click', function () {
                zoom = Math.max(0.8, zoom - 0.1);
                syncZoom();
            });
        }

        if (toggleLarge) {
            toggleLarge.addEventListener('click', function () {
                var active = shell.classList.toggle('is-large-window');
                if (overlay) {
                    overlay.classList.toggle('active', active);
                }
                toggleLarge.textContent = active ? 'Close Large Window' : 'Open Large Window';
                zoom = active ? Math.max(zoom, 2.2) : Math.min(zoom, 1.7);
                syncZoom();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function () {
                if (shell.classList.contains('is-large-window')) {
                    shell.classList.remove('is-large-window');
                    overlay.classList.remove('active');
                    if (toggleLarge) {
                        toggleLarge.textContent = 'Open Large Window';
                    }
                    zoom = Math.min(zoom, 1.7);
                    syncZoom();
                }
            });
        }

        syncSize();
        renderLayout();
        syncPhotoShape();
        syncHeaderColor();
        syncToggles();
        syncZoom();
        syncOrientationFromSize();
    };
}

document.addEventListener('DOMContentLoaded', function () {
    window.initIdCardDesigner({
        previewId: <?php echo json_encode($preview_id); ?>,
        layoutInputId: <?php echo json_encode($layout_input_id); ?>,
        widthInputId: <?php echo json_encode($builder_type . '_card_width'); ?>,
        heightInputId: <?php echo json_encode($builder_type . '_card_height'); ?>,
        orientationInputId: <?php echo json_encode($builder_type . '_card_orientation'); ?>,
        unitInputId: <?php echo json_encode($builder_type . '_card_unit'); ?>,
        photoStyleInputId: <?php echo json_encode($builder_type . '_photo_style'); ?>,
        checkboxMap: <?php echo json_encode($checkbox_map); ?>,
        defaultsPortrait: <?php echo json_encode($layout_defaults_portrait); ?>,
        defaultsLandscape: <?php echo json_encode($layout_defaults_landscape); ?>
    });
});
</script>
