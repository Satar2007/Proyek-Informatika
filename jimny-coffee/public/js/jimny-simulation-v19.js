/* JIMNY V19 Simulation Lab. Explicit opt-in; no mutations or DB seeding. */
(() => {
  'use strict';
  const ENDPOINT = '/smart-assistant/simulation-v19';
  const WINDOWS = [['1','Besok'],['7','7 hari'],['30','30 hari'],['90','90 hari'],['365','365 hari']];
  const api = {active:false,everUsed:false};
  window.JimnySimulationLabV19 = api;
  const fmt = n => Number(n).toLocaleString('id-ID',{maximumFractionDigits:1});
  const rupiah = n => 'Rp '+Number(n).toLocaleString('id-ID',{maximumFractionDigits:0});
  const el = (tag,cls,text) => {
    const node=document.createElement(tag);
    if(cls) node.className=cls;
    if(text!==undefined) node.textContent=String(text);
    return node;
  };
  function install(root) {
    const board=root.querySelector('.smart-v2-dashboard.jimny-v15');
    if(!board || board.dataset.j19Ready==='1') return !!board;
    const forecast=board.querySelector('.j360-forecast');
    const restock=board.querySelector('.j360-restock');
    if(!forecast || !restock) return false;
    if(board.querySelector('.j19-switch')) return false;

    let mode='live', selected='30', data=null, pending=null;
    const switchers=[];
    const createSwitcher=()=> {
      const wrap=el('div','j19-switch');
      const live=el('button','j19-option j19-live','Live POS');
      const demo=el('button','j19-option j19-demo','Simulasi 24 Bulan');
      [live,demo].forEach(b=>{ b.type='button';wrap.append(b);});
      live.addEventListener('click',()=>changeMode('live'));
      demo.addEventListener('click',()=>changeMode('demo'));
      switchers.push({live,demo});
      return wrap;
    };
    const fMode=createSwitcher();
    forecast.insertBefore(fMode, forecast.querySelector('.j360-horizons'));
    const sMode=createSwitcher();
    restock.insertBefore(sMode, restock.querySelector('.j360-restock-list'));

    const fpanel=el('div','j19-forecast-content');
    const spanel=el('div','j19-stock-content');
    forecast.append(fpanel);
    restock.append(spanel);

    const chat=root.querySelector('.smart-panel');
    let chatNotice;
    if(chat) {
      chatNotice=el('div','j19-chat-notice','SIMULASI 24 BULAN · Jawaban AI memakai data sintetis, bukan transaksi nyata.');
      chatNotice.setAttribute('role','status');
      const heading=chat.querySelector('.smart-chat-header, .smart-panel-head, .smart-chat-heading');
      if(heading && heading.parentElement===chat) heading.insertAdjacentElement('afterend',chatNotice);
      else chat.prepend(chatNotice);
    }

    function setButtons(){
      const isDemo=mode==='demo';
      board.dataset.j19Mode=isDemo?'demo':'live';
      root.dataset.j19Mode=isDemo?'demo':'live';
      api.active=isDemo;
      for(const pair of switchers) {
        pair.live.classList.toggle('is-selected',!isDemo);
        pair.demo.classList.toggle('is-selected',isDemo);
        pair.live.setAttribute('aria-pressed',String(!isDemo));
        pair.demo.setAttribute('aria-pressed',String(isDemo));
      }
    }
    function changeMode(value){
      mode=value;
      if(value==='demo') api.everUsed=true;
      setButtons();
      if(value==='demo') load();
    }
    function renderForecast() {
      fpanel.replaceChildren();
      const heading=el('div','j19-hero');
      heading.append(el('strong','','SIMULASI · BUKAN DATA POS'), el('span','','Data sintetis 24 bulan'));
      fpanel.append(heading);
      if(!data) {fpanel.append(el('p','j19-message','Memuat data simulasi…'));return;}
      const summary=el('div','j19-metrics');
      const summaryItems=[
        ['Riwayat',data.history_days+' hari'],
        ['Porsi simulasi',fmt(data.summary.total_portions)],
        ['Total omzet simulasi',rupiah(data.summary.total_revenue_rp)],
      ];
      for(const [label,value] of summaryItems) {
        const card=el('div','j19-metric');
        card.append(el('span','',label), el('b','',value));
        summary.append(card);
      }
      fpanel.append(summary);
      const controls=el('div','j19-horizons');
      for(const [value,label] of WINDOWS) {
        const button=el('button','j19-horizon'+(selected===value?' is-active':''),label);
        button.type='button';
        button.setAttribute('aria-pressed',String(selected===value));
        button.addEventListener('click',()=>{selected=value;renderForecast();});
        controls.append(button);
      }
      fpanel.append(controls);
      const projection=data.forecasts?.[selected];
      if(!projection || !projection.available) {
        fpanel.append(el('p','j19-warning','Riwayat simulasi belum cukup untuk horizon ini.'));
        return;
      }
      const prediction=el('div','j19-projection');
      prediction.append(
        el('span','',`Proyeksi ${selected} hari ke depan · DEMO`),
        el('strong','',fmt(projection.point)+' porsi'),
        el('small','',`Rentang skenario ${fmt(projection.lower)}–${fmt(projection.upper)} porsi`),
        el('small','',`Omzet simulasi ${rupiah(projection.revenue_point_rp)} · asumsi harga rata-rata tetap`)
      );
      fpanel.append(prediction);
      const bt=projection.backtest;
      fpanel.append(el('p','j19-context',bt.tests
        ? `Backtest ${bt.tests} jendela: MAE total ${fmt(bt.mae_total_portions)} porsi/${selected} hari.`
        : 'Belum ada backtest penuh untuk horizon ini. Prediksi belum tervalidasi.'));
      if(selected==='365') {
        fpanel.append(el('p','j19-warning','Forecast setahun ini hanya DEMO. 24 bulan belum cukup untuk holdout 365 hari setelah minimum training 540 hari.'));
      }
      const recent=data.monthly.slice(-12);
      const max=Math.max(1,...recent.map(x=>x.portions));
      const chart=el('div','j19-chart');
      chart.setAttribute('aria-label','Grafik 12 bulan penjualan simulasi');
      for(const m of recent) {
        const column=el('div','j19-chart-col');
        const bar=el('span','j19-chart-bar');
        bar.style.height=Math.max(3,(m.portions/max*100))+'%';
        bar.title=`${m.month}: ${fmt(m.portions)} porsi SIMULASI`;
        column.append(bar,el('small','',m.month.slice(5)));
        chart.append(column);
      }
      fpanel.append(el('h4','j19-chart-title','12 bulan terakhir · sintetis'),chart);
      fpanel.append(el('p','j19-context',`Dataset dibuat untuk uji fungsi. Periode ${data.history_from} sampai ${data.history_through}.`));
    }
    function renderStock() {
      spanel.replaceChildren();
      spanel.append(el('div','j19-hero','SIMULASI · STOK FIKTIF, BUKAN STOK GUDANG'));
      if(!data){spanel.append(el('p','j19-message','Memuat stok simulasi…'));return;}
      const priorities=data.restock.filter(r=>r.suggested_portions>0).slice(0,6);
      if(!priorities.length){
        spanel.append(el('p','j19-ok','Tidak ada prioritas restock dari data simulasi.'));
      }
      for(const item of priorities) {
        const row=el('div','j19-stock-row');
        const left=el('div','');
        left.append(el('strong','',item.menu),el('small','',`Stok simulasi ${item.stock} · Rata-rata ${fmt(item.avg_daily_portions)}/hari`));
        row.append(left,el('b','',`+${item.suggested_portions} porsi`));
        spanel.append(row);
      }
      spanel.append(el('p','j19-warning','HANYA UJI COBA. Jangan membeli stok asli berdasarkan simulasi. Tidak ada perubahan database.'));
    }
    async function load() {
      if(data){renderForecast();renderStock();return;}
      if(pending)return pending;
      fpanel.replaceChildren(el('p','j19-message','Membaca snapshot simulasi…'));
      pending=(async()=>{
        try{
          const response=await fetch(ENDPOINT,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
          if(!response.ok) throw new Error('HTTP '+response.status);
          const parsed=await response.json();
          if(parsed.synthetic!==true || parsed.mode!=='SIMULATION_ONLY') throw new Error('Sumber bukan data sintetis');
          data=parsed;
          renderForecast();renderStock();
        }catch(error){
          fpanel.replaceChildren(el('p','j19-warning','Simulasi belum bisa dibaca: '+error.message));
          spanel.replaceChildren(el('p','j19-warning','Jalankan php artisan jimny:simulation-build.'));
        }finally{pending=null;}
      })();
      return pending;
    }
    setButtons();
    board.dataset.j19Ready='1';
    return true;
  }
  function start(){
    let attempts=0;
    const timer=setInterval(()=>{
      document.querySelectorAll('.smart-v5-root,.smart-v4-root,.jimny-glass-v9').forEach(install);
      if(++attempts>50)clearInterval(timer);
    },250);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});
  else start();
})();
