<?php
$runtime_assets = array();
foreach ((array) $studio_assets as $asset_id => $asset) {
    $runtime_assets[(int) $asset_id] = site_url('admin/idcardstudio/asset/' . (int) $asset_id);
}
$runtime_config = array(
    'title' => (string) $studio_design->title,
    'widthMm' => (float) $studio_design->width_mm,
    'heightMm' => (float) $studio_design->height_mm,
    'front' => json_decode($studio_design->front_json, true),
    'back' => json_decode($studio_design->back_json, true),
    'printSettings' => json_decode($studio_design->print_settings_json, true),
    'assets' => $runtime_assets,
    'cards' => array_values($studio_cards),
);
$duplex = !empty($runtime_config['printSettings']['duplex']);
$paper_orientation = !empty($runtime_config['printSettings']['orientation']) && $runtime_config['printSettings']['orientation'] === 'landscape' ? 'landscape' : 'portrait';
$back_cards = array_reverse(array_values($studio_cards));
?>
<style>
@page { size: A4 <?php echo $paper_orientation; ?>; margin: <?php echo isset($runtime_config['printSettings']['marginMm']) ? (float) $runtime_config['printSettings']['marginMm'] : 8; ?>mm; }
html, body { margin: 0; padding: 0; background: #fff; font-family: Arial, sans-serif; }
.studio-print-sheet { display: grid; grid-template-columns: repeat(auto-fit, <?php echo (float) $studio_design->width_mm; ?>mm); align-items: start; justify-content: start; gap: <?php echo isset($runtime_config['printSettings']['gapMm']) ? (float) $runtime_config['printSettings']['gapMm'] : 4; ?>mm; }
.studio-runtime-card { position: relative; width: <?php echo (float) $studio_design->width_mm; ?>mm; height: <?php echo (float) $studio_design->height_mm; ?>mm; overflow: hidden; break-inside: avoid; page-break-inside: avoid; background: #fff; }
.studio-runtime-card .canvas-container, .studio-runtime-card canvas { width: 100% !important; height: 100% !important; }
.studio-print-side-label { display: none; }
.studio-duplex-back { page-break-before: always; }
.studio-runtime-error { padding: 12px; border: 1px solid #b91c1c; color: #991b1b; }
.studio-runtime-toolbar { position: sticky; top: 0; z-index: 100; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px; margin: -10mm -10mm 8mm; color: #172033; background: #fff; border-bottom: 1px solid #cbd5e1; box-shadow: 0 2px 10px rgba(15,23,42,.12); }
.studio-runtime-toolbar button { padding: 7px 10px; border: 1px solid #94a3b8; border-radius: 4px; background: #f8fafc; cursor: pointer; }
.studio-runtime-toolbar button:hover { border-color: #2563eb; color: #1d4ed8; }
.studio-runtime-progress { flex: 1 1 210px; min-width: 180px; font-size: 12px; }
.studio-runtime-progress progress { width: 100%; height: 9px; }
@media screen {
    body { padding: 10mm; background: #e5e7eb; }
    .studio-print-sheet { padding: 8mm; background: #fff; box-shadow: 0 3px 18px rgba(0,0,0,.15); }
    .studio-print-side-label { display: block; grid-column: 1 / -1; margin: 2mm 0; font-weight: 700; }
}
@media print { .studio-runtime-toolbar { display: none !important; } body { padding: 0; } }
</style>

<div class="studio-runtime-toolbar" id="studio-runtime-toolbar">
    <strong><?php echo html_escape($studio_design->title); ?></strong>
    <button type="button" data-runtime-action="print">Print cards</button>
    <button type="button" data-runtime-action="png">First card PNG (300 DPI)</button>
    <button type="button" data-runtime-action="exact-pdf">First card exact PDF</button>
    <button type="button" data-runtime-action="a4-pdf">A4 PDF (all cards)</button>
    <button type="button" data-runtime-action="cancel" disabled>Cancel export</button>
    <button type="button" data-runtime-action="close">Close</button>
    <div class="studio-runtime-progress"><span id="studio-runtime-status">Rendering cards…</span><progress id="studio-runtime-progress" value="0" max="100"></progress></div>
</div>

<div class="studio-print-side-label">Front</div>
<div class="studio-print-sheet" id="studio-runtime-front">
    <?php foreach ($studio_cards as $index => $card) { ?>
        <div class="studio-runtime-card"><canvas id="studio-card-front-<?php echo (int) $index; ?>"></canvas></div>
    <?php } ?>
</div>

<?php if ($duplex) { ?>
    <div class="studio-print-side-label studio-duplex-back">Back</div>
    <div class="studio-print-sheet studio-duplex-back" id="studio-runtime-back">
        <?php foreach ($back_cards as $index => $card) { ?>
            <div class="studio-runtime-card"><canvas id="studio-card-back-<?php echo (int) $index; ?>"></canvas></div>
        <?php } ?>
    </div>
<?php } ?>

<script>window.ID_CARD_RUNTIME_CONFIG = <?php echo json_encode($runtime_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?php echo base_url('backend/idcard-studio/vendor/fabric-5.3.0.min.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/vendor/qrcode-1.0.0.min.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/vendor/jsbarcode-3.11.6.min.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/vendor/jspdf-2.5.2.umd.min.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/idcard-renderer.js'); ?>"></script>
<script src="<?php echo base_url('backend/idcard-studio/idcard-runtime.js'); ?>"></script>
