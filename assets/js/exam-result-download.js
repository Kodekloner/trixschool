/* global window, document */
(function (window, document) {
    'use strict';

    var MAX_RESULTS_PER_DOWNLOAD = 500;
    var downloadInProgress = false;

    function toArray(collection) {
        return Array.prototype.slice.call(collection || []);
    }

    function closest(element, selector) {
        if (!element) {
            return null;
        }

        if (element.closest) {
            return element.closest(selector);
        }

        while (element && element.nodeType === 1) {
            if (element.matches && element.matches(selector)) {
                return element;
            }
            element = element.parentNode;
        }

        return null;
    }

    function resultRows(list) {
        var table = list.querySelector('.result-download-table');

        try {
            if (table
                && window.jQuery
                && window.jQuery.fn
                && window.jQuery.fn.dataTable
                && typeof window.jQuery.fn.dataTable.isDataTable === 'function'
                && window.jQuery.fn.dataTable.isDataTable(table)
            ) {
                return window.jQuery(table).DataTable().rows().nodes().toArray();
            }
        } catch (error) {
            /* Fall back to the table DOM if a legacy DataTables build has no API. */
        }

        return toArray(list.querySelectorAll('[data-result-row]'));
    }

    function safeFilename(value, extension, fallback) {
        var filename = String(value || '')
            .replace(/[\\/:*?"<>|\u0000-\u001f\u007f]+/g, '-')
            .replace(/\s+/g, ' ')
            .replace(/[.\s-]+$/g, '')
            .replace(/^[.\s-]+/g, '')
            .trim();
        var suffix = '.' + extension;

        if (!filename) {
            filename = fallback;
        }
        if (filename.toLowerCase().slice(-suffix.length) !== suffix) {
            filename += suffix;
        }

        if (filename.length > 180) {
            filename = filename.slice(0, 180 - suffix.length).replace(/[.\s-]+$/g, '') + suffix;
        }

        return filename;
    }

    function isPositiveInteger(value) {
        return /^[1-9][0-9]*$/.test(String(value || ''));
    }

    function isAllowedResultUrl(rawUrl) {
        var parsed;
        var page;
        var pathParts;
        var requiredFields = ['classsection', 'classsectionactual', 'classid', 'session', 'id'];

        try {
            parsed = new window.URL(rawUrl, window.location.href);
        } catch (error) {
            return false;
        }

        if (parsed.origin !== window.location.origin) {
            return false;
        }

        pathParts = parsed.pathname.replace(/\/+$/, '').split('/').filter(Boolean);
        page = pathParts.pop();
        if (page === 'index.html' && pathParts.length) {
            page = pathParts.pop();
        }
        if (page !== 'resultPage.php' && page !== 'kindergarten_result_page.php') {
            return false;
        }

        if (!requiredFields.every(function (field) {
            return isPositiveInteger(parsed.searchParams.get(field));
        })) {
            return false;
        }

        if (['1st', '2nd', '3rd'].indexOf(parsed.searchParams.get('term')) === -1
            || ['midterm', 'termly', 'cummulative'].indexOf(
                String(parsed.searchParams.get('reltype') || '').toLowerCase()
            ) === -1
        ) {
            return false;
        }

        return page !== 'kindergarten_result_page.php'
            || isPositiveInteger(parsed.searchParams.get('assessment_id'));
    }

    function rowItem(row) {
        var link = row.querySelector('[data-result-download-one]');
        var rawUrl = link ? link.getAttribute('href') : '';
        var studentId = row.getAttribute('data-result-student-id') || '';

        if (!link || !isAllowedResultUrl(rawUrl)) {
            return null;
        }

        return {
            url: rawUrl,
            filename: safeFilename(
                link.getAttribute('data-result-filename'),
                'pdf',
                studentId ? 'student-' + studentId + '-result' : 'student-result'
            ),
            archiveFilename: safeFilename(
                link.getAttribute('data-result-archive-filename'),
                'zip',
                'student-results'
            )
        };
    }

    function uniqueItems(items) {
        var seen = {};

        return items.filter(function (item) {
            var key;

            if (!item) {
                return false;
            }

            key = new window.URL(item.url, window.location.href).href;
            if (seen[key]) {
                return false;
            }
            seen[key] = true;
            return true;
        });
    }

    function selectedItems(list) {
        return uniqueItems(resultRows(list).filter(function (row) {
            var checkbox = row.querySelector('[data-result-select]');
            return checkbox && checkbox.checked;
        }).map(rowItem));
    }

    function allItems(list) {
        return uniqueItems(resultRows(list).map(rowItem));
    }

    function setMessage(list, message, isError) {
        var target = list.querySelector('[data-result-download-message]');

        if (!target) {
            return;
        }

        target.textContent = message || '';
        target.classList.toggle('is-visible', Boolean(message));
        target.classList.toggle('is-error', Boolean(message) && Boolean(isError));
    }

    function updateSelection(list) {
        var rows = resultRows(list);
        var selected = rows.filter(function (row) {
            var checkbox = row.querySelector('[data-result-select]');
            return checkbox && checkbox.checked;
        }).length;
        var selectAll = list.querySelector('[data-result-select-all]');
        var count = list.querySelector('[data-result-selected-count]');

        if (selectAll) {
            selectAll.checked = rows.length > 0 && selected === rows.length;
            selectAll.indeterminate = selected > 0 && selected < rows.length;
            selectAll.disabled = downloadInProgress;
        }

        if (count) {
            count.textContent = selected + ' selected';
        }

        toArray(list.querySelectorAll('[data-result-select]')).forEach(function (checkbox) {
            checkbox.disabled = downloadInProgress;
        });

        toArray(list.querySelectorAll('[data-result-download-selected]')).forEach(function (button) {
            button.disabled = downloadInProgress || selected === 0;
        });

        toArray(list.querySelectorAll('[data-result-download-all]')).forEach(function (button) {
            button.disabled = downloadInProgress || rows.length === 0;
        });

        toArray(list.querySelectorAll('[data-result-download-one]')).forEach(function (link) {
            link.setAttribute('aria-disabled', downloadInProgress ? 'true' : 'false');
        });
    }

    function setBusy(list, busy) {
        downloadInProgress = busy;
        list.classList.toggle('is-downloading', busy);
        list.setAttribute('aria-busy', busy ? 'true' : 'false');
        updateSelection(list);
    }

    function assertDownloadLibraries() {
        if (typeof window.html2canvas !== 'function') {
            throw new Error('The result image renderer could not be loaded. Please refresh and try again.');
        }
        if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
            throw new Error('The PDF library could not be loaded. Please refresh and try again.');
        }
        if (typeof window.JSZip !== 'function') {
            throw new Error('The ZIP library could not be loaded. Please refresh and try again.');
        }
    }

    function imagesReady(root) {
        return toArray(root.querySelectorAll('img')).every(function (image) {
            return image.complete;
        });
    }

    function prepareReportForCapture(frameDocument, report) {
        var preview = closest(report, '[data-result-report-preview]');
        var importantStyles = {
            position: 'relative',
            top: 'auto',
            left: 'auto',
            width: '210mm',
            minWidth: '210mm',
            maxWidth: '210mm',
            height: '297mm',
            minHeight: '297mm',
            maxHeight: '297mm',
            margin: '0',
            border: '0',
            borderRadius: '0',
            boxShadow: 'none',
            transform: 'none'
        };

        frameDocument.documentElement.style.setProperty('width', '210mm', 'important');
        frameDocument.documentElement.style.setProperty('height', '297mm', 'important');
        frameDocument.documentElement.style.setProperty('margin', '0', 'important');
        frameDocument.body.style.setProperty('width', '210mm', 'important');
        frameDocument.body.style.setProperty('height', '297mm', 'important');
        frameDocument.body.style.setProperty('margin', '0', 'important');
        frameDocument.body.style.setProperty('padding', '0', 'important');
        frameDocument.body.style.setProperty('overflow', 'hidden', 'important');

        if (preview) {
            preview.style.setProperty('position', 'static', 'important');
            preview.style.setProperty('width', '210mm', 'important');
            preview.style.setProperty('max-width', '210mm', 'important');
            preview.style.setProperty('height', '297mm', 'important');
            preview.style.setProperty('min-height', '297mm', 'important');
            preview.style.setProperty('margin', '0', 'important');
            preview.style.setProperty('padding', '0', 'important');
            preview.style.setProperty('overflow', 'hidden', 'important');
            preview.style.setProperty('transform', 'none', 'important');
        }

        Object.keys(importantStyles).forEach(function (property) {
            var cssProperty = property.replace(/[A-Z]/g, function (character) {
                return '-' + character.toLowerCase();
            });
            report.style.setProperty(cssProperty, importantStyles[property], 'important');
        });
    }

    function renderResultPage(item, index, total, list) {
        return new Promise(function (resolve, reject) {
            var frame = document.createElement('iframe');
            var settled = false;
            var rendering = false;
            var poll;
            var timeout;

            function cleanup() {
                window.clearInterval(poll);
                window.clearTimeout(timeout);
                if (frame.parentNode) {
                    frame.parentNode.removeChild(frame);
                }
            }

            function finish(error, canvas) {
                if (settled) {
                    return;
                }
                settled = true;
                cleanup();
                if (error) {
                    reject(error);
                } else {
                    resolve(canvas);
                }
            }

            function capture(frameDocument, report) {
                var width;
                var height;

                if (rendering || settled) {
                    return;
                }
                rendering = true;
                prepareReportForCapture(frameDocument, report);
                width = Math.max(1, Math.round(report.offsetWidth));
                height = Math.max(1, Math.round(report.offsetHeight));

                setMessage(list, 'Creating PDF ' + index + ' of ' + total + '…', false);
                window.html2canvas(report, {
                    allowTaint: false,
                    backgroundColor: '#ffffff',
                    height: height,
                    imageTimeout: 20000,
                    logging: false,
                    proxy: 'resultImageProxy.php',
                    removeContainer: true,
                    scale: 2,
                    useCORS: false,
                    width: width,
                    windowHeight: height,
                    windowWidth: width
                }).then(function (canvas) {
                    finish(null, canvas);
                }).catch(function () {
                    finish(new Error('This result could not be converted to PDF.'));
                });
            }

            frame.className = 'result-download-capture-frame';
            frame.title = 'Preparing result ' + index + ' of ' + total;
            frame.setAttribute('aria-hidden', 'true');
            frame.onload = function () {
                poll = window.setInterval(function () {
                    var frameDocument;
                    var report;
                    var fontsReady;

                    try {
                        frameDocument = frame.contentDocument;
                        report = frameDocument && frameDocument.querySelector('[data-result-report]');
                        fontsReady = !frameDocument.fonts || frameDocument.fonts.status !== 'loading';
                    } catch (error) {
                        finish(new Error('This result page could not be accessed.'));
                        return;
                    }

                    if (report
                        && report.getAttribute('data-result-report-ready') === 'true'
                        && fontsReady
                        && imagesReady(report)
                    ) {
                        capture(frameDocument, report);
                    }
                }, 100);
            };

            timeout = window.setTimeout(function () {
                finish(new Error('This result took too long to prepare.'));
            }, 45000);

            document.body.appendChild(frame);
            frame.src = item.url;
        });
    }

    function createPdfArrayBuffer(item, index, total, list) {
        return renderResultPage(item, index, total, list).then(function (canvas) {
            var image;
            var pdf;
            var result;

            try {
                image = canvas.toDataURL('image/jpeg', 0.94);
                pdf = new window.jspdf.jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4',
                    compress: true,
                    putOnlyUsedFonts: true
                });
                pdf.addImage(image, 'JPEG', 0, 0, 210, 297, undefined, 'FAST');
                pdf.setProperties({
                    title: item.filename.replace(/\.pdf$/i, ''),
                    subject: 'Student academic result',
                    creator: 'SchoolLift'
                });
                result = pdf.output('arraybuffer');
            } finally {
                canvas.width = 1;
                canvas.height = 1;
            }

            return result;
        });
    }

    function dispatchDownloadReady(blob, filename) {
        var event;

        try {
            event = new window.CustomEvent('resultdownloadready', {
                detail: { blob: blob, filename: filename }
            });
            document.dispatchEvent(event);
        } catch (error) {
            /* Older browsers can still receive the normal file download. */
        }
    }

    function downloadBlob(blob, filename) {
        var anchor;
        var objectUrl;

        dispatchDownloadReady(blob, filename);
        if (window.navigator && typeof window.navigator.msSaveOrOpenBlob === 'function') {
            window.navigator.msSaveOrOpenBlob(blob, filename);
            return;
        }

        objectUrl = window.URL.createObjectURL(blob);
        anchor = document.createElement('a');
        anchor.href = objectUrl;
        anchor.download = filename;
        anchor.style.display = 'none';
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(objectUrl);
        }, 60000);
    }

    function uniqueArchiveFilename(filename, usedNames) {
        var clean = safeFilename(filename, 'pdf', 'student-result');
        var stem = clean.replace(/\.pdf$/i, '');
        var candidate = clean;
        var suffix = 2;

        while (usedNames[candidate.toLowerCase()]) {
            candidate = stem + '-' + suffix + '.pdf';
            suffix += 1;
        }
        usedNames[candidate.toLowerCase()] = true;
        return candidate;
    }

    async function downloadOne(item, list) {
        var buffer = await createPdfArrayBuffer(item, 1, 1, list);
        downloadBlob(new window.Blob([buffer], { type: 'application/pdf' }), item.filename);
        return { downloaded: 1, failed: 0 };
    }

    async function downloadArchive(items, list) {
        var zip = new window.JSZip();
        var usedNames = {};
        var failures = [];
        var completed = 0;
        var index;
        var buffer;
        var archiveBlob;

        for (index = 0; index < items.length; index += 1) {
            try {
                buffer = await createPdfArrayBuffer(items[index], index + 1, items.length, list);
                zip.file(uniqueArchiveFilename(items[index].filename, usedNames), buffer, {
                    binary: true,
                    compression: 'STORE'
                });
                completed += 1;
            } catch (error) {
                failures.push(items[index].filename);
            }
        }

        if (!completed) {
            throw new Error('None of the selected results could be converted to PDF.');
        }

        if (failures.length) {
            zip.file(
                'download-errors.txt',
                'The following results could not be prepared:\r\n- ' + failures.join('\r\n- ')
            );
        }

        setMessage(list, 'Compressing ' + completed + (completed === 1 ? ' PDF' : ' PDFs') + '…', false);
        archiveBlob = await zip.generateAsync({
            type: 'blob',
            mimeType: 'application/zip',
            compression: 'STORE'
        });
        downloadBlob(archiveBlob, items[0].archiveFilename);

        return { downloaded: completed, failed: failures.length };
    }

    async function startDownload(items, list, asArchive) {
        var outcome;
        var message;

        items = uniqueItems(items);
        if (!items.length) {
            setMessage(list, 'Select at least one valid result to download.', true);
            return;
        }
        if (items.length > MAX_RESULTS_PER_DOWNLOAD) {
            setMessage(list, 'A maximum of ' + MAX_RESULTS_PER_DOWNLOAD + ' results can be downloaded at once.', true);
            return;
        }
        if (downloadInProgress) {
            return;
        }

        try {
            assertDownloadLibraries();
            setBusy(list, true);
            setMessage(
                list,
                'Preparing ' + items.length + (items.length === 1 ? ' result…' : ' results…')
                    + ' Keep this page open.',
                false
            );
            outcome = asArchive
                ? await downloadArchive(items, list)
                : await downloadOne(items[0], list);

            message = asArchive
                ? 'ZIP downloaded with ' + outcome.downloaded + (outcome.downloaded === 1 ? ' PDF.' : ' PDFs.')
                : 'PDF downloaded.';
            if (outcome.failed) {
                message += ' ' + outcome.failed + (outcome.failed === 1 ? ' result failed.' : ' results failed.');
            }
            setMessage(list, message, outcome.failed > 0);
        } catch (error) {
            setMessage(
                list,
                error && error.message ? error.message : 'The result download could not be completed.',
                true
            );
        } finally {
            setBusy(list, false);
        }
    }

    document.addEventListener('change', function (event) {
        var selectAll = closest(event.target, '[data-result-select-all]');
        var rowSelect = closest(event.target, '[data-result-select]');
        var list = closest(event.target, '[data-result-download-list]');

        if (!list || downloadInProgress) {
            return;
        }

        if (selectAll) {
            resultRows(list).forEach(function (row) {
                var checkbox = row.querySelector('[data-result-select]');
                if (checkbox) {
                    checkbox.checked = selectAll.checked;
                }
            });
        } else if (!rowSelect) {
            return;
        }

        setMessage(list, '', false);
        updateSelection(list);
    });

    document.addEventListener('click', function (event) {
        var one = closest(event.target, '[data-result-download-one]');
        var selected = closest(event.target, '[data-result-download-selected]');
        var all = closest(event.target, '[data-result-download-all]');
        var list = closest(event.target, '[data-result-download-list]');
        var row;
        var item;

        if (!list || (!one && !selected && !all)) {
            return;
        }

        event.preventDefault();
        if (downloadInProgress) {
            return;
        }

        if (one) {
            row = closest(one, '[data-result-row]');
            item = row ? rowItem(row) : null;
            startDownload(item ? [item] : [], list, false);
        } else if (selected && !selected.disabled) {
            startDownload(selectedItems(list), list, true);
        } else if (all && !all.disabled) {
            startDownload(allItems(list), list, true);
        }
    });

    window.ResultDownloadList = {
        refresh: function (scope) {
            toArray((scope || document).querySelectorAll('[data-result-download-list]')).forEach(updateSelection);
        }
    };
}(window, document));
