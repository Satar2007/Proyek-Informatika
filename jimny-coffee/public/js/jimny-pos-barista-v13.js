/* JIMNY COFFEE | Barista Flow V13 - visual enhancements only.
   The Laravel/Alpine cart is the single source of truth. Nothing here
   creates orders, changes quantities, reads payment responses or modifies stock. */
(() => {
    'use strict';
    if (!document.body.classList.contains('pos-screen')) return;
    const workspace = document.querySelector('.pos-workspace');
    const cart = workspace?.querySelector('#order-cart');
    if (!workspace || !cart) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const supportsMotion = () => !reduceMotion.matches && typeof Element.prototype.animate === 'function';
    const running = new Set();
    const bubbles = new Set();
    let receivedTimeout = 0;

    function motion(el, frames, options) {
        if (!el || !supportsMotion()) return;
        const animation = el.animate(frames, options);
        running.add(animation);
        animation.finished.then(() => running.delete(animation), () => running.delete(animation));
        return animation;
    }
    function finishAfter(el, animation) {
        bubbles.add(el);
        const cleanup = () => { el.remove(); bubbles.delete(el); };
        if (animation) animation.finished.then(cleanup, cleanup);
        else cleanup();
    }
    function countItems() {
        const label = cart.querySelector(':scope > div:first-child p span:first-child');
        const value = Number.parseInt(label?.textContent?.trim() || '', 10);
        return Number.isFinite(value) ? value : null;
    }
    function cartGlyph() {
        return cart.querySelector(':scope > div:first-child svg') || cart.querySelector('h2');
    }
    function destination() {
        const glyph = cartGlyph();
        if (glyph && glyph.getBoundingClientRect().width) return glyph;
        return workspace.querySelector('.mobile-cart-link') || cart;
    }
    function receive() {
        cart.classList.remove('barista-cart-received');
        // Reflow makes repeated adds visible without delaying the cart.
        void cart.offsetWidth;
        cart.classList.add('barista-cart-received');
        window.clearTimeout(receivedTimeout);
        receivedTimeout = window.setTimeout(() => cart.classList.remove('barista-cart-received'), 420);
        motion(cartGlyph(), [
            { transform:'scale(1)' },
            { transform:'scale(1.17) rotate(-6deg)', offset: .52 },
            { transform:'scale(1)' }
        ], { duration:340, easing:'cubic-bezier(.2,.9,.2,1)' });
    }
    function flowAccent(source) {
        if (!supportsMotion()) return;
        const target = destination();
        if (!source || !target) return;
        const s = source.getBoundingClientRect();
        const t = target.getBoundingClientRect();
        if (!s.width || !t.width || t.bottom < 0 || t.top > window.innerHeight ||
            s.bottom < 0 || s.top > window.innerHeight) return;
        const x = s.right - 20, y = s.top + Math.min(s.height / 2, 80);
        const endX = t.left + t.width / 2, endY = t.top + t.height / 2;
        // Existing pos-motion.js renders the main flight; these are only
        // small caramel reflections that follow behind it.
        for (const i of [0, 1]) {
            const dot = document.createElement('span');
            dot.className = 'barista-flight-dot';
            dot.setAttribute('aria-hidden','true');
            dot.style.left = `${x}px`;
            dot.style.top = `${y}px`;
            document.body.append(dot);
            const dx = endX - x, dy = endY - y;
            const drift = i === 0 ? -15 : 14;
            const animation = motion(dot, [
                { transform:'translate(0,0) scale(.85)', opacity: 0, offset: 0 },
                { transform:`translate(${dx*.42}px,${dy*.44 + drift}px) scale(1)`, opacity: .95, offset: .40 },
                { transform:`translate(${dx}px,${dy}px) scale(.22)`, opacity: 0, offset: 1 }
            ], { duration: 510, delay:i * 75, easing:'cubic-bezier(.30,.20,.25,1)', fill:'forwards' });
            finishAfter(dot, animation);
        }
    }

    // Only animate a successfully increased cart count. In particular, a
    // blocked add at stock limit will NOT fly or show false success.
    workspace.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const card = target.closest('.product-card[data-product-id]');
        if (card && !card.disabled) {
            const before = countItems();
            // Alpine updates the x-text binding in its next microtask.
            window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
                const after = countItems();
                if (before !== null && after !== null && after > before) {
                    flowAccent(card);
                    receive();
                }
            }));
            return;
        }
        const category = target.closest('.category-tabs button');
        if (category) {
            motion(category, [
                { transform:'scale(1)' },
                { transform:'scale(.94)', offset: .42 },
                { transform:'scale(1)' }
            ], { duration:220, easing:'cubic-bezier(.2,.8,.2,1)' });
            return;
        }
        const checkout = target.closest('.cart-body .grid.grid-cols-2 button');
        if (checkout && !checkout.disabled) {
            // Feedback here means "button pressed", never "paid".
            motion(checkout, [
                { transform:'scale(1)' },
                { transform:'scale(.965)', offset: .45 },
                { transform:'scale(1)' }
            ], { duration:250, easing:'cubic-bezier(.2,.8,.2,1)' });
        }
    }, { passive:true });

    // Cart row quantities remain controlled by Alpine; animation observes
    // the rendered result rather than altering the application's cart data.
    const itemsPanel = cart.querySelector('.cart-items');
    if (itemsPanel) {
        const known = new WeakMap();
        let scheduled = false;
        const update = () => {
            scheduled = false;
            for (const row of itemsPanel.querySelectorAll('.cart-line')) {
                const quantity = row.querySelector('.w-7');
                if (!quantity) continue;
                const value = quantity.textContent.trim();
                const previous = known.get(row);
                known.set(row, value);
                if (previous !== undefined && previous !== value) {
                    motion(quantity, [
                        { transform:'scale(1)', opacity:1 },
                        { transform:'scale(1.22)', opacity:.78, offset:.5 },
                        { transform:'scale(1)', opacity:1 }
                    ], { duration:260, easing:'cubic-bezier(.2,.8,.2,1)' });
                }
            }
        };
        new MutationObserver(() => {
            if (scheduled) return;
            scheduled = true;
            window.requestAnimationFrame(update);
        }).observe(itemsPanel, { childList:true, subtree:true, characterData:true });
        update();
    }
    reduceMotion.addEventListener?.('change', () => {
        if (!reduceMotion.matches) return;
        running.forEach(a => a.cancel());
        running.clear();
        bubbles.forEach(el => el.remove());
        bubbles.clear();
    });
})();
