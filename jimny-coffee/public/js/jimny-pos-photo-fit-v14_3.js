/* JIMNY COFFEE / POS PHOTO FIT V14.3
 * A small visual-only adjustment for the outer SVG menu atlas renderer.
 * The source SVG says preserveAspectRatio="meet", which letterboxes the photo
 * in a fixed-height slot even if CSS applies object-fit: fill.
 * This setting allows the complete SVG to fill the same media slot as <img>.
 * No data, transactions, stock or button behavior is modified.
 */
(() => {
  'use strict';
  const apply = () => {
    const workspace = document.querySelector('body.pos-screen .pos-workspace');
    if (!workspace) return;
    const photos = workspace.querySelectorAll('.product-grid .product-card > .product-photo > svg.menu-photo-image');
    for (const photo of photos) {
      photo.setAttribute('preserveAspectRatio', 'none');
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', apply, { once: true });
  } else {
    apply();
  }
})();
