/* Motion enhancement only. Transactions never wait for animations. */
(() => {
    'use strict';
    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const running = new Set();
    const particles = new Set();
    const canAnimate = () => !preference.matches && typeof Element.prototype.animate === 'function';
    function animate(element, frames, options) {
        if (!element || !canAnimate()) return null;
        const animation = element.animate(frames, { duration: 360, easing: 'cubic-bezier(.2,.8,.2,1)', ...options });
        running.add(animation);
        animation.finished.then(() => running.delete(animation), () => running.delete(animation));
        return animation;
    }
    function temporary(element, animation) {
        particles.add(element);
        while (particles.size > 8) {
            const oldest = particles.values().next().value;
            oldest.getAnimations().forEach(a => a.cancel());
            oldest.remove(); particles.delete(oldest);
        }
        const remove = () => { element.remove(); particles.delete(element); };
        if (animation) animation.finished.then(remove, remove); else remove();
    }
    function flyToCart(source, target) {
        if (!source || !target || !canAnimate()) return;
        const from = source.getBoundingClientRect();
        const to = target.getBoundingClientRect();
        // An offscreen cart receives a local confirmation instead of a distracting flight.
        if (to.top >= innerHeight || to.bottom <= 0) {
            animate(source, [{transform:'scale(.97)'},{transform:'scale(1.025)'},{transform:'scale(1)'}], {duration:270});
            return;
        }
        const startX = from.right - 25, startY = from.bottom - 22;
        const endX = Math.min(innerWidth - 24, to.right - 36), endY = Math.max(30, to.top + 30);
        const dx = endX - startX, dy = endY - startY;
        const dot = document.createElement('span');
        dot.className = 'motion-flight'; dot.textContent = '+'; dot.setAttribute('aria-hidden','true');
        dot.style.left = `${startX - 15}px`; dot.style.top = `${startY - 15}px`;
        document.body.append(dot);
        const flight = animate(dot, [
            {transform:'translate(0,0) scale(1)',opacity:1,offset:0},
            {transform:`translate(${dx * .45}px,${dy * .45 - 65}px) scale(.9)`,opacity:1,offset:.45},
            {transform:`translate(${dx}px,${dy}px) scale(.25)`,opacity:0,offset:1}
        ], {duration:530,easing:'cubic-bezier(.3,.1,.3,1)'});
        temporary(dot, flight);
        flight?.finished.then(() => animate(target.querySelector('h2'), [
            {transform:'scale(1)'},{transform:'scale(1.07)',color:'#b8621b'},{transform:'scale(1)'}
        ], {duration:240}), () => {});
    }
    window.PosMotion = Object.freeze({ flyToCart });
    function start() {
        const heading = document.querySelector('.page-lead, .page-heading, .pos-compact-heading');
        animate(heading, [{opacity:0,transform:'translateY(10px)'},{opacity:1,transform:'translateY(0)'}],{duration:450});
        const surfaces = document.querySelectorAll('.login-card, .metric-card, .analytics-grid>.panel, main>.space-y-8>.grid>div');
        surfaces.forEach((el,i) => animate(el,[{opacity:0,transform:'translateY(14px)'},{opacity:1,transform:'translateY(0)'}],{duration:420,delay:Math.min(i*45,240)}));
        const grid = document.querySelector('.product-grid');
        if (grid) {
            let visible = new Set(), frame = 0;
            const reveal = () => {
                frame = 0;
                const cards = [...grid.querySelectorAll('.product-card')].filter(el => el.getClientRects().length);
                let index = 0;
                for (const el of cards) {
                    if (!visible.has(el)) animate(el,[{opacity:.25,transform:'translateY(9px) scale(.98)'},{opacity:1,transform:'translateY(0) scale(1)'}],{duration:310,delay:Math.min(index++ * 14,220)});
                }
                visible = new Set(cards);
            };
            const observer = new MutationObserver(() => { if (!frame) frame = requestAnimationFrame(reveal); });
            observer.observe(grid,{subtree:true,attributes:true,attributeFilter:['style']});
            requestAnimationFrame(reveal);
        }
        const totals = document.querySelector('[data-motion-total]');
        if (totals) {
            let previous = totals.textContent, frame = 0;
            new MutationObserver(() => {
                if (frame || previous === totals.textContent) return;
                previous = totals.textContent;
                frame = requestAnimationFrame(() => {
                    frame = 0;
                    const amount = totals.querySelector('.text-2xl');
                    amount?.getAnimations().forEach(a=>a.cancel());
                    animate(amount,[{opacity:.45,transform:'translateY(5px)',filter:'blur(1px)'},{opacity:1,transform:'translateY(0)',filter:'blur(0)'}],{duration:260});
                });
            }).observe(totals,{subtree:true,characterData:true,childList:true});
        }
        if ('IntersectionObserver' in window) {
            const charts = new IntersectionObserver(entries => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    const el = entry.target;
                    if (el.matches('polyline')) {
                        const length = el.getTotalLength();
                        animate(el,[{strokeDasharray:`${length}`,strokeDashoffset:length},{strokeDasharray:`${length}`,strokeDashoffset:0}],{duration:850});
                    } else animate(el,[{transform:'scaleX(0)',transformOrigin:'left'},{transform:'scaleX(1)',transformOrigin:'left'}],{duration:650});
                    charts.unobserve(el);
                }
            },{threshold:.15});
            document.querySelectorAll('.trend-chart polyline, .bar-fill').forEach(el=>charts.observe(el));
        }
        document.addEventListener('pointerdown', event => {
            if (!canAnimate() || event.button !== 0) return;
            const button = event.target.closest('.product-card, .primary-action, .category-tabs button');
            if (!button || button.disabled) return;
            const ripple = document.createElement('span');
            ripple.className='motion-ripple'; ripple.setAttribute('aria-hidden','true');
            ripple.style.left=`${event.clientX - 16}px`;ripple.style.top=`${event.clientY - 16}px`;
            document.body.append(ripple);
            temporary(ripple,animate(ripple,[{transform:'scale(.25)',opacity:.8},{transform:'scale(2.6)',opacity:0}],{duration:380}));
        },{passive:true});
    }
    preference.addEventListener('change', () => {
        if (preference.matches) {
            running.forEach(a=>a.cancel()); running.clear();
            particles.forEach(el=>el.remove()); particles.clear();
        }
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true}); else start();
})();
