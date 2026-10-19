(() => {
  'use strict';
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const overlay = () => document.getElementById('page-transition');
  function isNavigable(a) {
    if (!a || reduce) return false;
    if (a.target && a.target !== '_self') return false;
    if (a.hasAttribute('download')) return false;
    const href = a.href;
    if (!href || href.startsWith('javascript:')) return false;
    const url = new URL(href, location.href);
    if (url.origin !== location.origin) return false;
    if (url.pathname === location.pathname && url.search === location.search) return false;
    if (url.hash && url.pathname === location.pathname && url.search === location.search) return false;
    return true;
  }
  function show() {
    const el = overlay();
    if (!el) return;
    el.classList.add('is-active');
    el.setAttribute('aria-hidden','false');
  }
  document.addEventListener('click', e => {
    const a = e.target.closest('a');
    if (!isNavigable(a)) return;
    if (e.defaultPrevented) return;
    show();
  }, true);
  // Login & logout juga memakai transisi. 'submit' hanya terpicu setelah validasi browser lolos.
  document.addEventListener('submit', e => {
    const f = e.target;
    if (reduce || !f || f.tagName !== 'FORM' || e.defaultPrevented) return;
    if ((f.method || '').toLowerCase() !== 'post') return;
    let path = '';
    try { path = new URL(f.action, location.href).pathname; } catch (_) { return; }
    if (/\/(login|logout)$/.test(path)) show();
  }, true);
  window.addEventListener('pageshow', () => {
    const el = overlay();
    if (!el) return;
    el.classList.remove('is-active');
    el.setAttribute('aria-hidden','true');
  });
})();