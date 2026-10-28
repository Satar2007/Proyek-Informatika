/* JIMNY 11.1 - Latte Light / Midnight Espresso: no external dependencies. */
(function () {
    'use strict';
    var KEY = 'jimny-theme-preference';
    var doc = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    function stored() {
        try { var v = localStorage.getItem(KEY); return v === 'light' || v === 'dark' ? v : null; }
        catch (e) { return null; }
    }
    function current() { return stored() || (media && media.matches ? 'dark' : 'light'); }
    function syncButtons() {
        var isDark = doc.getAttribute('data-theme') === 'dark';
        document.querySelectorAll('[data-jimny-theme-toggle]').forEach(function (b) {
            b.setAttribute('aria-checked', String(isDark));
            b.setAttribute('aria-label', isDark ? 'Gunakan mode terang' : 'Gunakan mode gelap');
            b.title = isDark ? 'Ganti ke Latte Light' : 'Ganti ke Midnight Espresso';
            var label = b.querySelector('[data-jimny-theme-label]');
            if (label) label.textContent = isDark ? 'Gelap' : 'Terang';
        });
    }
    function setTheme(value) {
        doc.setAttribute('data-theme', value);
        doc.style.colorScheme = value;
        if (document.body) syncButtons();
    }
    setTheme(current()); // Runs in <head> before content is rendered.
    function persist(value) {
        try { localStorage.setItem(KEY, value); } catch (e) { /* private mode */ }
        if (typeof window.JimnyV12ThemeMotion === 'function') {
            window.JimnyV12ThemeMotion(value, setTheme);
        } else {
            setTheme(value);
        }
    }
    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('[data-jimny-theme-toggle]') : null;
        if (button) { persist(doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); }
        var reveal = event.target && event.target.closest ? event.target.closest('[data-jimny-reveal-password]') : null;
        if (reveal) {
            var input = document.getElementById('password');
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            reveal.textContent = show ? 'Sembunyi' : 'Lihat';
            reveal.setAttribute('aria-pressed', String(show));
            reveal.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        }
    });
    document.addEventListener('DOMContentLoaded', function () {
        syncButtons();
        var password = document.getElementById('password');
        var capsHint = document.querySelector('[data-jimny-caps-hint]');
        if (password && capsHint) {
            function checkCaps(event) {
                capsHint.hidden = !(event.getModifierState && event.getModifierState('CapsLock'));
            }
            password.addEventListener('keydown', checkCaps);
            password.addEventListener('keyup', checkCaps);
            password.addEventListener('blur', function () { capsHint.hidden = true; });
        }
        var form = document.getElementById('jimny-login-form');
        if (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('[data-jimny-login-submit]');
                var label = form.querySelector('[data-jimny-login-label]');
                if (button) { button.disabled = true; button.setAttribute('aria-busy','true'); }
                if (label) label.textContent = 'Memproses...';
            });
        }
    });
    window.addEventListener('storage', function (event) {
        if (event.key === KEY) setTheme(current());
    });
    if (media) {
        var onChange = function () { if (!stored()) setTheme(current()); };
        if (media.addEventListener) media.addEventListener('change', onChange);
        else if (media.addListener) media.addListener(onChange);
    }
})();
