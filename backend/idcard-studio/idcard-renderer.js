(function (window) {
    'use strict';

    var PX_PER_MM = 4;

    function number(value, fallback) {
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function textValue(object, bindings) {
        var raw = object.binding ? bindings[object.binding] : object.text;
        if (raw === null || typeof raw === 'undefined') {
            raw = '';
        }
        return String(object.prefix || '') + String(raw) + String(object.suffix || '');
    }

    function commonOptions(object) {
        return {
            left: number(object.x, 0) * PX_PER_MM,
            top: number(object.y, 0) * PX_PER_MM,
            angle: number(object.rotation, 0),
            opacity: number(object.opacity, 1),
            visible: object.visible !== false,
            selectable: object.locked !== true,
            evented: object.locked !== true,
            lockMovementX: object.locked === true,
            lockMovementY: object.locked === true,
            lockRotation: object.locked === true,
            lockScalingX: object.locked === true,
            lockScalingY: object.locked === true,
            objectCaching: false,
            transparentCorners: false,
            cornerColor: '#2563eb',
            borderColor: '#2563eb',
            cornerStyle: 'circle',
            padding: 1,
            studioId: object.id,
            studioType: object.type,
            studioGroup: object.group || '',
            studioData: clone(object)
        };
    }

    function sized(object, options) {
        options.width = Math.max(1, number(object.width, 10) * PX_PER_MM);
        options.height = Math.max(1, number(object.height, 5) * PX_PER_MM);
        return options;
    }

    function placeholder(object, label, options) {
        var width = Math.max(1, number(object.width, 10) * PX_PER_MM);
        var height = Math.max(1, number(object.height, 5) * PX_PER_MM);
        var box = new fabric.Rect({
            width: width,
            height: height,
            fill: '#f8fafc',
            stroke: '#94a3b8',
            strokeDashArray: [5, 4],
            strokeWidth: 1
        });
        var caption = new fabric.Text(String(label || 'Image'), {
            originX: 'center',
            originY: 'center',
            left: width / 2,
            top: height / 2,
            fontFamily: 'Arial',
            fontSize: Math.max(8, Math.min(14, height / 4)),
            fill: '#64748b'
        });
        return new fabric.Group([box, caption], Object.assign(options, {width: width, height: height}));
    }

    function loadImage(url, object, options) {
        return new Promise(function (resolve) {
            if (!url) {
                resolve(placeholder(object, object.binding ? object.binding.split('.').pop() : 'Image', options));
                return;
            }
            fabric.Image.fromURL(url, function (image) {
                if (!image || !image.width || !image.height) {
                    resolve(placeholder(object, 'Image unavailable', options));
                    return;
                }
                var targetWidth = Math.max(1, number(object.width, 10) * PX_PER_MM);
                var targetHeight = Math.max(1, number(object.height, 5) * PX_PER_MM);
                var fit = object.fit || 'cover';
                var sourceWidth = image.width;
                var sourceHeight = image.height;
                var cropX = 0;
                var cropY = 0;
                var cropWidth = sourceWidth;
                var cropHeight = sourceHeight;
                if (fit === 'cover') {
                    var sourceRatio = sourceWidth / sourceHeight;
                    var targetRatio = targetWidth / targetHeight;
                    if (sourceRatio > targetRatio) {
                        cropWidth = sourceHeight * targetRatio;
                        cropX = (sourceWidth - cropWidth) / 2;
                    } else {
                        cropHeight = sourceWidth / targetRatio;
                        cropY = (sourceHeight - cropHeight) / 2;
                    }
                }
                var scaleX = targetWidth / cropWidth;
                var scaleY = targetHeight / cropHeight;
                if (fit === 'contain') {
                    scaleX = scaleY = Math.min(targetWidth / sourceWidth, targetHeight / sourceHeight);
                    cropWidth = sourceWidth;
                    cropHeight = sourceHeight;
                }
                image.set(Object.assign(options, {
                    cropX: cropX,
                    cropY: cropY,
                    width: cropWidth,
                    height: cropHeight,
                    scaleX: scaleX,
                    scaleY: scaleY,
                    stroke: object.stroke || 'transparent',
                    strokeWidth: number(object.strokeWidth, 0) * PX_PER_MM,
                    strokeUniform: true
                }));
                var radiusPx = Math.max(0, number(object.radius, 0) * PX_PER_MM);
                if (radiusPx > 0 && fit !== 'contain') {
                    image.clipPath = new fabric.Rect({
                        originX: 'center', originY: 'center',
                        width: cropWidth, height: cropHeight,
                        rx: Math.min(cropWidth / 2, radiusPx / Math.max(scaleX, .0001)),
                        ry: Math.min(cropHeight / 2, radiusPx / Math.max(scaleY, .0001))
                    });
                }
                resolve(image);
            }, {crossOrigin: 'anonymous'});
        });
    }

    function qrDataUrl(value, foreground, background) {
        return new Promise(function (resolve) {
            if (!window.QRCode) {
                resolve('');
                return;
            }
            var holder = document.createElement('div');
            holder.style.position = 'fixed';
            holder.style.left = '-10000px';
            document.body.appendChild(holder);
            try {
                new QRCode(holder, {
                    text: String(value || 'UNISSUED-CREDENTIAL'),
                    width: 512,
                    height: 512,
                    colorDark: foreground || '#111827',
                    colorLight: background || '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                var canvas = holder.querySelector('canvas');
                var image = holder.querySelector('img');
                resolve(canvas ? canvas.toDataURL('image/png') : (image ? image.src : ''));
            } catch (error) {
                resolve('');
            } finally {
                document.body.removeChild(holder);
            }
        });
    }

    function barcodeDataUrl(value, foreground, background) {
        if (!window.JsBarcode) {
            return '';
        }
        var canvas = document.createElement('canvas');
        try {
            JsBarcode(canvas, String(value || 'UNISSUED'), {
                format: 'CODE128',
                displayValue: false,
                margin: 4,
                lineColor: foreground || '#111827',
                background: background || '#ffffff',
                height: 80,
                width: 2
            });
            return canvas.toDataURL('image/png');
        } catch (error) {
            return '';
        }
    }

    function codeImage(object, bindings, options) {
        var value = bindings[object.binding] || '';
        var source = object.type === 'qr'
            ? qrDataUrl(value, object.foreground, object.background)
            : Promise.resolve(barcodeDataUrl(value, object.foreground, object.background));
        return source.then(function (dataUrl) {
            return loadImage(dataUrl, object, options);
        });
    }

    function objectToFabric(object, context) {
        var options = commonOptions(object);
        var width = Math.max(1, number(object.width, 10) * PX_PER_MM);
        var height = Math.max(1, number(object.height, 5) * PX_PER_MM);
        var fabricObject;

        if (object.type === 'text') {
            fabricObject = new fabric.Textbox(textValue(object, context.bindings || {}), Object.assign(options, {
                width: width,
                height: height,
                fontFamily: object.fontFamily || 'Arial',
                fontSize: number(object.fontSize, 3) * PX_PER_MM,
                fontWeight: object.fontWeight || 'normal',
                fontStyle: object.fontStyle || 'normal',
                textAlign: object.align || 'left',
                fill: object.fill || '#111827',
                lineHeight: number(object.lineHeight, 1.16),
                charSpacing: number(object.charSpacing, 0),
                splitByGrapheme: false
            }));
            return Promise.resolve(fabricObject);
        }

        if (object.type === 'rect' || object.type === 'line') {
            fabricObject = new fabric.Rect(Object.assign(options, {
                width: width,
                height: object.type === 'line' ? Math.max(1, height) : height,
                fill: object.type === 'line' ? (object.stroke || '#64748b') : (object.fill || '#e2e8f0'),
                stroke: object.type === 'line' ? 'transparent' : (object.stroke || '#64748b'),
                strokeWidth: object.type === 'line' ? 0 : number(object.strokeWidth, .25) * PX_PER_MM,
                rx: number(object.radius, 0) * PX_PER_MM,
                ry: number(object.radius, 0) * PX_PER_MM
            }));
            return Promise.resolve(fabricObject);
        }

        if (object.type === 'ellipse') {
            fabricObject = new fabric.Ellipse(Object.assign(options, {
                rx: width / 2,
                ry: height / 2,
                width: width,
                height: height,
                fill: object.fill || '#e2e8f0',
                stroke: object.stroke || '#64748b',
                strokeWidth: number(object.strokeWidth, .25) * PX_PER_MM
            }));
            return Promise.resolve(fabricObject);
        }

        if (object.type === 'image') {
            var imageUrl = object.assetId ? (context.assets[object.assetId] || '') : ((context.bindings || {})[object.binding] || '');
            return loadImage(imageUrl, object, options);
        }

        if (object.type === 'qr' || object.type === 'barcode') {
            return codeImage(object, context.bindings || {}, options);
        }

        return Promise.resolve(placeholder(object, 'Unsupported', sized(object, options)));
    }

    function render(canvas, documentData, context) {
        context = context || {};
        var widthMm = number(context.widthMm, 85.6);
        var heightMm = number(context.heightMm, 53.98);
        canvas.clear();
        canvas.setDimensions({width: Math.round(widthMm * PX_PER_MM), height: Math.round(heightMm * PX_PER_MM)});
        canvas.backgroundColor = documentData.background && documentData.background.type === 'color'
            ? documentData.background.value : '#ffffff';

        var backgroundPromise = Promise.resolve();
        if (documentData.background && documentData.background.type === 'asset') {
            var backgroundUrl = context.assets[documentData.background.assetId] || '';
            backgroundPromise = loadImage(backgroundUrl, {
                id: 'studio-background', type: 'image', x: 0, y: 0,
                width: widthMm, height: heightMm, fit: 'cover', opacity: 1,
                visible: true, locked: true, assetId: documentData.background.assetId
            }, {selectable: false, evented: false}).then(function (image) {
                canvas.setBackgroundImage(image, canvas.renderAll.bind(canvas));
            });
        } else if (documentData.background && documentData.background.type === 'binding') {
            var bindingUrl = (context.bindings || {})[documentData.background.binding] || '';
            backgroundPromise = loadImage(bindingUrl, {
                id: 'studio-background', type: 'image', x: 0, y: 0,
                width: widthMm, height: heightMm, fit: 'cover', opacity: 1,
                visible: true, locked: true, binding: documentData.background.binding
            }, {selectable: false, evented: false}).then(function (image) {
                canvas.setBackgroundImage(image, canvas.renderAll.bind(canvas));
            });
        }

        var jobs = (documentData.objects || []).map(function (object) {
            return objectToFabric(object, context).then(function (fabricObject) {
                canvas.add(fabricObject);
                return fabricObject;
            });
        });
        return Promise.all([backgroundPromise].concat(jobs)).then(function () {
            canvas.requestRenderAll();
            return canvas;
        });
    }

    function toCanonical(canvas, side, background) {
        var objects = canvas.getObjects().map(function (object) {
            var source = clone(object.studioData || {});
            var transform = fabric.util.qrDecompose(object.calcTransformMatrix());
            var width = Math.abs(object.width * transform.scaleX) / PX_PER_MM;
            var height = Math.abs(object.height * transform.scaleY) / PX_PER_MM;
            var centre = new fabric.Point(transform.translateX, transform.translateY);
            var topLeft = object.translateToOriginPoint(centre, 'left', 'top');
            source.id = object.studioId || source.id;
            source.type = object.studioType || source.type;
            source.x = round(topLeft.x / PX_PER_MM, 3);
            source.y = round(topLeft.y / PX_PER_MM, 3);
            source.width = round(width, 3);
            source.height = round(height, 3);
            source.rotation = round(transform.angle || 0, 2);
            source.opacity = round(object.opacity, 3);
            source.visible = object.visible !== false;
            source.locked = object.selectable === false;
            source.group = object.studioGroup || source.group || '';
            if (source.type === 'text') {
                source.text = source.binding ? (source.text || '') : String(object.text || '');
                source.fontFamily = object.fontFamily || 'Arial';
                source.fontSize = round(number(object.fontSize, 12) / PX_PER_MM, 2);
                source.fontWeight = object.fontWeight === 'bold' ? 'bold' : 'normal';
                source.fontStyle = object.fontStyle === 'italic' ? 'italic' : 'normal';
                source.align = object.textAlign || 'left';
                source.fill = typeof object.fill === 'string' ? object.fill : '#111827';
                source.lineHeight = round(number(object.lineHeight, 1.16), 2);
                source.charSpacing = Math.round(number(object.charSpacing, 0));
            }
            object.studioData = clone(source);
            return source;
        });
        return {
            schemaVersion: 1,
            side: side,
            background: clone(background || {type: 'color', value: '#ffffff'}),
            objects: objects
        };
    }

    function round(value, precision) {
        var factor = Math.pow(10, precision || 0);
        return Math.round(value * factor) / factor;
    }

    window.SchoolLiftIdCardRenderer = {
        PX_PER_MM: PX_PER_MM,
        clone: clone,
        render: render,
        objectToFabric: objectToFabric,
        toCanonical: toCanonical,
        textValue: textValue,
        qrDataUrl: qrDataUrl,
        barcodeDataUrl: barcodeDataUrl
    };
}(window));
