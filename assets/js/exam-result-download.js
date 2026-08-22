/* global window, document */
(function (window, document) {
    'use strict';

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
        return toArray(list.querySelectorAll('[data-result-row]'));
    }

    function rowUrl(row) {
        var link = row.querySelector('[data-result-download-one]');
        return link ? link.getAttribute('href') : '';
    }

    function uniqueUrls(urls) {
        var seen = {};

        return urls.filter(function (url) {
            if (!url || seen[url]) {
                return false;
            }
            seen[url] = true;
            return true;
        });
    }

    function selectedUrls(list) {
        return uniqueUrls(resultRows(list).filter(function (row) {
            var checkbox = row.querySelector('[data-result-select]');
            return checkbox && checkbox.checked;
        }).map(rowUrl));
    }

    function allUrls(list) {
        return uniqueUrls(resultRows(list).map(rowUrl));
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
        }

        if (count) {
            count.textContent = selected + ' selected';
        }

        toArray(list.querySelectorAll('[data-result-download-selected]')).forEach(function (button) {
            button.disabled = selected === 0;
        });

        toArray(list.querySelectorAll('[data-result-download-all]')).forEach(function (button) {
            button.disabled = rows.length === 0;
        });
    }

    function submitDownload(urls, list) {
        var form;
        var input;

        urls = uniqueUrls(urls);
        if (!urls.length) {
            setMessage(list, 'Select at least one result to download.', true);
            return;
        }

        setMessage(
            list,
            'Preparing ' + urls.length + (urls.length === 1 ? ' result…' : ' results…'),
            false
        );

        form = document.createElement('form');
        form.method = 'post';
        form.action = 'resultBulkDownload.php';
        form.target = '_blank';
        form.style.display = 'none';

        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'result_urls';
        input.value = JSON.stringify(urls);
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        window.setTimeout(function () {
            setMessage(list, '', false);
        }, 2500);
    }

    document.addEventListener('change', function (event) {
        var selectAll = closest(event.target, '[data-result-select-all]');
        var rowSelect = closest(event.target, '[data-result-select]');
        var list = closest(event.target, '[data-result-download-list]');

        if (!list) {
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

        if (!list || (!one && !selected && !all)) {
            return;
        }

        event.preventDefault();

        if (one) {
            submitDownload([one.getAttribute('href')], list);
        } else if (selected && !selected.disabled) {
            submitDownload(selectedUrls(list), list);
        } else if (all && !all.disabled) {
            submitDownload(allUrls(list), list);
        }
    });

    window.ResultDownloadList = {
        refresh: function (scope) {
            toArray((scope || document).querySelectorAll('[data-result-download-list]')).forEach(updateSelection);
        }
    };
}(window, document));
