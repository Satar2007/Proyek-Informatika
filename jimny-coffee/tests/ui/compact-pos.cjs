const fs = require('fs');
const vm = require('vm');
const path = require('path');
const assert = require('assert/strict');

const source = fs
    .readFileSync(
        path.join(
            __dirname,
            '../../resources/views/kasir/index.blade.php'
        ),
        'utf8'
    )
    .split('<script>')[1]
    .split('</script>')[0]
    .replace(/\{\{.*?\}\}/g, 'null');

const alerts = [];

const context = {
    setTimeout: () => 1,
    clearTimeout: () => {},
    window: {
        matchMedia: () => ({ matches: true })
    },
    Swal: {
        fire: (...args) => alerts.push(args)
    }
};

vm.createContext(context);
vm.runInContext(source, context);

const app = context.kasirApp();

app.menuIndex = Array.from(
    { length: 42 },
    (_, i) => ({
        id: i + 1,
        name: i === 0 ? "chef's latte" : 'menu ' + i,
        category: i < 21 ? '1' : '2'
    })
);


// ============================================================
// TEST 1
// Large desktop layout
// 1530 x 700
// Current algorithm:
// max 5 columns
// available height gives 2 rows
// pageSize = 10
// ============================================================

context.window.matchMedia = () => ({ matches: true });

app.$refs = {
    productGrid: {
        clientWidth: 1530,
        clientHeight: 700
    }
};

app.page = 1;
app.fitCatalog();

assert.equal(app.gridColumns, 5);
assert.equal(app.gridRows, 2);
assert.equal(app.pageSize, 10);
assert.equal(app.pageCount, 5);
assert.equal(app.visibleMenuIds.length, 10);


// ============================================================
// TEST 2
// Tablet / sub-1024 layout
// Current non-desktop rule:
// width > 650 => 3 columns
// rows = 3
// pageSize = 9
// ============================================================

context.window.matchMedia = () => ({ matches: false });

app.$refs = {
    productGrid: {
        clientWidth: 950,
        clientHeight: 430
    }
};

app.page = 1;
app.fitCatalog();

assert.equal(app.gridColumns, 3);
assert.equal(app.gridRows, 3);
assert.equal(app.pageSize, 9);
assert.equal(app.pageCount, 5);


// ============================================================
// TEST 3
// Pagination must expose all 42 products exactly once
// ============================================================

const ids = [];

for (let i = 1; i <= app.pageCount; i++) {
    app.page = i;
    ids.push(...app.visibleMenuIds);
}

assert.equal(
    new Set(ids).size,
    42
);

assert.equal(
    ids.length,
    42
);


// ============================================================
// TEST 4
// Search is case-insensitive
// ============================================================

app.page = 1;
app.search = "CHEF'S";

assert.equal(
    app.filteredMenus.length,
    1
);

assert.equal(
    app.visibleMenuIds[0],
    1
);


// ============================================================
// TEST 5
// Category filter
// ============================================================

app.search = '';
app.selectedCategory = '2';

assert.equal(
    app.filteredMenus.length,
    21
);


// ============================================================
// TEST 6
// Empty search state
// ============================================================

app.search = 'not a menu';

assert.equal(
    app.hasVisibleMenus(),
    false
);

assert.equal(
    app.pageCount,
    1
);


// ============================================================
// TEST 7
// Cart stock limit
// ============================================================

app.search = '';
app.selectedCategory = '';

app.tambahCart(
    1,
    "Chef's latte",
    10000,
    2
);

app.tambahQty(1);
app.tambahQty(1);

assert.equal(
    app.cart[0].qty,
    2
);

assert.equal(
    alerts.length,
    1
);


// ============================================================
// TEST 8
// Calculation
// ============================================================

assert.equal(
    app.subtotal,
    20000
);

assert.equal(
    app.pajak,
    600
);

assert.equal(
    app.grandTotal,
    20600
);


// ============================================================
// TEST 9
// Reduce quantity
// ============================================================

app.kurangiQty(1);

assert.equal(
    app.cart[0].qty,
    1
);

assert.equal(
    app.recentItem,
    1
);


// ============================================================
// TEST 10
// Remove cart item
// ============================================================

app.hapusItem(1);

assert.equal(
    app.cart.length,
    0
);


console.log(
    'PASS: responsive POS pagination, all 42 products, search, category, empty state, stock limit, cart quantity, subtotal, tax, and grand total.'
);
