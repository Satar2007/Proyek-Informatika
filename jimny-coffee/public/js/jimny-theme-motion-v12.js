/* JIMNY V12 theme morph — presentation only. No POS/API/ML logic. */
(function () {
    'use strict';
    var root = document.documentElement;
    var lastOrigin = null;
    var activeTransition = null;
    var cleaning = null;
    var durations = { kasir: 390, admin: 550, owner: 670, guest: 500 };

    function captureOrigin(event) {
        var target = event.target;
        var toggle = target && target.closest ? target.closest('[data-jimny-theme-toggle]') : null;
        if (!toggle) return;
        var rect = toggle.getBoundingClientRect();
        lastOrigin = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
    }
    // Capture before the original V11 document click handler.
    document.addEventListener('pointerdown', captureOrigin, true);
    document.addEventListener('click', captureOrigin, true);

    function roleOfPage() {
        var dataRole = document.body && document.body.getAttribute('data-jimny-v12-role');
        if (dataRole === 'admin' || dataRole === 'owner' || dataRole === 'kasir') return dataRole;
        return 'guest';
    }
    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }
    function clean() {
        if (cleaning) { clearTimeout(cleaning); cleaning = null; }
        root.classList.remove('jimny-v12-animating');
        root.classList.remove('jimny-v12-fallback');
        root.removeAttribute('data-jimny-v12-role');
        activeTransition = null;
    }
    function transitionTheme(nextTheme, applyTheme) {
        if (typeof applyTheme !== 'function') return;
        if (!document.body || prefersReducedMotion() || document.visibilityState === 'hidden') {
            applyTheme(nextTheme);
            return;
        }
        if (root.getAttribute('data-theme') === nextTheme) {
            applyTheme(nextTheme);
            return;
        }
        if (activeTransition && typeof activeTransition.skipTransition === 'function') {
            try { activeTransition.skipTransition(); } catch (e) { /* interrupted transition */ }
        }
        clean();

        var role = roleOfPage();
        var time = durations[role];
        var x = lastOrigin ? lastOrigin.x : window.innerWidth / 2;
        var y = lastOrigin ? lastOrigin.y : 65;
        lastOrigin = null;
        var radius = Math.ceil(Math.max(
            Math.hypot(x, y),
            Math.hypot(window.innerWidth - x, y),
            Math.hypot(x, window.innerHeight - y),
            Math.hypot(window.innerWidth - x, window.innerHeight - y)
        )) + 8;
        root.style.setProperty('--jimny-v12-x', x.toFixed(1) + 'px');
        root.style.setProperty('--jimny-v12-y', y.toFixed(1) + 'px');
        root.style.setProperty('--jimny-v12-radius', radius + 'px');
        root.style.setProperty('--jimny-v12-time', time + 'ms');
        root.setAttribute('data-jimny-v12-role', role);
        root.classList.add('jimny-v12-animating');

        if (typeof document.startViewTransition === 'function') {
            try {
                var vt = document.startViewTransition(function () { applyTheme(nextTheme); });
                activeTransition = vt;
                vt.finished.then(function () { if (activeTransition === vt) clean(); },
                    function () { if (activeTransition === vt) clean(); });
                // Defensive cleanup in unusual browser failure states.
                cleaning = setTimeout(function () { if (activeTransition === vt) clean(); }, time + 1200);
                return;
            } catch (error) {
                // Browser does not support this page's view-transition state.
            }
        }
        root.classList.add('jimny-v12-fallback');
        // Ensure fallback transitions are registered before colors change.
        void document.body.offsetWidth;
        applyTheme(nextTheme);
        cleaning = setTimeout(clean, time + 100);
    }
    window.JimnyV12ThemeMotion = transitionTheme;
})();
