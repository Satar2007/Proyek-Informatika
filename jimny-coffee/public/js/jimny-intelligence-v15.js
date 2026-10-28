/* JIMNY INTELLIGENCE V15 — view-only enhancement. No business logic or network calls. */
(function () {
  'use strict';
  const VIEWS = [
    ['overview', 'Overview'],
    ['forecast', 'Prediksi'],
    ['stock', 'Stok']
  ];
  function enhance(root) {
    const board = root.querySelector('.smart-v2-dashboard');
    if (!board || board.dataset.jv15Ready === '1') return;
    const heading = board.querySelector('.smart-v2-heading');
    if (!heading || !board.querySelector('.jimny-glass-trend') || !board.querySelector('.jimny-ml-zone')) return;

    const nav = document.createElement('nav');
    nav.className = 'jv15-nav';
    nav.setAttribute('aria-label', 'Halaman analitik JIMNY');
    nav.setAttribute('role', 'tablist');
    const pill = document.createElement('span');
    pill.className = 'jv15-nav-pill';
    pill.setAttribute('aria-hidden', 'true');
    nav.appendChild(pill);
    const buttons = VIEWS.map(([key, label]) => {
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'jv15-tab';
      btn.textContent = label; btn.dataset.view = key;
      btn.setAttribute('role', 'tab');
      btn.addEventListener('click', () => switchView(key));
      btn.addEventListener('keydown', ev => {
        const i = VIEWS.findIndex(v => v[0] === board.dataset.jv15View);
        if (ev.key === 'ArrowRight' || ev.key === 'ArrowLeft') {
          ev.preventDefault();
          const next = (i + (ev.key === 'ArrowRight' ? 1 : -1) + VIEWS.length) % VIEWS.length;
          buttons[next].focus(); switchView(VIEWS[next][0]);
        }
      });
      nav.appendChild(btn); return btn;
    });

    const status = document.createElement('div');
    status.className = 'jv15-statusline';
    status.setAttribute('aria-live', 'polite');
    const statusIcon = document.createElement('span');
    statusIcon.className = 'jv15-statusdot'; statusIcon.setAttribute('aria-hidden', 'true');
    const statusText = document.createElement('span');
    status.append(statusIcon, statusText);

    const subtitle = heading.querySelector('p');
    const topTitle = heading.querySelector('h2');
    const eyebrow = heading.querySelector('.smart-v2-eyebrow');
    const labels = {
      overview: ['Business Overview', 'Ringkasan penjualan dalam satu layar.', 'Analitik langsung dari POS'],
      forecast: ['Prediksi Bisnis', 'Estimasi untuk membantu keputusan, bukan perintah stok.', 'Periksa tanggal dan kualitas data'],
      stock: ['Pantauan Stok', 'Lihat menu yang perlu perhatian.', 'Data stok aktual dari POS']
    };
    const detailBar = document.createElement('div');
    detailBar.className = 'jv15-detailbar';
    const compareButton = document.createElement('button');
    compareButton.type = 'button';
    compareButton.textContent = 'Lihat metode POS';
    compareButton.className = 'jv15-compare';
    compareButton.setAttribute('aria-pressed', 'false');
    compareButton.addEventListener('click', () => {
      const enabled = board.dataset.jv15Compare !== '1';
      board.dataset.jv15Compare = enabled ? '1' : '0';
      compareButton.textContent = enabled ? 'Sembunyikan metode POS' : 'Lihat metode POS';
      compareButton.setAttribute('aria-pressed', String(enabled));
    });
    const freshness = document.createElement('span');
    freshness.className = 'jv15-freshness';
    detailBar.append(freshness, compareButton);

    heading.insertAdjacentElement('afterend', nav);
    nav.insertAdjacentElement('afterend', status);
    const mlHeading = board.querySelector('.jimny-glass-ml-heading');
    if (mlHeading) mlHeading.insertAdjacentElement('afterend', detailBar);

    // Title changes are purely presentational; analytics data and services remain untouched.
    function updateDataNotice() {
      const mlState = root.__x && root.__x.$data ? root.__x.$data.mlStatus : null;
      const mlVerified = !!board.querySelector('.jimny-ml-ok') &&
        board.querySelector('.jimny-ml-results')?.getClientRects().length > 0;
      // Alpine's x-if can render asynchronously. Presence is enough to infer verified package.
      const hasVerifiedMarkup = !!board.querySelector('.jimny-ml-results');
      board.dataset.jv15Ml = hasVerifiedMarkup || mlVerified || mlState === 'verified' ? 'verified' : 'unavailable';
      const posDate = board.querySelector('.jimny-glass-forecast-date')?.textContent?.trim() || '';
      const dateMeta = board.querySelector('.jimny-glass-ml-metadata > span:last-child strong')?.textContent?.trim() || '';
      const mismatch = /^\d{4}-\d{2}-\d{2}$/.test(posDate) && /^\d{4}-\d{2}-\d{2}$/.test(dateMeta) && posDate !== dateMeta;
      const newFresh = mismatch ? 'Tanggal paket ML berbeda dari prediksi POS. Periksa sebelum dipakai.' :
        (board.dataset.jv15Ml === 'verified' ? 'Paket ML perlu review manusia; bukan instruksi pembelian.' : 'Belum ada paket ML terverifikasi; metode POS tetap eksperimental.');
      if (freshness.textContent !== newFresh) freshness.textContent = newFresh;
    }
    let raf = 0;
    function updatePill() {
      if (!nav.isConnected) return;
      const b = buttons.find(x => x.dataset.view === board.dataset.jv15View) || buttons[0];
      if (b.offsetWidth) {
        nav.style.setProperty('--jv15-pill-left', b.offsetLeft + 'px');
        nav.style.setProperty('--jv15-pill-width', b.offsetWidth + 'px');
      }
    }
    function switchView(view) {
      if (!labels[view]) return;
      board.dataset.jv15View = view;
      for (const button of buttons) {
        const selected = button.dataset.view === view;
        button.classList.toggle('is-active', selected);
        button.setAttribute('aria-selected', String(selected));
        button.tabIndex = selected ? 0 : -1;
      }
      if (topTitle) topTitle.textContent = labels[view][0];
      if (subtitle) subtitle.textContent = labels[view][1];
      if (eyebrow) {
        const textNode = Array.from(eyebrow.childNodes).find(n => n.nodeType === 3);
        if (textNode) textNode.textContent = ' ' + labels[view][2];
      }
      statusText.textContent = view === 'overview' ? 'Penjualan & jam ramai' : view === 'forecast' ? 'Eksperimental · periksa kualitas data' : 'Stok dan prioritas restok';
      board.scrollTop = 0;
      updateDataNotice();
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(updatePill);
    }
    board.dataset.jv15Ready = '1';
    board.classList.add('jimny-v15');
    board.dataset.jv15Compare = '0';
    switchView('overview');
    const observer = new MutationObserver(() => {
      // no DOM rewrite when unchanged, avoids mutation-observer loops
      if (board.dataset.jv15View === 'forecast') updateDataNotice();
    });
    observer.observe(board, {childList:true, subtree:true, characterData:true});
    root.addEventListener('click', (ev) => {
      if (ev.target.closest('.smart-v2-refresh, .jimny-ml-refresh')) {
        setTimeout(updateDataNotice, 400);
      }
    });
    window.addEventListener('resize', () => requestAnimationFrame(updatePill), {passive:true});
    root.addEventListener('click', e => {
      if (e.target.closest('.smart-fab, .smart-v3-mobile-tabs')) {
        setTimeout(updatePill, 100);
      }
    });
    // Delicate specular highlight on hover, only while pointer is over a card.
    board.addEventListener('pointermove', e => {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || e.pointerType === 'touch') return;
      const card = e.target.closest('.smart-v2-kpi, .smart-v2-chart-card, .jimny-ml-zone');
      if (!card || !board.contains(card)) return;
      const box = card.getBoundingClientRect();
      if (!box.width || !box.height) return;
      card.style.setProperty('--jv15-x', ((e.clientX-box.left)/box.width*100).toFixed(1)+'%');
      card.style.setProperty('--jv15-y', ((e.clientY-box.top)/box.height*100).toFixed(1)+'%');
    }, {passive:true});
  }
  const boot = () => document.querySelectorAll('.smart-assistant-root.jimny-glass-v9').forEach(enhance);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once:true});
  else boot();
})();
