const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(process.env.KKK_PHOTOSHOP_SCRIPT || path.join(__dirname, '../../photoshop/auto_kad_full_qr_patched_v35.jsx'), 'utf8');
const context = vm.createContext({
    UnitValue: function (value) { return value; },
    _boundsPx: layer => layer.bounds,
});
for (const name of ['kkkCoverGeometry', 'kkkContainGeometry', 'kkkVisiblePhotoMaskBox', 'kkkPositionPhoto']) {
    const start = source.indexOf('function ' + name + '(');
    assert.ok(start >= 0, name + ' exists');
    const end = source.indexOf('\nfunction ', start + 1);
    vm.runInContext(source.slice(start, end < 0 ? undefined : end), context);
}

test('main photo fits fully while its fixed fill covers wide and tall mask areas', () => {
    for (const [width, height] of [[4000, 2000], [2000, 4000], [3000, 3000]]) {
        for (const box of [{x: 0, y: 0, w: 2667, h: 2700}, {x: 0, y: 569, w: 1200, h: 1831}]) {
            const main = context.kkkContainGeometry(width, height, box);
            const fill = context.kkkCoverGeometry(width, height, box, 0.5, 0.5);
            assert.ok(main.w <= box.w * 0.9 + 0.001);
            assert.ok(main.h <= box.h * 0.9 + 0.001);
            assert.ok(fill.w >= box.w - 0.001 && fill.h >= box.h - 0.001);
            assert.ok(Math.abs(main.w / main.h - width / height) < 0.001);
            for (const x of [0, 0.5, 1]) {
                for (const y of [0, 0.5, 1]) {
                    const moved = context.kkkPositionPhoto({translate() {}}, box, x, y, {...main, x: box.x, y: box.y});
                    assert.ok(moved.x >= box.x - 0.001 && moved.y >= box.y - 0.001);
                    assert.ok(moved.x + moved.w <= box.x + box.w + 0.001);
                    assert.ok(moved.y + moved.h <= box.y + box.h + 0.001);
                    assert.ok(fill.x <= box.x + 0.001 && fill.y <= box.y + 0.001);
                    assert.ok(fill.x + fill.w >= box.x + box.w - 0.001);
                    assert.ok(fill.y + fill.h >= box.y + box.h - 0.001);
                }
            }
        }
    }
});

test('mask bounds use only the visible canvas instead of off-canvas brush pixels', () => {
    const doc = {width: {as: () => 5400}, height: {as: () => 2700}};
    const box = context.kkkVisiblePhotoMaskBox(doc, {bounds: {x: -4, y: -273, w: 2671, h: 3528}});
    assert.equal(box.x, 0);
    assert.equal(box.y, 0);
    assert.equal(box.w, 2667);
    assert.equal(box.h, 2700);
    assert.throws(() => context.kkkVisiblePhotoMaskBox(doc, {bounds: {x: 6000, y: 0, w: 10, h: 10}}));
});

test('invalid image sizes fail instead of producing an uncovered mask', () => {
    assert.throws(() => context.kkkContainGeometry(0, 2000, {w: 100, h: 100}));
    assert.throws(() => context.kkkCoverGeometry(100, 100, {w: 0, h: 100}, 0.5, 0.5));
});

