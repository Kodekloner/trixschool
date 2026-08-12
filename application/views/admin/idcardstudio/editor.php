<?php
$asset_payload = array();
foreach ((array) $assets as $asset) {
    $asset_payload[] = array(
        'id' => (int) $asset->id,
        'name' => $asset->original_name,
        'mime' => $asset->mime_type,
        'width' => (int) $asset->pixel_width,
        'height' => (int) $asset->pixel_height,
        'url' => site_url('admin/idcardstudio/asset/' . (int) $asset->id),
    );
}
$studio_config = array(
    'designId' => (int) $design->id,
    'subjectType' => $subject_type,
    'title' => $design->title,
    'widthMm' => (float) $design->width_mm,
    'heightMm' => (float) $design->height_mm,
    'checksum' => $draft->checksum,
    'publishedVersionId' => $design->published_version_id ? (int) $design->published_version_id : null,
    'front' => json_decode($draft->front_json, true),
    'back' => json_decode($draft->back_json, true),
    'printSettings' => json_decode($draft->print_settings_json, true),
    'bindings' => $bindings,
    'sampleData' => $sample_data,
    'assets' => $asset_payload,
    'csrf' => $studio_csrf,
    'endpoints' => array(
        'save' => site_url('admin/idcardstudio/save/' . (int) $design->id),
        'publish' => site_url('admin/idcardstudio/publish/' . (int) $design->id),
        'useLegacy' => site_url('admin/idcardstudio/use_legacy/' . (int) $design->id),
        'upload' => site_url('admin/idcardstudio/upload_asset/' . (int) $design->id),
    ),
);
?>
<link rel="stylesheet" href="<?php echo base_url('backend/idcard-studio/idcard-studio.css'); ?>">

<div class="content-wrapper idstudio-page">
    <section class="content-header idstudio-page-header">
        <h1>ID Card Design Studio <small><?php echo html_escape(ucfirst($subject_type)); ?> template</small></h1>
        <a class="btn btn-default btn-sm" href="<?php echo site_url('admin/idcardstudio/index/' . $subject_type); ?>"><i class="fa fa-arrow-left"></i> All templates</a>
    </section>

    <section class="content idstudio-content">
        <div id="idstudio-alert" class="alert idstudio-alert" role="alert" aria-live="polite"></div>

        <div id="idstudio-app" class="idstudio-app" data-studio-ready="0">
            <div class="idstudio-topbar">
                <div class="idstudio-title-group">
                    <label for="idstudio-title">Design name</label>
                    <input id="idstudio-title" class="form-control input-sm" maxlength="191" value="<?php echo html_escape($design->title); ?>">
                </div>

                <div class="idstudio-size-group" aria-label="Card dimensions">
                    <button type="button" class="btn btn-default btn-sm" data-action="preset-landscape">CR80 Landscape</button>
                    <button type="button" class="btn btn-default btn-sm" data-action="preset-portrait">CR80 Portrait</button>
                    <label>W <input id="idstudio-width" type="number" min="40" max="220" step="0.01" value="<?php echo html_escape($design->width_mm); ?>"></label>
                    <label>H <input id="idstudio-height" type="number" min="40" max="220" step="0.01" value="<?php echo html_escape($design->height_mm); ?>"></label>
                    <span>mm</span>
                </div>

                <div class="btn-group" role="group" aria-label="History controls">
                    <button type="button" class="btn btn-default btn-sm" data-action="undo" title="Undo (Ctrl+Z)"><i class="fa fa-undo"></i></button>
                    <button type="button" class="btn btn-default btn-sm" data-action="redo" title="Redo (Ctrl+Y)"><i class="fa fa-repeat"></i></button>
                </div>

                <div class="idstudio-save-group">
                    <span id="idstudio-save-state" class="text-muted">Draft loaded</span>
                    <button type="button" class="btn btn-default btn-sm" data-action="save"><i class="fa fa-save"></i> Save draft</button>
                    <button type="button" class="btn btn-success btn-sm" data-action="publish"><i class="fa fa-check-circle"></i> Publish</button>
                    <?php if (!empty($design->published_version_id)) { ?>
                        <button type="button" class="btn btn-warning btn-sm" data-action="use-legacy"><i class="fa fa-history"></i> Use legacy</button>
                    <?php } ?>
                </div>
            </div>

            <div class="idstudio-main">
                <aside class="idstudio-left-panel">
                    <div class="idstudio-panel-heading">Add objects</div>
                    <div class="idstudio-tool-grid">
                        <button type="button" data-add="text"><i class="fa fa-font"></i><span>Text</span></button>
                        <button type="button" data-add="rect"><i class="fa fa-square-o"></i><span>Rectangle</span></button>
                        <button type="button" data-add="ellipse"><i class="fa fa-circle-thin"></i><span>Ellipse</span></button>
                        <button type="button" data-add="line"><i class="fa fa-minus"></i><span>Line</span></button>
                        <button type="button" data-add="qr"><i class="fa fa-qrcode"></i><span>QR</span></button>
                        <button type="button" data-add="barcode"><i class="fa fa-barcode"></i><span>Code128</span></button>
                    </div>

                    <div class="idstudio-panel-heading">Dynamic fields</div>
                    <div id="idstudio-binding-list" class="idstudio-binding-list">
                        <?php foreach ($bindings as $binding => $label) { ?>
                            <?php if (in_array($binding, array('school.logo', 'school.signature', 'school.background', $subject_type . '.photo'), true)) { ?>
                                <button type="button" data-add-binding-image="<?php echo html_escape($binding); ?>"><i class="fa fa-picture-o"></i> <?php echo html_escape($label); ?></button>
                            <?php } elseif ($binding === 'attendance.credential') { ?>
                                <button type="button" data-add-binding-qr="<?php echo html_escape($binding); ?>"><i class="fa fa-qrcode"></i> <?php echo html_escape($label); ?></button>
                            <?php } else { ?>
                                <button type="button" data-add-binding="<?php echo html_escape($binding); ?>"><i class="fa fa-tag"></i> <?php echo html_escape($label); ?></button>
                            <?php } ?>
                        <?php } ?>
                    </div>

                    <div class="idstudio-panel-heading">Uploaded images</div>
                    <form id="idstudio-upload-form" enctype="multipart/form-data">
                        <label class="btn btn-default btn-sm btn-block">
                            <i class="fa fa-upload"></i> Upload PNG/JPEG
                            <input id="idstudio-asset-input" type="file" name="asset" accept="image/png,image/jpeg" class="sr-only">
                        </label>
                        <small class="help-block">Maximum 5 MB. Files are served through an authenticated same-origin endpoint.</small>
                    </form>
                    <div id="idstudio-assets" class="idstudio-assets"></div>
                </aside>

                <main class="idstudio-workspace">
                    <div class="idstudio-workspace-toolbar">
                        <div class="btn-group" role="group" aria-label="Card side">
                            <button type="button" class="btn btn-primary btn-sm active" data-side="front">Front</button>
                            <button type="button" class="btn btn-default btn-sm" data-side="back">Back</button>
                        </div>
                        <label><input id="idstudio-grid-toggle" type="checkbox" checked> Grid</label>
                        <label><input id="idstudio-snap-toggle" type="checkbox" checked> Snap</label>
                        <label><input id="idstudio-guides-toggle" type="checkbox" checked> Safe area</label>
                        <label><input id="idstudio-bleed-toggle" type="checkbox" checked> Bleed</label>
                        <div class="idstudio-zoom-controls">
                            <button type="button" class="btn btn-default btn-xs" data-action="zoom-out">−</button>
                            <input id="idstudio-zoom" type="range" min="50" max="250" value="125" step="5">
                            <button type="button" class="btn btn-default btn-xs" data-action="zoom-in">+</button>
                            <span id="idstudio-zoom-label">125%</span>
                        </div>
                    </div>

                    <div id="idstudio-scroll" class="idstudio-scroll">
                        <div id="idstudio-ruler-x" class="idstudio-ruler idstudio-ruler-x"></div>
                        <div id="idstudio-ruler-y" class="idstudio-ruler idstudio-ruler-y"></div>
                        <div id="idstudio-stage" class="idstudio-stage">
                            <div id="idstudio-bleed-area" class="idstudio-bleed-area" aria-hidden="true"></div>
                            <div id="idstudio-safe-area" class="idstudio-safe-area" aria-hidden="true"></div>
                            <canvas id="idstudio-canvas" aria-label="Editable ID card canvas"></canvas>
                        </div>
                    </div>

                    <div class="idstudio-statusbar">
                        <span id="idstudio-side-status">Front side</span>
                        <span id="idstudio-selection-status">Nothing selected</span>
                        <span id="idstudio-dimensions-status"><?php echo html_escape($design->width_mm . ' × ' . $design->height_mm); ?> mm</span>
                    </div>
                </main>

                <aside class="idstudio-right-panel">
                    <div class="idstudio-panel-heading">Selection</div>
                    <div id="idstudio-no-selection" class="text-muted idstudio-empty">Select an object to edit its properties.</div>
                    <div id="idstudio-properties" class="idstudio-properties" hidden>
                        <label>Object name<input id="idstudio-object-name" class="form-control input-sm" maxlength="64"></label>
                        <div class="idstudio-property-grid">
                            <label>X (mm)<input id="idstudio-prop-x" type="number" step="0.1" class="form-control input-sm"></label>
                            <label>Y (mm)<input id="idstudio-prop-y" type="number" step="0.1" class="form-control input-sm"></label>
                            <label>Width<input id="idstudio-prop-width" type="number" step="0.1" class="form-control input-sm"></label>
                            <label>Height<input id="idstudio-prop-height" type="number" step="0.1" class="form-control input-sm"></label>
                            <label>Rotate<input id="idstudio-prop-rotation" type="number" min="-360" max="360" step="1" class="form-control input-sm"></label>
                            <label>Opacity<input id="idstudio-prop-opacity" type="number" min="0" max="1" step="0.05" class="form-control input-sm"></label>
                        </div>
                        <div id="idstudio-text-properties">
                            <label>Text<textarea id="idstudio-prop-text" class="form-control input-sm" rows="2"></textarea></label>
                            <label>Dynamic field<select id="idstudio-prop-binding" class="form-control input-sm"><option value="">Static text</option></select></label>
                            <div class="idstudio-property-grid">
                                <label>Font<select id="idstudio-prop-font" class="form-control input-sm"><option>Arial</option><option>Helvetica</option><option>Times New Roman</option><option>Georgia</option><option>Verdana</option><option>Courier New</option></select></label>
                                <label>Size (mm)<input id="idstudio-prop-font-size" type="number" min="1.5" max="20" step="0.1" class="form-control input-sm"></label>
                                <label>Colour<input id="idstudio-prop-fill" type="color" class="form-control input-sm"></label>
                                <label>Align<select id="idstudio-prop-align" class="form-control input-sm"><option value="left">Left</option><option value="center">Centre</option><option value="right">Right</option></select></label>
                                <label>Line spacing<input id="idstudio-prop-line-height" type="number" min="0.7" max="3" step="0.05" class="form-control input-sm"></label>
                                <label>Letter spacing<input id="idstudio-prop-char-spacing" type="number" min="-200" max="1000" step="10" class="form-control input-sm"></label>
                            </div>
                            <label class="checkbox-inline"><input id="idstudio-prop-bold" type="checkbox"> Bold</label>
                            <label class="checkbox-inline"><input id="idstudio-prop-italic" type="checkbox"> Italic</label>
                        </div>
                        <div id="idstudio-shape-properties">
                            <div class="idstudio-property-grid">
                                <label>Fill<input id="idstudio-prop-shape-fill" type="color" class="form-control input-sm"></label>
                                <label>Border<input id="idstudio-prop-stroke" type="color" class="form-control input-sm"></label>
                                <label>Border mm<input id="idstudio-prop-stroke-width" type="number" min="0" max="5" step="0.1" class="form-control input-sm"></label>
                                <label>Corner mm<input id="idstudio-prop-radius" type="number" min="0" max="50" step="0.5" class="form-control input-sm"></label>
                            </div>
                            <label id="idstudio-image-fit-row">Image fit<select id="idstudio-prop-fit" class="form-control input-sm"><option value="cover">Crop to cover</option><option value="contain">Contain</option><option value="fill">Stretch</option></select></label>
                        </div>
                        <div class="idstudio-property-actions">
                            <button type="button" class="btn btn-default btn-xs" data-action="duplicate"><i class="fa fa-copy"></i> Duplicate</button>
                            <button type="button" class="btn btn-default btn-xs" data-action="lock"><i class="fa fa-lock"></i> Lock</button>
                            <button type="button" class="btn btn-danger btn-xs" data-action="delete"><i class="fa fa-trash"></i> Delete</button>
                        </div>
                    </div>

                    <div class="idstudio-panel-heading idstudio-layer-heading">
                        <span>Layers</span>
                        <span class="btn-group">
                            <button type="button" class="btn btn-default btn-xs" data-action="layer-up" title="Move forward"><i class="fa fa-arrow-up"></i></button>
                            <button type="button" class="btn btn-default btn-xs" data-action="layer-down" title="Move backward"><i class="fa fa-arrow-down"></i></button>
                        </span>
                    </div>
                    <ol id="idstudio-layers" class="idstudio-layers"></ol>

                    <div class="idstudio-panel-heading">Align selection</div>
                    <div class="idstudio-align-grid">
                        <button type="button" data-align="left" title="Align left"><i class="fa fa-align-left"></i></button>
                        <button type="button" data-align="center" title="Centre horizontally"><i class="fa fa-align-center"></i></button>
                        <button type="button" data-align="right" title="Align right"><i class="fa fa-align-right"></i></button>
                        <button type="button" data-align="top" title="Align top">Top</button>
                        <button type="button" data-align="middle" title="Centre vertically">Mid</button>
                        <button type="button" data-align="bottom" title="Align bottom">Bottom</button>
                        <button type="button" data-action="distribute-horizontal" title="Distribute selected objects horizontally">Distribute H</button>
                        <button type="button" data-action="distribute-vertical" title="Distribute selected objects vertically">Distribute V</button>
                        <button type="button" data-action="group" title="Group selected objects">Group</button>
                        <button type="button" data-action="ungroup" title="Ungroup selected objects">Ungroup</button>
                    </div>

                    <details class="idstudio-print-settings">
                        <summary>Print and export</summary>
                        <label>Paper<select id="idstudio-print-paper" class="form-control input-sm"><option value="a4">A4 grid</option><option value="card">Exact card size</option></select></label>
                        <div class="idstudio-property-grid">
                            <label>Margin mm<input id="idstudio-print-margin" type="number" min="0" max="30" step="1" class="form-control input-sm"></label>
                            <label>Gap mm<input id="idstudio-print-gap" type="number" min="0" max="30" step="1" class="form-control input-sm"></label>
                        </div>
                        <label><input id="idstudio-print-crop" type="checkbox"> Crop marks</label>
                        <label><input id="idstudio-print-duplex" type="checkbox"> Include front/back duplex pages</label>
                        <div class="idstudio-export-grid">
                            <button type="button" class="btn btn-default btn-sm" data-action="export-png">300-DPI PNG</button>
                            <button type="button" class="btn btn-default btn-sm" data-action="export-pdf">Exact PDF</button>
                            <button type="button" class="btn btn-default btn-sm" data-action="export-a4">A4 grid PDF</button>
                            <button type="button" class="btn btn-default btn-sm" data-action="print">Print</button>
                        </div>
                    </details>

                    <details class="idstudio-version-history">
                        <summary>Version history</summary>
                        <ul>
                            <?php foreach ($history as $version) { ?>
                                <li>
                                    <strong>v<?php echo (int) $version->version_no; ?></strong>
                                    <span class="label <?php echo $version->state === 'published' ? 'label-success' : ($version->state === 'draft' ? 'label-warning' : 'label-default'); ?>"><?php echo html_escape($version->state); ?></span>
                                    <small><?php echo html_escape($version->updated_at); ?></small>
                                </li>
                            <?php } ?>
                        </ul>
                    </details>
                </aside>
            </div>
        </div>
    </section>
</div>

<script>
window.ID_CARD_STUDIO_CONFIG = <?php echo json_encode($studio_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo base_url('backend/idcard-studio/vendor/fabric-5.3.0.min.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/vendor/qrcode-1.0.0.min.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/vendor/jsbarcode-3.11.6.min.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/vendor/jspdf-2.5.2.umd.min.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/idcard-renderer.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/idcard-studio.js'); ?>"></script>
