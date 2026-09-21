(function (window, document) {
    'use strict';

    window.IDCARD_LEGACY_QR_EXPECTED = true;
    window.IDCARD_LEGACY_QR_READY = false;
    window.IDCARD_LEGACY_QR_ERROR = '';

    function render() {
        var holders = Array.prototype.slice.call(document.querySelectorAll('[data-attendance-qr]'));
        var missing = holders.filter(function (holder) {
            return !holder.getAttribute('data-attendance-qr');
        });

        if (!window.QRCode && holders.length > missing.length) {
            fail('The local attendance QR renderer could not be loaded. Reload the page and try again.');
            return;
        }

        try {
            holders.forEach(function (holder) {
                var token = holder.getAttribute('data-attendance-qr');
                if (!token) { return; }
                holder.textContent = '';
                new window.QRCode(holder, {
                    text: token,
                    width: 256,
                    height: 256,
                    colorDark: '#111827',
                    colorLight: '#ffffff',
                    correctLevel: window.QRCode.CorrectLevel.M
                });
                holder.removeAttribute('title');
                holder.setAttribute('aria-label', 'Attendance QR code');
                var image = holder.querySelector('canvas, img');
                if (!image) {
                    throw new Error('QR output was empty.');
                }
                image.style.display = 'block';
                image.style.width = '100%';
                image.style.height = '100%';
                holder.setAttribute('data-attendance-qr-rendered', '1');
            });
        } catch (error) {
            fail('An attendance QR could not be rendered. Reload the page and try again.');
            return;
        }

        if (missing.length) {
            var labels = missing.slice(0, 5).map(function (holder) {
                return holder.getAttribute('data-attendance-qr-label') || '';
            }).filter(Boolean);
            var remaining = missing.length - labels.length;
            fail(missing.length + ' selected student' + (missing.length === 1 ? ' has' : 's have') +
                ' no printable attendance credential' +
                (labels.length ? ': ' + labels.join(', ') + (remaining > 0 ? ' and ' + remaining + ' more' : '') : '') +
                '. Issue an active QR credential in Biometric Attendance before printing.');
            return;
        }

        window.IDCARD_LEGACY_QR_READY = true;
        document.body.setAttribute('data-legacy-qr-ready', '1');
    }

    function fail(message) {
        window.IDCARD_LEGACY_QR_ERROR = message;
        document.body.classList.add('legacy-qr-blocked');
        document.body.setAttribute('data-legacy-qr-ready', 'error');
        var warning = document.querySelector('.legacy-qr-warning');
        if (!warning) {
            warning = document.createElement('div');
            warning.className = 'legacy-qr-warning';
            warning.setAttribute('role', 'alert');
            document.body.insertBefore(warning, document.body.firstChild);
        }
        if (!warning.textContent.trim()) {
            warning.textContent = message;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        render();
    }
}(window, document));
