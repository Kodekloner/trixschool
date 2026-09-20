(function (root) {
    'use strict';
    var EPS = 0.001;
    function clamp(n, lo, hi) {
        return Math.max(lo, Math.min(hi, n));
    }
    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }
    function stroke(object) {
        return object.stroke && object.stroke !== 'transparent' && object.type !== 'line'
            ? Number(object.strokeWidth || 0)
            : 0;
    }
    function bounds(object) {
        var a = (Number(object.rotation || 0) * Math.PI) / 180;
        var w = Number(object.width) + stroke(object),
            h = Number(object.height) + stroke(object);
        var dx = (Math.abs(Math.cos(a)) * w + Math.abs(Math.sin(a)) * h) / 2;
        var dy = (Math.abs(Math.sin(a)) * w + Math.abs(Math.cos(a)) * h) / 2;
        return {
            left: object.x - dx,
            top: object.y - dy,
            right: object.x + dx,
            bottom: object.y + dy,
            width: dx * 2,
            height: dy * 2
        };
    }
    function union(objects) {
        var boxes = objects.map(bounds);
        var left = Math.min.apply(
            null,
            boxes.map(function (b) {
                return b.left;
            })
        );
        var top = Math.min.apply(
            null,
            boxes.map(function (b) {
                return b.top;
            })
        );
        var right = Math.max.apply(
            null,
            boxes.map(function (b) {
                return b.right;
            })
        );
        var bottom = Math.max.apply(
            null,
            boxes.map(function (b) {
                return b.bottom;
            })
        );
        return {
            left: left,
            top: top,
            right: right,
            bottom: bottom,
            width: right - left,
            height: bottom - top
        };
    }
    function inside(object, width, height) {
        var b = bounds(object);
        return (
            b.left >= -EPS && b.top >= -EPS && b.right <= width + EPS && b.bottom <= height + EPS
        );
    }
    // Fit a logical selection uniformly; stroke widths remain physical millimetres.
    function fit(objects, width, height) {
        if (!objects.length) {
            return objects;
        }
        var box = union(objects),
            cx = (box.left + box.right) / 2,
            cy = (box.top + box.bottom) / 2;
        if (box.width > width || box.height > height) {
            var original = clone(objects),
                lo = 0,
                hi = 1;
            function scale(factor) {
                objects.forEach(function (o, i) {
                    var s = original[i];
                    o.x = cx + (s.x - cx) * factor;
                    o.y = cy + (s.y - cy) * factor;
                    o.width = s.width * factor;
                    o.height = s.height * factor;
                });
            }
            for (var i = 0; i < 40; i++) {
                var mid = (lo + hi) / 2;
                scale(mid);
                var trial = union(objects);
                if (trial.width <= width && trial.height <= height) {
                    lo = mid;
                } else {
                    hi = mid;
                }
            }
            scale(lo);
            box = union(objects);
        }
        var dx = box.left < 0 ? -box.left : box.right > width ? width - box.right : 0;
        var dy = box.top < 0 ? -box.top : box.bottom > height ? height - box.bottom : 0;
        objects.forEach(function (o) {
            o.x += dx;
            o.y += dy;
        });
        return objects;
    }
    function node(x, y, extra) {
        return Object.assign({x: x, y: y, mode: 'corner'}, extra || {});
    }
    function shapeNodes(object) {
        var type = object.type,
            p = object.shape || {},
            nodes = [],
            count,
            i,
            angle;
        if (type === 'path') {
            return clone(object.nodes);
        }
        if (type === 'ellipse') {
            var k = 0.2761423749154;
            return [
                node(0.5, 0, {in: {x: 0.5 - k, y: 0}, out: {x: 0.5 + k, y: 0}, mode: 'smooth'}),
                node(1, 0.5, {in: {x: 1, y: 0.5 - k}, out: {x: 1, y: 0.5 + k}, mode: 'smooth'}),
                node(0.5, 1, {in: {x: 0.5 + k, y: 1}, out: {x: 0.5 - k, y: 1}, mode: 'smooth'}),
                node(0, 0.5, {in: {x: 0, y: 0.5 + k}, out: {x: 0, y: 0.5 - k}, mode: 'smooth'})
            ];
        }
        if (type === 'triangle') {
            return [node(0.5, 0), node(1, 1), node(0, 1)];
        }
        if (type === 'diamond') {
            return [node(0.5, 0), node(1, 0.5), node(0.5, 1), node(0, 0.5)];
        }
        if (type === 'polygon' || type === 'star') {
            count = type === 'star' ? (p.points || 5) * 2 : p.sides || 6;
            for (i = 0; i < count; i++) {
                angle = -Math.PI / 2 + (i * 2 * Math.PI) / count;
                var radius = type === 'star' && i % 2 ? (p.innerRadius || 0.45) / 2 : 0.5;
                nodes.push(node(0.5 + Math.cos(angle) * radius, 0.5 + Math.sin(angle) * radius));
            }
            return nodes;
        }
        if (type === 'arrow') {
            var head = 1 - (p.head || 0.35),
                shaft = (p.shaft || 0.4) / 2;
            return [
                node(0, 0.5 - shaft),
                node(head, 0.5 - shaft),
                node(head, 0),
                node(1, 0.5),
                node(head, 1),
                node(head, 0.5 + shaft),
                node(0, 0.5 + shaft)
            ];
        }
        var rx = Math.min(0.5, Number(object.radius || 0) / object.width);
        var ry = Math.min(0.5, Number(object.radius || 0) / object.height);
        if (rx && ry && type === 'rect') {
            var c = 0.5522847498308;
            return [
                node(rx, 0, {in: {x: rx - c * rx, y: 0}}),
                node(1 - rx, 0, {out: {x: 1 - rx + c * rx, y: 0}}),
                node(1, ry, {in: {x: 1, y: ry - c * ry}}),
                node(1, 1 - ry, {out: {x: 1, y: 1 - ry + c * ry}}),
                node(1 - rx, 1, {in: {x: 1 - rx + c * rx, y: 1}}),
                node(rx, 1, {out: {x: rx - c * rx, y: 1}}),
                node(0, 1 - ry, {in: {x: 0, y: 1 - ry + c * ry}}),
                node(0, ry, {out: {x: 0, y: ry - c * ry}})
            ];
        }
        return [node(0, 0), node(1, 0), node(1, 1), node(0, 1)];
    }
    function pathCommands(nodes, closed, width, height) {
        function p(n) {
            return [(n.x - 0.5) * width, (n.y - 0.5) * height];
        }
        var commands = [['M'].concat(p(nodes[0]))];
        for (var i = 1; i < nodes.length + (closed ? 1 : 0); i++) {
            var prev = nodes[(i - 1) % nodes.length],
                next = nodes[i % nodes.length];
            commands.push(
                prev.out || next.in
                    ? ['C'].concat(p(prev.out || prev), p(next.in || next), p(next))
                    : ['L'].concat(p(next))
            );
        }
        if (closed) {
            commands.push(['Z']);
        }
        return commands;
    }
    // Simplification is measured in document millimetres, independent of zoom.
    function simplify(points, tolerance) {
        if (points.length <= 2) {
            return points;
        }
        var a = points[0],
            b = points[points.length - 1],
            dx = b.x - a.x,
            dy = b.y - a.y,
            max = 0,
            index = 0;
        for (var i = 1; i < points.length - 1; i++) {
            var t = clamp(
                ((points[i].x - a.x) * dx + (points[i].y - a.y) * dy) / (dx * dx + dy * dy || 1),
                0,
                1
            );
            var d = Math.hypot(points[i].x - a.x - t * dx, points[i].y - a.y - t * dy);
            if (d > max) {
                max = d;
                index = i;
            }
        }
        if (max <= tolerance) {
            return [a, b];
        }
        return simplify(points.slice(0, index + 1), tolerance)
            .slice(0, -1)
            .concat(simplify(points.slice(index), tolerance));
    }
    var api = {
        bounds: bounds,
        union: union,
        inside: inside,
        fit: fit,
        shapeNodes: shapeNodes,
        pathCommands: pathCommands,
        simplify: simplify,
        clamp: clamp
    };
    root.SchoolLiftIdCardGeometry = api;
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);
