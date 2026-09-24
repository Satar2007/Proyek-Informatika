@auth
<div x-data="smartAssistant()" x-init="init()" x-ref="root" class="smart-assistant-root" :style="rootStyle">
    <div x-cloak x-show="proactiveVisible && !open" x-transition.opacity class="smart-proactive" @click="openFromProactive()">
        <button type="button" class="smart-proactive-close" @click.stop="dismissProactive()" aria-label="Tutup notifikasi">×</button>
        <div class="smart-proactive-label">✦ Smart Insight</div>
        <div class="smart-proactive-text" x-text="currentInsight"></div>
        <div class="smart-proactive-hint">Klik untuk melihat detail · atau abaikan saja</div>
    </div>

    <button type="button" @pointerdown="startDrag($event)" @click="toggleFromClick()" class="smart-fab" :class="{'is-dragging': dragging}" aria-label="Buka JIMNY Smart Assistant" title="Geser untuk memindahkan · Klik untuk membuka"> 
        <img src="{{ asset('images/coffee-cup-splash.png') }}" class="smart-fab-logo" alt="" draggable="false">
        <span class="smart-fab-pulse"></span>
    </button>

    <section x-cloak x-show="open" x-transition class="smart-panel" :style="panelStyle" @click.outside="open = false">
        <div class="smart-header">
            <div class="smart-avatar"><img src="{{ asset('images/coffee-cup-splash.png') }}" alt="" draggable="false"></div>
            <div class="min-w-0">
                <div class="smart-title">JIMNY Smart Assistant</div>
                <div class="smart-subtitle">Mode {{ ucfirst(auth()->user()->role) }} · berbasis data POS</div>
            </div>
            <button type="button" @click="open = false" class="smart-close">×</button>
        </div>

        <div class="smart-insights" x-show="insights.length">
            <template x-for="(item, index) in insights" :key="index">
                <div class="smart-insight" x-text="item"></div>
            </template>
        </div>

        <div class="smart-messages" x-ref="messages">
            <div class="smart-message smart-bot">
                <div class="smart-message-label">Smart Assistant</div>
                <div>Halo! Saya siap membantu pekerjaan {{ strtolower(auth()->user()->role) }}. Tanyakan sesuatu tentang stok, penjualan, menu, jam ramai, atau prediksi.</div>
            </div>
            <template x-for="(message, index) in messages" :key="index">
                <div :class="message.role === 'user' ? 'smart-message smart-user' : 'smart-message smart-bot'">
                    <div x-show="message.role !== 'user'" class="smart-message-label">Smart Assistant</div>
                    <div x-text="message.text"></div>
                </div>
            </template>
            <div x-show="loading" class="smart-message smart-bot smart-typing">Menganalisis data<span>...</span></div>
        </div>

        <div class="smart-suggestions">
            <button type="button" @click="ask('Stok apa yang perlu direstock?')">Cek stok</button>
            <button type="button" @click="ask('Menu paling laku?')">Menu laku</button>
            <button type="button" @click="ask('Omzet hari ini?')">Omzet</button>
            <button type="button" @click="ask('Prediksi penjualan minggu depan')">Prediksi</button>
        </div>

        <form @submit.prevent="ask(input)" class="smart-form">
            <input x-model="input" type="text" maxlength="500" placeholder="Tanya JIMNY Smart Assistant..." autocomplete="off">
            <button type="submit" :disabled="loading || !input.trim()">Kirim</button>
        </form>
    </section>
</div>

<style>
    [x-cloak]{display:none!important}
    .smart-assistant-root{position:fixed;left:calc(100vw - 90px);top:calc(100vh - 90px);z-index:10000;font-family:inherit;touch-action:none}
    .smart-proactive{position:absolute;right:0;bottom:70px;width:min(310px,calc(100vw - 28px));padding:13px 34px 12px 14px;border:1px solid #ead9cb;border-radius:17px;background:#fffdf9;color:#4B2E1F;box-shadow:0 18px 45px rgba(52,31,20,.20);cursor:pointer;touch-action:auto}
    .smart-proactive-label{font-size:10px;font-weight:900;color:#C98A4A;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
    .smart-proactive-text{font-size:12px;font-weight:800;line-height:1.45}
    .smart-proactive-hint{font-size:9px;color:#8a6a57;margin-top:5px}
    .smart-proactive-close{position:absolute;right:8px;top:7px;width:24px;height:24px;border:0;border-radius:999px;background:#f4ece5;color:#7B4B2A;font-size:18px;line-height:1;cursor:pointer}
    .smart-fab{position:relative;width:66px;height:66px;padding:0;border:1px solid rgba(246,211,143,.65);border-radius:22px;background:radial-gradient(circle at 50% 28%,#7a4423,#3a2013 72%);color:#fff;box-shadow:0 16px 34px rgba(50,24,10,.34),inset 0 1px rgba(255,255,255,.22);cursor:grab;display:grid;place-items:center;transition:.2s;-webkit-user-select:none;user-select:none}
    .smart-fab:hover{transform:translateY(-2px) scale(1.03)}.smart-fab.is-dragging{cursor:grabbing;transform:scale(1.04)}
    .smart-fab-logo{width:54px;height:54px;object-fit:contain;pointer-events:none;filter:drop-shadow(0 4px 6px rgba(0,0,0,.35))}
    .smart-fab-pulse{position:absolute;inset:-5px;border:2px solid rgba(224,173,83,.5);border-radius:26px;pointer-events:none;animation:smartPulse 2s infinite}
    @keyframes smartPulse{0%,100%{opacity:.15;transform:scale(1)}50%{opacity:.55;transform:scale(1.08)}}
    .smart-panel{position:fixed;width:min(390px,calc(100vw - 32px));height:min(650px,calc(100vh - 110px));display:flex;flex-direction:column;overflow:hidden;border:1px solid #e4d7cc;border-radius:24px;background:#fffdf9;box-shadow:0 25px 70px rgba(52,31,20,.25)}
    .smart-header{display:flex;align-items:center;gap:12px;padding:16px;background:#4B2E1F;color:white}
    .smart-avatar{width:44px;height:44px;display:grid;place-items:center;border-radius:15px;background:linear-gradient(135deg,#6a3517,#32190d);border:1px solid rgba(246,211,143,.55);font-weight:900;font-size:20px;flex:none;overflow:hidden}.smart-avatar img{width:38px;height:38px;object-fit:contain;pointer-events:none}
    .smart-title{font-weight:900;font-size:15px}.smart-subtitle{font-size:11px;color:#D9B08C;margin-top:2px}.smart-close{margin-left:auto;background:transparent;border:0;color:white;font-size:28px;cursor:pointer;line-height:1}
    .smart-insights{padding:10px 12px 0;display:grid;gap:7px;max-height:145px;overflow:auto}.smart-insight{padding:9px 11px;border-radius:12px;background:#f5eee8;border:1px solid #ead9cb;color:#5b3c2a;font-size:11px;line-height:1.4;font-weight:700}
    .smart-messages{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px}.smart-message{max-width:88%;padding:10px 12px;border-radius:15px;font-size:12px;line-height:1.5}.smart-bot{align-self:flex-start;background:#f3eee9;color:#4B2E1F;border-bottom-left-radius:5px}.smart-user{align-self:flex-end;background:#4B2E1F;color:#fff;border-bottom-right-radius:5px}.smart-message-label{font-size:10px;font-weight:900;color:#8a5b3c;margin-bottom:3px}.smart-user .smart-message-label{color:#D9B08C}.smart-typing{font-style:italic;color:#7B4B2A}.smart-suggestions{padding:0 12px 10px;display:flex;gap:6px;overflow-x:auto}.smart-suggestions button{white-space:nowrap;border:1px solid #decfc3;background:#fff;color:#5d3c29;border-radius:999px;padding:7px 10px;font-size:10px;font-weight:800;cursor:pointer}.smart-suggestions button:hover{background:#f7efe8}.smart-form{display:flex;gap:8px;padding:11px;border-top:1px solid #eaded5;background:#fff}.smart-form input{min-width:0;flex:1;border:1px solid #d8c9bd;border-radius:13px;padding:10px 12px;font-size:12px;outline:none;color:#1f1712!important}.smart-form input:focus{border-color:#C98A4A;box-shadow:0 0 0 3px rgba(201,138,74,.13)}.smart-form button{border:0;border-radius:13px;background:#C98A4A;color:#fff;padding:0 14px;font-weight:900;font-size:11px;cursor:pointer}.smart-form button:disabled{opacity:.5;cursor:not-allowed}
    @media(max-width:640px){.smart-panel{width:calc(100vw - 28px);height:min(620px,calc(100vh - 95px))}}
</style>

<script>
function smartAssistant(){
    return {
        open:false, loading:false, input:'', messages:[], insights:[], proactiveVisible:false, currentInsight:'', proactiveTimer:null, proactiveDismissed:false,
        dragging:false, moved:false, dragStartX:0, dragStartY:0, startLeft:0, startTop:0,
        left:null, top:null, suppressClick:false, panelStyle:{}, rootStyle:{},
        async init(){
            this.restorePosition();
            this.applyPosition();
            window.addEventListener('resize',()=>{ this.clampPosition(); this.applyPosition(); if(this.open)this.positionPanel(); });
            window.addEventListener('pointermove',(e)=>this.dragMove(e));
            window.addEventListener('pointerup',(e)=>this.endDrag(e));
            this.$nextTick(()=>this.positionPanel());
            try{
                const response = await fetch('{{ route('smart-assistant.insights') }}', {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
                if(response.ok){
                    const data=await response.json();
                    this.insights=data.messages||[];
                    this.prepareProactiveInsight();
                }
            }catch(e){}
            // Perbarui insight berkala agar assistant tetap mengikuti kondisi POS.
            setInterval(()=>this.refreshInsights(), 60000);
        },
        async refreshInsights(){
            try{
                const response = await fetch('{{ route('smart-assistant.insights') }}', {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Cache-Control':'no-cache'}});
                if(response.ok){
                    const data=await response.json();
                    this.insights=data.messages||[];
                    if(!this.open && this.insights.length){
                        this.currentInsight=this.insights[0];
                        if(!this.proactiveDismissed)this.proactiveVisible=true;
                    }
                }
            }catch(e){}
        },
        prepareProactiveInsight(){
            if(!this.insights.length)return;
            const path=window.location.pathname.toLowerCase();
            const role='{{ auth()->user()->role }}';
            let preferred=this.insights.filter(text=>{
                if(role==='kasir' && (path.includes('transaksi') || path.includes('kasir'))) return /stok|transaksi|menu/i.test(text);
                if(role==='admin' && (path.includes('admin') || path.includes('menu') || path.includes('stok'))) return /stok|menu|transaksi/i.test(text);
                if(role==='owner' && path.includes('owner')) return /omzet|menu|ramai|penjualan|prediksi/i.test(text);
                return true;
            });
            const pool=preferred.length?preferred:this.insights;
            this.currentInsight=pool[0];
            this.proactiveDismissed=false;
            setTimeout(()=>{ if(!this.open && !this.proactiveDismissed)this.proactiveVisible=true; },900);
            if(pool.length>1){
                let index=0;
                this.proactiveTimer=setInterval(()=>{
                    if(this.open || this.proactiveDismissed)return;
                    index=(index+1)%pool.length;
                    this.currentInsight=pool[index];
                    this.proactiveVisible=true;
                },12000);
            }
        },
        dismissProactive(){
            this.proactiveDismissed=true;
            this.proactiveVisible=false;
        },
        openFromProactive(){
            this.proactiveVisible=false;
            this.open=true;
            this.proactiveDismissed=true;
            this.$nextTick(()=>this.positionPanel());
        },
        restorePosition(){
            try{
                const saved=JSON.parse(localStorage.getItem('jimnySmartAssistantPosition')||'null');
                if(saved && Number.isFinite(saved.left) && Number.isFinite(saved.top)){ this.left=saved.left; this.top=saved.top; }
            }catch(e){}
            if(this.left===null){
                // Posisi awal: di layar kasir tombol diletakkan di tengah bawah area konten agar tidak menutup tombol pembayaran.
                const onPos=document.body.classList.contains('pos-screen') && window.innerWidth>=1024;
                if(onPos){
                    const sidebar=document.querySelector('.app-sidebar');
                    const offset=sidebar?sidebar.getBoundingClientRect().right:0;
                    this.left=offset+(window.innerWidth-offset)/2-33;
                } else { this.left=window.innerWidth-90; }
                this.top=window.innerHeight-90;
            }
            this.clampPosition();
        },
        clampPosition(){
            const size=66, margin=8;
            this.left=Math.max(margin,Math.min(Number(this.left)||margin,window.innerWidth-size-margin));
            this.top=Math.max(margin,Math.min(Number(this.top)||margin,window.innerHeight-size-margin));
        },
        applyPosition(){
            this.rootStyle={left:this.left+'px',top:this.top+'px',right:'auto',bottom:'auto'};
        },
        startDrag(e){
            if(e.button!==undefined && e.button!==0)return;
            this.dragging=true; this.moved=false; this.suppressClick=false;
            this.dragStartX=e.clientX; this.dragStartY=e.clientY; this.startLeft=this.left; this.startTop=this.top;
            try{e.currentTarget.setPointerCapture(e.pointerId)}catch(err){}
        },
        dragMove(e){
            if(!this.dragging)return;
            const dx=e.clientX-this.dragStartX, dy=e.clientY-this.dragStartY;
            if(Math.abs(dx)>5 || Math.abs(dy)>5){ this.moved=true; this.suppressClick=true; }
            if(!this.moved)return;
            this.left=this.startLeft+dx; this.top=this.startTop+dy; this.clampPosition(); this.applyPosition();
            if(this.open)this.positionPanel();
        },
        endDrag(){
            if(!this.dragging)return;
            this.dragging=false;
            if(this.moved){
                localStorage.setItem('jimnySmartAssistantPosition',JSON.stringify({left:this.left,top:this.top}));
                setTimeout(()=>this.suppressClick=false,80);
            }
        },
        toggleFromClick(){
            if(this.suppressClick)return;
            this.open=!this.open;
            this.proactiveVisible=false;
            if(this.open)this.$nextTick(()=>this.positionPanel());
        },
        positionPanel(){
            if(!this.open)return;
            const gap=12, button=66, width=Math.min(390,window.innerWidth-32);
            const height=Math.min(650,window.innerHeight-110);
            let x=this.left+button-width;
            if(x<16)x=16;
            if(x+width>window.innerWidth-16)x=window.innerWidth-width-16;
            let y=this.top-height-gap;
            if(y<16)y=this.top+button+gap;
            if(y+height>window.innerHeight-16)y=Math.max(16,window.innerHeight-height-16);
            this.panelStyle={left:x+'px',top:y+'px',right:'auto',bottom:'auto'};
        },
        async ask(text){
            text=(text||'').trim(); if(!text||this.loading)return;
            this.messages.push({role:'user',text}); this.input=''; this.loading=true; this.scroll();
            try{
                const response=await fetch('{{ route('smart-assistant.chat') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({message:text})});
                const data=await response.json();
                this.messages.push({role:'bot',text:data.answer||'Maaf, saya belum dapat membaca permintaan itu.'});
            }catch(e){this.messages.push({role:'bot',text:'Terjadi kendala saat membaca data. Pastikan Laravel dan database sedang aktif.'});}
            finally{this.loading=false; this.scroll();}
        },
        scroll(){this.$nextTick(()=>{if(this.$refs.messages)this.$refs.messages.scrollTop=this.$refs.messages.scrollHeight;});}
    }
}
</script>
@endauth
