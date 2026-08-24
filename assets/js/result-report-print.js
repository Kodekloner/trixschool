/* global window, document */
(function (window, document) {
    'use strict';

    var ROOT_SELECTOR = '[data-result-report]';
    var CONTENT_SELECTOR = '[data-result-report-content]';
    var PREVIEW_SELECTOR = '[data-result-report-preview]';
    var DENSITY_CLASSES = [
        'result-report--standard',
        'result-report--compact',
        'result-report--ultra'
    ];
    var instances = [];
    var resizeTimer = null;
    var raf = window.requestAnimationFrame || function (callback) {
        return window.setTimeout(callback, 16);
    };

    function toArray(collection) {
        return Array.prototype.slice.call(collection || []);
    }

    function hasClass(element, className) {
        return element.classList
            ? element.classList.contains(className)
            : new RegExp('(^|\\s)' + className + '(\\s|$)').test(element.className);
    }

    function addClass(element, className) {
        if (element.classList) {
            element.classList.add(className);
            return;
        }

        if (!hasClass(element, className)) {
            element.className += (element.className ? ' ' : '') + className;
        }
    }

    function removeClass(element, className) {
        if (element.classList) {
            element.classList.remove(className);
            return;
        }

        element.className = element.className.replace(
            new RegExp('(^|\\s)' + className + '(?=\\s|$)', 'g'),
            ' '
        ).replace(/^\s+|\s+$/g, '');
    }

    function closest(element, selector) {
        var node = element;

        if (node.closest) {
            return node.closest(selector);
        }

        while (node && node.nodeType === 1) {
            if (node.matches && node.matches(selector)) {
                return node;
            }
            node = node.parentNode;
        }

        return null;
    }

    function parseRowCount(root) {
        var raw = root.getAttribute('data-academic-row-count');
        var count = parseInt(raw, 10);

        if (!isNaN(count) && count >= 0) {
            return count;
        }

        count = root.querySelectorAll('[data-academic-row]').length;
        root.setAttribute('data-academic-row-count', String(count));
        return count;
    }

    function cellHasContent(cell) {
        return !!(cell && cell.textContent && cell.textContent.replace(/\s+/g, '') !== '');
    }

    /*
     * Older result branches render one domain item plus two empty cells per
     * row. Pairing adjacent single items uses the intended four-cell layout
     * and removes unnecessary vertical space without changing any values.
     */
    function compactDomainTables(root) {
        var allTables = toArray(root.querySelectorAll('.result-report__domain-table'));
        var tables = allTables.filter(function (table) {
            return !hasClass(table, 'result-report__domain-table--combined');
        });

        allTables.forEach(function (table) {
            var isCombined = hasClass(table, 'result-report__domain-table--combined');
            var combinedState = table.getAttribute('data-result-domain-state');
            var isSingleCombinedDomain = isCombined
                && (combinedState === 'affective' || combinedState === 'psychomotor');
            var widths = isCombined && !isSingleCombinedDomain
                ? [21, 4, 21, 4, 21, 4, 21, 4]
                : [42, 8, 42, 8];
            var group = table.querySelector('colgroup[data-result-domain-columns]');

            if (group && group.children.length !== widths.length) {
                group.parentNode.removeChild(group);
                group = null;
            }

            if (!group) {
                group = document.createElement('colgroup');
                group.setAttribute('data-result-domain-columns', 'true');
                widths.forEach(function (width) {
                    var column = document.createElement('col');
                    column.style.width = width + '%';
                    group.appendChild(column);
                });
                table.insertBefore(group, table.firstChild);
            } else {
                widths.forEach(function (width, index) {
                    group.children[index].style.width = width + '%';
                });
            }
        });

        tables.forEach(function (table) {
            var bodies = toArray(table.tBodies || []);

            bodies.forEach(function (body) {
                var rows = toArray(body.rows || []);
                var pending = null;

                rows.forEach(function (row) {
                    var cells = toArray(row.cells || []);
                    var isSingleItem = cells.length === 4
                        && cells[0].colSpan === 1
                        && cellHasContent(cells[0])
                        && !cellHasContent(cells[2])
                        && !cellHasContent(cells[3]);

                    if (!isSingleItem) {
                        pending = null;
                        return;
                    }

                    if (!pending) {
                        pending = row;
                        return;
                    }

                    pending.removeChild(pending.cells[3]);
                    pending.removeChild(pending.cells[2]);
                    row.removeChild(cells[3]);
                    row.removeChild(cells[2]);
                    pending.appendChild(cells[0]);
                    pending.appendChild(cells[1]);
                    body.removeChild(row);
                    pending = null;
                });
            });

            table.setAttribute('data-result-domain-compacted', 'true');
        });
    }

    function tableHasLayoutContent(table) {
        var combinedState = table.getAttribute('data-result-domain-state');
        var rows;

        if (hasClass(table, 'result-report__domain-table--combined')) {
            return combinedState !== 'none';
        }

        rows = toArray(table.rows || []);
        return rows.some(function (row) {
            var cells;

            if (row.querySelector('.result-report__domain-title')) {
                return false;
            }

            /* A configured panel with no score remains as a compact status
             * table. Only a truly empty, unconfigured table is removed. */
            if (row.querySelector('.alert')) {
                return true;
            }

            cells = toArray(row.cells || []);
            return cells.some(function (cell) {
                return !!(cell.textContent && cell.textContent.replace(/\s+/g, '') !== '');
            });
        });
    }

    function setPanelAvailability(element, available) {
        if (!element) {
            return;
        }

        if (available) {
            removeClass(element, 'result-report__panel--unavailable');
            element.setAttribute('data-result-panel-available', 'true');
            element.removeAttribute('aria-hidden');
            return;
        }

        addClass(element, 'result-report__panel--unavailable');
        element.setAttribute('data-result-panel-available', 'false');
        element.setAttribute('aria-hidden', 'true');
    }

    function arrangePerformancePanels(root) {
        var tables = toArray(root.querySelectorAll(
            '.result-report__domain-table, .result-report__attendance-table'
        ));
        var tableGroups = toArray(root.querySelectorAll('.result-report__domain-tables'));
        var rows = toArray(root.querySelectorAll('.result-report__performance-row'));

        tables.forEach(function (table) {
            setPanelAvailability(table, tableHasLayoutContent(table));
        });

        tableGroups.forEach(function (group) {
            var groupTables = toArray(group.children).filter(function (child) {
                return hasClass(child, 'result-report__domain-table')
                    || hasClass(child, 'result-report__attendance-table');
            });
            var availableCount = groupTables.filter(function (table) {
                return table.getAttribute('data-result-panel-available') === 'true';
            }).length;

            group.setAttribute('data-result-panel-count', String(availableCount));
        });

        rows.forEach(function (row) {
            var panels = toArray(row.children).filter(function (child) {
                return hasClass(child, 'result-report__chart-column')
                    || hasClass(child, 'result-report__domain-column')
                    || hasClass(child, 'result-report__domains-column');
            });
            var availableCount = 0;

            panels.forEach(function (panel) {
                var available = true;
                var panelTables;
                var chart;

                if (hasClass(panel, 'result-report__chart-column')) {
                    chart = panel.querySelector('[data-result-decorative-chart], .result-report__chart');
                    available = !!chart && window.getComputedStyle(chart).display !== 'none';
                } else {
                    panelTables = toArray(panel.querySelectorAll(
                        '.result-report__domain-table, .result-report__attendance-table'
                    ));
                    available = panelTables.some(function (table) {
                        return table.getAttribute('data-result-panel-available') === 'true';
                    });
                }

                setPanelAvailability(panel, available);
                if (available) {
                    availableCount += 1;
                }
            });

            row.setAttribute('data-result-panel-count', String(availableCount));
        });
    }

    function applyDensity(root) {
        var count = parseRowCount(root);
        var density = 'standard';
        var i;

        if (count > 18) {
            density = 'ultra';
        } else if (count > 12) {
            density = 'compact';
        }

        for (i = 0; i < DENSITY_CLASSES.length; i += 1) {
            removeClass(root, DENSITY_CLASSES[i]);
        }

        addClass(root, 'result-report--' + density);
        root.setAttribute('data-result-density', density);
        return density;
    }

    function setCustomProperty(element, property, value) {
        if (element.style && element.style.setProperty) {
            element.style.setProperty(property, value);
        }
    }

    function resetContentScale(root, content) {
        setCustomProperty(root, '--result-fit-scale', '1');
        content.style.transform = 'scale(1)';
    }

    function fitContent(root) {
        var content = root.querySelector(CONTENT_SELECTOR);
        var availableWidth;
        var availableHeight;
        var neededWidth;
        var neededHeight;
        var widthScale;
        var heightScale;
        var scale;

        if (!content) {
            return 1;
        }

        resetContentScale(root, content);

        /* Force layout after resetting a scale applied by an earlier pass. */
        availableWidth = content.clientWidth;
        availableHeight = content.clientHeight;
        neededWidth = Math.max(content.scrollWidth, content.offsetWidth);
        neededHeight = Math.max(content.scrollHeight, content.offsetHeight);

        if (!availableWidth || !availableHeight || !neededWidth || !neededHeight) {
            return 1;
        }

        widthScale = availableWidth / neededWidth;
        heightScale = availableHeight / neededHeight;
        scale = Math.min(1, widthScale, heightScale);

        /* Leave a small rounding buffer for print rasterisation and table borders. */
        if (scale < 1) {
            scale = Math.max(0.01, scale * 0.995);
        }

        scale = Math.floor(scale * 10000) / 10000;
        setCustomProperty(root, '--result-fit-scale', String(scale));
        content.style.transform = '';
        root.setAttribute('data-result-fit-scale', String(scale));
        root.setAttribute('data-result-overflow-width', String(Math.max(0, neededWidth - availableWidth)));
        root.setAttribute('data-result-overflow-height', String(Math.max(0, neededHeight - availableHeight)));

        return scale;
    }

    function fitPreview(root) {
        var preview = closest(root, PREVIEW_SELECTOR);
        var rootWidth;
        var rootHeight;
        var availableWidth;
        var scale;

        if (!preview || !root.getBoundingClientRect) {
            setCustomProperty(root, '--result-preview-scale', '1');
            return 1;
        }

        rootWidth = root.offsetWidth;
        rootHeight = root.offsetHeight;
        availableWidth = preview.clientWidth;

        if (document.documentElement && document.documentElement.clientWidth) {
            availableWidth = Math.min(
                availableWidth,
                Math.max(1, document.documentElement.clientWidth - 16)
            );
        }

        if (!rootWidth || !rootHeight || !availableWidth) {
            return 1;
        }

        scale = Math.min(1, availableWidth / rootWidth);
        scale = Math.floor(scale * 10000) / 10000;
        setCustomProperty(root, '--result-preview-scale', String(scale));
        preview.style.height = Math.ceil(rootHeight * scale) + 'px';
        preview.style.minHeight = '0';
        preview.setAttribute('data-result-preview-scale', String(scale));

        return scale;
    }

    function fit(root) {
        if (!root || root.nodeType !== 1) {
            return null;
        }

        compactDomainTables(root);
        applyDensity(root);
        arrangePerformancePanels(root);
        fitContent(root);
        fitPreview(root);
        addClass(root, 'result-report--ready');
        root.setAttribute('data-result-report-ready', 'true');
        return root;
    }

    function waitForImages(root, callback) {
        var images = toArray(root.querySelectorAll('img'));
        var pending = 0;
        var completed = false;

        function finish() {
            if (!completed && pending === 0) {
                completed = true;
                callback();
            }
        }

        function settle() {
            pending -= 1;
            finish();
        }

        images.forEach(function (image) {
            if (!image.complete) {
                pending += 1;
                image.addEventListener('load', settle, { once: true });
                image.addEventListener('error', settle, { once: true });
            }
        });

        finish();
    }

    function whenAssetsReady(root, callback) {
        var imagesReady = false;
        var fontsReady = !document.fonts || !document.fonts.ready;
        var called = false;

        function ready() {
            if (!called && imagesReady && fontsReady) {
                called = true;
                raf(function () {
                    raf(callback);
                });
            }
        }

        waitForImages(root, function () {
            imagesReady = true;
            ready();
        });

        if (!fontsReady) {
            document.fonts.ready.then(function () {
                fontsReady = true;
                ready();
            }, function () {
                fontsReady = true;
                ready();
            });
        }
    }

    function findInstance(root) {
        var i;

        for (i = 0; i < instances.length; i += 1) {
            if (instances[i].root === root) {
                return instances[i];
            }
        }

        return null;
    }

    function init(root) {
        var instance;

        if (!root || root.nodeType !== 1) {
            return null;
        }

        instance = findInstance(root);
        if (instance) {
            fit(root);
            return instance;
        }

        instance = {
            root: root,
            fit: function () {
                return fit(root);
            },
            refresh: function () {
                return fit(root);
            }
        };
        instances.push(instance);

        applyDensity(root);
        arrangePerformancePanels(root);
        whenAssetsReady(root, instance.fit);

        return instance;
    }

    function initAll(scope) {
        var context = scope && scope.querySelectorAll ? scope : document;
        var roots = toArray(context.querySelectorAll(ROOT_SELECTOR));

        if (context.nodeType === 1 && context.getAttribute('data-result-report') !== null) {
            roots.unshift(context);
        }

        roots.forEach(init);
        return instances.slice(0);
    }

    function fitAll() {
        instances.forEach(function (instance) {
            instance.fit();
        });
    }

    function scheduleFitAll() {
        if (resizeTimer !== null) {
            window.clearTimeout(resizeTimer);
        }

        resizeTimer = window.setTimeout(function () {
            resizeTimer = null;
            fitAll();
        }, 100);
    }

    function boot() {
        initAll(document);

        if (document.body) {
            addClass(document.body, 'result-report-page');
        }
    }

    window.ResultReportPrint = {
        init: init,
        initAll: initAll,
        fit: fit,
        fitAll: fitAll,
        refresh: fitAll
    };

    /* A simple alias is useful to legacy pages with inline scripts. */
    window.initResultReport = init;

    window.addEventListener('beforeprint', fitAll);
    window.addEventListener('afterprint', scheduleFitAll);
    window.addEventListener('resize', scheduleFitAll);
    window.addEventListener('load', fitAll);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}(window, document));
