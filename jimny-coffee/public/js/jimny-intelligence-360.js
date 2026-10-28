/* V16+V17+V18: view-only forecast/restock panel. No checkout, stock or payment mutation. */
(() => {
  'use strict';
  const ENDPOINT = '/smart-assistant/intelligence-360';
  const HORIZONS = [['1','Besok'],['7','7 hari'],['30','30 hari'],['90','90 hari'],['365','1 tahun']];
  const fmt = n => Number(n).toLocaleString('id-ID',{maximumFractionDigits:1});
  const el = (tag, cls, txt) => {
    const x = document.createElement(tag);
    if (cls) x.className = cls;
    if (txt != null) x.textContent = String(txt);
    return x;
  };
  function mount(root) {
    const board = root.querySelector('.smart-v2-dashboard.jimny-v15');
    if (!board || board.dataset.j360Ready === '1') return;
    const ml = board.querySelector('.jimny-ml-zone');
    const stock = board.querySelector('.jimny-glass-stock');
    if (!ml || !stock) return;
    board.dataset.j360Ready = '1';

    const forecastPanel = el('section', 'j360-panel j360-forecast');
    forecastPanel.setAttribute('aria-label','Proyeksi permintaan multi-periode');
    const head = el('div','j360-head');
    const hgroup = el('div','j360-headline');
    hgroup.append(el('span','j360-eyebrow','FORECAST / POS LIVE'), el('h3','', 'Proyeksi Permintaan'));
    const refresh = el('button','j360-refresh','↻ Perbarui');
    refresh.type = 'button';
    head.append(hgroup,refresh);
    const controls = el('div','j360-horizons');
    controls.setAttribute('role','group');
    controls.setAttribute('aria-label','Rentang forecast');
    const body = el('div','j360-body');
    body.setAttribute('aria-live','polite');
    const note = el('p','j360-note','Hanya data transaksi sukses dan lunas; hasil statistik memerlukan review manusia.');
    forecastPanel.append(head,controls,body,note);
    ml.insertAdjacentElement('afterend',forecastPanel);

    const stockPanel = el('section','j360-panel j360-restock');
    stockPanel.setAttribute('aria-label','Rekomendasi stok 5 hari');
    stockPanel.append(el('h3','', 'Saran Persiapan Stok'), el('p','j360-note','Satuan porsi per menu. Hanya rekomendasi; tidak mengubah stok.'),el('div','j360-restock-list'));
    stock.insertAdjacentElement('afterend',stockPanel);

    let dataset = null;
    let selected = '7';
    let fetchedAt = 0;
    let pending = null;
    const buttons = [];
    HORIZONS.forEach(([value,label]) => {
      const btn = el('button','j360-horizon',label);
      btn.type = 'button';
      btn.addEventListener('click',() => { selected = value; render(); });
      controls.append(btn);
      buttons.push([value,btn]);
    });
    refresh.addEventListener('click',() => load(true));

    function render() {
      buttons.forEach(([value,btn]) => {
        btn.classList.toggle('is-active',selected===value);
        btn.setAttribute('aria-pressed',String(selected===value));
      });
      body.replaceChildren();
      if (!dataset) {
        body.append(el('p','j360-message','Memuat analitik...'));
        return;
      }
      const f = dataset.forecasts?.[selected];
      if (!f || !f.available) {
        body.append(el('div','j360-unavailable','Riwayat belum mencukupi untuk estimasi periode ini.'));
        body.append(el('p','j360-small',`Tersedia ${dataset.history_days ?? 0} hari kalender · butuh minimal ${f?.min_history_days ?? '—'} hari. Data tidak dibuat-buat.`));
      } else {
        const kpi = el('div','j360-result');
        const n = el('strong','j360-value',`${fmt(f.point)} porsi`);
        kpi.append(n,el('span','j360-range',`Rentang skenario: ${fmt(f.lower)}–${fmt(f.upper)} porsi`));
        body.append(kpi,el('p','j360-small',`Model statistik · MAE backtest ${fmt(f.backtest_mae)} porsi/hari · perlu review`));
      }
      body.append(el('div','j360-foot',`Data sampai ${dataset.data_through ?? '—'} · ${dataset.active_sales_days ?? 0} hari dengan penjualan`));
      const list = stockPanel.querySelector('.j360-restock-list');
      list.replaceChildren();
      if (!dataset.restock?.length) {
        list.append(el('p','j360-small','Belum ada menu aktif yang dapat diperiksa.'));
      } else {
        const priority = dataset.restock.filter(x => x.suggested_portions > 0).slice(0,5);
        if (!priority.length) list.append(el('p','j360-ok','Belum ada tambahan stok yang direkomendasikan dari data saat ini.'));
        priority.forEach(x => {
          const row = el('div','j360-restock-row');
          const info = el('div','j360-restock-info');
          info.append(el('strong','',x.menu),el('span','',`Stok ${x.stock} · minimum ${x.minimum} · ${x.basis === 'minimum_stock_only' ? 'berdasarkan minimum' : 'proyeksi 5 hari'}`));
          row.append(info,el('b','',`${x.suggested_portions} porsi`));
          list.append(row);
        });
      }
    }
    async function load(force=false) {
      if (pending) return pending;
      if (!force && dataset && Date.now()-fetchedAt < 60000) return render();
      body.replaceChildren(el('p','j360-message','Membaca transaksi yang sudah lunas...'));
      refresh.disabled = true;
      pending = (async () => {
        try {
          const response = await fetch(ENDPOINT, {credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
          if (!response.ok) throw new Error('HTTP '+response.status);
          const data = await response.json();
          if (!data.forecasts || !data.restock) throw new Error('Data analitik tidak lengkap');
          dataset = data; fetchedAt = Date.now(); render();
        } catch (err) {
          body.replaceChildren(el('p','j360-error','Data POS belum dapat dibaca: '+err.message));
        } finally { refresh.disabled=false; pending=null; }
      })();
      return pending;
    }
    board.addEventListener('click',event => {
      if (!event.target.closest('.jv15-tab')) return;
      // V15 changes the view synchronously before this timeout fires.
      setTimeout(() => {
        if (['forecast','stock'].includes(board.dataset.jv15View)) load();
      }, 40);
    });
    if (['forecast','stock'].includes(board.dataset.jv15View)) load();
    render();
  }
  function start() {
    const roots = document.querySelectorAll('.smart-v5-root, .smart-v4-root, .jimny-glass-v9');
    roots.forEach(mount);
    // Delayed V15 init / lazy Alpine mount may need a second pass.
    let pass = 0;
    const checker = setInterval(() => {
      roots.forEach(mount);
      if (++pass > 25 || Array.from(roots).every(root => !root.querySelector('.smart-v2-dashboard') || root.querySelector('[data-j360-ready="1"]'))) clearInterval(checker);
    }, 300);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',start,{once:true});
  else start();
})();
