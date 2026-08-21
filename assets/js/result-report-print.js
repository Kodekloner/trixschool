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

        applyDensity(root);
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
