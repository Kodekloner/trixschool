/* global window, document, Node */
(function (window, document) {
    'use strict';

    var payloadElement = document.getElementById('result-download-payload');
    var output = document.querySelector('[data-result-bulk-output]');
    var status = document.querySelector('[data-result-bulk-status]');
    var printButton = document.querySelector('[data-result-bulk-print]');
    var closeButton = document.querySelector('[data-result-bulk-close]');
    var printStarted = false;

    function setStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function parsePayload() {
        if (!payloadElement) {
            return { urls: [], error: 'The result download request is missing.' };
        }

        try {
            return JSON.parse(payloadElement.textContent || '{}');
        } catch (error) {
            return { urls: [], error: 'The result download request could not be read.' };
        }
    }

    function showError(message) {
        var alert = document.createElement('div');
        alert.className = 'result-bulk-download-error';
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        output.appendChild(alert);
        setStatus(message);
    }

    function imagesReady(root) {
        return Array.prototype.every.call(root.querySelectorAll('img'), function (image) {
            return image.complete;
        });
    }

    function replaceCanvases(sourceRoot, clonedRoot) {
        var sourceCanvases = sourceRoot.querySelectorAll('canvas');
        var clonedCanvases = clonedRoot.querySelectorAll('canvas');

        Array.prototype.forEach.call(sourceCanvases, function (sourceCanvas, index) {
            var clonedCanvas = clonedCanvases[index];
            var image;

            if (!clonedCanvas || !clonedCanvas.parentNode) {
                return;
            }

            try {
                image = document.createElement('img');
                image.src = sourceCanvas.toDataURL('image/png');
                image.className = sourceCanvas.className;
                image.style.cssText = sourceCanvas.style.cssText;
                image.width = sourceCanvas.width;
                image.height = sourceCanvas.height;
                image.alt = 'Result chart';
                clonedCanvas.parentNode.replaceChild(image, clonedCanvas);
            } catch (error) {
                /* Keep the cloned canvas when a browser blocks canvas export. */
            }
        });
    }

    function cloneReport(sourceRoot) {
        var clonedRoot = sourceRoot.cloneNode(true);
        var sheet = document.createElement('section');

        replaceCanvases(sourceRoot, clonedRoot);
        clonedRoot.removeAttribute('id');
        Array.prototype.forEach.call(clonedRoot.querySelectorAll('[id]'), function (element) {
            element.removeAttribute('id');
        });

        sheet.className = 'result-bulk-download-sheet';
        sheet.appendChild(clonedRoot);
        return sheet;
    }

    function loadReport(url, index, total) {
        return new Promise(function (resolve, reject) {
            var frame = document.createElement('iframe');
            var settled = false;
            var timeout;
            var poll;

            function cleanup() {
                window.clearTimeout(timeout);
                window.clearInterval(poll);
                if (frame.parentNode) {
                    frame.parentNode.removeChild(frame);
                }
            }

            function finish(error, report) {
                if (settled) {
                    return;
                }
                settled = true;
                cleanup();
                if (error) {
                    reject(error);
                } else {
                    resolve(report);
                }
            }

            frame.className = 'result-bulk-download-loader';
            frame.title = 'Preparing result ' + index + ' of ' + total;
            frame.setAttribute('aria-hidden', 'true');
            frame.onload = function () {
                poll = window.setInterval(function () {
                    var frameDocument;
                    var report;

                    try {
                        frameDocument = frame.contentDocument;
                        report = frameDocument && frameDocument.querySelector('[data-result-report]');
                    } catch (error) {
                        finish(new Error('A result page could not be accessed.'));
                        return;
                    }

                    if (report
                        && report.getAttribute('data-result-report-ready') === 'true'
                        && imagesReady(report)
                    ) {
                        finish(null, cloneReport(report));
                    }
                }, 100);
            };

            timeout = window.setTimeout(function () {
                finish(new Error('A result page took too long to prepare.'));
            }, 45000);

            document.body.appendChild(frame);
            frame.src = url;
        });
    }

    function waitForOutputImages(callback) {
        var images = Array.prototype.slice.call(output.querySelectorAll('img'));
        var pending = images.filter(function (image) {
            return !image.complete;
        }).length;

        if (!pending) {
            callback();
            return;
        }

        function settle() {
            pending -= 1;
            if (pending <= 0) {
                callback();
            }
        }

        images.forEach(function (image) {
            if (!image.complete) {
                image.addEventListener('load', settle, { once: true });
                image.addEventListener('error', settle, { once: true });
            }
        });
    }

    function fitScreenPreview() {
        var naturalWidth;
        var naturalHeight;
        var availableWidth;
        var scale;

        if (!output || window.matchMedia('print').matches) {
            return;
        }

        output.style.transform = 'none';
        output.style.marginBottom = '';
        naturalWidth = output.offsetWidth;
        naturalHeight = output.offsetHeight;
        availableWidth = Math.max(1, document.documentElement.clientWidth - 16);
        scale = Math.min(1, availableWidth / naturalWidth);

        if (scale < 1) {
            output.style.transform = 'scale(' + scale + ')';
            output.style.transformOrigin = 'top center';
            output.style.marginBottom = Math.round(-naturalHeight * (1 - scale)) + 'px';
        }
    }

    function openPrintDialog() {
        if (printStarted) {
            return;
        }
        printStarted = true;
        fitScreenPreview();
        window.setTimeout(function () {
            window.print();
            printStarted = false;
        }, 250);
    }

    async function prepare() {
        var payload = parsePayload();
        var urls = Array.isArray(payload.urls) ? payload.urls : [];
        var failures = 0;
        var index;

        if (payload.error || !urls.length) {
            showError(payload.error || 'No results were selected.');
            return;
        }

        document.title = (urls.length === 1 ? 'Student Result' : urls.length + ' Student Results');

        for (index = 0; index < urls.length; index += 1) {
            setStatus('Preparing result ' + (index + 1) + ' of ' + urls.length + '…');
            try {
                output.appendChild(await loadReport(urls[index], index + 1, urls.length));
            } catch (error) {
                failures += 1;
            }
        }

        if (!output.querySelector('.result-bulk-download-sheet')) {
            showError('The selected results could not be prepared. Please try again.');
            return;
        }

        waitForOutputImages(function () {
            var prepared = urls.length - failures;
            setStatus(
                prepared + (prepared === 1 ? ' result is' : ' results are')
                + ' ready. Choose “Save as PDF” in the print window.'
                + (failures ? ' ' + failures + ' could not be loaded.' : '')
            );
            printButton.hidden = false;
            fitScreenPreview();
            openPrintDialog();
        });
    }

    if (printButton) {
        printButton.addEventListener('click', openPrintDialog);
    }

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            window.close();
        });
    }

    window.addEventListener('resize', fitScreenPreview);
    window.addEventListener('beforeprint', function () {
        output.style.transform = 'none';
        output.style.marginBottom = '';
    });
    window.addEventListener('afterprint', fitScreenPreview);

    prepare();
}(window, document));
