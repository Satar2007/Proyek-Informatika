/* JSA V20: mounted once from DOM, loaded whenever the existing assistant opens. */
(()=>{'use strict';
 const api=window.JimnyOwnerV20={days:30,horizon:7,load:()=>{}};
 const nf=new Intl.NumberFormat('id-ID',{maximumFractionDigits:1});
 const fmt=n=>n==null?'Belum tersedia':nf.format(n),rp=n=>n==null?'Belum tersedia':'Rp '+new Intl.NumberFormat('id-ID',{maximumFractionDigits:0}).format(n);
 const el=(tag,cls,text)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=String(text);return e;};
 function start(){const root=document.getElementById('jimny-owner-v20');if(!root)return;
 const get=id=>root.querySelector('#j20-'+id);let pending=false,revision=0;
 const table=(headers,rows)=>{const t=el('table'),head=el('thead'),tr=el('tr');headers.forEach(h=>{const th=el('th','',h);th.scope='col';tr.append(th);});head.append(tr);t.append(head);const body=el('tbody');
 rows.forEach(row=>{const tr=el('tr');row.forEach(c=>tr.append(el('td','',c)));body.append(tr);});if(!rows.length){const tr=el('tr'),td=el('td','','Belum ada data pada periode ini.');td.colSpan=headers.length;tr.append(td);body.append(tr);}t.append(body);return t;};
 function render(s){
  get('content').hidden=false;get('status').textContent=`POS Â· ${s.current.from}â€“${s.current.through} Â· transaksi terakhir ${s.last_sale_date||'belum ada'} Â· dibanding ${s.previous.from}â€“${s.previous.through} Â· diperbarui ${new Date(s.generated_at).toLocaleString('id-ID')}`;
  const kpis=get('kpis');kpis.replaceChildren();
  [['Penjualan sebelum pajak',rp(s.current.sales_rp),s.changes.sales_pct],['Transaksi lunas',fmt(s.current.transactions),s.changes.transactions_pct],['Rata-rata belanja',rp(s.current.average_ticket_rp),s.changes.ticket_pct],['Porsi terjual',fmt(s.current.portions),undefined]].forEach(([label,val,delta])=>{
   const k=el('article','j20-kpi');k.append(el('span','',label),el('strong','',val));if(delta!==undefined)k.append(el('small',delta<0?'j20-negative':'',delta==null?'Periode pembanding belum memiliki nilai':`${delta>0?'+':''}${fmt(delta)}% vs periode sebelumnya`));else k.append(el('small','',`Jam terbanyak transaksi: ${s.peak_hour||'â€”'}`));kpis.append(k);
  });
  get('actions').replaceChildren();s.actions.forEach((a,i)=>{const card=el('article','j20-card j20-action');card.append(el('span','j20-kicker',`PRIORITAS ${i+1}`),el('h4','',a.title),el('p','',a.reason),el('strong','j20-action-text',a.action));get('actions').append(card);});
  const trend=get('trend');
  trend.replaceChildren();

  if(s.trend.length){
   const ns='http://www.w3.org/2000/svg';
   const svg=document.createElementNS(ns,'svg');

   const left=150,right=24,top=24,bottom=125;
   const daySpacing=46;
   const W=Math.max(820,left+right+Math.max(1,s.trend.length-1)*daySpacing);
   const H=380;
   const plotW=W-left-right,plotH=H-top-bottom;

   svg.setAttribute('viewBox',`0 0 ${W} ${H}`);
   svg.setAttribute('role','group');
   svg.setAttribute('aria-label','Grafik tren penjualan sebelum pajak. Pilih titik untuk melihat tanggal dan nominal penjualan.');

   const values=s.trend.map(r=>Math.max(0,Number(r.sales_rp)||0));
   const maxValue=Math.max(0,...values);
   const rawStep=Math.max(1,maxValue/4);
   const magnitude=Math.pow(10,Math.floor(Math.log10(rawStep)));
   const normalized=rawStep/magnitude;
   const niceStep=(normalized<=1?1:normalized<=2?2:normalized<=5?5:10)*magnitude;
   const yMax=Math.max(niceStep,Math.ceil(maxValue/niceStep)*niceStep);

   const x=i=>left+(s.trend.length===1?plotW/2:i*plotW/(s.trend.length-1));
   const y=value=>top+plotH-(Number(value)||0)/yMax*plotH;

   const add=(tag,attrs,parent=svg)=>{
    const node=document.createElementNS(ns,tag);
    Object.entries(attrs).forEach(([key,value])=>node.setAttribute(key,String(value)));
    parent.append(node);
    return node;
   };

   for(let i=0;i<=4;i++){
    const value=yMax*i/4;
    const yy=y(value);

    add('line',{
     x1:left,x2:W-right,y1:yy,y2:yy,
     stroke:'#e4d5c8','stroke-width':1
    });

    const label=add('text',{
     x:left-12,y:yy+4,
     fill:'#785a45','font-size':12,
     'text-anchor':'end'
    });
    label.textContent=rp(value);
   }

   add('line',{
    x1:left,x2:left,y1:top,y2:top+plotH,
    stroke:'#785a45','stroke-width':1.5
   });

   add('line',{
    x1:left,x2:W-right,y1:top+plotH,y2:top+plotH,
    stroke:'#785a45','stroke-width':1.5
   });

   const yTitle=add('text',{
    x:22,y:top+plotH/2,
    fill:'#68472d','font-size':12,
    'text-anchor':'middle',
    transform:`rotate(-90 22 ${top+plotH/2})`
   });
   yTitle.textContent='Penjualan sebelum pajak (Rupiah)';

   s.trend.forEach((row,i)=>{
    const xx=x(i);

    add('line',{
     x1:xx,x2:xx,
     y1:top+plotH,y2:top+plotH+5,
     stroke:'#785a45'
    });

    const label=add('text',{
     x:xx-5,y:top+plotH+18,
     fill:'#785a45','font-size':10,
     'text-anchor':'end',
     transform:`rotate(-50 ${xx-5} ${top+plotH+18})`
    });

    label.textContent=new Date(row.date+'T00:00:00').toLocaleDateString('id-ID',{
     day:'2-digit',month:'short'
    });
   });

   const xTitle=add('text',{
    x:left+plotW/2,y:H-12,
    fill:'#68472d','font-size':12,
    'text-anchor':'middle'
   });
   xTitle.textContent='Tanggal transaksi';

   const poly=add('polyline',{
    points:s.trend.map((row,i)=>`${x(i)},${y(row.sales_rp)}`).join(' '),
    fill:'none',stroke:'#a86a3d','stroke-width':3,
    'stroke-linejoin':'round','stroke-linecap':'round'
   });

   const detail=el('div','j20-point-detail');
   detail.setAttribute('aria-live','polite');
   detail.append(el('span','','Pilih salah satu titik pada grafik untuk melihat detail penjualan.'));
   trend.style.overflowX='auto';
   svg.style.width=`${W}px`;
   svg.style.maxWidth='none';
   trend.append(svg,detail);

   const showPoint=i=>{
    const row=s.trend[i];
    detail.replaceChildren(
     el('span','',`Tanggal: ${row.date}`),
     el('strong','',rp(row.sales_rp)),
     el('span','',`Jumlah porsi: ${fmt(row.portions)}`)
    );
   };

   s.trend.forEach((row,i)=>{
    const circle=add('circle',{
     cx:x(i),cy:y(row.sales_rp),r:5,
     fill:'#a86a3d',stroke:'#fffaf4','stroke-width':2,
     tabindex:0,role:'button',
     'aria-label':`${row.date}, penjualan ${rp(row.sales_rp)}`
    });

    const tooltip=add('title',{},circle);
    tooltip.textContent=`${row.date}: ${rp(row.sales_rp)}`;

    circle.addEventListener('click',()=>showPoint(i));
    circle.addEventListener('keydown',event=>{
     if(event.key==='Enter'||event.key===' '){
      event.preventDefault();
      showPoint(i);
     }
    });
   });

  }else{
   trend.append(el('p','','Belum ada riwayat penjualan.'));
  }

  get('trend-table').replaceChildren(table(['Tanggal','Penjualan','Porsi'],s.trend.map(r=>[r.date,rp(r.sales_rp),fmt(r.portions)])));
  get('menus').replaceChildren(table(['Menu','Porsi','Sebelumnya','Perubahan','Nilai item'],s.menus.map(m=>[m.name,fmt(m.qty),fmt(m.previous_qty),m.change_pct==null?'â€”':fmt(m.change_pct)+'%',rp(m.item_sales_rp)])));
  const f=s.forecast,box=get('forecast');box.replaceChildren();box.append(el('p','',`${f.from}â€“${f.through} Â· ${f.history_days} hari riwayat`));
  if(!f.available)box.append(el('p','j20-warning',`Riwayat belum cukup. Dibutuhkan minimal ${f.min_history_days} hari.`));
  else{const hero=el('div','j20-projection');hero.append(el('span','','Perkiraan total porsi'),el('strong','',fmt(f.point)+' porsi'),el('p','',`Rentang skenario ${fmt(f.lower)}â€“${fmt(f.upper)} porsi`));box.append(hero);
   box.append(el('p','',`Proyeksi penjualan sebelum pajak: ${rp(f.revenue.point)} (${rp(f.revenue.lower)}â€“${rp(f.revenue.upper)}).`),el('p','j20-warning','Perlu review Â· rentang skenario bukan interval probabilitas terkalibrasi.'));
   box.append(el('p','',`Metode porsi: ${f.method==='mean28'?'rata-rata 28 hari':'pola hari + rata-rata 28 hari'}. Metode penjualan: ${f.revenue.method==='mean28'?'rata-rata 28 hari':'pola hari + rata-rata 28 hari'}.`));
  }
  const ev=get('evaluation');ev.replaceChildren();if(!f.tests)ev.append(el('p','j20-warning',`Belum ada holdout penuh ${f.horizon_days} hari setelah minimum pelatihan ${f.min_history_days} hari. Tidak ada klaim akurasi untuk horizon ini.`));
  else{ev.append(el('p','',`Evaluasi terakhir ${f.evaluation.from}â€“${f.evaluation.through}. ${f.tests} jendela lengkap; ${f.evaluation.selection_windows} jendela terdahulu tersedia untuk pemilihan model.`),table(['Prediksi','Realisasi','Galat absolut','Galat baseline'],[[fmt(f.evaluation.predicted)+' porsi',fmt(f.evaluation.actual)+' porsi',fmt(f.mae)+' porsi',fmt(f.baseline_mae)+' porsi']]));ev.append(el('p','j20-note',f.tests>=3?'Model dipilih menggunakan jendela terdahulu, kemudian diuji pada jendela terakhir. Satu jendela evaluasi belum membuktikan keandalan.':'Jendela pemilihan belum cukup; baseline rata-rata 28 hari tetap digunakan.'));}
  get('stock').replaceChildren(table(['Menu','Stok tersedia','Kebutuhan 7 hari','Cakupan','Usulan tambahan'],s.stock.map(m=>[m.name,fmt(m.stock),fmt(m.demand_7),m.coverage_days==null?'Belum dapat dihitung':fmt(m.coverage_days)+' hari',fmt(m.suggested_portions)+' porsi'])));
  get('definitions').replaceChildren(...Object.values(s.definitions).map(t=>el('p','',t)),el('p','',`Penerimaan termasuk pajak: ${rp(s.current.collected_rp)}; pajak: ${rp(s.current.tax_rp)}.`));
 }
 api.load=async()=>{if(pending)return;pending=true;const rev=++revision;get('refresh').disabled=true;get('days').disabled=true;get('horizon').disabled=true;get('status').textContent='Membaca transaksi POSâ€¦';get('content').hidden=true;
  const abort=new AbortController(),timer=setTimeout(()=>abort.abort(),45000);
  try{const u=new URL(root.dataset.endpoint,location.href);u.searchParams.set('days',api.days);u.searchParams.set('horizon',api.horizon);const response=await fetch(u,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:abort.signal});if(!response.ok)throw Error('HTTP '+response.status);const s=await response.json();if(s.source!=='pos_database')throw Error('Sumber data tidak sesuai');if(rev===revision)render(s);}
  catch(e){get('status').textContent='Data belum dapat dibaca: '+e.message+'. Klik Perbarui untuk mencoba lagi.';}
  finally{clearTimeout(timer);pending=false;get('refresh').disabled=false;get('days').disabled=false;get('horizon').disabled=false;}
 };
 get('refresh').addEventListener('click',api.load);get('days').addEventListener('change',e=>{api.days=Number(e.target.value);api.load();});get('horizon').addEventListener('change',e=>{api.horizon=Number(e.target.value);api.load();});
 root.querySelectorAll('[data-j20-tab]').forEach(b=>b.addEventListener('click',()=>{root.querySelectorAll('[data-j20-tab]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));root.querySelectorAll('[data-j20-panel]').forEach(x=>x.hidden=x.dataset.j20Panel!==b.dataset.j20Tab);}));
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();


