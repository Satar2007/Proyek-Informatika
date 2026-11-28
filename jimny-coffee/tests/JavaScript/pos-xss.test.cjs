// Exercises the actual POS script with mock browser/API/Swal boundaries.
// No database, browser, network, or real payment is used.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/kasir/index.blade.php'), 'utf8');
const match = blade.match(/<script>\s*(function kasirApp\(\)\s*\{[\s\S]*?)<\/script>/);
assert.ok(match, 'POS script must exist');
// Replace only the known server-rendered catalog and route placeholders.
const script = match[1]
    .replace(/menuIndex: \{\{[^\r\n]+\}\},/, 'menuIndex: [],')
    .replace(/\{\{\s*route\("([a-z.-]+)"\)\s*\}\}/g, '/mock/$1');
assert.ok(!script.includes('{{'), 'Unexpected Blade expression: update the fixture');

const probe = `<b data-a="x">R&D's Latte</b>`;
const encoded = '&lt;b data-a=&quot;x&quot;&gt;R&amp;D&#39;s Latte&lt;/b&gt;';

function fixture({ success = false, answer = { isConfirmed: true, value: 20000 } } = {}) {
    const calls = [];
    const context = vm.createContext({
        Swal: { fire: async (...args) => { calls.push(args); return answer; } },
        fetch: async () => ({ json: async () => ({ success, message: probe }) }),
        document: { querySelector: () => ({ content: 'dummy-csrf' }) },
        console,
    });
    const app = vm.runInContext(script + '\nkasirApp()', context);
    app.cart = [{ id: 1, nama: probe, harga: 10000, qty: 1, stok: 2 }];
    app.namaPelanggan = probe;
    return { app, calls };
}

function assertTextMessage(calls, icon) {
    const args = calls.at(-1);
    assert.equal(args.length, 1, 'Dynamic message must use named options');
    assert.equal(args[0].text, probe);
    assert.equal(args[0].icon, icon);
    assert.equal(Object.hasOwn(args[0], 'html'), false);
}

test('remove item encodes menu name in confirmation and keeps success message as text', async () => {
    const { app, calls } = fixture();
    await app.konfirmasiHapusItem(app.cart[0]);
    assert.ok(calls[0][0].html.includes(encoded));
    assert.ok(!calls[0][0].html.includes(probe));
    assert.equal(app.cart.length, 0);
    assert.ok(calls[1][0].text.includes(probe));
});

test('cancel remove item leaves cart unchanged', async () => {
    const { app, calls } = fixture({ answer: { isConfirmed: false } });
    await app.konfirmasiHapusItem(app.cart[0]);
    assert.ok(calls[0][0].html.includes(encoded));
    assert.equal(app.cart.length, 1);
});

for (const method of ['clockIn', 'clockOut', 'ajukanIzin']) {
    for (const success of [true, false]) {
        test(`${method} ${success ? 'success' : 'failure'} message uses text`, async () => {
            const { app, calls } = fixture({ success, answer: { isConfirmed: true, value: 'Dummy reason' } });
            await app[method]();
            assertTextMessage(calls, success ? 'success' : 'error');
        });
    }
}

for (const method of ['cash', 'qris']) {
    test(`${method} customer name is encoded and checkout error uses text`, async () => {
        const { app, calls } = fixture();
        await app.prosesCheckout(method);
        assert.ok(calls[0][0].html.includes(encoded));
        assert.ok(!calls[0][0].html.includes(probe));
        assertTextMessage(calls, 'error');
        assert.equal(app.cart.length, 1);
    });
}
