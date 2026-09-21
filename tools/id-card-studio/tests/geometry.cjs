const assert = require('node:assert/strict');
const g = require('../../../backend/idcard-studio/idcard-geometry.js');
let cases = 0;
for (const angle of [-180, -125, -90, -45, 0, 17, 45, 89, 135, 180]) {
    for (const stroke of [0, 0.25, 5]) {
        for (const [width, height] of [
            [85.6, 53.98],
            [40, 220],
            [220, 40]
        ]) {
            const members = [
                {
                    id: 'a',
                    type: 'rect',
                    x: -60,
                    y: 70,
                    width: 120,
                    height: 30,
                    rotation: angle,
                    stroke: '#ffffff',
                    strokeWidth: stroke
                },
                {
                    id: 'b',
                    type: 'ellipse',
                    x: 50,
                    y: 100,
                    width: 60,
                    height: 45,
                    rotation: angle,
                    stroke: '#000000',
                    strokeWidth: stroke
                }
            ];
            const ratio = members[0].width / members[1].width;
            g.fit(members, width, height);
            assert.ok(members.every((o) => g.inside(o, width, height)));
            assert.ok(Math.abs(members[0].width / members[1].width - ratio) < 1e-8);
            cases++;
        }
    }
}
for (const type of ['rect', 'ellipse', 'triangle', 'diamond', 'polygon', 'star', 'arrow']) {
    const nodes = g.shapeNodes({type, width: 20, height: 15, radius: 3});
    assert.ok(nodes.length >= 3 && nodes.length <= 256);
    for (const n of nodes)
        for (const p of [n, n.in, n.out].filter(Boolean))
            assert.ok(p.x >= 0 && p.x <= 1 && p.y >= 0 && p.y <= 1);
    const commands = g.pathCommands(nodes, true, 20, 15);
    assert.ok(commands.every((c) => ['M', 'L', 'C', 'Z'].includes(c[0])));
    if (type === 'polygon' || type === 'star') {
        assert.equal(Math.min(...nodes.map((node) => node.x)), 0);
        assert.equal(Math.max(...nodes.map((node) => node.x)), 1);
        assert.equal(Math.min(...nodes.map((node) => node.y)), 0);
        assert.equal(Math.max(...nodes.map((node) => node.y)), 1);
    }
    cases++;
}
const points = Array.from({length: 2000}, (_, i) => ({
    x: i / 50,
    y: Math.sin(i / 100)
}));
assert.ok(g.simplify(points, 0.1).length < 256);
cases++;
console.log(cases + ' geometry checks passed');
