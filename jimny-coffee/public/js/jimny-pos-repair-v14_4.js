/* V14.4: visual-only adjustment. Does not change Alpine state or POS logic. */
(() => {
  'use strict';
  const fixAtlas = () => {
    if (!document.body.classList.contains('pos-screen')) return;
    document.querySelectorAll('.pos-workspace .product-grid .product-card > .product-photo > svg.menu-photo-image')
      .forEach(svg => svg.setAttribute('preserveAspectRatio', 'none'));
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fixAtlas, { once: true });
  } else {
    fixAtlas();
  }
})();
