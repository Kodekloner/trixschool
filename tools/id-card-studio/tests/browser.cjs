/* Run against browser-router.php, not a school database. See the Studio guide. */
const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.STUDIO_TEST_URL || 'http://127.0.0.1:8765';
let passed = 0;
function check(value, message) {
    assert.ok(value, message);
    console.log('PASS:', message);
    passed++;
}
(async () => {
    const browser = await chromium.launch({
        headless: true,
        ...(process.env.CHROMIUM_EXECUTABLE
            ? {executablePath: process.env.CHROMIUM_EXECUTABLE}
            : {})
    });
    const context = await browser.newContext({
        viewport: {width: 1440, height: 900},
        acceptDownloads: true
    });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    // Test-only instrumentation; production scripts expose no test state.
    await page.route('**/idcard-studio.js*', async (route) => {
        const response = await route.fetch();
        const body = (await response.text())
            .replace(
                'var canvas = new fabric.Canvas',
                'var canvas = window.testCanvas = new fabric.Canvas'
            )
            .replace('var state = {', 'var state = window.testState = {');
        await route.fulfill({response, body});
    });
    async function ready() {
        await page.waitForFunction(
            () =>
                document.getElementById('idstudio-app').dataset.studioReady === '1' &&
                !window.testState.loading
        );
    }
    async function action(name) {
        await page.evaluate(
            (name) => document.querySelector('[data-action="' + name + '"]').click(),
            name
        );
        await ready();
    }
    async function command(name) {
        await page.evaluate((name) => {
            document.querySelector('[data-action="commands"]').click();
            document.querySelector('[data-command="' + name + '"]').click();
        }, name);
        await ready();
    }
    async function objects() {
        return page.evaluate(
            () =>
                SchoolLiftIdCardRenderer.toCanonical(
                    testCanvas,
                    testState.side,
                    testState.documents[testState.side].background
                ).objects
        );
    }
    async function add(type) {
        await page.evaluate(
            (type) => document.querySelector('[data-add="' + type + '"]').click(),
            type
        );
        await page.waitForTimeout(50);
        await ready();
    }
    async function select(ids) {
        await page.evaluate((ids) => {
            const c = testCanvas;
            c.discardActiveObject();
            const os = ids.map((id) => c.getObjects().find((o) => o.studioId === id));
            if (os.length)
                c.setActiveObject(
                    os.length === 1 ? os[0] : new fabric.ActiveSelection(os, {canvas: c})
                );
            c.requestRenderAll();
        }, ids);
    }
    async function modify(id, values) {
        await select([id]);
        await page.evaluate(
            ({id, values}) => {
                const o = testCanvas.getObjects().find((o) => o.studioId === id);
                o.set(values);
                o.setCoords();
                testCanvas.fire('object:modified', {target: o});
            },
            {id, values}
        );
        await ready();
    }
    async function allInside() {
        return page.evaluate(() =>
            SchoolLiftIdCardRenderer.toCanonical(
                testCanvas,
                testState.side,
                testState.documents[testState.side].background
            ).objects.every((o) =>
                SchoolLiftIdCardGeometry.inside(o, testState.widthMm, testState.heightMm)
            )
        );
    }
    async function save() {
        const response = page.waitForResponse((r) => r.url().endsWith('/fixture/save'));
        await action('save');
        const result = await response;
        const body = await result.json();
        assert.equal(result.status(), 200, JSON.stringify(body));
        await page.waitForFunction(() => !testState.dirty && !testState.savePromise);
        return body;
    }
    try {
        await page.goto(base + '/fixture?empty');
        await ready();
        check(
            await page.evaluate(
                () =>
                    document.querySelector('.idstudio-statusbar').getBoundingClientRect().bottom <=
                    innerHeight
            ),
            'desktop workspace and fixed status bar fit the viewport'
        );
        for (const shape of [
            'rect',
            'rounded-rect',
            'ellipse',
            'line',
            'triangle',
            'diamond',
            'polygon',
            'star',
            'arrow',
            'text',
            'qr',
            'barcode'
        ]) {
            await add(shape);
        }
        check((await objects()).length === 12, 'every shape and text/code tool creates an object');
        check(
            await page.evaluate(() =>
                testCanvas.getObjects().every((object) => object.padding === 0)
            ),
            'selection borders have zero padding around every object frame'
        );
        check(
            await page.evaluate(() =>
                ['polygon', 'star'].every((type) => {
                    const object = SchoolLiftIdCardRenderer.toCanonical(
                        testCanvas,
                        'front',
                        {}
                    ).objects.find((item) => item.type === type);
                    const nodes = SchoolLiftIdCardGeometry.shapeNodes(object);
                    return (
                        Math.min(...nodes.map((node) => node.x)) === 0 &&
                        Math.max(...nodes.map((node) => node.x)) === 1 &&
                        Math.min(...nodes.map((node) => node.y)) === 0 &&
                        Math.max(...nodes.map((node) => node.y)) === 1
                    );
                })
            ),
            'polygon and star artwork reaches every edge of its bounding box'
        );
        await select(['star-1']);
        await page.locator('#idstudio-shape-points').fill('7');
        await page.locator('#idstudio-shape-points').press('Tab');
        await ready();
        await page.locator('#idstudio-shadow-enabled').check();
        await ready();
        await action('flip-horizontal');
        await action('flip-vertical');
        let styled = (await objects()).find((o) => o.id === 'star-1');
        check(
            styled.shape.points === 7 && styled.shadow.blur === 1,
            'shape parameters, shadows and flip controls apply'
        );
        await page.evaluate(() =>
            document.querySelector('[data-add-binding-image="student.photo"]').click()
        );
        await page.waitForTimeout(100);
        await ready();
        check(
            await page.evaluate(() => testCanvas.getActiveObject().type === 'studioPhoto'),
            'real image binding renders in a fixed photo frame'
        );
        await page.locator('#idstudio-prop-fit').selectOption('contain');
        await ready();
        let photoBefore = (await objects()).find((o) => o.type === 'image');
        const photoSave = await save();
        check(
            photoSave.documents.front.objects.find((o) => o.id === photoBefore.id).width ===
                photoBefore.width,
            'contain-fit image retains its configured frame on save'
        );
        await save();
        check(true, 'all tools pass the real PHP v2 validator');
        let retryAttempts = 0;
        await page.route('**/fixture/save', async (route) => {
            retryAttempts++;
            if (retryAttempts === 1) {
                await route.fulfill({
                    status: 422,
                    contentType: 'application/json',
                    body: JSON.stringify({message: 'Unexpected validator error.'})
                });
            } else {
                await route.continue();
            }
        });
        await add('diamond');
        const recoveredSave = page.waitForResponse(
            (response) => response.url().endsWith('/fixture/save') && response.status() === 200
        );
        await action('save');
        await recoveredSave;
        await page.waitForFunction(() => !testState.dirty && !testState.savePromise);
        await page.unroute('**/fixture/save');
        check(retryAttempts === 2, 'Save repairs and retries an unexpected validation error');

        await page.evaluate(() => {
            const object = testCanvas.getObjects()[0];
            object.set({left: -1000, top: -1000, scaleX: 50, scaleY: 50});
            object.studioData.untrustedPayload = 'x'.repeat(300000);
            object.setCoords();
            testState.documents.back = 'invalid document';
            document.getElementById('idstudio-title').value = '<b></b>';
            document.querySelector('[data-tool="pen"]').click();
        });
        const cardForIncompletePath = await page.locator('.upper-canvas').boundingBox();
        await page.mouse.click(cardForIncompletePath.x + 80, cardForIncompletePath.y + 80);
        let publishAttempts = 0;
        await page.route('**/fixture/publish', async (route) => {
            publishAttempts++;
            if (publishAttempts === 1) {
                await route.fetch();
                await route.fulfill({
                    status: 500,
                    contentType: 'application/json',
                    body: JSON.stringify({message: 'Response lost after commit.'})
                });
            } else {
                await route.continue();
            }
        });
        page.once('dialog', (dialog) => dialog.accept());
        const publishSave = page.waitForResponse(
            (response) => response.url().endsWith('/fixture/save') && response.status() === 200
        );
        const publishResponse = page.waitForResponse(
            (response) => response.url().endsWith('/fixture/publish') && response.status() === 200
        );
        await action('publish');
        await publishSave;
        await publishResponse;
        await page.waitForFunction(() =>
            document.getElementById('idstudio-save-state').textContent.startsWith('Published')
        );
        await page.unroute('**/fixture/publish');
        check(
            publishAttempts === 2,
            'Publish retry recovers a committed request whose response was lost'
        );
        check(
            await page.evaluate(
                () =>
                    testState.documents.front.objects.every((object) =>
                        SchoolLiftIdCardGeometry.inside(
                            object,
                            testState.widthMm,
                            testState.heightMm
                        )
                    ) &&
                    testState.documents.front.objects.every(
                        (object) => !Object.hasOwn(object, 'untrustedPayload')
                    ) &&
                    Array.isArray(testState.documents.back.objects) &&
                    !testState.drawNodes.length
            ),
            'Publish repairs invalid geometry/data, discards an incomplete point and publishes the saved draft'
        );
        await page.locator('[data-tool="select"]').first().click();
        await page.keyboard.press('Control+a');
        await page.keyboard.press('Delete');
        await ready();
        await add('rect');
        await modify('rect-1', {left: 42.8 * 4, top: 27 * 4});
        for (const zoom of [0.5, 1.25]) {
            await page.locator('#idstudio-zoom').evaluate((el, value) => {
                el.value = value;
                el.dispatchEvent(new Event('input'));
            }, zoom * 100);
            for (const snap of [false, true]) {
                await page
                    .locator('#idstudio-snap-toggle')
                    .evaluate((el, value) => (el.checked = value), snap);
                for (const [dx, dy] of [
                    [-600, 0],
                    [600, 0],
                    [0, -600],
                    [0, 600]
                ]) {
                    await modify('rect-1', {left: 42.8 * 4, top: 27 * 4});
                    const p = await page.evaluate(() => {
                        const r = testCanvas.upperCanvasEl.getBoundingClientRect(),
                            o = testCanvas.getActiveObject(),
                            p = fabric.util.transformPoint(
                                o.getCenterPoint(),
                                testCanvas.viewportTransform
                            );
                        return {x: r.left + p.x, y: r.top + p.y};
                    });
                    await page.mouse.move(p.x, p.y);
                    await page.mouse.down();
                    await page.mouse.move(p.x + dx, p.y + dy, {steps: 8});
                    await page.mouse.up();
                    await ready();
                    assert.ok(await allInside(), 'drag escaped card');
                }
            }
        }
        check(
            true,
            'real pointer drags stop at all four edges at two zooms, with snapping on and off'
        );
        await modify('rect-1', {left: 42.8 * 4, top: 27 * 4});
        const anchor = await page.evaluate(() => {
            const c = testCanvas,
                o = c.getActiveObject();
            c.fire('before:transform', {transform: {target: o}});
            const anchor = {
                x: o.left - (o.width * o.scaleX) / 2,
                y: o.top - (o.height * o.scaleY) / 2
            };
            o.set({
                left: anchor.x + (o.width * 10) / 2,
                top: anchor.y + (o.height * 10) / 2,
                scaleX: 10,
                scaleY: 10
            });
            c.fire('object:scaling', {target: o, transform: {action: 'scale'}});
            const result = {
                before: anchor,
                after: {
                    x: o.left - (o.width * o.scaleX) / 2,
                    y: o.top - (o.height * o.scaleY) / 2
                }
            };
            c.fire('object:modified', {target: o});
            return result;
        });
        await ready();
        check(
            (await allInside()) &&
                Math.abs(anchor.before.x - anchor.after.x) < 0.01 &&
                Math.abs(anchor.before.y - anchor.after.y) < 0.01,
            'oversized resize stops at the boundary and preserves its anchor'
        );
        const angle = await page.evaluate(() => {
            const c = testCanvas,
                o = c.getActiveObject();
            c.fire('before:transform', {transform: {target: o}});
            const old = o.angle;
            o.angle = 90;
            c.fire('object:rotating', {target: o, transform: {action: 'rotate'}});
            const angle = o.angle;
            c.fire('object:modified', {target: o});
            return {old, angle};
        });
        await ready();
        check(
            angle.angle === angle.old && (await allInside()),
            'invalid rotation retains the last valid angle'
        );
        await modify('rect-1', {
            left: 25 * 4,
            top: 25 * 4,
            scaleX: 0.5,
            scaleY: 0.5
        });
        await add('ellipse');
        await modify('ellipse-1', {left: 60 * 4, top: 35 * 4});
        await select(['rect-1']);
        // Actual Shift-click selection determines the reference, not layer order.
        const second = await page.evaluate(() => {
            const r = testCanvas.upperCanvasEl.getBoundingClientRect(),
                o = testCanvas.getObjects().find((o) => o.studioId === 'ellipse-1'),
                p = fabric.util.transformPoint(o.getCenterPoint(), testCanvas.viewportTransform);
            return {x: r.left + p.x, y: r.top + p.y};
        });
        await page.keyboard.down('Shift');
        await page.mouse.click(second.x, second.y);
        await page.keyboard.up('Shift');
        check(
            (await page.locator('#idstudio-reference-label').textContent()) ===
                'Align to: ellipse-1',
            'A then Shift-B makes B the reference'
        );
        await page.mouse.click(second.x, second.y, {button: 'right'});
        check(
            (await page.evaluate(() => testCanvas.getActiveObjects().length)) === 2,
            'right-click inside a selection preserves all selected objects'
        );
        await page.keyboard.press('Escape');
        let before = await objects();
        await command('align-both');
        let after = await objects();
        const b = after.find((o) => o.id === 'ellipse-1'),
            a = after.find((o) => o.id === 'rect-1');
        check(
            b.x === before.find((o) => o.id === b.id).x &&
                b.y === before.find((o) => o.id === b.id).y &&
                Math.abs(a.x - b.x) < 0.001 &&
                Math.abs(a.y - b.y) < 0.001,
            'reference alignment moves A and leaves B stationary'
        );
        await select(['ellipse-1']);
        await action('lock');
        await select(['rect-1']);
        await page.keyboard.down('Shift');
        await page.mouse.click(second.x, second.y);
        await page.keyboard.up('Shift');
        check(
            (await page.evaluate(() => testCanvas.getActiveObjects().length)) === 2 &&
                (await page.locator('#idstudio-reference-label').textContent()) ===
                    'Align to: ellipse-1',
            'Shift-click can select a locked alignment reference'
        );
        await command('align-top');
        check(await allInside(), 'locked objects can serve as alignment references');
        await select(['ellipse-1']);
        await action('lock');
        await select(['rect-1', 'ellipse-1']);
        await action('group');
        await page.keyboard.press('Control+c');
        await page.keyboard.press('Control+v');
        await ready();
        check(
            (await objects()).length === 4 && (await allInside()),
            'group paste retains members and fits the selection as one unit'
        );
        let saved = await save();
        const serialized = JSON.stringify(saved.documents);
        await page.goto(base + '/fixture?reload');
        await ready();
        saved = await save();
        check(
            JSON.stringify(saved.documents) === serialized,
            'grouped positions save and reload without drift'
        );
        await page.keyboard.press('Control+a');
        await page.keyboard.press('Delete');
        await ready();
        await add('star');
        const conversionBefore = await page.evaluate(async () => {
            const c = new fabric.StaticCanvas(document.createElement('canvas'));
            await SchoolLiftIdCardRenderer.render(
                c,
                SchoolLiftIdCardRenderer.toCanonical(testCanvas, 'front', {}),
                {widthMm: 85.6, heightMm: 53.98}
            );
            c.renderAll();
            const url = c.toDataURL();
            c.dispose();
            return url;
        });
        await command('convert-curves');
        const conversionAfter = await page.evaluate(async () => {
            const c = new fabric.StaticCanvas(document.createElement('canvas'));
            await SchoolLiftIdCardRenderer.render(
                c,
                SchoolLiftIdCardRenderer.toCanonical(testCanvas, 'front', {}),
                {widthMm: 85.6, heightMm: 53.98}
            );
            c.renderAll();
            const url = c.toDataURL();
            c.dispose();
            return url;
        });
        check(
            conversionBefore === conversionAfter,
            'shape-to-curve conversion preserves rendered pixels'
        );
        const count = (await objects())[0].nodes.length;
        await command('node-insert');
        await command('node-smooth');
        await command('node-curve');
        await command('node-corner');
        await command('node-straight');
        await command('path-closed');
        check(
            (await objects())[0].nodes.length === count + 1 &&
                (await objects())[0].closed === false,
            'node insertion, segment editing and opening paths work'
        );
        const control = await page.evaluate(() => {
            const r = testCanvas.upperCanvasEl.getBoundingClientRect(),
                p = testCanvas.getActiveObject().oCoords['1-point'];
            return {x: r.left + p.x, y: r.top + p.y};
        });
        await page.mouse.move(control.x, control.y);
        await page.mouse.down();
        await page.mouse.move(control.x + 8, control.y + 10, {steps: 5});
        await page.mouse.up();
        await ready();
        check(await allInside(), 'custom node controls remain inside the physical card');
        await command('node-delete');
        await page.keyboard.press('Control+z');
        await ready();
        check(
            (await objects())[0].nodes.length === count + 1,
            'node command undo is one history step'
        );
        await page.keyboard.press('Escape');
        await add('text');
        await page.locator('#idstudio-prop-text').fill('A very long name '.repeat(20));
        await page.locator('#idstudio-prop-text').press('Tab');
        await ready();
        check(
            await page.evaluate(() => {
                const t = testCanvas.getActiveObject();
                return (
                    t.fittedText.height <= t.height + 0.01 && t.fittedText.width <= t.width + 0.01
                );
            }),
            'long text fits its fixed frame'
        );
        const historyBefore = await page.evaluate(() => testState.history.index);
        await page.locator('#idstudio-prop-text').focus();
        await page.keyboard.press('Control+z');
        check(
            (await page.evaluate(() => testState.history.index)) === historyBefore,
            'typing shortcuts do not undo the document'
        );
        await page.locator('#idstudio-prop-text').press('Tab');
        await ready();
        await page.locator('[data-tool="select"]').first().click();
        await page.keyboard.press('p');
        const card = await page.locator('.upper-canvas').boundingBox();
        await page.mouse.click(card.x + 60, card.y + 70);
        await page.mouse.move(card.x + 130, card.y + 100);
        await page.mouse.down();
        await page.mouse.move(card.x + 150, card.y + 75, {steps: 5});
        await page.mouse.up();
        await page.mouse.click(card.x + 210, card.y + 125);
        await page.keyboard.press('Enter');
        await ready();
        check(
            (await objects()).some((o) => o.id.startsWith('path-') && o.nodes.some((n) => n.out)),
            'pen drag creates editable Bézier handles'
        );
        await page.keyboard.press('Escape');
        await page.evaluate(() => document.querySelector('[data-tool="freehand"]').click());
        await page.mouse.move(card.x + 70, card.y + 80);
        await page.mouse.down();
        for (let i = 0; i < 35; i++) {
            await page.mouse.move(card.x + 70 + i * 3, card.y + 80 + Math.sin(i / 5) * 20);
        }
        await page.mouse.up();
        await page.keyboard.press('Enter');
        await ready();
        check(
            (await objects())
                .filter((o) => o.id.startsWith('path-'))
                .every((o) => o.nodes.length <= 256),
            'freehand simplifies into bounded editable paths'
        );
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => !testState.savePromise);
        const saveStarted = page.waitForRequest((r) => r.url().endsWith('/fixture/save'));
        await action('save');
        await saveStarted;
        await add('diamond');
        await page.waitForFunction(
            () => !testState.dirty && !testState.savePromise,
            {},
            {timeout: 10000}
        );
        await page.goto(base + '/fixture?reload');
        await ready();
        check(
            (await objects()).some((o) => o.type === 'diamond'),
            'edits arriving during save are queued and persisted'
        );
        await action('preset-portrait');
        await page.keyboard.press('Control+z');
        await ready();
        check(
            (await page.evaluate(() => testState.widthMm)) === 85.6,
            'card dimensions are included in document undo'
        );
        for (const actionName of ['export-png', 'export-pdf', 'export-a4']) {
            const downloadPromise = page.waitForEvent('download');
            await action(actionName);
            const download = await downloadPromise;
            assert.equal(await download.failure(), null);
            if (actionName === 'export-pdf') {
                const stream = await download.createReadStream(),
                    chunks = [];
                for await (const chunk of stream) {
                    chunks.push(chunk);
                }
                const pdf = Buffer.concat(chunks).toString('latin1');
                const box = pdf.match(/\/MediaBox\s*\[0 0 ([\d.]+) ([\d.]+)\]/);
                assert.ok(box, 'PDF has physical page dimensions');
                assert.ok(
                    Math.abs((Number(box[1]) * 25.4) / 72 - 85.6) < 0.01 &&
                        Math.abs((Number(box[2]) * 25.4) / 72 - 53.98) < 0.01
                );
            }
        }
        check(true, 'PNG, exact-size PDF and A4 output complete');
        const popupPromise = page.waitForEvent('popup');
        await action('print');
        const popup = await popupPromise;
        await popup.waitForSelector('img');
        check(
            await popup.evaluate(
                () =>
                    opener === null &&
                    document.querySelector('img').style.width === '' &&
                    document.querySelector('style').textContent.includes('85.6mm')
            ),
            'print opens a detached exact-size card window'
        );
        await popup.close();
        // Conflict must not mark pending edits as saved or overwrite another draft.
        await page.waitForFunction(() => !testState.savePromise);
        await add('triangle');
        await page.route('**/fixture/save', (route) =>
            route.fulfill({
                status: 409,
                contentType: 'application/json',
                body: JSON.stringify({
                    message: 'Draft changed. Reload before saving.'
                })
            })
        );
        await action('save');
        await page
            .waitForFunction(
                () => document.getElementById('idstudio-save-state').textContent === 'Save failed',
                {},
                {timeout: 5000}
            )
            .catch(async (error) => {
                console.log(
                    await page.evaluate(() => ({
                        loading: testState.loading,
                        gesture: testState.gesture,
                        nodes: testState.drawNodes.length,
                        saving: !!testState.savePromise,
                        dirty: testState.dirty,
                        status: document.getElementById('idstudio-save-state').textContent,
                        alert: document.getElementById('idstudio-alert').textContent
                    }))
                );
                throw error;
            });
        check(
            (await page.evaluate(() => testState.dirty)) &&
                (await page.locator('#idstudio-alert').textContent()) ===
                    'Draft changed. Reload before saving.',
            'save conflict preserves pending edits and explains recovery'
        );
        await page.unroute('**/fixture/save');
        await save();
        for (const viewport of [
            {width: 768, height: 1024},
            {width: 390, height: 844},
            {width: 320, height: 568}
        ]) {
            await page.setViewportSize(viewport);
            await page.waitForTimeout(300);
            check(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <= innerWidth &&
                        document.querySelector('.idstudio-statusbar').getBoundingClientRect()
                            .bottom <=
                            innerHeight + 1
                ),
                'workspace fits ' + viewport.width + 'px viewport'
            );
            await action('commands');
            check(
                await page.evaluate(() => {
                    const r = document
                        .querySelector('.idstudio-command-menu')
                        .getBoundingClientRect();
                    return (
                        r.left >= 0 &&
                        r.top >= 0 &&
                        r.right <= innerWidth &&
                        r.bottom <= innerHeight
                    );
                }),
                'command menu stays within ' + viewport.width + 'px viewport'
            );
            await page.keyboard.press('Escape');
        }
        await page.screenshot({path: '/tmp/idstudio-mobile.png'});
        await page.setViewportSize({width: 1440, height: 900});
        for (const subject of ['student', 'staff']) {
            await page.goto(base + '/fixture?' + (subject === 'staff' ? 'staff' : ''));
            await ready();
            check(
                (await objects()).length > 5 && (await allInside()),
                subject + ' v1 draft adapts to bounded v2 geometry'
            );
            await save();
            const runtime = await context.newPage();
            runtime.on('pageerror', (error) => errors.push(error.message));
            await runtime.goto(
                base + '/fixture/runtime?reload' + (subject === 'staff' ? '&staff' : '')
            );
            await runtime.waitForFunction(() => window.IDCARD_STUDIO_RENDER_READY === true);
            const qrVisible = await runtime.evaluate(() => {
                const config = ID_CARD_RUNTIME_CONFIG,
                    qr = (config.front.objects || []).find(
                        (object) => object.type === 'qr' && object.binding === 'attendance.credential'
                    );
                if (!qr) return false;
                const scale = SchoolLiftIdCardRenderer.PX_PER_MM,
                    isV2 = Number(config.front.schemaVersion || 1) === 2,
                    left = (isV2 ? qr.x - qr.width / 2 : qr.x) * scale,
                    top = (isV2 ? qr.y - qr.height / 2 : qr.y) * scale,
                    width = Math.max(1, Math.floor(qr.width * scale)),
                    height = Math.max(1, Math.floor(qr.height * scale)),
                    canvas = document.getElementById('studio-card-front-0'),
                    pixels = canvas.getContext('2d').getImageData(
                        Math.max(0, Math.floor(left)),
                        Math.max(0, Math.floor(top)),
                        Math.min(width, canvas.width - Math.max(0, Math.floor(left))),
                        Math.min(height, canvas.height - Math.max(0, Math.floor(top)))
                    ).data;
                let dark = 0, light = 0, count = 0;
                for (let i = 0; i < pixels.length; i += 16) {
                    const luminance = (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
                    if (luminance < 100) dark++;
                    if (luminance > 220) light++;
                    count++;
                }
                return count > 0 && dark / count > 0.05 && light / count > 0.15;
            });
            check(qrVisible, subject + ' attendance QR is visibly rendered in print preview');
            const matches = await runtime.evaluate(async () => {
                const config = ID_CARD_RUNTIME_CONFIG,
                    doc = SchoolLiftIdCardRenderer.clone(config.front),
                    bindings = config.cards[0].bindings;
                doc.objects = doc.objects.filter(
                    (o) => o.type !== 'image' || !o.binding || !!bindings[o.binding]
                );
                const offscreen = new fabric.StaticCanvas(document.createElement('canvas'));
                await SchoolLiftIdCardRenderer.render(offscreen, doc, {
                    widthMm: config.widthMm,
                    heightMm: config.heightMm,
                    bindings,
                    assets: config.assets
                });
                offscreen.renderAll();
                const a = offscreen.toDataURL(),
                    b = document.getElementById('studio-card-front-0').toDataURL();
                offscreen.dispose();
                return a === b;
            });
            check(matches, subject + ' generated cards match shared-renderer preview pixels');
            for (const exportName of ['png', 'exact-pdf', 'a4-pdf']) {
                const d = runtime.waitForEvent('download');
                await runtime.locator('[data-runtime-action="' + exportName + '"]').click();
                assert.equal(await (await d).failure(), null);
            }
            await runtime.close();
        }
        for (const subject of ['student', 'staff']) {
            const missingQr = await context.newPage();
            missingQr.on('pageerror', (error) => errors.push(error.message));
            await missingQr.goto(
                base + '/fixture/runtime?reload&missingqr' + (subject === 'staff' ? '&staff' : '')
            );
            await missingQr.waitForFunction(() => window.IDCARD_STUDIO_RENDER_READY === true);
            check(
                await missingQr.evaluate(() =>
                    !!window.IDCARD_STUDIO_CREDENTIAL_WARNING &&
                    document.getElementById('studio-runtime-credential-warning').offsetParent !== null &&
                    document.querySelector('[data-runtime-action="print"]').disabled
                ),
                subject + ' preview blocks printing when attendance credentials are missing'
            );
            await missingQr.close();
        }
        const legacyQr = await context.newPage();
        legacyQr.on('pageerror', (error) => errors.push(error.message));
        await legacyQr.goto(base + '/fixture/legacy-qr');
        await legacyQr.waitForFunction(() => window.IDCARD_LEGACY_QR_READY === true);
        check(
            await legacyQr.evaluate(() => {
                const holder = document.querySelector('[data-attendance-qr]');
                return holder.getAttribute('data-attendance-qr-rendered') === '1' &&
                    !!holder.querySelector('canvas, img');
            }),
            'legacy student template renders its attendance QR locally'
        );
        await legacyQr.goto(base + '/fixture/legacy-qr?missingqr');
        await legacyQr.waitForFunction(() => !!window.IDCARD_LEGACY_QR_ERROR);
        check(
            await legacyQr.evaluate(() =>
                document.body.classList.contains('legacy-qr-blocked') &&
                !!document.querySelector('.legacy-qr-warning')
            ),
            'legacy student template blocks output when its credential is missing'
        );
        await legacyQr.close();
        const touchContext = await browser.newContext({
            viewport: {width: 390, height: 844},
            hasTouch: true,
            isMobile: true
        });
        const touch = await touchContext.newPage();
        touch.on('pageerror', (error) => errors.push(error.message));
        await touch.route('**/idcard-studio.js*', async (route) => {
            const response = await route.fetch();
            await route.fulfill({
                response,
                body: (await response.text())
                    .replace(
                        'var canvas = new fabric.Canvas',
                        'var canvas = window.testCanvas = new fabric.Canvas'
                    )
                    .replace('var state = {', 'var state = window.testState = {')
            });
        });
        await touch.goto(base + '/fixture?empty');
        await touch.waitForFunction(
            () => document.getElementById('idstudio-app').dataset.studioReady === '1'
        );
        for (const type of ['rect', 'ellipse']) {
            await touch.evaluate(
                (type) => document.querySelector('[data-add="' + type + '"]').click(),
                type
            );
            await touch.waitForTimeout(80);
        }
        await touch.evaluate(() => {
            const c = testCanvas;
            c.discardActiveObject();
            c.getObjects()[0].set({left: 20 * 4, top: 20 * 4});
            c.getObjects()[1].set({left: 60 * 4, top: 35 * 4});
            c.getObjects().forEach((o) => o.setCoords());
            c.requestRenderAll();
        });
        const locations = await touch.evaluate(() => {
            const r = testCanvas.upperCanvasEl.getBoundingClientRect();
            return testCanvas.getObjects().map((o) => {
                const p = fabric.util.transformPoint(
                    o.getCenterPoint(),
                    testCanvas.viewportTransform
                );
                return {x: r.left + p.x, y: r.top + p.y};
            });
        });
        await touch.locator('[data-action="multi-select"]').tap();
        for (const p of locations) {
            await touch.touchscreen.tap(p.x, p.y);
            await touch.waitForTimeout(450);
        }
        check(
            (await touch.evaluate(() => testCanvas.getActiveObjects().length)) === 2,
            'touch multi-select selects two independent objects'
        );
        await touch.locator('[data-action="actions"]').tap();
        await touch.locator('[data-command="align-center"]').tap();
        await touch.waitForFunction(() => !testState.loading);
        check(
            await touch.evaluate(() => {
                const os = SchoolLiftIdCardRenderer.toCanonical(testCanvas, 'front', {}).objects;
                return Math.abs(os[0].x - os[1].x) < 0.001;
            }),
            'touch Actions aligns to the last selected reference'
        );
        await touch.locator('[data-tool="pan"]').first().tap();
        await touch.evaluate(() => {
            const el = document.getElementById('idstudio-zoom');
            el.value = 250;
            el.dispatchEvent(new Event('input'));
            document.getElementById('idstudio-scroll').scrollLeft = 0;
        });
        const dragArea = await touch.locator('#idstudio-scroll').boundingBox(),
            cdp = await touchContext.newCDPSession(touch);
        await cdp.send('Input.dispatchTouchEvent', {
            type: 'touchStart',
            touchPoints: [{x: dragArea.x + 200, y: dragArea.y + 80}]
        });
        await cdp.send('Input.dispatchTouchEvent', {
            type: 'touchMove',
            touchPoints: [{x: dragArea.x + 80, y: dragArea.y + 80}]
        });
        await cdp.send('Input.dispatchTouchEvent', {
            type: 'touchEnd',
            touchPoints: []
        });
        check(
            (await touch.evaluate(() => document.getElementById('idstudio-scroll').scrollLeft)) > 0,
            'touch Pan scrolls the design without scrolling the page'
        );
        await touch.locator('[data-panel-toggle="properties"]').tap();
        await touch.waitForTimeout(250);
        check(
            await touch.locator('#idstudio-properties-panel [data-panel-dismiss]').isVisible(),
            'mobile property sheet keeps its close control accessible'
        );
        await touchContext.close();
        check(errors.length === 0, 'no browser JavaScript errors: ' + errors.join('; '));
        console.log(passed + ' browser checks passed');
    } finally {
        await browser.close();
    }
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
