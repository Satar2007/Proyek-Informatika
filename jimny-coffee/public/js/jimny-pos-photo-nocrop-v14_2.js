/* JIMNY POS PHOTO NO-CROP V14.2
   Does NOT modify POS state, stock, orders or payment flows.
   Resizes media boxes to natural aspect ratio. Only when a desktop card is
   too short, use a blurred duplicate of the SAME media as visual fill behind
   the fully visible, object-fit: contain original. */
(() => {
  'use strict';
  const workspace = document.querySelector('body.pos-screen .pos-workspace');
  if (!workspace || window.__jimnyPhotoNoCropV142) return;
  window.__jimnyPhotoNoCropV142 = true;
  const cards = Array.from(workspace.querySelectorAll('.product-grid .product-card'));
  const number = (value) => Number.parseFloat(value) || 0;

  function ratioOf(media) {
    if (media instanceof SVGSVGElement) {
      const box = media.viewBox && media.viewBox.baseVal;
      if (box && box.width > 0 && box.height > 0) return box.width / box.height;
    }
    if (media instanceof HTMLImageElement && media.naturalWidth > 0 && media.naturalHeight > 0) {
      return media.naturalWidth / media.naturalHeight;
    }
    return 4 / 3;
  }

  function measure(card) {
    if (!card.isConnected || getComputedStyle(card).display === 'none') return;
    const photo = card.querySelector(':scope > .product-photo');
    const image = photo && photo.querySelector(':scope > .menu-photo-image');
    if (!photo || !image) return;
    const width = photo.clientWidth;
    if (!width) return;
    const natural = width / ratioOf(image);
    const shortLayout = window.matchMedia('(max-width: 1023px)').matches;

    let chosen = natural;
    if (!shortLayout) {
      const c = getComputedStyle(card);
      const name = card.querySelector(':scope > .product-name');
      const info = card.querySelector(':scope > .product-info-row');
      const bottom = card.querySelector(':scope > .product-bottom');
      const pieces = [name, info, bottom].filter(Boolean);
      const occupied = pieces.reduce((sum, el) => {
        const s = getComputedStyle(el);
        return sum + el.offsetHeight + number(s.marginTop) + number(s.marginBottom);
      }, 0);
      const gap = number(c.rowGap) || number(c.gap);
      const verticalPadding = number(c.paddingTop) + number(c.paddingBottom);
      const room = card.clientHeight - occupied - verticalPadding - gap * 3 - 2;
      if (room > 60) chosen = Math.min(natural, room);
    }

    photo.style.setProperty('--jimny-photo-natural-height', `${Math.round(chosen * 10) / 10}px`);
    photo.classList.add('jimny-photo-measured');
    const needsFill = chosen + 2 < natural;
    let backdrop = photo.querySelector(':scope > .jimny-photo-backdrop');
    if (needsFill && !backdrop) {
      backdrop = image.cloneNode(true);
      backdrop.classList.remove('menu-photo-image');
      backdrop.classList.add('jimny-photo-backdrop');
      backdrop.setAttribute('aria-hidden', 'true');
      backdrop.setAttribute('focusable', 'false');
      backdrop.removeAttribute('aria-label');
      if (backdrop instanceof SVGSVGElement) {
        backdrop.setAttribute('preserveAspectRatio', 'xMidYMid slice');
      }
      photo.insertBefore(backdrop, image);
    } else if (!needsFill && backdrop) {
      backdrop.remove();
    }
  }

  let pending = false;
  function schedule() {
    if (pending) return;
    pending = true;
    requestAnimationFrame(() => {
      pending = false;
      cards.forEach(measure);
    });
  }

  cards.forEach(card => {
    const photo = card.querySelector(':scope > .product-photo');
    const img = photo && photo.querySelector(':scope > img.menu-photo-image');
    if (img) img.addEventListener('load', schedule, { passive: true });
  });
  window.addEventListener('resize', schedule, { passive: true });
  if ('ResizeObserver' in window) {
    const observer = new ResizeObserver(schedule);
    const grid = workspace.querySelector('.product-grid');
    if (grid) observer.observe(grid);
  }
  const controls = workspace.querySelectorAll('.category-tabs, .catalog-pager');
  controls.forEach(el => el.addEventListener('click', () => requestAnimationFrame(schedule), { passive: true }));
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', schedule, { once: true });
  } else {
    schedule();
  }
})();
