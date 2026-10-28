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
  get('content').hidden=false;get('status').textContent=`POS · ${s.current.from}–${s.current.through} · transaksi terakhir ${s.last_sale_date||'belum ada'} · dibanding ${s.previous.from}–${s.previous.through} · diperbarui ${new Date(s.generated_at).toLocaleString('id-ID')}`;
  const kpis=get('kpis');kpis.replaceChildren();
  [['Penjualan sebelum pajak',rp(s.current.sales_rp),s.changes.sales_pct],['Transaksi lunas',fmt(s.current.transactions),s.changes.transactions_pct],['Rata-rata belanja',rp(s.current.average_ticket_rp),s.changes.ticket_pct],['Porsi terjual',fmt(s.current.portions),undefined]].forEach(([label,val,delta])=>{
   const k=el('article','j20-kpi');k.append(el('span','',label),el('strong','',val));if(delta!==undefined)k.append(el('small',delta<0?'j20-negative':'',delta==null?'Periode pembanding belum memiliki nilai':`${delta>0?'+':''}${fmt(delta)}% vs periode sebelumnya`));else k.append(el('small','',`Jam terbanyak transaksi: ${s.peak_hour||'—'}`));kpis.append(k);
  });
  get('actions').replaceChildren();s.actions.forEach((a,i)=>{const card=el('article','j20-card j20-action');card.append(el('span','j20-kicker',`PRIORITAS ${i+1}`),el('h4','',a.title),el('p','',a.reason),el('strong','j20-action-text',a.action));get('actions').append(card);});
  const trend=get('trend');trend.replaceChildren();
  if(s.trend.length){const ns='http://www.w3.org/2000/svg';const svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox','0 0 680 200');svg.setAttribute('role','img');svg.setAttribute('aria-label','Tren penjualan sebelum pajak; angka tersedia pada tabel harian.');
   const max=Math.max(1,...s.trend.map(x=>x.sales_rp));[0,.5,1].forEach(v=>{const line=document.createElementNS(ns,'line');line.setAttribute('x1','105');line.setAttribute('x2','665');line.setAttribute('y1',String(170-v*140));line.setAttribute('y2',String(170-v*140));line.setAttribute('stroke','#e4d5c8');svg.append(line);const text=document.createElementNS(ns,'text');text.setAttribute('x','0');text.setAttribute('y',String(174-v*140));text.setAttribute('fill','#785a45');text.setAttribute('font-size','11');text.textContent=rp(max*v);svg.append(text);});
   const poly=document.createElementNS(ns,'polyline');poly.setAttribute('points',s.trend.map((v,i)=>`${105+i*560/Math.max(1,s.trend.length-1)},${170-v.sales_rp/max*140}`).join(' '));poly.setAttribute('fill','none');poly.setAttribute('stroke','#a86a3d');poly.setAttribute('stroke-width','3');svg.append(poly);trend.append(svg,el('p','j20-date-range',`${s.trend[0].date} — ${s.trend.at(-1).date}`));
  }else trend.append(el('p','','Belum ada riwayat penjualan.'));
  get('trend-table').replaceChildren(table(['Tanggal','Penjualan','Porsi'],s.trend.map(r=>[r.date,rp(r.sales_rp),fmt(r.portions)])));
  get('menus').replaceChildren(table(['Menu','Porsi','Sebelumnya','Perubahan','Nilai item'],s.menus.map(m=>[m.name,fmt(m.qty),fmt(m.previous_qty),m.change_pct==null?'—':fmt(m.change_pct)+'%',rp(m.item_sales_rp)])));
  const f=s.forecast,box=get('forecast');box.replaceChildren();box.append(el('p','',`${f.from}–${f.through} · ${f.history_days} hari riwayat`));
  if(!f.available)box.append(el('p','j20-warning',`Riwayat belum cukup. Dibutuhkan minimal ${f.min_history_days} hari.`));
  else{const hero=el('div','j20-projection');hero.append(el('span','','Perkiraan total porsi'),el('strong','',fmt(f.point)+' porsi'),el('p','',`Rentang skenario ${fmt(f.lower)}–${fmt(f.upper)} porsi`));box.append(hero);
   box.append(el('p','',`Proyeksi penjualan sebelum pajak: ${rp(f.revenue.point)} (${rp(f.revenue.lower)}–${rp(f.revenue.upper)}).`),el('p','j20-warning','Perlu review · rentang skenario bukan interval probabilitas terkalibrasi.'));
   box.append(el('p','',`Metode porsi: ${f.method==='mean28'?'rata-rata 28 hari':'pola hari + rata-rata 28 hari'}. Metode penjualan: ${f.revenue.method==='mean28'?'rata-rata 28 hari':'pola hari + rata-rata 28 hari'}.`));
  }
  const ev=get('evaluation');ev.replaceChildren();if(!f.tests)ev.append(el('p','j20-warning',`Belum ada holdout penuh ${f.horizon_days} hari setelah minimum pelatihan ${f.min_history_days} hari. Tidak ada klaim akurasi untuk horizon ini.`));
  else{ev.append(el('p','',`Evaluasi terakhir ${f.evaluation.from}–${f.evaluation.through}. ${f.tests} jendela lengkap; ${f.evaluation.selection_windows} jendela terdahulu tersedia untuk pemilihan model.`),table(['Prediksi','Realisasi','Galat absolut','Galat baseline'],[[fmt(f.evaluation.predicted)+' porsi',fmt(f.evaluation.actual)+' porsi',fmt(f.mae)+' porsi',fmt(f.baseline_mae)+' porsi']]));ev.append(el('p','j20-note',f.tests>=3?'Model dipilih menggunakan jendela terdahulu, kemudian diuji pada jendela terakhir. Satu jendela evaluasi belum membuktikan keandalan.':'Jendela pemilihan belum cukup; baseline rata-rata 28 hari tetap digunakan.'));}
  get('stock').replaceChildren(table(['Menu','Stok tersedia','Kebutuhan 7 hari','Cakupan','Usulan tambahan'],s.stock.map(m=>[m.name,fmt(m.stock),fmt(m.demand_7),m.coverage_days==null?'Belum dapat dihitung':fmt(m.coverage_days)+' hari',fmt(m.suggested_portions)+' porsi'])));
  get('definitions').replaceChildren(...Object.values(s.definitions).map(t=>el('p','',t)),el('p','',`Penerimaan termasuk pajak: ${rp(s.current.collected_rp)}; pajak: ${rp(s.current.tax_rp)}.`));
 }
 api.load=async()=>{if(pending)return;pending=true;const rev=++revision;get('refresh').disabled=true;get('days').disabled=true;get('horizon').disabled=true;get('status').textContent='Membaca transaksi POS…';get('content').hidden=true;
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
