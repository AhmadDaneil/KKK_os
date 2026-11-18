const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(process.env.KKK_PHOTOSHOP_SCRIPT || path.join(__dirname, '../../photoshop/auto_kad_full_qr_patched_v35.jsx'), 'utf8');
const context = vm.createContext({
    app: {}, LayerKind: {TEXT: 'text'}, AnchorPosition: {MIDDLECENTER: 0},
    UnitValue: function (value) { return value; }, t: value => String(value).trim(),
    findLayerByNames: (doc, names) => doc.layers.find(layer => names.includes(layer.name)),
    kkkWalkLayers: (doc, visitor) => doc.layers.forEach(visitor),
    kkkEffectiveVisible: layer => layer.visible !== false,
    _boundsPx: layer => ({...layer.box, cx: layer.box.x + layer.box.w / 2, cy: layer.box.y + layer.box.h / 2}),
});
for (const name of ['kkkPairNameSlots', 'kkkAlignProductNamesToAmpersand']) {
    const start = source.indexOf('function ' + name + '(');
    assert.ok(start >= 0);
    const end = source.indexOf('\nfunction ', start + 1);
    vm.runInContext(source.slice(start, end < 0 ? undefined : end), context);
}
function layer(name, box, visible = true) {
    return {name, box: {...box}, visible, kind: 'text',
        translate(x, y) { this.box.x += x; this.box.y += y; },
        resize(x, y) {
            const width = this.box.w * x / 100, height = this.box.h * y / 100;
            this.box.x += (this.box.w - width) / 2;
            this.box.y += (this.box.h - height) / 2;
            this.box.w = width; this.box.h = height;
        },
    };
}
test('short and long names share equal gaps and vertical centers without moving the original ampersand', () => {
    for (const banner of [true, false]) {
        for (const width of [200, 1600]) {
            const amp = layer('&', {x: 3700, y: 1000, w: 350, h: 500});
            const original = {...amp.box};
            const left = layer('singkatanlelaki', {x: 2500, y: 700, w: width, h: 300});
            const right = layer('singkatanperempuan', {x: 4200, y: 1500, w: width * 0.8, h: 250});
            const hiddenAmp = layer('&', {x: 0, y: 0, w: 100, h: 100}, false);
            const doc = {width: {as: () => 5400}, layers: [hiddenAmp, left, right, amp]};
            assert.equal(context.kkkAlignProductNamesToAmpersand(doc, banner, {cy: 1000}, {cy: 1500}), true);
            assert.deepEqual(amp.box, original);
            const gapLeft = amp.box.x - left.box.x - left.box.w;
            const gapRight = right.box.x - amp.box.x - amp.box.w;
            assert.ok(Math.abs(gapLeft - gapRight) < 0.001);
            for (const name of [left, right]) {
                assert.ok(Math.abs(name.box.y + name.box.h / 2 - 1250) < 0.001);
                assert.ok(name.box.x >= 0 && name.box.x + name.box.w <= 5400);
            }
        }
    }
});
test('single-name templates remain unchanged when no ampersand exists', () => {
    const left = layer('singkatanlelaki', {x: 20, y: 30, w: 100, h: 50});
    const before = {...left.box};
    assert.equal(context.kkkAlignProductNamesToAmpersand({layers: [left]}, false), false);
    assert.deepEqual(left.box, before);
});

