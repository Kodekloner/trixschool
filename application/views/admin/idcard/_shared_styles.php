<style type="text/css">
    .id-card-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.15in;
        align-items: flex-start;
    }

    .id-card-item {
        page-break-inside: avoid;
    }

    .id-card-canvas {
        position: relative;
        overflow: hidden;
        border-radius: 14px;
        border: 1px solid rgba(0, 0, 0, 0.18);
        background: #f8fafc;
        box-shadow: 0 8px 26px rgba(15, 23, 42, 0.12);
        color: #0f172a;
        font-family: Arial, sans-serif;
    }

    .id-card-bg {
        position: absolute;
        inset: 0;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
    }

    .id-card-layer {
        position: absolute;
        overflow: hidden;
        box-sizing: border-box;
        z-index: 2;
    }

    .id-card-panel {
        background: rgba(255, 255, 255, 0.82);
        border-radius: 8px;
        padding: 4px 6px;
        backdrop-filter: blur(2px);
    }

    .id-card-brand {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 700;
        font-size: 11px;
    }

    .id-card-brand img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .id-card-heading {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        border-radius: 8px;
        padding: 4px 8px;
    }

    .id-card-address {
        font-size: 7px;
        line-height: 1.35;
        text-align: center;
    }

    .id-card-photo img,
    .id-card-signature img,
    .id-card-qr img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .id-card-signature img,
    .id-card-qr img {
        object-fit: contain;
        background: rgba(255, 255, 255, 0.9);
        padding: 4px;
        border-radius: 8px;
    }

    .id-card-photo img.round {
        border-radius: 999px;
    }

    .id-card-photo img.square {
        border-radius: 12px;
    }

    .id-card-text {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        width: 100%;
        height: 100%;
        font-size: 9px;
        line-height: 1.2;
        word-break: break-word;
    }

    .id-card-text.center {
        justify-content: center;
        text-align: center;
    }

    .id-card-text strong {
        font-weight: 700;
    }

    .id-card-text.block {
        display: block;
    }

    .id-card-field-line {
        display: block;
    }

    .id-card-field-line .label {
        font-weight: 700;
        display: inline;
    }

    .id-card-field-line .value {
        display: inline;
    }

    .id-card-qr-label {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 2px;
        font-size: 7px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        text-align: center;
        color: #0f172a;
    }

    @media print {
        .id-card-grid {
            gap: 0.12in;
        }

        .id-card-canvas {
            box-shadow: none;
        }
    }
</style>
