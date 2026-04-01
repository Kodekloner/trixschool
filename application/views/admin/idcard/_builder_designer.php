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
    }

    .id-card-designer-shell .help-block {
        margin-bottom: 8px;
    }

    .id-card-designer-preview {
        padding: 14px;
        background: linear-gradient(180deg, #ffffff, #eef2f7);
        border-radius: 10px;
        overflow: auto;
    }

    .designer-element {
        cursor: move;
        user-select: none;
    }

    .designer-element.hidden-by-toggle {
        display: none !important;
    }

    .id-card-designer-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        gap: 10px;
    }

    .id-card-designer-meta {
        font-size: 12px;
        color: #6b7280;
    }
</style>

<div class="form-group">
    <label>Card Size</label><small class="req"> *</small>
    <div class="row">
        <div class="col-xs-4">
            <select class="form-control" name="card_unit" id="<?php echo $builder_type; ?>_card_unit">
                <option value="in" <?php echo $dimensions['unit'] === 'in' ? 'selected' : ''; ?>>Inches</option>
                <option value="mm" <?php echo $dimensions['unit'] === 'mm' ? 'selected' : ''; ?>>Millimetres</option>
                <option value="cm" <?php echo $dimensions['unit'] === 'cm' ? 'selected' : ''; ?>>Centimetres</option>
                <option value="px" <?php echo $dimensions['unit'] === 'px' ? 'selected' : ''; ?>>Pixels</option>
            </select>
        </div>
        <div class="col-xs-4">
            <input type="number" step="0.01" min="0.01" class="form-control" name="card_width" id="<?php echo $builder_type; ?>_card_width" value="<?php echo set_value('card_width', format_id_card_measurement($dimensions['width'])); ?>" placeholder="Width">
        </div>
        <div class="col-xs-4">
            <input type="number" step="0.01" min="0.01" class="form-control" name="card_height" id="<?php echo $builder_type; ?>_card_height" value="<?php echo set_value('card_height', format_id_card_measurement($dimensions['height'])); ?>" placeholder="Height">
        </div>
    </div>
    <span class="help-block">Default size is 2.1 inches wide by 3.3 inches tall.</span>
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
    <div class="id-card-designer-shell">
        <div class="id-card-designer-actions">
            <div class="id-card-designer-meta">Drag items in the preview to change their position. The saved layout is used for print and preview.</div>
            <button type="button" class="btn btn-default btn-xs" data-reset-layout="<?php echo $preview_id; ?>">Reset Positions</button>
        </div>
        <div class="id-card-designer-preview">
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
        var unitInput = document.getElementById(config.unitInputId);
        var photoStyleInput = document.getElementById(config.photoStyleInputId);
        var layoutInput = document.getElementById(config.layoutInputId);
        var resetButton = document.querySelector('[data-reset-layout="' + config.previewId + '"]');
        var form = layoutInput ? layoutInput.form : null;
        var headerColorInput = form ? form.querySelector('input[name="header_color"]') : null;
        var backgroundInput = form ? form.querySelector('input[name="background_image"]') : null;
        var titleNode = card.querySelector('[data-element="title"]');
        var elements = {};
        var layout = JSON.parse(layoutInput.value || '{}');

        card.querySelectorAll('.designer-element').forEach(function (node) {
            elements[node.getAttribute('data-element')] = node;
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

        var activeDrag = null;

        function startDrag(event) {
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
                originX: layout[key].x,
                originY: layout[key].y
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
            box.x = clamp(activeDrag.originX + dx, 0, 100 - box.w);
            box.y = clamp(activeDrag.originY + dy, 0, 100 - box.h);
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

        syncSize();
        renderLayout();
        syncPhotoShape();
        syncHeaderColor();
        syncToggles();
    };
}

document.addEventListener('DOMContentLoaded', function () {
    window.initIdCardDesigner({
        previewId: <?php echo json_encode($preview_id); ?>,
        layoutInputId: <?php echo json_encode($layout_input_id); ?>,
        widthInputId: <?php echo json_encode($builder_type . '_card_width'); ?>,
        heightInputId: <?php echo json_encode($builder_type . '_card_height'); ?>,
        unitInputId: <?php echo json_encode($builder_type . '_card_unit'); ?>,
        photoStyleInputId: <?php echo json_encode($builder_type . '_photo_style'); ?>,
        checkboxMap: <?php echo json_encode($checkbox_map); ?>,
        defaultsPortrait: <?php echo json_encode($layout_defaults_portrait); ?>,
        defaultsLandscape: <?php echo json_encode($layout_defaults_landscape); ?>
    });
});
</script>
