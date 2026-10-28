@auth
<!-- JIMNY_OWNER_V20: integrated into existing JSA, no separate dashboard. -->
<link rel="stylesheet" href="{{ asset('css/jimny-owner-v20.css') }}?v=20.0.0">
<script src="{{ asset('js/jimny-owner-v20.js') }}?v=20.0.0" defer></script>
<div x-data="smartAssistant()" x-init="init()" x-ref="root" class="smart-assistant-root smart-v3-root smart-v4-root smart-v5-root jimny-glass-v9" :class="{'j5-live': motionOn}" :style="rootStyle" @keydown.escape.window="if(open) closePanel()">
    <div x-cloak x-show="proactiveVisible && !open" x-transition.opacity class="smart-proactive" x-ref="bubble" :style="bubbleStyle" @click="openFromProactive()">
        <button type="button" class="smart-proactive-close" @click.stop="dismissProactive()" aria-label="Tutup notifikasi">×</button>
        <div class="smart-proactive-label">Smart Insight</div>
        <div class="smart-proactive-text" x-text="currentInsight"></div>
        <div class="smart-proactive-hint">Klik untuk melihat detail · otomatis hilang dalam 5 detik</div>
    </div>

    <button type="button" @pointerdown="startDrag($event)" @click="toggleFromClick()" class="smart-fab" :class="{'is-dragging': dragging}" x-show="!open" aria-label="Buka JIMNY Smart Assistant" title="Geser untuk memindahkan · Klik untuk membuka"> 
        <img src="{{ asset('images/smart-assistant-barista.jpg') }}" class="smart-fab-logo" alt="" draggable="false">
        <span class="smart-fab-pulse"></span>
    </button>

    <!-- One-step assistant: backdrop and centered modal for all roles. -->
    <div x-cloak x-show="open"
         x-transition:enter="smart-v3-backdrop-transition"
         x-transition:enter-start="smart-v3-backdrop-off"
         x-transition:enter-end="smart-v3-backdrop-on"
         x-transition:leave="smart-v3-backdrop-transition"
         x-transition:leave-start="smart-v3-backdrop-on"
         x-transition:leave-end="smart-v3-backdrop-off"
         class="smart-v2-backdrop smart-v3-backdrop"
         aria-hidden="true" @click="closePanel()"></div>

    <section x-cloak x-show="open"
             x-transition:enter="smart-v3-panel-transition"
             x-transition:enter-start="smart-v3-panel-off"
             x-transition:enter-end="smart-v3-panel-on"
             x-transition:leave="smart-v3-panel-transition"
             x-transition:leave-start="smart-v3-panel-on"
             x-transition:leave-end="smart-v3-panel-off"
             class="smart-panel smart-v3-unified jimny-glass-shell"
             :class="{'smart-v3-chat-only': !canAnalytics, 'smart-v3-mobile-analytics': activeTab === 'analytics'}"
             x-ref="panel" :style="panelStyle"
             role="dialog" aria-modal="true" aria-labelledby="jimny-assistant-title"
             @keydown.tab="trapFocus($event)">
        <div class="smart-header smart-v3-header">
            <span class="j5-header-streak" aria-hidden="true"></span>
            <span class="j5-header-sparkles" aria-hidden="true"><i></i><i></i><i></i></span>
            <div class="smart-avatar"><img src="{{ asset('images/smart-assistant-barista.jpg') }}" alt="" draggable="false"></div>
            <div class="smart-v3-brand">
                <div class="smart-v3-overline"><span class="smart-v3-live-dot"></span> JIMNY INTELLIGENCE <span class="smart-v3-overline-divider">/</span> POS ASSISTANT</div>
                <div class="smart-title" id="jimny-assistant-title">JIMNY Smart Assistant</div>
                <div class="smart-subtitle">Mode {{ ucfirst(auth()->user()->role) }} · Satu sumber data POS</div>
            </div>
            <span class="smart-v3-header-status" aria-label="Berbasis data POS"><span class="smart-v3-live-dot"></span> Database POS • AI Lokal</span>
            <button type="button" @click="closePanel()" class="smart-close smart-v3-close" aria-label="Tutup Smart Assistant" title="Tutup (Esc)">
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <div class="smart-v3-mobile-tabs" x-show="canAnalytics" role="tablist" aria-label="Navigasi Smart Assistant">
            <button type="button" role="tab" :aria-selected="(activeTab === 'chat').toString()" :class="{'is-active': activeTab === 'chat'}" @click="setActiveTab('chat')">Percakapan</button>
            <button type="button" role="tab" :aria-selected="(activeTab === 'analytics').toString()" :class="{'is-active': activeTab === 'analytics'}" @click="setActiveTab('analytics')">Analitik & Insight</button>
        </div>

        <div class="smart-v2-chat">
        <!-- Compact operational header for cashier; never renders owner dashboards. -->
        <div class="smart-v10-cashier-hero" x-show="!canAnalytics" x-cloak>
            <div class="smart-v10-cashier-kicker"><span class="smart-v3-live-dot"></span> ASISTEN OPERASIONAL</div>
            <h2>Siap bantu shift-mu.</h2>
            <p>Cek stok dan menu favorit pelanggan, langsung dari POS.</p>
        </div>

        <div class="smart-insights"
             x-show="insights.length && !canAnalytics"
             aria-label="Ringkasan Smart Insight">

            <div class="smart-v2-insights-list"
                 :class="{'is-expanded': showAllInsights}">
                <template x-for="(item, index) in insights" :key="index">
                    <div class="smart-insight"
                         x-show="showAllInsights || index < 2"
                         x-text="item"></div>
                </template>
            </div>

            <button type="button"
                    class="smart-v2-insights-toggle"
                    x-show="insights.length > 2"
                    :aria-expanded="showAllInsights.toString()"
                    @click="showAllInsights = !showAllInsights"
                    x-text="showAllInsights ? 'Ringkas insight' : 'Lihat semua (' + insights.length + ')'">
            </button>
        </div>

        <div class="smart-v2-chat-head">
            <div class="smart-v2-chat-head-title">
                <span class="smart-v2-chat-dot"></span>
                {{ in_array(auth()->user()->role, ['owner', 'admin']) ? 'JIMNY AI Business' : 'JIMNY AI Kasir' }}
            </div>
            <div class="smart-v2-chat-head-desc">
                {{ in_array(auth()->user()->role, ['owner', 'admin']) ? 'Tanya penjualan, stok, atau hasil prediksi.' : 'Tanya stok, menu, dan bantuan operasional.' }}
            </div>
        </div>
        <!-- V10: separate message ROW from CONTENT bubble; fixes flex stretching and clipping. -->
        <div class="smart-messages smart-v10-messages" x-ref="messages" role="log" aria-live="polite" aria-label="Riwayat percakapan">
            <div class="smart-v10-message-row is-bot">
                <div class="smart-v10-bubble smart-message smart-bot">
                    <div class="smart-v10-message-text">Halo! Aku JIMNY, siap bantu cek penjualan, menu, atau stok.</div>
                </div>
            </div>
            <template x-for="(message, index) in messages" :key="index">
                <div class="smart-v10-message-row" :class="message.role === 'user' ? 'is-user' : 'is-bot'">
                    <div class="smart-v10-bubble smart-message" :class="message.role === 'user' ? 'smart-user' : 'smart-bot'">
                        <div class="smart-v10-message-text" x-text="message.text"></div>
                    </div>
                </div>
            </template>
            <div x-show="loading && !streamingReply" class="smart-v10-message-row is-bot" role="status">
                <div class="smart-v10-bubble smart-message smart-bot smart-typing">
                    <span class="smart-v3-typing-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span class="sr-only">JIMNY sedang menjawab...</span>
                </div>
            </div>
        </div>

        <div class="smart-suggestions" aria-label="Pertanyaan cepat">
            <button type="button" @click="ask('Stok apa yang perlu direstock?')">Cek stok</button>
            <button type="button" @click="ask('Menu paling laku?')">Menu laku</button>
            <button type="button" x-show="canAnalytics" @click="ask('Berapa penjualan pada periode laporan ini?')">Omzet</button>
            <button type="button" x-show="canAnalytics" @click="ask('Prediksi penjualan minggu depan')">Prediksi</button>
            <button type="button" x-show="canAnalytics" @click="ask('Apa prioritas owner berdasarkan periode laporan ini?')">Prioritas owner</button>
        </div>

        <form @submit.prevent="ask(input)" class="smart-form">
            <input x-ref="chatInput" x-model="input" type="text" maxlength="500" aria-label="Tulis pertanyaan" placeholder="Tanyakan sesuatu tentang bisnis..." autocomplete="off">
            <button type="submit" :disabled="loading || !input.trim()" aria-label="Kirim pertanyaan"><span>Kirim</span><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
        </form>
        </div>

        @if(in_array(auth()->user()->role, ['owner','admin']))
        <div class="smart-v2-dashboard j20-host" x-show="canAnalytics">
            @include('components.jimny-owner-v20')
        </div>
        @endif
    </section>
</div>

<style>
    [x-cloak]{display:none!important}
    .smart-assistant-root{position:fixed;left:calc(100vw - 90px);top:calc(100vh - 90px);z-index:10000;font-family:inherit;touch-action:none}
    .smart-proactive{position:fixed;right:auto;bottom:auto;width:min(310px,calc(100vw - 24px));max-height:calc(100dvh - 24px);overflow:auto;box-sizing:border-box;padding:13px 34px 12px 14px;border:1px solid #ead9cb;border-radius:17px;background:#fffdf9;color:#4B2E1F;box-shadow:0 18px 45px rgba(52,31,20,.20);cursor:pointer;touch-action:auto}
    .smart-proactive-label{font-size:10px;font-weight:900;color:#C98A4A;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
    .smart-proactive-text{font-size:12px;font-weight:800;line-height:1.45}
    .smart-proactive-hint{font-size:9px;color:#8a6a57;margin-top:5px}
    .smart-proactive-close{position:absolute;right:8px;top:7px;width:24px;height:24px;border:0;border-radius:999px;background:#f4ece5;color:#7B4B2A;font-size:18px;line-height:1;cursor:pointer}
    .smart-fab{position:relative;width:66px;height:66px;padding:0;border:1px solid rgba(246,211,143,.65);border-radius:22px;background:#fff;color:#fff;box-shadow:0 16px 34px rgba(50,24,10,.34),inset 0 1px rgba(255,255,255,.22);cursor:grab;display:grid;place-items:center;transition:.2s;-webkit-user-select:none;user-select:none}
    .smart-fab:hover{transform:translateY(-2px) scale(1.03)}.smart-fab.is-dragging{cursor:grabbing;transform:scale(1.04)}
    .smart-fab-logo{width:54px;height:54px;object-fit:contain;pointer-events:none;border-radius:14px}
    .smart-fab-pulse{position:absolute;inset:-5px;border:2px solid rgba(224,173,83,.5);border-radius:26px;pointer-events:none;display:none}
    @keyframes smartPulse{0%,100%{opacity:.15;transform:scale(1)}50%{opacity:.55;transform:scale(1.08)}}
    .smart-panel{touch-action:auto;position:fixed;width:min(390px,calc(100vw - 32px));height:min(650px,calc(100vh - 110px));display:flex;flex-direction:column;overflow:hidden;border:1px solid #e4d7cc;border-radius:24px;background:#fffdf9;box-shadow:0 25px 70px rgba(52,31,20,.25)}
    .smart-header{display:flex;align-items:center;gap:12px;padding:16px;background:#4B2E1F;color:white}
    .smart-avatar{width:44px;height:44px;display:grid;place-items:center;border-radius:15px;background:#fff;border:1px solid rgba(246,211,143,.55);font-weight:900;font-size:20px;flex:none;overflow:hidden}.smart-avatar img{width:38px;height:38px;object-fit:contain;pointer-events:none}
    .smart-title{font-weight:900;font-size:15px}.smart-subtitle{font-size:11px;color:#D9B08C;margin-top:2px}.smart-close{margin-left:auto;background:transparent;border:0;color:white;font-size:28px;cursor:pointer;line-height:1}
    .smart-insights{padding:10px 12px 0;display:grid;gap:7px;max-height:145px;overflow:auto}.smart-insight{padding:9px 11px;border-radius:12px;background:#f5eee8;border:1px solid #ead9cb;color:#5b3c2a;font-size:11px;line-height:1.4;font-weight:700}
    .smart-messages{min-height:0;flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px}.smart-message{max-width:88%;padding:10px 12px;border-radius:15px;font-size:12px;line-height:1.5}.smart-bot{align-self:flex-start;background:#f3eee9;color:#4B2E1F;border-bottom-left-radius:5px}.smart-user{align-self:flex-end;background:#4B2E1F;color:#fff;border-bottom-right-radius:5px}.smart-message-label{font-size:10px;font-weight:900;color:#8a5b3c;margin-bottom:3px}.smart-user .smart-message-label{color:#D9B08C}.smart-typing{font-style:italic;color:#7B4B2A}.smart-suggestions{padding:0 12px 10px;display:flex;gap:6px;overflow-x:auto}.smart-suggestions button{white-space:nowrap;border:1px solid #decfc3;background:#fff;color:#5d3c29;border-radius:999px;padding:7px 10px;font-size:10px;font-weight:800;cursor:pointer}.smart-suggestions button:hover{background:#f7efe8}.smart-form{display:flex;gap:8px;padding:11px;border-top:1px solid #eaded5;background:#fff}.smart-form input{min-width:0;flex:1;border:1px solid #d8c9bd;border-radius:13px;padding:10px 12px;font-size:12px;outline:none;color:#1f1712!important}.smart-form input:focus{border-color:#C98A4A;box-shadow:0 0 0 3px rgba(201,138,74,.13)}.smart-form button{border:0;border-radius:13px;background:#C98A4A;color:#fff;padding:0 14px;font-weight:900;font-size:11px;cursor:pointer}.smart-form button:disabled{opacity:.5;cursor:not-allowed}
    @media(max-width:640px){.smart-panel{width:calc(100vw - 28px);height:min(620px,calc(100vh - 95px))}}
    .smart-v2-backdrop{
        position:fixed;inset:0;z-index:0;
        background:rgba(34,21,14,.52);
        backdrop-filter:blur(5px);
        touch-action:auto;
    }
    .smart-panel.smart-v2-expanded{
        z-index:2;
        border-radius:22px;
        max-width:none;
        max-height:none;
    }
    .smart-v2-expand-button{
        margin-left:auto;flex:none;
        border:1px solid rgba(255,255,255,.35);
        color:#fff;background:rgba(255,255,255,.12);
        border-radius:10px;padding:8px 11px;
        font-size:11px;font-weight:800;cursor:pointer;
    }
    .smart-v2-expand-button + .smart-close{margin-left:0}
    .smart-v2-chat{
        flex:1;min-height:0;
        display:flex;flex-direction:column;
        overflow:hidden;
    }
    .smart-v2-tabs{
        display:flex;gap:8px;padding:10px 16px;
        border-bottom:1px solid #eadbd0;
        background:#fffaf6;
    }
    .smart-v2-tabs button{
        padding:9px 16px;border:0;border-radius:10px;
        background:transparent;color:#71523c;
        font-size:12px;font-weight:800;cursor:pointer;
    }
    .smart-v2-tabs button.active{
        background:#4B2E1F;color:white;
    }
    .smart-v2-dashboard{
        flex:1;min-height:0;
        overflow-y:auto;overscroll-behavior:contain;
        padding:22px;background:#F8F4EF;
        color:#4B2E1F;touch-action:pan-y;
    }
    .smart-v2-heading{
        display:flex;justify-content:space-between;
        align-items:center;gap:12px;margin-bottom:18px;
    }
    .smart-v2-eyebrow{
        font-size:10px;font-weight:900;
        letter-spacing:2px;color:#A66A43;
    }
    .smart-v2-heading h2{
        font-size:22px;font-weight:900;
        margin:4px 0;color:#4B2E1F;
    }
    .smart-v2-heading p{
        font-size:12px;color:#967966;
    }
    .smart-v2-refresh{
        border:1px solid #decbbb;border-radius:12px;
        background:#fff;padding:10px 14px;
        color:#674531;font-size:12px;
        font-weight:800;cursor:pointer;
    }
    .smart-v2-notice{
        padding:13px 16px;border-radius:12px;
        border:1px solid #e8d4ba;
        background:#fff7e9;color:#78502e;
        font-size:12px;margin-bottom:14px;
    }
    .smart-v2-kpis{
        display:grid;grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;margin-bottom:14px;
    }
    .smart-v2-kpi{
        background:#fff;border:1px solid #eadfd4;
        border-radius:16px;padding:16px;
        display:flex;flex-direction:column;gap:9px;
    }
    .smart-v2-kpi span{
        font-size:11px;color:#987865;font-weight:700;
    }
    .smart-v2-kpi strong{
        font-size:27px;color:#4B2E1F;
    }
    .smart-v2-kpi strong.smart-v2-status{font-size:15px}
    .smart-v2-charts{
        display:grid;grid-template-columns:repeat(2,minmax(0,1fr));
        gap:14px;
    }
    .smart-v2-chart-card{
        background:white;border:1px solid #eadfd4;
        border-radius:17px;padding:19px;
        min-width:0;min-height:220px;
    }
    .smart-v2-chart-card h3{
        font-size:15px;font-weight:900;
        color:#4B2E1F;margin-bottom:3px;
    }
    .smart-v2-chart-card p{
        font-size:11px;color:#9a7b68;
        margin-bottom:18px;
    }
    .smart-v2-line-chart{
        display:block;width:100%;height:auto;
        max-height:210px;
    }
    .smart-v2-empty{
        padding:18px;border:1px dashed #dcc8b9;
        border-radius:12px;background:#fffcf8;
        color:#927664;font-size:12px;
        line-height:1.6;
    }
    .smart-v2-bar-list{
        display:flex;flex-direction:column;gap:15px;
    }
    .smart-v2-bar-label{
        display:flex;justify-content:space-between;
        gap:8px;margin-bottom:6px;
        font-size:11px;color:#60432f;
    }
    .smart-v2-track{
        height:11px;border-radius:999px;
        background:#eee1d5;overflow:hidden;
    }
    .smart-v2-fill{
        height:100%;border-radius:999px;
        background:linear-gradient(90deg,#C98A4A,#87502e);
    }
    .smart-v2-hours{
        display:flex;align-items:flex-end;gap:8px;
        height:140px;overflow-x:auto;
    }
    .smart-v2-hour{
        display:flex;flex-direction:column;
        align-items:center;gap:6px;
        min-width:24px;height:100%;
        font-size:10px;color:#785a47;
    }
    .smart-v2-hour-track{
        flex:1;width:18px;display:flex;
        align-items:flex-end;
        border-radius:5px;background:#f5ede6;
        overflow:hidden;
    }
    .smart-v2-hour-track div{
        width:100%;border-radius:5px 5px 0 0;
        background:#A66A43;
    }
    .smart-v2-disclaimer{
        margin-top:16px;padding:12px;
        font-size:11px;color:#846b59;
        line-height:1.6;
    }
    @media(max-width:760px){
        .smart-v2-dashboard{padding:12px}
        .smart-v2-heading h2{font-size:17px}
        .smart-v2-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
        .smart-v2-charts{grid-template-columns:1fr}
        .smart-v2-kpi{padding:12px}
        .smart-v2-kpi strong{font-size:22px}
        .smart-v2-chart-card{padding:14px}
    }
    /* JSA V2 - Unified Intelligence Overlay */

    .smart-panel.smart-v2-expanded {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 350px;
        grid-template-rows: auto minmax(0, 1fr);
        overflow: hidden;
    }

    .smart-panel.smart-v2-expanded .smart-header {
        grid-column: 1 / -1;
        grid-row: 1;
        min-width: 0;
    }

    .smart-panel.smart-v2-expanded .smart-v2-dashboard {
        grid-column: 1;
        grid-row: 2;
        height: 100%;
        min-width: 0;
        min-height: 0;
        overflow-y: auto;
        border-right: 1px solid #eadbd0;
    }

    .smart-panel.smart-v2-expanded .smart-v2-chat {
        grid-column: 2;
        grid-row: 2;
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 0;
        height: 100%;
        overflow: hidden;
        background: #fffdfa;
    }

    .smart-panel.smart-v2-expanded .smart-messages {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
    }

    .smart-panel.smart-v2-expanded .smart-suggestions {
        flex-shrink: 0;
    }

    .smart-panel.smart-v2-expanded .smart-form {
        flex-shrink: 0;
    }

    .smart-v2-preview .smart-v2-chart-card {
        min-height: 190px;
    }

    @media (max-width: 760px) {
        .smart-panel.smart-v2-expanded {
            grid-template-columns: minmax(0, 1fr);
            grid-template-rows:
                auto
                minmax(0, 1.2fr)
                minmax(160px, .8fr);
        }

        .smart-panel.smart-v2-expanded .smart-v2-dashboard {
            grid-column: 1;
            grid-row: 2;
            border-right: none;
            border-bottom: 1px solid #eadbd0;
        }

        .smart-panel.smart-v2-expanded .smart-v2-chat {
            grid-column: 1;
            grid-row: 3;
        }
    }
    /* JSA V2 - Analytics Filters */
    .smart-v2-heading{
        flex-wrap:wrap;
    }
    .smart-v2-actions{
        display:flex;
        align-items:end;
        flex-wrap:wrap;
        gap:9px;
    }
    .smart-v2-filter{
        display:flex;
        flex-direction:column;
        gap:5px;
        min-width:110px;
        color:#795a45;
        font-size:11px;
        font-weight:800;
    }
    .smart-v2-filter select{
        width:100%;
        max-width:210px;
        min-height:37px;
        padding:8px 30px 8px 10px;
        background:#fff;
        border:1px solid #decbbb;
        border-radius:10px;
        color:#4B2E1F;
        font-size:12px;
        outline-offset:2px;
        cursor:pointer;
    }
    .smart-v2-filter select:disabled{
        opacity:.55;
        cursor:wait;
    }
    @media(max-width:760px){
        .smart-v2-actions{
            width:100%;
        }
        .smart-v2-filter{
            flex:1;
        }
        .smart-v2-filter select{
            max-width:none;
        }
    }
    /* JSA V2 - Enhanced Data Visualizations */

    .smart-v2-chart-meta{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
        padding:9px 11px;
        margin-bottom:12px;
        background:#F8F1E9;
        border:1px solid #EBDDD0;
        border-radius:10px;
        color:#7C5C46;
        font-size:11px;
    }

    .smart-v2-chart-meta strong{
        color:#72472D;
        font-size:12px;
        font-weight:900;
    }

    .smart-v2-axis{
        display:flex;
        justify-content:space-between;
        gap:8px;
        margin-top:8px;
        color:#927462;
        font-size:10px;
        font-weight:700;
    }

    .smart-v2-stock-note{
        margin-bottom:7px;
        color:#927462;
        font-size:10px;
        line-height:1.45;
    }

    .smart-v2-fill-warning{
        background:linear-gradient(90deg,#E5A147,#B65F38);
    }

    .smart-v2-hour{
        cursor:help;
    }

    .smart-v2-hour:hover .smart-v2-hour-track div{
        background:#70432A;
    }

    .smart-v2-line-chart circle{
        transition:r .15s ease;
    }

    .smart-v2-line-chart circle:hover{
        r:7;
        cursor:crosshair;
    }

    .smart-v2-preview .smart-v2-chart-card{
        background:linear-gradient(
            150deg,#FFFFFF 20%,#FFFBF6 100%
        );
    }

    .smart-v2-preview .smart-v2-empty{
        border-color:#DCC8B7;
        background:#FDF7F0;
    }
    /* JSA V2 - Insight & Conversation Polish */

    .smart-insights{
        display:flex;
        flex-direction:column;
        gap:7px;
        padding:10px 12px 8px;
        max-height:none;
        overflow:visible;
        flex-shrink:0;
    }

    .smart-v2-insights-list{
        display:grid;
        gap:7px;
    }

    .smart-v2-insights-list.is-expanded{
        max-height:220px;
        overflow-y:auto;
        overscroll-behavior:contain;
        padding-right:3px;
    }

    .smart-v2-insights-toggle{
        align-self:flex-start;
        background:#FFF8F1;
        border:1px solid #E8D3BE;
        border-radius:9px;
        color:#795038;
        padding:6px 10px;
        font-size:10px;
        font-weight:800;
        cursor:pointer;
    }

    .smart-v2-insights-toggle:hover{
        background:#F4E6D8;
    }

    .smart-v2-chat-head{
        flex-shrink:0;
        padding:12px 15px;
        background:#FFFCF8;
        border-bottom:1px solid #EDE1D5;
    }

    .smart-v2-chat-head-title{
        display:flex;
        align-items:center;
        gap:8px;
        color:#4B2E1F;
        font-size:12px;
        font-weight:900;
    }

    .smart-v2-chat-dot{
        width:8px;
        height:8px;
        border-radius:50%;
        background:#538B62;
        flex:none;
    }

    .smart-v2-chat-head-desc{
        margin-top:4px;
        color:#987966;
        font-size:10px;
        line-height:1.4;
    }

    @media(max-width:640px){
        .smart-v2-insights-list.is-expanded{
            max-height:160px;
        }

        .smart-v2-chat-head{
            padding:9px 12px;
        }
    }
    /* JSA V2 - Final Analytics Readability */

    .smart-v2-kpi-scope{
        margin-bottom:12px;
        color:#8D705C;
        font-size:11px;
        line-height:1.6;
    }

    .smart-v2-chart-scope{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:8px;
        flex-wrap:wrap;
        margin:-4px 0 12px;
        color:#846750;
        font-size:10px;
    }

    .smart-v2-chart-scope strong{
        color:#653E28;
        font-size:12px;
        font-weight:900;
    }

    .smart-v2-svg-label{
        fill:#886D5B;
        font-size:13px;
        font-weight:700;
    }

    .smart-v2-hours-toolbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:8px;
        flex-wrap:wrap;
        margin-bottom:12px;
        color:#937360;
        font-size:10px;
    }

    .smart-v2-hours-toolbar button{
        border:1px solid #DFCBBB;
        background:#FFF9F3;
        border-radius:9px;
        padding:7px 10px;
        color:#69442C;
        font-size:10px;
        font-weight:800;
        cursor:pointer;
    }

    .smart-v2-hours-toolbar button:hover{
        background:#F2E4D7;
    }

    .smart-v2-hour-value{
        font-size:10px;
        color:#79533A;
        font-weight:900;
        line-height:1;
    }

    .smart-v2-hour{
        min-width:30px;
    }

    @media(max-width:760px){
        .smart-v2-hours-toolbar{
            align-items:flex-start;
        }

        .smart-v2-chart-scope{
            font-size:10px;
        }
    }

    /* JIMNY V3 · single-stage coffee intelligence experience */
    .smart-v3-root {
        --j3-espresso:#2e1b14;--j3-coffee:#4b2e1f;--j3-mocha:#715039;
        --j3-caramel:#c28a55;--j3-cream:#faf7f2;--j3-milk:#fffdf9;
        --j3-border:#ebdfd3;--j3-muted:#957965;--j3-success:#5b966b;
    }
    .smart-v3-root .smart-v3-backdrop {
        z-index:0;
        background:radial-gradient(ellipse at 50% 15%,rgba(138,92,58,.28),transparent 62%),rgba(26,17,12,.70);
        -webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
    }
    .smart-v3-backdrop-transition {transition:opacity .28s ease}
    .smart-v3-backdrop-off {opacity:0}
    .smart-v3-backdrop-on {opacity:1}
    .smart-v3-panel-transition {transition:opacity .32s cubic-bezier(.19,1,.22,1),transform .38s cubic-bezier(.2,.8,.2,1)}
    .smart-v3-panel-off {opacity:0;transform:translateY(26px) scale(.965)}
    .smart-v3-panel-on {opacity:1;transform:translateY(0) scale(1)}
    .smart-v3-root .smart-fab {animation:j3-fab-entry .6s both;box-shadow:0 12px 38px rgba(45,21,10,.24),0 0 0 5px rgba(194,138,85,.12)}
    .smart-v3-root .smart-fab:focus-visible,.smart-v3-root button:focus-visible,.smart-v3-root input:focus-visible,.smart-v3-root select:focus-visible {outline:3px solid var(--j3-caramel);outline-offset:3px}
    .smart-v3-root .smart-fab-pulse {display:block;animation:smartPulse 3.2s ease-in-out infinite}
    .smart-v3-root .smart-fab:hover {box-shadow:0 18px 42px rgba(45,21,10,.32),0 0 0 7px rgba(194,138,85,.18)}
    .smart-panel.smart-v3-unified {
        z-index:2;display:grid;grid-template-columns:minmax(0,1fr) clamp(340px,30%,425px);
        grid-template-rows:76px minmax(0,1fr);width:100%;height:100%;
        max-width:none;max-height:none;min-width:0;min-height:0;
        overflow:hidden;border:1px solid rgba(230,212,192,.9);border-radius:24px;
        background:var(--j3-cream);color:var(--j3-espresso);
        box-shadow:0 40px 120px rgba(18,11,7,.43),0 0 0 1px rgba(255,255,255,.10) inset;
        isolation:isolate;
    }
    .smart-panel.smart-v3-unified::after {
        content:'';position:absolute;inset:auto 0 0;height:3px;
        pointer-events:none;z-index:5;background:linear-gradient(90deg,transparent,#c58b52 25%,#f0d9aa 50%,#a9673b 75%,transparent);opacity:.65;
    }
    .smart-panel.smart-v3-chat-only {grid-template-columns:minmax(0,1fr);max-width:none}
    .smart-panel.smart-v3-unified .smart-v3-header {
        grid-column:1/-1;grid-row:1;display:flex;align-items:center;min-width:0;
        padding:13px 24px;gap:13px;position:relative;z-index:3;
        background:linear-gradient(112deg,#302016 0%,#4b2f21 52%,#67432d 100%);
        border-bottom:1px solid rgba(255,255,255,.11);
    }
    .smart-panel.smart-v3-unified .smart-v3-header::before {
        content:'';position:absolute;inset:0;pointer-events:none;opacity:.26;
        background:radial-gradient(ellipse at 74% 0%,rgba(227,178,107,.34),transparent 48%);
    }
    .smart-panel.smart-v3-unified .smart-avatar {width:46px;height:46px;border-radius:14px;border:1px solid rgba(255,230,191,.48);box-shadow:0 4px 18px rgba(0,0,0,.13)}
    .smart-v3-brand {min-width:0;flex:1;position:relative}
    .smart-v3-overline {display:flex;align-items:center;gap:7px;color:#e3bf9b;font-size:8px;letter-spacing:.16em;font-weight:900}
    .smart-v3-overline-divider {opacity:.55}
    .smart-v3-live-dot {display:inline-block;flex:none;width:7px;height:7px;border-radius:50%;background:#8aca93;box-shadow:0 0 0 4px rgba(138,202,147,.12);animation:j3-status-pulse 2.8s infinite}
    .smart-panel.smart-v3-unified .smart-title {font-size:17px;letter-spacing:-.025em;line-height:1.35;margin:3px 0 0}
    .smart-panel.smart-v3-unified .smart-subtitle {font-size:10px;color:#e4cbb4;line-height:1.45}
    .smart-v3-header-status {display:flex;align-items:center;gap:9px;white-space:nowrap;color:#ebd4b9;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:999px;font-size:10px;font-weight:800;padding:8px 12px;position:relative}
    .smart-panel.smart-v3-unified .smart-v3-close {margin-left:4px;flex:none;display:grid;place-items:center;width:38px;height:38px;border-radius:12px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.09);transition:background .2s,transform .2s}
    .smart-panel.smart-v3-unified .smart-v3-close:hover {background:rgba(255,255,255,.2);transform:rotate(90deg)}
    .smart-v3-close svg {width:19px;height:19px}
    .smart-panel.smart-v3-unified .smart-v2-dashboard {
        grid-column:1;grid-row:2;display:block;min-width:0;min-height:0;height:100%;
        padding:26px clamp(18px,2.4vw,38px) 30px;overflow:auto;overscroll-behavior:contain;
        background:radial-gradient(circle at 0 0,rgba(222,193,155,.18),transparent 32%),var(--j3-cream);
        border-right:1px solid var(--j3-border);scrollbar-width:thin;scrollbar-color:#d3bba7 transparent;
    }
    .smart-panel.smart-v3-unified .smart-v2-chat {
        grid-column:2;grid-row:2;display:flex;flex-direction:column;min-width:0;min-height:0;
        overflow:hidden;background:var(--j3-milk);height:100%;
    }
    .smart-panel.smart-v3-chat-only .smart-v2-chat {grid-column:1}
    .smart-panel.smart-v3-unified .smart-v2-heading {gap:20px;align-items:end;margin-bottom:20px}
    .smart-panel.smart-v3-unified .smart-v2-eyebrow {display:flex;align-items:center;gap:8px;color:#ad794e;letter-spacing:.16em;font-size:10px}
    .smart-v3-eyebrow-mark {width:18px;height:2px;display:inline-block;background:var(--j3-caramel);border-radius:3px}
    .smart-panel.smart-v3-unified .smart-v2-heading h2 {font-size:clamp(23px,2.2vw,31px);letter-spacing:-.045em;line-height:1.18;margin:8px 0}
    .smart-panel.smart-v3-unified .smart-v2-heading h2 em {font-style:normal;color:#ad744a}
    .smart-panel.smart-v3-unified .smart-v2-heading p {font-size:11px;line-height:1.65;max-width:410px;color:#8b7260;margin:0}
    .smart-panel.smart-v3-unified .smart-v2-actions {gap:8px;flex-wrap:wrap}
    .smart-panel.smart-v3-unified .smart-v2-filter {font-size:10px;gap:5px;min-width:100px}
    .smart-panel.smart-v3-unified .smart-v2-filter select {max-width:195px;min-height:40px;border:1px solid #e4d6c7;border-radius:11px;background:#fffdf9;font-size:11px;font-weight:750;box-shadow:0 2px 6px rgba(61,40,25,.03);transition:border-color .2s,box-shadow .2s}
    .smart-panel.smart-v3-unified .smart-v2-filter select:hover {border-color:#c99d74}
    .smart-panel.smart-v3-unified .smart-v2-refresh {min-height:40px;padding:9px 16px;border-radius:11px;background:var(--j3-coffee);color:white;border:0;font-size:11px;box-shadow:0 6px 14px rgba(65,37,22,.15);transition:transform .18s,box-shadow .18s}
    .smart-panel.smart-v3-unified .smart-v2-refresh:hover:not(:disabled) {transform:translateY(-2px);box-shadow:0 9px 18px rgba(65,37,22,.22)}
    .smart-panel.smart-v3-unified .smart-v2-kpi-scope {font-size:10px;margin:0 0 12px;color:#9a7a65}
    .smart-panel.smart-v3-unified .smart-v2-kpis {grid-template-columns:repeat(4,minmax(0,1fr));gap:11px;margin:0 0 15px}
    .smart-panel.smart-v3-unified .smart-v2-kpi {
        position:relative;isolation:isolate;overflow:hidden;min-height:98px;gap:9px;
        padding:17px 16px;border:1px solid #e9ded1;border-radius:16px;background:#fffdfb;
        box-shadow:0 6px 18px rgba(70,42,25,.035);transition:transform .26s cubic-bezier(.2,.8,.2,1),box-shadow .26s,border-color .26s;
        animation:j3-card-in .55s backwards;
    }
    .smart-panel.smart-v3-unified .smart-v2-kpi:nth-child(2) {animation-delay:.065s}
    .smart-panel.smart-v3-unified .smart-v2-kpi:nth-child(3) {animation-delay:.12s}
    .smart-panel.smart-v3-unified .smart-v2-kpi:nth-child(4) {animation-delay:.18s}
    .smart-panel.smart-v3-unified .smart-v2-kpi:hover {transform:translateY(-4px);border-color:#d7b99b;box-shadow:0 14px 29px rgba(70,42,25,.10)}
    .smart-panel.smart-v3-unified .smart-v2-content>.smart-v2-kpis>.smart-v2-kpi:first-child {background:linear-gradient(145deg,#382418 0%,#64432d 100%);border-color:#533726;box-shadow:0 10px 23px rgba(59,32,17,.18)}
    .smart-panel.smart-v3-unified .smart-v2-content>.smart-v2-kpis>.smart-v2-kpi:first-child::after {content:'';position:absolute;width:120px;height:120px;border:1px solid rgba(248,226,181,.1);border-radius:50%;right:-42px;bottom:-66px;box-shadow:0 0 0 15px rgba(248,226,181,.045),0 0 0 34px rgba(248,226,181,.03)}
    .smart-panel.smart-v3-unified .smart-v2-content>.smart-v2-kpis>.smart-v2-kpi:first-child span {color:#dfc5b2}
    .smart-panel.smart-v3-unified .smart-v2-content>.smart-v2-kpis>.smart-v2-kpi:first-child strong {color:#fff7ed}
    .smart-panel.smart-v3-unified .smart-v2-kpi span {font-size:10px;line-height:1.45;color:#987966}
    .smart-panel.smart-v3-unified .smart-v2-kpi strong {font-size:clamp(22px,2vw,28px);letter-spacing:-.04em;line-height:1.15;overflow-wrap:anywhere;color:#3c261b;font-variant-numeric:tabular-nums}
    .smart-panel.smart-v3-unified .smart-v2-kpi strong.smart-v2-status {font-size:14px;line-height:1.45;letter-spacing:0}
    .smart-panel.smart-v3-unified .smart-v2-charts {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start;gap:14px}
    .smart-panel.smart-v3-unified .smart-v2-chart-card {
        padding:19px;min-width:0;min-height:0;border:1px solid #eadfd3;
        background:#fffdfb;border-radius:19px;box-shadow:0 8px 22px rgba(62,41,26,.04);
        transition:transform .3s cubic-bezier(.2,.8,.2,1),box-shadow .3s,border-color .3s;
        animation:j3-card-in .64s backwards;
    }
    .smart-panel.smart-v3-unified .smart-v2-chart-card:nth-child(2){animation-delay:.08s}
    .smart-panel.smart-v3-unified .smart-v2-chart-card:nth-child(3){animation-delay:.15s}
    .smart-panel.smart-v3-unified .smart-v2-chart-card:nth-child(4){animation-delay:.22s}
    .smart-panel.smart-v3-unified .smart-v2-chart-card:hover {transform:translateY(-3px);border-color:#d7bfa6;box-shadow:0 14px 32px rgba(62,41,26,.09)}
    .smart-panel.smart-v3-unified .smart-v2-chart-card h3 {font-size:14px;letter-spacing:-.02em;margin-bottom:4px}
    .smart-panel.smart-v3-unified .smart-v2-chart-card p {font-size:10px;margin-bottom:15px}
    .smart-panel.smart-v3-unified .smart-v2-chart-meta {min-width:0;padding:10px 12px;border-color:#efe2d4;background:#f8f2eb;gap:9px;font-size:10px}
    .smart-panel.smart-v3-unified .smart-v2-chart-meta strong {font-size:11px;text-align:right;overflow-wrap:anywhere}
    .smart-panel.smart-v3-unified .smart-v2-chart-card .smart-v2-kpis {grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
    .smart-panel.smart-v3-unified .smart-v2-chart-card .smart-v2-kpi {min-height:91px;padding:12px 13px;border-radius:13px;background:#fff;border-color:#ecddd0}
    .smart-panel.smart-v3-unified .smart-v2-chart-card .smart-v2-kpi strong {font-size:clamp(17px,1.4vw,22px);overflow-wrap:anywhere;word-break:break-word}
    .smart-panel.smart-v3-unified .smart-v2-line-chart polyline {stroke-dasharray:1;stroke-dashoffset:1;animation:j3-line-draw 1.6s .16s cubic-bezier(.2,.8,.2,1) forwards}
    .smart-panel.smart-v3-unified .smart-v2-line-chart circle {animation:j3-dot-in .42s .7s both}
    .smart-panel.smart-v3-unified .smart-v2-fill {transform-origin:left center;animation:j3-bar-grow 1.1s cubic-bezier(.2,.8,.2,1) both}
    .smart-panel.smart-v3-unified .smart-v2-hour-track>div {transform-origin:bottom;animation:j3-bar-rise .8s cubic-bezier(.2,.8,.2,1) both}
    .smart-panel.smart-v3-unified .smart-v2-hour:nth-child(2n) .smart-v2-hour-track>div {animation-delay:.09s}
    .smart-panel.smart-v3-unified .smart-v2-hour:nth-child(3n) .smart-v2-hour-track>div {animation-delay:.17s}
    .smart-panel.smart-v3-unified .smart-v2-empty {min-height:0;background:linear-gradient(135deg,#fffdf9,#f9f1e8);border:1px dashed #e4d0bb;font-size:11px;padding:16px;line-height:1.65}
    .smart-panel.smart-v3-unified .smart-v2-disclaimer {font-size:10px;padding:13px 3px 3px}
    .smart-panel.smart-v3-unified .smart-v2-notice {display:flex;align-items:center;gap:9px;border:1px solid #eddcc8;background:#fff8ed}
    .smart-v3-loading-spinner {width:13px;height:13px;flex:none;border:2px solid rgba(167,110,65,.22);border-top-color:#ad7248;border-radius:50%;animation:j3-spin .8s linear infinite}
    .smart-panel.smart-v3-unified .smart-v2-chat-head {padding:17px 20px 15px;background:#fffdfb;border-bottom:1px solid #f0e6dc}
    .smart-panel.smart-v3-unified .smart-v2-chat-head-title {font-size:12px;letter-spacing:.005em}
    .smart-panel.smart-v3-unified .smart-v2-chat-head-desc {font-size:10px;margin-top:5px;color:#987b65}
    .smart-panel.smart-v3-unified .smart-v2-chat-dot {width:8px;height:8px;box-shadow:0 0 0 4px rgba(91,150,107,.11);animation:j3-status-pulse 2.8s infinite}
    .smart-panel.smart-v3-unified .smart-messages {gap:13px;padding:20px 17px 17px;scrollbar-width:thin;scrollbar-color:#d6c4b3 transparent;overscroll-behavior:contain}
    .smart-panel.smart-v3-unified .smart-message {max-width:92%;font-size:11px;line-height:1.75;padding:13px 14px;border-radius:15px;white-space:pre-line;overflow-wrap:anywhere;animation:j3-bubble-pop .35s cubic-bezier(.2,.8,.2,1) backwards}
    .smart-panel.smart-v3-unified .smart-bot {background:#f5f0e9;border:1px solid #f0e6db;color:#483021;border-bottom-left-radius:5px}
    .smart-panel.smart-v3-unified .smart-user {background:linear-gradient(140deg,#4b2f21,#70452e);box-shadow:0 6px 17px rgba(66,37,22,.1);color:white;border-bottom-right-radius:5px}
    .smart-panel.smart-v3-unified .smart-message-label {font-size:9px;letter-spacing:.02em;color:#9c744e;margin-bottom:4px}
    .smart-panel.smart-v3-unified .smart-suggestions {flex-shrink:0;display:flex;flex-wrap:wrap;overflow:visible;gap:7px;padding:10px 15px 13px;background:#fffdfb}
    .smart-panel.smart-v3-unified .smart-suggestions button {background:#fff;border:1px solid #e9d8c6;border-radius:11px;font-size:10px;padding:8px 10px;transition:transform .17s,background .17s,border-color .17s,color .17s}
    .smart-panel.smart-v3-unified .smart-suggestions button:hover {background:#4b2e1f;color:white;border-color:#4b2e1f;transform:translateY(-2px)}
    .smart-panel.smart-v3-unified .smart-form {flex-shrink:0;display:flex;align-items:stretch;padding:13px 15px 16px;border-top:1px solid #f0e6dc;background:#fffdfb;gap:9px}
    .smart-panel.smart-v3-unified .smart-form input {min-height:43px;padding:11px 14px;font-size:11px;border-radius:12px;background:#fbf7f2;border:1px solid #e6d9cd;transition:background .2s,border-color .2s,box-shadow .2s}
    .smart-panel.smart-v3-unified .smart-form input:focus {background:#fff;border-color:#c28a55;box-shadow:0 0 0 4px rgba(194,138,85,.12)}
    .smart-panel.smart-v3-unified .smart-form button {display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:43px;background:#4b2e1f;border-radius:12px;padding:0 15px;font-size:11px;transition:transform .18s,background .18s,box-shadow .18s}
    .smart-panel.smart-v3-unified .smart-form button:not(:disabled):hover {background:#774a30;box-shadow:0 7px 15px rgba(65,37,22,.15);transform:translateY(-2px)}
    .smart-panel.smart-v3-unified .smart-form button svg {width:16px;height:16px;transition:transform .2s}
    .smart-panel.smart-v3-unified .smart-form button:hover svg {transform:translateX(3px)}
    .smart-v3-typing-dots {display:flex;align-items:center;gap:5px;height:17px}
    .smart-v3-typing-dots i {width:6px;height:6px;border-radius:50%;background:#b18159;animation:j3-typing 1s ease-in-out infinite}
    .smart-v3-typing-dots i:nth-child(2) {animation-delay:.16s}.smart-v3-typing-dots i:nth-child(3) {animation-delay:.32s}
    .smart-v3-mobile-tabs {display:none}
    @keyframes j3-fab-entry {from{opacity:0;transform:translateY(18px) scale(.9)}to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes j3-status-pulse {50%{box-shadow:0 0 0 7px rgba(138,202,147,.045)}}
    @keyframes j3-card-in {from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}
    @keyframes j3-bubble-pop {from{opacity:0;transform:translateY(10px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes j3-line-draw {to{stroke-dashoffset:0}}
    @keyframes j3-dot-in {from{opacity:0;transform:scale(.5);transform-origin:center}to{opacity:1;transform:scale(1);transform-origin:center}}
    @keyframes j3-bar-grow {from{transform:scaleX(0)}to{transform:scaleX(1)}}
    @keyframes j3-bar-rise {from{transform:scaleY(0)}to{transform:scaleY(1)}}
    @keyframes j3-spin {to{transform:rotate(360deg)}}
    @keyframes j3-typing {0%,60%,100%{transform:translateY(0);opacity:.45}30%{transform:translateY(-4px);opacity:1}}

    @media(max-width:1200px) and (min-width:981px) {
        .smart-panel.smart-v3-unified {grid-template-columns:minmax(0,1fr) 335px}
        .smart-panel.smart-v3-unified .smart-v2-dashboard {padding:20px 17px 25px}
        .smart-panel.smart-v3-unified .smart-v2-heading {align-items:start}
        .smart-panel.smart-v3-unified .smart-v2-charts {gap:10px}
        .smart-panel.smart-v3-unified .smart-v2-chart-card {padding:14px}
        .smart-panel.smart-v3-unified .smart-v2-kpi {padding:13px}
    }
    @media(max-width:980px) {
        .smart-panel.smart-v3-unified {grid-template-columns:minmax(0,1fr);grid-template-rows:70px 54px minmax(0,1fr)}
        .smart-panel.smart-v3-chat-only {grid-template-rows:70px minmax(0,1fr)}
        .smart-panel.smart-v3-unified .smart-v3-header {grid-row:1;padding:10px 16px}
        .smart-v3-root .smart-v3-header-status {display:none}
        .smart-v3-root .smart-v3-mobile-tabs {grid-column:1;grid-row:2;display:flex;align-items:center;gap:5px;padding:8px 13px;background:#fffdf9;border-bottom:1px solid #eae0d5}
        .smart-v3-mobile-tabs button {flex:1;min-height:36px;border:0;background:transparent;color:#82654c;border-radius:10px;font-size:11px;font-weight:850;transition:background .18s,color .18s}
        .smart-v3-mobile-tabs button.is-active {color:#fff;background:#4b2e1f;box-shadow:0 4px 10px rgba(75,46,31,.17)}
        .smart-panel.smart-v3-unified .smart-v2-dashboard,.smart-panel.smart-v3-unified .smart-v2-chat {grid-column:1;grid-row:3;border:0;min-height:0;height:100%}
        .smart-panel.smart-v3-chat-only .smart-v2-chat {grid-row:2}
        .smart-panel.smart-v3-unified:not(.smart-v3-mobile-analytics) .smart-v2-dashboard {display:none!important}
        .smart-panel.smart-v3-unified.smart-v3-mobile-analytics .smart-v2-chat {display:none!important}
        .smart-panel.smart-v3-unified .smart-v2-dashboard {padding:20px 20px 30px}
        .smart-panel.smart-v3-unified .smart-v2-charts {grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media(max-width:760px) {
        .smart-panel.smart-v3-unified {border:0;border-radius:0;grid-template-rows:70px 53px minmax(0,1fr)}
        .smart-panel.smart-v3-chat-only {grid-template-rows:70px minmax(0,1fr)}
        .smart-panel.smart-v3-unified::after {display:none}
        .smart-panel.smart-v3-unified .smart-v3-header {padding:10px 14px}
        .smart-panel.smart-v3-unified .smart-avatar {width:42px;height:42px}
        .smart-panel.smart-v3-unified .smart-v3-overline {font-size:7px}
        .smart-panel.smart-v3-unified .smart-title {font-size:15px}
        .smart-panel.smart-v3-unified .smart-subtitle {font-size:9px}
        .smart-panel.smart-v3-unified .smart-v3-close {width:36px;height:36px;margin-left:auto}
        .smart-panel.smart-v3-unified .smart-v2-dashboard {padding:18px 15px 32px}
        .smart-panel.smart-v3-unified .smart-v2-heading {align-items:start;gap:12px}
        .smart-panel.smart-v3-unified .smart-v2-heading h2 {font-size:23px}
        .smart-panel.smart-v3-unified .smart-v2-actions {width:100%;flex-wrap:wrap}
        .smart-panel.smart-v3-unified .smart-v2-filter {flex:1;min-width:115px}
        .smart-panel.smart-v3-unified .smart-v2-filter select {max-width:none}
        .smart-panel.smart-v3-unified .smart-v2-kpis {grid-template-columns:repeat(2,minmax(0,1fr))}
        .smart-panel.smart-v3-unified .smart-v2-charts {grid-template-columns:1fr;gap:11px}
        .smart-panel.smart-v3-unified .smart-v2-chart-card {padding:17px}
        .smart-panel.smart-v3-unified .smart-messages {padding:15px 13px}
        .smart-panel.smart-v3-unified .smart-suggestions {flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;padding:8px 12px}
        .smart-panel.smart-v3-unified .smart-suggestions::-webkit-scrollbar {display:none}
        .smart-panel.smart-v3-unified .smart-form {padding:9px 11px max(12px,env(safe-area-inset-bottom))}
    }
    @media(max-width:375px) {
        .smart-panel.smart-v3-unified .smart-v3-overline {font-size:6px}
        .smart-panel.smart-v3-unified .smart-subtitle {display:none}
        .smart-panel.smart-v3-unified .smart-v2-kpi {padding:12px 10px}
        .smart-panel.smart-v3-unified .smart-v2-heading h2 {font-size:21px}
    }
    @media(prefers-reduced-motion:reduce) {
        .smart-v3-root *, .smart-v3-root *::before, .smart-v3-root *::after {animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
        .smart-panel.smart-v3-unified .smart-v2-line-chart polyline {stroke-dashoffset:0}
    }

    /* JIMNY V4 · Final Polish: clarity, compact conversation and interactive data. */
    .smart-v4-root {--j4-cocoa:#422819;--j4-sand:#f8f3ed;--j4-border:#e8dace;--j4-focus:#c89261}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-dashboard {scrollbar-gutter:stable}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-heading h2 {font-weight:900;text-wrap:balance}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-heading p {font-size:13px;line-height:1.68;color:#7f6452}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-kpi-scope {font-size:12px;line-height:1.55}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-kpi span {font-size:12px;line-height:1.4}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-kpi strong.smart-v2-status {font-size:14px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chart-card h3 {font-size:16px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chart-card>p {font-size:12px;line-height:1.55;margin-bottom:13px;color:#816756}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chart-meta {font-size:12px;line-height:1.4;padding:10px 12px;background:#faf5ef}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chart-meta strong {font-size:12px;color:#593923}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-filter {font-size:12px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-filter select {font-size:12px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-refresh {font-size:12px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-axis {font-size:11px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-hours-toolbar {font-size:12px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-hour-value {font-size:11px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-disclaimer {font-size:12px;line-height:1.6}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chart-card .smart-v2-kpi span {font-size:11px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-empty:not(.smart-v4-stock-empty) {font-size:12px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chart-scope {font-size:12px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chart-scope strong {font-size:13px}
    /* Draw attention to meaningful content, never stretch short chat messages. */
    .smart-v4-root .smart-panel.smart-v3-unified .smart-messages {gap:11px;padding:18px 16px 16px;background:linear-gradient(180deg,#fffdfa 0%,#fffcf7 100%)}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-message {flex:0 0 auto;min-height:0;width:max-content;max-width:min(86%,335px);box-sizing:border-box;padding:11px 13px;font-size:12px;line-height:1.65;border-radius:16px;box-shadow:0 4px 13px rgba(54,32,18,.035);white-space:pre-line;overflow-wrap:anywhere}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-message.smart-user {max-width:min(80%,310px);padding:11px 14px;background:linear-gradient(138deg,#493023 0%,#66412b 100%);border-radius:16px 16px 5px 16px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-message.smart-bot {background:#f7f1e9;border:1px solid #e9ddcf;border-radius:16px 16px 16px 5px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-message-label {font-size:10px;margin-bottom:5px;letter-spacing:.02em}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chat-head-title {font-size:13px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chat-head-desc {font-size:11px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-suggestions {gap:7px;padding-top:10px;border-top:1px solid #f2e9df}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-suggestions button {font-size:11px;font-weight:800;border-radius:999px;padding:8px 12px;box-shadow:0 2px 7px rgba(61,37,20,.03)}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-form input {font-size:12px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-form button {font-size:12px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-messages::-webkit-scrollbar,.smart-v4-root .smart-panel.smart-v3-unified .smart-v2-dashboard::-webkit-scrollbar {width:7px;height:7px}
    .smart-v4-root .smart-panel.smart-v3-unified .smart-messages::-webkit-scrollbar-thumb,.smart-v4-root .smart-panel.smart-v3-unified .smart-v2-dashboard::-webkit-scrollbar-thumb {background:#d6bcaa;border-radius:99px;border:2px solid #fffaf5}
    /* Context-aware stock state: never assert stock is safe if the API returns no recommendations. */
    .smart-v4-root .smart-panel.smart-v3-unified .smart-v4-stock-empty {padding:18px 15px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:11px;border:1px dashed #dfc7ad;border-radius:16px;background:radial-gradient(circle at 50% 0%,#fffaf2,transparent 68%),#fcf8f2}
    .smart-v4-stock-symbol {display:grid;place-items:center;width:48px;height:48px;border-radius:16px;background:linear-gradient(145deg,#efe0d1,#f7eee5);color:#945e3b;box-shadow:0 5px 15px rgba(94,56,30,.08)}
    .smart-v4-stock-symbol svg {width:25px;height:25px}
    .smart-v4-stock-copy {display:grid;gap:5px;max-width:310px}
    .smart-v4-stock-copy strong {font-size:13px;color:#513322;line-height:1.35}
    .smart-v4-stock-copy span {font-size:12px;line-height:1.55;color:#8c6e59}
    .smart-v4-stock-reload {margin-top:3px;border:1px solid #d9bea3;background:#fffdfa;color:#71462c;border-radius:10px;padding:8px 14px;font-size:11px;font-weight:850;cursor:pointer;transition:transform .2s,background .2s,box-shadow .2s}
    .smart-v4-stock-reload:hover:not(:disabled) {background:#f4e8dc;transform:translateY(-2px);box-shadow:0 6px 15px rgba(95,57,30,.08)}
    .smart-v4-stock-reload:disabled {opacity:.5;cursor:wait}
    /* Accessible pointer/keyboard tooltip, chart math and API data stay unchanged. */
    .smart-v4-root .smart-v4-interactive-chart {cursor:crosshair;border-radius:8px;outline-offset:3px;touch-action:pan-y}
    .smart-v4-root .smart-v4-interactive-chart:focus-visible {outline:2px solid #c49264}
    .smart-v4-root .smart-v4-tooltip-date {fill:#dbbda3;font-size:14px;font-weight:750}
    .smart-v4-root .smart-v4-tooltip-value {fill:#fffaf3;font-size:17px;font-weight:900}
    .smart-v4-root .smart-v2-line-chart polyline {transition:stroke .2s ease}
    .smart-v4-root .smart-v4-interactive-chart:hover polyline,.smart-v4-root .smart-v4-interactive-chart:focus polyline {stroke:#8c5535}
    .smart-v4-root .smart-v2-hour-track>div {transition:filter .22s ease,background .22s ease}
    .smart-v4-root .smart-v2-hour:hover .smart-v2-hour-track>div {filter:brightness(.86)}
    .smart-v4-root .smart-v2-chart-card,.smart-v4-root .smart-v2-kpi {will-change:auto}
    @media(max-width:760px){
        .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-heading p {font-size:12px}
        .smart-v4-root .smart-panel.smart-v3-unified .smart-message {font-size:12px;max-width:87%}
        .smart-v4-root .smart-panel.smart-v3-unified .smart-message.smart-user {max-width:83%}
        .smart-v4-root .smart-panel.smart-v3-unified .smart-suggestions button {font-size:11px}
        .smart-v4-root .smart-panel.smart-v3-unified .smart-v2-chart-card {padding:16px}
    }
    @media(prefers-reduced-motion:reduce){
        .smart-v4-root .smart-panel.smart-v3-unified .smart-v4-trend-hover {transition:none!important}
    }


    /* ============================================================
       JIMNY MOTION STUDIO V5 — visual motion only. No API changes.
       Animations are scoped to Smart Assistant and respect reduced motion.
       ============================================================ */
    .smart-v5-root{--j5-gold:#dfa769;--j5-amber:#b97b49;--j5-ivory:#fffdf6;--j5-shadow:rgba(72,38,18,.17)}
    .smart-v5-root .smart-v3-backdrop{
        background:radial-gradient(ellipse at 28% 14%,rgba(211,148,94,.24),transparent 46%),
            radial-gradient(ellipse at 78% 85%,rgba(126,79,51,.21),transparent 53%),rgba(23,13,9,.72);
        background-size:115% 115%;animation:j5-ambient-drift 15s ease-in-out infinite alternate;
    }
    .smart-v5-root .smart-panel.smart-v3-unified{
        box-shadow:0 45px 140px rgba(25,12,6,.48),0 0 0 1px rgba(255,252,236,.16) inset;
    }
    .smart-v5-root .smart-panel.smart-v3-unified .smart-v3-header{
        background:linear-gradient(112deg,#301d14 2%,#543625 36%,#41271c 67%,#69412b 100%);
        background-size:190% 100%;animation:j5-header-shift 11s ease-in-out infinite alternate;
    }
    .smart-v5-root .smart-panel.smart-v3-unified .smart-v3-header::after{
        content:'';position:absolute;pointer-events:none;inset:auto 0 0;height:1px;
        background:linear-gradient(90deg,transparent,#ffdfa7 40%,#e1a66c 65%,transparent);
        opacity:.6;transform-origin:left center;animation:j5-header-line 1.2s cubic-bezier(.19,1,.22,1) both;
    }
    .j5-header-streak{position:absolute;pointer-events:none;top:-140%;left:-36%;width:26%;height:380%;
        background:linear-gradient(90deg,transparent,rgba(255,220,164,.07),rgba(255,245,218,.12),transparent);
        transform:rotate(19deg) translateX(-180%);animation:j5-streak 9s 1.5s ease-in-out infinite;z-index:0}
    .j5-header-sparkles{position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:0}
    .j5-header-sparkles i{position:absolute;width:3px;height:3px;border-radius:50%;background:#f2d4a3;
        box-shadow:0 0 10px 3px rgba(247,184,102,.28);opacity:0;animation:j5-sparkle 6s ease-in-out infinite}
    .j5-header-sparkles i:nth-child(1){left:33%;top:26%;animation-delay:0s}
    .j5-header-sparkles i:nth-child(2){left:58%;top:70%;animation-delay:2s}
    .j5-header-sparkles i:nth-child(3){left:79%;top:24%;animation-delay:3.7s}
    .smart-v5-root .smart-panel.smart-v3-unified .smart-avatar{
        box-shadow:0 5px 22px rgba(0,0,0,.18),0 0 0 1px rgba(247,209,158,.18);
        animation:j5-avatar-arrive .75s .15s cubic-bezier(.16,1,.3,1) both;
    }
    .smart-v5-root .smart-v3-header-status{transition:border-color .25s,box-shadow .25s;}
    .smart-v5-root .smart-v3-header-status:hover{border-color:rgba(238,199,137,.46);box-shadow:0 0 0 4px rgba(213,153,91,.07)}
    .smart-v5-root .smart-panel.smart-v3-unified .smart-v2-dashboard{
        background:radial-gradient(circle at 0% 0%,rgba(235,195,150,.19),transparent 35%),
            radial-gradient(circle at 96% 91%,rgba(232,193,156,.12),transparent 38%),#faf7f2;
        background-size:110% 110%,110% 110%,auto;
    }
    .smart-v5-root .smart-v2-heading h2 em{
        background:linear-gradient(100deg,#98613a 0%,#d29b62 32%,#ad714a 65%,#bf8856 100%);
        background-size:220% auto;-webkit-background-clip:text;background-clip:text;
        -webkit-text-fill-color:transparent;animation:j5-title-shine 7s ease-in-out infinite alternate;
    }
    .smart-v5-root .smart-v3-eyebrow-mark{
        background:#c38a55;box-shadow:0 0 0 0 rgba(207,149,87,.3);
        animation:j5-eyebrow 2.8s ease-in-out infinite;
    }
    /* Staggered, scroll-aware reveal driven by IntersectionObserver. */
    .smart-v5-root.j5-live .smart-v2-dashboard .j5-reveal{
        transition:opacity .72s cubic-bezier(.18,1,.22,1),
            transform .8s cubic-bezier(.16,1,.3,1),
            box-shadow .3s,border-color .3s;
        transition-delay:var(--j5-delay,0ms);
        animation:none;
    }
    .smart-v5-root.j5-live .smart-v2-dashboard .j5-reveal:not(.j5-in-view){opacity:0;transform:translate3d(0,26px,0) scale(.975)}
    .smart-v5-root.j5-live .smart-v2-dashboard .j5-reveal.j5-in-view{opacity:1;transform:translate3d(0,0,0) scale(1)}
    .smart-v5-root.j5-live .smart-v2-dashboard .j5-reveal.j5-in-view:hover{transition-delay:0ms;transform:translateY(-5px) scale(1.009)}
    .smart-v5-root .smart-v2-kpi{backface-visibility:hidden}
    .smart-v5-root .smart-v2-content>.smart-v2-kpis>.smart-v2-kpi:first-child::before{
        content:'';position:absolute;pointer-events:none;inset:-85% -65%;z-index:2;
        background:linear-gradient(105deg,transparent 41%,rgba(255,230,179,.045) 49%,rgba(255,234,180,.18) 51%,transparent 59%);
        transform:translateX(-90%) rotate(9deg);animation:j5-kpi-light 6s 1.6s ease-in-out infinite;
    }
    .smart-v5-root .smart-v2-kpi strong{font-variant-numeric:tabular-nums;}
    .smart-v5-root .smart-v2-chart-card{position:relative;isolation:isolate;}
    .smart-v5-root .smart-v2-chart-card::before{
        content:'';position:absolute;inset:0;pointer-events:none;border-radius:inherit;
        background:radial-gradient(430px circle at var(--j5-mouse-x,50%) var(--j5-mouse-y,0%),rgba(214,157,98,.14),transparent 52%);
        opacity:0;transition:opacity .32s ease;z-index:0;
    }
    .smart-v5-root .smart-v2-chart-card:hover::before{opacity:1}
    .smart-v5-root .smart-v2-chart-card>*{position:relative;z-index:1}
    /* Real SVG polyline draws when its card enters the viewport. */
    .smart-v5-root .smart-v2-line-chart polyline.j5-chart-stroke{stroke-dasharray:1;stroke-dashoffset:0;animation:none;}
    .smart-v5-root.j5-live .j5-reveal.j5-in-view .smart-v2-line-chart polyline.j5-chart-stroke{
        animation:j5-trend-draw 1.8s .18s cubic-bezier(.25,.82,.22,1) both;
        filter:drop-shadow(0 3px 2px rgba(165,107,65,.14));
    }
    .smart-v5-root .smart-v4-trend-hover circle{
        transform-box:fill-box;transform-origin:center;animation:j5-marker-pop .4s cubic-bezier(.16,1.4,.2,1) both;
        filter:drop-shadow(0 0 5px rgba(196,128,65,.28));
    }
    .smart-v5-root .smart-v2-hour-track>div{animation:j5-bar-rise .85s cubic-bezier(.2,.8,.2,1) both;}
    .smart-v5-root .smart-v2-hour:nth-child(4n+1) .smart-v2-hour-track>div{animation-delay:.11s}
    .smart-v5-root .smart-v2-hour:nth-child(4n+2) .smart-v2-hour-track>div{animation-delay:.22s}
    .smart-v5-root .smart-v2-hour:nth-child(4n+3) .smart-v2-hour-track>div{animation-delay:.34s}
    .smart-v5-root .smart-v2-hour:hover .smart-v2-hour-track{box-shadow:0 0 0 3px rgba(189,126,77,.1);}
    .smart-v5-root .smart-v4-stock-symbol{
        animation:j5-box-float 3.7s ease-in-out infinite;
        box-shadow:0 9px 21px rgba(105,61,30,.1);
    }
    .smart-v5-root .smart-v4-stock-reload{position:relative;overflow:hidden}
    .smart-v5-root .smart-v4-stock-reload::before,.smart-v5-root .smart-v2-refresh::before{
        content:'';position:absolute;inset:-20% auto -20% -50%;width:34%;pointer-events:none;
        background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);
        transform:skewX(-22deg);transition:left .6s ease;
    }
    .smart-v5-root .smart-v4-stock-reload:hover::before,.smart-v5-root .smart-v2-refresh:hover::before{left:130%}
    .smart-v5-root .smart-v2-refresh{position:relative;overflow:hidden}
    .smart-v5-root .smart-messages .smart-message{
        animation:j5-chat-enter .47s cubic-bezier(.16,1,.3,1) both;
    }
    .smart-v5-root .smart-message.smart-user{box-shadow:0 9px 23px rgba(71,38,23,.14)}
    .smart-v5-root .smart-suggestions button,.smart-v5-root .smart-form button{
        transition:transform .23s cubic-bezier(.16,1,.3,1),box-shadow .23s,background .23s,color .23s;
    }
    .smart-v5-root .smart-suggestions button:active:not(:disabled){transform:scale(.92)}
    .smart-v5-root .smart-form button:not(:disabled):hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(102,62,35,.23)}
    .smart-v5-root .smart-form button:not(:disabled):hover svg{animation:j5-send-arrow .65s ease-in-out infinite alternate}
    .smart-v5-root .smart-form:focus-within{box-shadow:0 -4px 25px rgba(203,147,93,.07)}
    .smart-v5-root .smart-form input{transition:box-shadow .26s,border-color .26s,background .26s}
    .smart-v5-root .smart-form input:focus{background:#fffdfa;box-shadow:0 0 0 4px rgba(194,138,85,.16),0 3px 12px rgba(100,60,36,.06)}
    .smart-v5-root .smart-v2-notice{position:relative;overflow:hidden}
    .smart-v5-root .smart-v2-notice:has(.smart-v3-loading-spinner)::after{
        content:'';position:absolute;inset:0;pointer-events:none;
        background:linear-gradient(95deg,transparent 0%,rgba(255,250,237,.63) 47%,transparent 82%);
        transform:translateX(-110%);animation:j5-skeleton-sheen 1.65s linear infinite;
    }
    .smart-v5-root .smart-v2-chat-head{position:relative}
    .smart-v5-root .smart-v2-chat-head::after{content:'';position:absolute;bottom:0;left:0;height:2px;width:100%;pointer-events:none;
        background:linear-gradient(90deg,transparent,#c58c5b,transparent);transform:scaleX(.0);transform-origin:left;animation:j5-header-line 1.4s .45s ease-out both}
    @keyframes j5-ambient-drift{0%{background-position:0% 0%,100% 100%,0 0}100%{background-position:100% 85%,0% 15%,0 0}}
    @keyframes j5-header-shift{to{background-position:100% 0}}
    @keyframes j5-header-line{to{transform:scaleX(1)}}
    @keyframes j5-streak{0%,12%{transform:rotate(19deg) translateX(-180%);opacity:0}22%{opacity:1}43%,100%{transform:rotate(19deg) translateX(800%);opacity:0}}
    @keyframes j5-sparkle{0%,16%,85%,100%{opacity:0;transform:translateY(8px) scale(.4)}35%{opacity:.55;transform:translateY(-6px) scale(1)}58%{opacity:.25;transform:translateY(-12px) scale(.6)}}
    @keyframes j5-avatar-arrive{from{transform:translateY(18px) rotate(-10deg) scale(.88);opacity:0}to{transform:translateY(0) rotate(0) scale(1);opacity:1}}
    @keyframes j5-title-shine{from{background-position:0% 50%}to{background-position:100% 50%}}
    @keyframes j5-eyebrow{50%{box-shadow:0 0 0 5px rgba(207,149,87,.05)}}
    @keyframes j5-kpi-light{0%,13%{transform:translateX(-90%) rotate(9deg);opacity:0}20%{opacity:1}39%,100%{transform:translateX(130%) rotate(9deg);opacity:0}}
    @keyframes j5-trend-draw{from{stroke-dashoffset:1}to{stroke-dashoffset:0}}
    @keyframes j5-marker-pop{from{opacity:.2;transform:scale(.25)}to{opacity:1;transform:scale(1)}}
    @keyframes j5-bar-rise{from{transform:scaleY(0)}to{transform:scaleY(1)}}
    @keyframes j5-box-float{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-5px) rotate(2deg)}}
    @keyframes j5-chat-enter{from{opacity:0;transform:translateY(16px) scale(.94)}to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes j5-send-arrow{to{transform:translateX(3px)}}
    @keyframes j5-skeleton-sheen{to{transform:translateX(115%)}}
    @media(max-width:760px){
        .smart-v5-root .smart-panel.smart-v3-unified .smart-v2-heading h2{font-size:clamp(22px,6vw,29px)}
        .smart-v5-root .smart-v2-chart-card::before{display:none}
        .smart-v5-root .j5-header-sparkles{display:none}
        .smart-v5-root .smart-messages .smart-message{animation-duration:.32s}
    }
    @media(prefers-reduced-motion:reduce){
        .smart-v5-root .smart-v3-backdrop,.smart-v5-root .smart-v3-header,
        .smart-v5-root .j5-header-streak,.smart-v5-root .j5-header-sparkles i,
        .smart-v5-root .smart-v2-chart-card,.smart-v5-root .smart-v2-chart-card::before,
        .smart-v5-root .smart-v2-kpi,.smart-v5-root .smart-messages .smart-message{
            animation:none!important;transition:none!important;transform:none!important;
        }
        .smart-v5-root.j5-live .smart-v2-dashboard .j5-reveal,
        .smart-v5-root.j5-live .smart-v2-dashboard .j5-reveal:not(.j5-in-view){
            opacity:1!important;transform:none!important;transition:none!important;
        }
        .smart-v5-root .smart-v2-line-chart polyline.j5-chart-stroke{animation:none!important;stroke-dashoffset:0!important}
        .smart-v5-root .smart-v2-hour-track>div{animation:none!important;transform:none!important}
    }


/* JIMNY JSA Machine Learning -- verified read-only insights. */
.smart-v5-root .jimny-ml-zone{margin:0 0 22px;padding:20px;border:1px solid #e6d3c3;border-radius:22px;background:linear-gradient(130deg,#fffdfb 0%,#f6ede4 100%);box-shadow:0 16px 42px rgba(65,35,21,.07);color:#482c20}
.smart-v5-root .jimny-ml-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:15px}
.smart-v5-root .jimny-ml-title-wrap{display:flex;align-items:flex-start;gap:12px}
.smart-v5-root .jimny-ml-mark{display:grid;place-items:center;flex:0 0 38px;height:38px;border-radius:13px;background:#4b2e21;color:#e9b983;font-size:21px;box-shadow:0 8px 18px #4b2e2126}
.smart-v5-root .jimny-ml-eyebrow{font-weight:800;letter-spacing:.15em;color:#ad744b;font-size:10px;margin-bottom:5px}
.smart-v5-root .jimny-ml-head h3{font-size:clamp(17px,1.5vw,22px);margin:0 0 5px;font-weight:900;line-height:1.2}
.smart-v5-root .jimny-ml-head p{font-size:12px;color:#8d6a58;max-width:520px;line-height:1.55;margin:0}
.smart-v5-root .jimny-ml-refresh,.smart-v5-root .jimny-ml-more{border:1px solid #dfbea5;background:#fff9f3;color:#68432e;border-radius:12px;padding:11px 13px;font-size:11px;font-weight:850;cursor:pointer;transition:transform .2s,background .2s,box-shadow .2s;flex-shrink:0}
.smart-v5-root .jimny-ml-refresh:hover,.smart-v5-root .jimny-ml-more:hover{background:#f9e5d1;transform:translateY(-2px);box-shadow:0 9px 20px #68432e18}
.smart-v5-root .jimny-ml-refresh:disabled{opacity:.55;cursor:wait}
.smart-v5-root .jimny-ml-refresh span:first-child{font-size:17px;margin-right:5px}
.smart-v5-root .jimny-ml-unavailable{display:flex;gap:12px;align-items:flex-start;border:1px dashed #dabba7;border-radius:14px;padding:15px;background:#fffaf5}
.smart-v5-root .jimny-ml-empty-glyph{display:grid;place-items:center;width:40px;height:40px;flex:0 0 40px;border-radius:12px;background:#f2e3d5;color:#925f43;font-size:26px}
.smart-v5-root .jimny-ml-unavailable strong{font-size:13px;color:#573625}
.smart-v5-root .jimny-ml-unavailable p{font-size:12px;line-height:1.6;margin:6px 0 4px;color:#866756}
.smart-v5-root .jimny-ml-unavailable small{font-size:11px;color:#ae8972;line-height:1.5}
.smart-v5-root .jimny-ml-badges{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:12px}
.smart-v5-root .jimny-ml-badge{display:inline-flex;align-items:center;gap:4px;padding:6px 9px;font-size:10px;line-height:1.2;font-weight:800;border:1px solid #d7c6b6;background:#fff9f1;color:#6b4a36;border-radius:999px}
.smart-v5-root .jimny-ml-ok{background:#ebf3ea;border-color:#bbd3be;color:#3d7451}
.smart-v5-root .jimny-ml-warn{background:#fff0db;border-color:#f1d3ac;color:#a86618}
.smart-v5-root .jimny-ml-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}
.smart-v5-root .jimny-ml-overview>div{min-width:0;border:1px solid #ebdacb;background:#fffdfb;border-radius:13px;padding:13px 11px;display:flex;flex-direction:column;gap:7px;transition:transform .2s,box-shadow .2s}
.smart-v5-root .jimny-ml-overview>div:hover{transform:translateY(-3px);box-shadow:0 8px 18px #3e241518}
.smart-v5-root .jimny-ml-overview span,.smart-v5-root .jimny-ml-row>div>span{font-size:10px;color:#a17f69}
.smart-v5-root .jimny-ml-overview strong{font-size:15px;font-weight:900;overflow-wrap:anywhere}
.smart-v5-root .jimny-ml-interval{margin-top:10px;font-size:11px;color:#87644d;background:#f4e9dc;border-radius:9px;padding:9px 11px}
.smart-v5-root .jimny-ml-table-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:20px 0 10px}
.smart-v5-root .jimny-ml-table-head h4{margin:0;font-size:14px;font-weight:850}.smart-v5-root .jimny-ml-table-head span{font-size:10px;color:#a17f69}
.smart-v5-root .jimny-ml-table{display:grid;gap:7px}
.smart-v5-root .jimny-ml-row{display:grid;grid-template-columns:minmax(130px,2fr) repeat(3,minmax(70px,1fr));gap:10px;align-items:center;padding:11px 13px;border:1px solid #e8d8ca;background:#fffdfb;border-radius:12px;transition:transform .2s,border-color .2s}
.smart-v5-root .jimny-ml-row:hover{transform:translateX(3px);border-color:#c99878}
.smart-v5-root .jimny-ml-row>div:not(:first-child){display:flex;flex-direction:column;gap:5px;min-width:0}
.smart-v5-root .jimny-ml-row strong{font-size:12px;overflow-wrap:anywhere}
.smart-v5-root .jimny-ml-menu{display:flex;flex-direction:column;gap:4px}.smart-v5-root .jimny-ml-menu small{font-size:10px;color:#a07b66}
.smart-v5-root .jimny-ml-highlight{color:#ab5d25}
.smart-v5-root .jimny-ml-more{margin:12px 0 0;width:100%}
.smart-v5-root .jimny-ml-note{padding:12px;border-radius:10px;background:#fbf1e7;color:#755741;font-size:12px;margin:0 0 12px}
.smart-v5-root .jimny-ml-safety{font-size:11px;line-height:1.6;margin:13px 0 0;padding:10px;border-left:3px solid #b88258;background:#fffaf5;color:#81614d}
.smart-v5-root .jimny-ml-run{margin-top:9px;font-size:10px;color:#a38571;overflow-wrap:anywhere}
.smart-v5-root .jimny-ml-run code{font-family:ui-monospace,Consolas,monospace;font-size:10px}
@media(max-width:960px){.smart-v5-root .jimny-ml-overview{grid-template-columns:repeat(2,minmax(0,1fr))}.smart-v5-root .jimny-ml-row{grid-template-columns:minmax(120px,2fr) repeat(3,minmax(56px,1fr));gap:5px;padding:10px}}
@media(max-width:610px){.smart-v5-root .jimny-ml-zone{padding:13px;border-radius:16px}.smart-v5-root .jimny-ml-head{align-items:stretch;flex-direction:column}.smart-v5-root .jimny-ml-refresh{align-self:start}.smart-v5-root .jimny-ml-row{grid-template-columns:1fr 1fr;gap:9px}.smart-v5-root .jimny-ml-menu{grid-column:1 / -1;border-bottom:1px solid #f0e4d9;padding-bottom:7px}.smart-v5-root .jimny-ml-row>div:last-child{grid-column:2}.smart-v5-root .jimny-ml-overview strong{font-size:13px}}
@media(prefers-reduced-motion:reduce){.smart-v5-root .jimny-ml-overview>div,.smart-v5-root .jimny-ml-row,.smart-v5-root .jimny-ml-refresh{transition:none!important;transform:none!important}}


/* ML compact card V7: concise by default, details on demand. */
.smart-v5-root .jimny-ml-compact{padding:17px 19px 15px;border-radius:19px;margin-bottom:17px;background:linear-gradient(140deg,#fffdfb,#f6eee6);animation:jimnyMlCardIn .48s cubic-bezier(.2,.8,.2,1) both}
.smart-v5-root .jimny-ml-compact .jimny-ml-head{margin-bottom:12px}
.smart-v5-root .jimny-ml-compact .jimny-ml-mark{width:34px;height:34px;flex:0 0 34px;border-radius:11px;font-size:18px}
.smart-v5-root .jimny-ml-compact .jimny-ml-eyebrow{font-size:9px;margin-bottom:3px;letter-spacing:.10em}
.smart-v5-root .jimny-ml-compact .jimny-ml-head h3{font-size:18px;margin-bottom:2px}
.smart-v5-root .jimny-ml-compact .jimny-ml-head p{font-size:11px;line-height:1.35}
.smart-v5-root .jimny-ml-compact .jimny-ml-refresh{font-size:11px;padding:9px 12px}
.smart-v5-root .jimny-ml-compact .jimny-ml-refresh span:first-child{font-size:15px}
.smart-v5-root .jimny-ml-compact .jimny-ml-unavailable{padding:11px 12px;align-items:center}
.smart-v5-root .jimny-ml-compact .jimny-ml-unavailable p{margin:3px 0 0}
.smart-v5-root .jimny-ml-compact .jimny-ml-loading{display:flex;gap:8px;align-items:center}
.smart-v5-root .jimny-ml-compact .jimny-ml-status-line{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin:0 0 10px}
.smart-v5-root .jimny-ml-compact .jimny-ml-badge{padding:4px 8px;font-size:10px}
.smart-v5-root .jimny-ml-compact .jimny-ml-meta{font-size:10px;color:#8c705f;line-height:1.3}
.smart-v5-root .jimny-ml-compact .jimny-ml-overview{gap:8px}
.smart-v5-root .jimny-ml-compact .jimny-ml-overview>div{padding:11px 11px 10px;gap:6px;border-radius:12px;background:#fffefc;box-shadow:none}
.smart-v5-root .jimny-ml-compact .jimny-ml-overview span{font-size:10px;line-height:1.25;color:#907362}
.smart-v5-root .jimny-ml-compact .jimny-ml-overview strong{font-size:13px;line-height:1.3;overflow-wrap:anywhere}
.smart-v5-root .jimny-ml-compact .jimny-ml-key-number{border-color:#e1b78f;background:#fff9f2}
.smart-v5-root .jimny-ml-compact .jimny-ml-key-number strong{color:#995d32;font-size:16px}
.smart-v5-root .jimny-ml-compact .jimny-ml-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:10px}
.smart-v5-root .jimny-ml-compact .jimny-ml-brief{margin:0;font-size:11px;color:#806351;line-height:1.45}
.smart-v5-root .jimny-ml-compact .jimny-ml-detail-toggle{border:1px solid #d8b698;background:#4d3023;color:white;border-radius:10px;padding:9px 12px;display:inline-flex;align-items:center;justify-content:center;gap:9px;white-space:nowrap;font:inherit;font-size:11px;font-weight:800;cursor:pointer;transition:transform .2s ease,box-shadow .2s ease,background .2s ease}
.smart-v5-root .jimny-ml-compact .jimny-ml-detail-toggle:hover{background:#75472f;transform:translateY(-2px);box-shadow:0 7px 16px #50312322}
.smart-v5-root .jimny-ml-compact .jimny-ml-detail-toggle:focus-visible{outline:3px solid #e3a344;outline-offset:2px}
.smart-v5-root .jimny-ml-compact .jimny-ml-chevron{font-size:18px;display:inline-block;line-height:.6;transition:transform .25s ease}
.smart-v5-root .jimny-ml-compact .jimny-ml-chevron.is-open{transform:rotate(180deg)}
.smart-v5-root .jimny-ml-compact .jimny-ml-details{border-top:1px solid #e9d8c8;margin-top:12px;padding-top:13px;transform-origin:top}
.smart-v5-root .jimny-ml-compact .jimny-ml-detail-top{display:flex;align-items:center;justify-content:space-between;background:#f7eee5;border-radius:9px;padding:9px 11px;gap:10px;font-size:11px;color:#86654f}
.smart-v5-root .jimny-ml-compact .jimny-ml-detail-top strong{font-size:12px;color:#523220}
.smart-v5-root .jimny-ml-compact .jimny-ml-table-head{margin:15px 0 9px}
.smart-v5-root .jimny-ml-compact .jimny-ml-table-head h4{font-size:13px}
.smart-v5-root .jimny-ml-compact .jimny-ml-row{padding:10px 11px}
.smart-v5-root .jimny-ml-compact .jimny-ml-scroll-list{max-height:360px;overflow-y:auto;padding-right:4px;overscroll-behavior:contain}
.smart-v5-root .jimny-ml-compact .jimny-ml-more{padding:10px 12px;font-size:11px}
.smart-v5-root .jimny-ml-compact .jimny-ml-detail-footer{margin-top:11px;display:flex;flex-wrap:wrap;gap:7px 13px;justify-content:space-between;font-size:10px;color:#846854;line-height:1.4}
.smart-v5-root .jimny-ml-compact .jimny-ml-detail-footer code{font-size:9px;overflow-wrap:anywhere}
.jimny-ml-reveal{transition:opacity .22s ease,transform .22s ease}
.jimny-ml-reveal-hidden{opacity:0;transform:translateY(-6px)}
.jimny-ml-reveal-visible{opacity:1;transform:translateY(0)}
@keyframes jimnyMlCardIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:610px){.smart-v5-root .jimny-ml-compact{padding:14px 12px}.smart-v5-root .jimny-ml-compact .jimny-ml-head{flex-direction:row;align-items:center;flex-wrap:wrap}.smart-v5-root .jimny-ml-compact .jimny-ml-refresh{margin-left:45px}.smart-v5-root .jimny-ml-compact .jimny-ml-overview{grid-template-columns:repeat(2,minmax(0,1fr))}.smart-v5-root .jimny-ml-compact .jimny-ml-actions{align-items:flex-start;flex-direction:column}.smart-v5-root .jimny-ml-compact .jimny-ml-detail-toggle{width:100%}}
@media(prefers-reduced-motion:reduce){.smart-v5-root .jimny-ml-compact{animation:none!important}.jimny-ml-reveal{transition:none!important}.smart-v5-root .jimny-ml-compact .jimny-ml-chevron{transition:none!important}}


/* ================================================================
   JIMNY LIQUID GLASS V9 | Owner-first dashboard; scoped to modal.
   Presentation only: POS/API, JSA ML, Ollama streaming stay intact.
   ================================================================ */
.jimny-glass-v9{--glass-coffee:#42291e;--glass-espresso:#2c1d17;--glass-caramel:#ae7248;--glass-bronze:#c89260;--glass-cream:#f9f3eb;--glass-ivory:#fffcf7;--glass-border:rgba(255,255,255,.76);--glass-text:#3f281f;--glass-shadow:rgba(67,35,19,.10)}
.jimny-glass-v9 .smart-v3-backdrop{background:radial-gradient(ellipse at 15% 18%,rgba(193,133,79,.32),transparent 46%),radial-gradient(ellipse at 84% 85%,rgba(242,203,152,.14),transparent 47%),rgba(26,18,14,.77)!important;backdrop-filter:blur(14px) saturate(1.14);-webkit-backdrop-filter:blur(14px) saturate(1.14)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell{grid-template-columns:minmax(0,1fr) clamp(318px,27%,370px);border-radius:29px;border:1px solid rgba(255,244,226,.66);background:rgba(255,251,244,.85);box-shadow:0 48px 130px rgba(24,14,9,.52),inset 0 1px 0 rgba(255,255,255,.5)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v3-header{background:linear-gradient(115deg,rgba(43,27,20,.93),rgba(94,55,35,.93) 55%,rgba(58,35,26,.95))!important;border-bottom:1px solid rgba(245,222,194,.34);box-shadow:inset 0 1px 0 rgba(255,248,223,.18)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v3-header-status{background:rgba(248,218,179,.12);border:1px solid rgba(252,233,203,.26)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-dashboard{background:radial-gradient(ellipse at 1% 3%,rgba(224,165,111,.16),transparent 42%),radial-gradient(ellipse at 95% 60%,rgba(223,190,143,.13),transparent 45%),linear-gradient(135deg,#fdf9f4,#f5eee6 56%,#faf5ee);padding:22px clamp(16px,2.3vw,30px) 38px;scrollbar-gutter:stable}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-chat{background:linear-gradient(152deg,rgba(255,255,255,.83),rgba(248,238,226,.77));border-left:1px solid rgba(215,176,139,.36);box-shadow:inset 1px 0 0 rgba(255,255,255,.6)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-heading{margin-bottom:19px;align-items:end}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-heading h2{font-size:clamp(28px,2.55vw,36px);letter-spacing:-.052em;margin:6px 0 7px;line-height:1.12}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-heading p{font-size:12px;max-width:400px;line-height:1.45}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-eyebrow{font-size:9px;letter-spacing:.20em}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-filter select{background:rgba(255,255,255,.66);border:1px solid rgba(211,174,138,.61);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-refresh{background:linear-gradient(135deg,#3f281c,#6a4230);border:1px solid rgba(255,255,255,.2);box-shadow:0 9px 18px rgba(66,38,24,.15),inset 0 1px rgba(255,255,255,.2)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-kpi-scope{font-size:10px;margin:0 0 11px;line-height:1.4}
.jimny-glass-small-dot{display:inline-block;width:6px;height:6px;margin-right:7px;border-radius:50%;background:#bb9368;box-shadow:0 0 0 3px rgba(202,144,84,.14)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-kpis.jimny-glass-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px;margin:0 0 24px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi{min-height:102px;border-radius:19px;padding:14px 15px;gap:7px;border:1px solid var(--glass-border);background:linear-gradient(148deg,rgba(255,255,255,.81),rgba(249,236,222,.62));box-shadow:0 7px 18px rgba(73,41,21,.07),inset 0 1px 0 rgba(255,255,255,.88);backdrop-filter:blur(13px) saturate(1.16);-webkit-backdrop-filter:blur(13px) saturate(1.16)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi:first-child{background:linear-gradient(125deg,rgba(58,33,22,.98),rgba(115,67,38,.94));border-color:rgba(239,202,163,.32)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi>span{font-size:11px;font-weight:750;color:#8d6e58}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi>strong{font-size:clamp(22px,2.25vw,31px);letter-spacing:-.043em;color:#37231a;white-space:nowrap}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi>small{font-size:10px;line-height:1.3;color:#a78973}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi:first-child>span,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi:first-child>small{color:#ddc6ad}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi:first-child>strong{color:#fff8ed}
.jimny-glass-section-heading{display:flex;justify-content:space-between;align-items:end;gap:10px;margin:5px 0 13px;padding:0 3px}
.jimny-glass-eyebrow{font-size:9px;letter-spacing:.19em;color:#aa7956;font-weight:850}
.jimny-glass-section-heading h3{margin:4px 0 0;font-size:19px;font-weight:900;letter-spacing:-.03em;color:#42291e}
.jimny-glass-label{font-size:10px;color:#886850;border:1px solid rgba(215,174,132,.5);border-radius:999px;padding:7px 10px;background:rgba(255,253,250,.55);white-space:nowrap}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-charts.jimny-glass-charts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.jimny-glass-trend,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.jimny-glass-hours{grid-column:1/-1}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.smart-v2-chart-card{border-radius:23px;border:1px solid rgba(255,255,255,.86);background:linear-gradient(130deg,rgba(255,255,255,.83),rgba(251,243,232,.68));box-shadow:0 14px 34px rgba(80,44,22,.065),inset 0 1px 0 #fff;backdrop-filter:blur(15px) saturate(1.13);-webkit-backdrop-filter:blur(15px) saturate(1.13);padding:22px;overflow:hidden}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.smart-v2-chart-card:focus-within{border-color:#c99362;box-shadow:0 0 0 3px rgba(211,145,85,.16),0 14px 34px rgba(80,44,22,.065)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-trend{min-height:355px;position:relative}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-trend h3{font-size:20px;letter-spacing:-.04em}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-trend .smart-v2-line-chart{display:block;width:100%;height:clamp(205px,27vh,270px);overflow:visible;margin-top:9px;filter:drop-shadow(0 6px 8px rgba(166,106,67,.08))}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-trend .smart-v2-chart-meta,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-trend .smart-v2-chart-scope{background:rgba(250,239,224,.54);border-radius:12px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-hours{min-height:250px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-hours h3{font-size:19px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-hours .smart-v2-hours{margin-top:14px;gap:9px;padding:2px 0 1px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-hours .smart-v2-hour-track{height:133px;border-radius:12px;background:rgba(184,137,94,.13)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-hours .smart-v2-hour-track>div{border-radius:10px 10px 7px 7px;background:linear-gradient(180deg,#d69d68,#9c623d)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-stock,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-forecast{min-height:186px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-stock .smart-v4-stock-empty{min-height:130px;padding:11px;margin:0;display:flex;align-items:center;justify-content:center;gap:7px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-stock .smart-v4-stock-symbol{width:36px;height:36px;flex:0 0 36px;animation:none}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-stock .smart-v4-stock-copy strong{font-size:12px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-stock .smart-v4-stock-copy span{font-size:10px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-stock .smart-v4-stock-reload{display:none}
.jimny-glass-insight-head{display:flex;align-items:start;justify-content:space-between;gap:10px}
.jimny-glass-chip{padding:7px 10px;font-size:10px;color:#7e4b2d;background:rgba(247,226,203,.64);border:1px solid rgba(207,164,120,.54);border-radius:999px;font-weight:850}
.jimny-glass-forecast-hero{display:flex;align-items:end;justify-content:space-between;flex-wrap:wrap;gap:10px;margin:8px 0 14px}
.jimny-glass-forecast-hero>div{display:flex;flex-direction:column;gap:4px}
.jimny-glass-forecast-hero>div>span{font-size:10px;color:#866d58}
.jimny-glass-forecast-hero strong{font-size:26px;letter-spacing:-.035em;color:#473020}
.jimny-glass-forecast-date{font-size:11px;color:#93755d}
.jimny-glass-technical-details{margin-top:7px;border-top:1px solid rgba(207,177,151,.4);padding-top:12px}
.jimny-glass-technical-details summary{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:11px;font-weight:800;color:#774d32;cursor:pointer;outline-offset:4px}
.jimny-glass-technical-details summary::-webkit-details-marker{display:none}
.jimny-glass-technical-details summary>span{transition:transform .24s}
.jimny-glass-technical-details[open] summary>span{transform:rotate(180deg)}
.jimny-glass-technical-content{padding:14px 0 0}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-technical-content .smart-v2-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-technical-content .smart-v2-kpi{min-height:78px;padding:10px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-technical-content .smart-v2-chart-meta{padding:9px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-disclaimer{margin-top:13px;font-size:10px;color:#9c816d;padding:8px 3px 0}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-ml-heading{margin-top:24px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-ml-zone.jimny-ml-compact{padding:19px 20px;margin-bottom:5px;background:linear-gradient(145deg,rgba(254,249,242,.84),rgba(240,221,198,.52));border:1px solid rgba(255,255,255,.84);border-radius:24px;box-shadow:0 14px 28px rgba(92,56,30,.08),inset 0 1px 0 rgba(255,255,255,.96);backdrop-filter:blur(14px) saturate(1.14);-webkit-backdrop-filter:blur(14px) saturate(1.14)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-ml-compact .jimny-ml-head{margin-bottom:13px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-ml-compact .jimny-ml-head h3{font-size:18px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-ml-overview{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-ml-overview>div{background:linear-gradient(150deg,rgba(255,255,255,.83),rgba(250,233,213,.55));border:1px solid rgba(255,255,255,.94);min-height:74px;box-shadow:inset 0 1px 0 #fff}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-ml-overview strong{font-size:clamp(17px,1.7vw,23px)}
.jimny-glass-ml-metadata{display:flex;flex-wrap:wrap;gap:5px 20px;margin:11px 1px 8px;color:#8a6a50;font-size:10px;line-height:1.6}
.jimny-glass-ml-metadata strong{color:#543526;font-weight:800}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-ml-compact .jimny-ml-status-line{margin-bottom:13px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-ml-compact .jimny-ml-actions{margin-top:8px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-chat-head{background:rgba(255,251,245,.65);border-bottom:1px solid rgba(225,198,170,.48);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-chat-head-title{font-size:13px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages{padding:19px 17px;gap:12px;background:radial-gradient(ellipse at 0 8%,rgba(236,201,158,.14),transparent 58%)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-message{max-width:91%;width:fit-content;min-width:0;min-height:0;font-size:12px;line-height:1.65;border-radius:19px;padding:13px 15px;box-shadow:0 7px 20px rgba(81,53,33,.055)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-bot{background:linear-gradient(145deg,rgba(255,255,255,.91),rgba(245,231,214,.84));border:1px solid rgba(255,255,255,.92);color:#492f23;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom-left-radius:6px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-user{background:linear-gradient(138deg,rgba(65,36,23,.97),rgba(119,68,39,.96));border:1px solid rgba(250,220,179,.28);color:#fffaf0;border-bottom-right-radius:6px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-suggestions{background:rgba(252,246,238,.80);border-top:1px solid rgba(234,206,177,.4);padding:11px 14px;gap:6px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-suggestions button{border-radius:999px;background:rgba(255,255,255,.78);border:1px solid rgba(214,167,121,.55);color:#67432c;font-size:10px;padding:9px 11px;box-shadow:inset 0 1px rgba(255,255,255,.93)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-suggestions button:hover{background:linear-gradient(130deg,#62402d,#99633c);color:#fff;transform:translateY(-2px)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-form{background:rgba(252,246,236,.92);padding:12px 14px 14px;border-top:1px solid rgba(227,195,160,.6);gap:9px}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-form input{min-height:47px;border-radius:15px;border:1px solid rgba(207,160,116,.6);background:rgba(255,255,255,.79);font-size:12px;box-shadow:inset 0 1px 3px rgba(85,54,31,.04);backdrop-filter:blur(10px)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-form input:focus{border-color:#cb8d57;box-shadow:0 0 0 4px rgba(207,146,84,.16),0 7px 24px rgba(142,92,47,.08)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-form button{background:linear-gradient(135deg,#503121,#83502f);border:1px solid rgba(255,255,255,.2);border-radius:15px;min-width:82px;box-shadow:inset 0 1px rgba(255,255,255,.17),0 7px 18px rgba(71,38,21,.18)}
/* Shared motion: slow, gentle glass reflections rather than continuous busy effects. */
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.smart-v2-chart-card,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-ml-compact{position:relative;isolation:isolate}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi::before,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.smart-v2-chart-card::after,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-ml-compact::before{content:'';position:absolute;pointer-events:none;inset:0;border-radius:inherit;background:linear-gradient(125deg,rgba(255,255,255,.29),transparent 32%,transparent 65%,rgba(255,255,255,.16));opacity:.32;transition:opacity .35s,transform .45s;z-index:0}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi:hover::before,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.smart-v2-chart-card:hover::after,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-ml-compact:hover::before{opacity:.72;transform:translateY(-3px)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.smart-v2-chart-card>*{position:relative;z-index:1}
.jimny-glass-v9 .smart-panel.jimny-glass-shell button:active{transform:translateY(1px) scale(.985)}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-chart-card:hover{transform:translateY(-2px)!important}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v4-stock-empty{background:rgba(255,252,246,.54)}
@media(max-width:1200px) and (min-width:981px){.jimny-glass-v9 .smart-panel.jimny-glass-shell{grid-template-columns:minmax(0,1fr) 320px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-dashboard{padding:20px 18px 30px}}
@media(max-width:980px){.jimny-glass-v9 .smart-panel.jimny-glass-shell{grid-template-columns:minmax(0,1fr);grid-template-rows:70px 54px minmax(0,1fr)}.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-dashboard,.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-chat{grid-column:1;grid-row:3;border-left:0}.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-dashboard{padding:20px 17px 30px}}
@media(max-width:760px){.jimny-glass-v9 .smart-panel.jimny-glass-shell{border-radius:0;border:0}.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-dashboard{padding:16px 13px 28px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-heading{gap:14px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-heading h2{font-size:27px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-charts.jimny-glass-charts{grid-template-columns:1fr;gap:12px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.jimny-glass-trend,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.jimny-glass-hours{grid-column:auto}.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.smart-v2-chart-card{padding:16px;border-radius:19px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi{padding:12px 10px;min-height:95px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-kpis.jimny-glass-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-trend .smart-v2-line-chart{height:190px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-hours .smart-v2-hour-track{height:118px}.jimny-glass-section-heading h3{font-size:17px}.jimny-glass-label{font-size:9px}.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-ml-overview{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:420px){.jimny-glass-section-heading{align-items:start;flex-direction:column}.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-stock .smart-v4-stock-empty{flex-direction:column;min-height:100px}}
@supports not ((-webkit-backdrop-filter:blur(1px)) or (backdrop-filter:blur(1px))){.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-chat,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-kpis>.smart-v2-kpi,.jimny-glass-v9 .smart-panel.jimny-glass-shell .jimny-glass-charts>.smart-v2-chart-card{background:#fffaf3}}
@media(prefers-reduced-motion:reduce){.jimny-glass-v9 .smart-panel.jimny-glass-shell *,.jimny-glass-v9 .smart-panel.jimny-glass-shell *::before,.jimny-glass-v9 .smart-panel.jimny-glass-shell *::after{animation-duration:.001ms!important;transition-duration:.001ms!important;scroll-behavior:auto!important;transform:none!important}}


/* =====================================================================
   JIMNY V10 FINAL - canonical role-specific layout + message-row architecture.
   Does NOT depend on a legacy V10 CSS shim. Keep this block LAST.
   ===================================================================== */
.jimny-glass-v9 .smart-panel.smart-v3-unified.smart-v3-chat-only.jimny-glass-shell {
    grid-template-columns: minmax(0,1fr);
    grid-template-rows: 76px minmax(0,1fr);
    width: 100%;
    max-width: none;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified.smart-v3-chat-only.jimny-glass-shell .smart-v2-chat {
    grid-column: 1 / -1;
    grid-row: 2;
    width: 100%;
    min-width: 0;
    max-width: none;
    height: 100%;
    border-left: 0;
    background: linear-gradient(150deg,rgba(255,253,249,.98),rgba(247,235,220,.93));
}
.jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-v10-cashier-hero {
    flex: 0 0 auto;
    padding: 15px 21px 14px;
    border-bottom: 1px solid rgba(216,181,146,.55);
    background: linear-gradient(118deg,rgba(240,214,183,.52),rgba(255,251,245,.82));
}
.jimny-glass-v9 .smart-v10-cashier-kicker {
    display:flex;align-items:center;gap:8px;color:#956840;
    font-size:9px;font-weight:900;letter-spacing:.12em;
}
.jimny-glass-v9 .smart-v10-cashier-hero h2 {
    margin:6px 0 4px;font-size:18px;line-height:1.2;color:#4b2e1f;
    font-weight:900;letter-spacing:-.025em;
}
.jimny-glass-v9 .smart-v10-cashier-hero p {
    margin:0;font-size:11px;line-height:1.4;color:#886952;
}
.jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-insights {
    flex:0 0 auto; max-height:118px;overflow-y:auto;align-content:start;
    padding:10px 20px;gap:7px;background:rgba(255,251,245,.65);
}
.jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-insight {
    padding:8px 10px;font-size:10px;line-height:1.45;max-width:none;
}
.jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-v2-chat-head {
    padding:13px 21px 12px;
}
.jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-suggestions,
.jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-form {padding-left:19px;padding-right:19px}
/* Each ROW spans the column. Bubbles size to contents; never stretch. */
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages.smart-v10-messages {
    display:flex;
    flex:1 1 0;
    flex-direction:column;
    align-items:stretch;
    justify-content:flex-start;
    gap:10px;
    min-height:0;
    min-width:0;
    padding:16px 17px 22px;
    overflow-y:auto;
    overscroll-behavior:contain;
    scroll-behavior:smooth;
}
.jimny-glass-v9 .smart-messages.smart-v10-messages > .smart-v10-message-row {
    width:100%;min-width:0;min-height:0;
    flex:0 0 auto;
    display:flex;
    align-items:flex-start;
    justify-content:flex-start;
}
.jimny-glass-v9 .smart-messages.smart-v10-messages > .smart-v10-message-row.is-user {
    justify-content:flex-end;
}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages.smart-v10-messages .smart-v10-bubble.smart-message {
    flex:0 0 auto;
    display:block;
    position:relative;
    box-sizing:border-box;
    width:max-content;
    min-width:0;
    max-width:min(88%,355px);
    height:auto;
    min-height:0;
    margin:0;
    padding:10px 14px;
    border-radius:17px 17px 17px 5px;
    font-size:12px;
    font-weight:450;
    line-height:1.6;
    white-space:normal;
    overflow-wrap:anywhere;
    word-break:normal;
    box-shadow:0 7px 20px rgba(81,53,33,.055);
}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages.smart-v10-messages .smart-v10-bubble.smart-user {
    max-width:min(82%,295px);
    width:max-content;
    min-height:0;
    height:auto;
    padding:10px 13px;
    color:#fffaf0;
    border-radius:17px 17px 5px 17px;
    background:linear-gradient(130deg,#493025,#744832);
    border:1px solid rgba(255,232,202,.25);
    box-shadow:0 7px 17px rgba(66,37,22,.12);
}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages.smart-v10-messages .smart-v10-bubble.smart-bot {
    background:linear-gradient(150deg,rgba(255,252,248,.95),rgba(245,233,219,.86));
    border:1px solid rgba(223,202,181,.74);
    color:#493126;
    border-radius:17px 17px 17px 5px;
}
.jimny-glass-v9 .smart-messages.smart-v10-messages .smart-v10-message-text {
    margin:0;padding:0;max-width:100%;min-height:0;
    height:auto;line-height:inherit;white-space:pre-wrap;
}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages.smart-v10-messages .smart-typing {
    width:58px;max-width:58px;padding:11px 13px;
}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-v2-chat-head {flex:0 0 auto}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-suggestions {flex:0 0 auto}
.jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-form {flex:0 0 auto}
/* Keep compact cards stable on smaller viewports. */
@media(max-width:980px){
    .jimny-glass-v9 .smart-panel.smart-v3-unified.smart-v3-chat-only.jimny-glass-shell {
        grid-template-columns:minmax(0,1fr);
        grid-template-rows:70px minmax(0,1fr);
    }
    .jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-v2-chat {
        grid-column:1 !important;grid-row:2 !important;
    }
}
@media(max-width:760px){
    .jimny-glass-v9 .smart-panel.smart-v3-unified.smart-v3-chat-only.jimny-glass-shell{
        grid-template-rows:70px minmax(0,1fr);
    }
    .jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-v10-cashier-hero{
        padding:11px 15px 10px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-insights{
        max-height:98px;padding:7px 15px;
    }
    .jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages.smart-v10-messages{
        padding:13px 12px 16px;gap:9px;
    }
    .jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages.smart-v10-messages .smart-v10-bubble.smart-message{
        max-width:90%;font-size:12px;padding:10px 12px;
    }
    .jimny-glass-v9 .smart-panel.jimny-glass-shell .smart-messages.smart-v10-messages .smart-v10-bubble.smart-user{
        max-width:82%;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-suggestions,
    .jimny-glass-v9 .smart-panel.smart-v3-chat-only .smart-form{padding-left:12px;padding-right:12px}
}
@media(prefers-reduced-motion:reduce){
    .jimny-glass-v9 .smart-messages.smart-v10-messages{scroll-behavior:auto}
    .jimny-glass-v9 .smart-messages.smart-v10-messages .smart-v10-bubble{animation:none;transition:none}
}
</style>

<script>
function smartAssistant(){
    return {
        allowProactive: {{ request()->routeIs('owner.dashboard') ? 'false' : 'true' }},
        open:false, loading:false, streamingReply:false, input:'', messages:[], insights:[], proactiveVisible:false, currentInsight:'', proactiveTimer:null, proactiveDismissed:false,
        activeTab:'chat', showAllInsights:false, showAllHours:false, savedBodyOverflow:null,
        canAnalytics: {{ in_array(auth()->user()->role, ['owner', 'admin']) ? 'true' : 'false' }},
        analytics:null, analyticsLoading:false, analyticsError:'', analyticsDays:'30', analyticsMenu:'', analyticsMenus:[], trendHover:null,
        motionOn:false, motionObserver:null, motionFrame:null, motionStartFrame:null, animatedKpis:{},
        mlDecision:null, mlStatus:'idle', mlMessage:'', mlLoading:false, mlShowAll:false, mlDetailsOpen:false,
        dragging:false, moved:false, dragStartX:0, dragStartY:0, startLeft:0, startTop:0,
        left:null, top:null, suppressClick:false, panelStyle:{}, bubbleStyle:{}, rootStyle:{},
        initialized:false, hideTimer:null, refreshTimer:null, firstTimer:null, layoutObserver:null, viewportHandler:null, moveHandler:null, endHandler:null,
        // JIMNY_SMART_INSIGHT_V14: smart cadence (idle 30s, activity polling 5s)
        activityTimer:null, activitySnapshot:null, activityBusy:false, lastProactiveAt:0, insightCursor:0,
        async init(){
            if(this.initialized)return;
            this.initialized=true;
            this.restorePosition();
            this.applyPosition();
            this.viewportHandler=()=>{this.clampPosition();this.applyPosition();this.positionPanel();this.positionBubble();};
            this.moveHandler=e=>this.dragMove(e);
            this.endHandler=()=>this.endDrag();
            window.addEventListener('resize',this.viewportHandler);
            window.visualViewport?.addEventListener('resize',this.viewportHandler);
            window.visualViewport?.addEventListener('scroll',this.viewportHandler);
            window.addEventListener('pointermove',this.moveHandler);
            window.addEventListener('pointerup',this.endHandler);
            window.addEventListener('pointercancel',this.endHandler);
            this.$nextTick(()=>{
                if(window.ResizeObserver){
                    this.layoutObserver=new ResizeObserver(()=>{this.positionBubble();this.positionPanel();});
                    this.layoutObserver.observe(this.$refs.bubble);
                    this.layoutObserver.observe(this.$refs.panel);
                }
            });
            try{
                const response = await fetch('{{ route('smart-assistant.insights') }}', {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
                if(response.ok){
                    const data=await response.json();
                    this.insights=data.messages||[];
                    this.prepareProactiveInsight();
                }
            }catch(e){}
            // V14: cek perubahan POS dengan respons ringan setiap 5 detik.
            // Jangan munculkan notifikasi hanya karena polling ini berjalan.
            this.activityTimer=setInterval(()=>this.pollBusinessActivity(),5000);
            this.pollBusinessActivity();
            // Cadangan: ringkasan POS biasa tetap disegarkan setiap 60 detik.
            this.refreshTimer=setInterval(()=>this.refreshInsights(),60000);
        },
        async refreshInsights(){
            try{
                const response = await fetch('{{ route('smart-assistant.insights') }}', {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Cache-Control':'no-cache'}});
                if(response.ok){
                    const data=await response.json();
                    this.insights=data.messages||[];
                    if(!this.proactiveTimer)this.prepareProactiveInsight();
                }
            }catch(e){}
        },
        prepareProactiveInsight(){
            if(!this.allowProactive || !this.insights.length || this.proactiveTimer)return;
            // Satu insight pertama baru muncul setelah 30 detik.
            // Timer idle akan di-reset ketika aktivitas penting terdeteksi.
            this.scheduleIdleInsight();
        },
        scheduleIdleInsight(){
            clearTimeout(this.proactiveTimer);
            if(!this.allowProactive)return;
            this.proactiveTimer=setTimeout(()=>{
                this.proactiveTimer=null;
                if(this.insights.length){
                    const text=this.insights[this.insightCursor % this.insights.length];
                    this.insightCursor++;
                    this.showProactive(text,false);
                }
                // Jika pengguna sedang mengetik/membuka chat, coba lagi 30 detik nanti.
                if(!this.proactiveTimer)this.scheduleIdleInsight();
            },30000);
        },
        async pollBusinessActivity(){
            if(this.activityBusy || document.hidden)return;
            this.activityBusy=true;
            try{
                const response=await fetch('{{ route('smart-assistant.insights') }}?activity_only=1',{
                    headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Cache-Control':'no-cache'},
                    cache:'no-store'
                });
                if(!response.ok)return;
                const data=await response.json();
                const current={
                    transaction_key:String(data.transaction_key||''),
                    stock_key:String(data.stock_key||'')
                };
                const storageKey='jimnySmartInsightV14Activity_{{ auth()->id() }}';
                let previous=this.activitySnapshot;
                if(!previous){
                    try{
                        const saved=JSON.parse(sessionStorage.getItem(storageKey)||'null');
                        if(saved && Number.isFinite(saved.savedAt) && Date.now()-saved.savedAt < 900000){
                            previous=saved;
                        }
                    }catch(e){}
                }
                this.activitySnapshot=current;
                try{sessionStorage.setItem(storageKey,JSON.stringify({...current,savedAt:Date.now()}));}catch(e){}
                if(!previous)return; // kunjungan pertama: tidak menganggap data lama sebagai kejadian baru.
                const transactionChanged=previous.transaction_key !== current.transaction_key && current.transaction_key;
                const stockChanged=previous.stock_key !== current.stock_key && current.stock_key;
                if(!transactionChanged && !stockChanged)return;
                await this.refreshInsights();
                let text='Ada pembaruan data POS. Lihat ringkasan terbaru di JIMNY.';
                if(transactionChanged){
                    const status=String(data.transaction_status||'').toLowerCase();
                    const payment=String(data.payment_status||'').toLowerCase();
                    if(status==='success' && payment==='paid'){
                        text=this.insights.find(m=>/transaksi sukses hari ini|hari ini tercatat/i.test(m))
                            || 'Ada transaksi berhasil dibayar. Ringkasan penjualan telah diperbarui.';
                    }else if(status==='expired'){
                        text='Pembayaran QRIS kedaluwarsa. Periksa detail transaksi untuk status terbaru.';
                    }else if(status==='cancelled' || status==='canceled'){
                        text='Ada transaksi dibatalkan. Periksa statusnya di riwayat transaksi.';
                    }else if(status==='pending'){
                        text='Ada transaksi menunggu pembayaran. Pastikan statusnya sebelum dianggap lunas.';
                    }else{
                        text='Ada perubahan status transaksi. Periksa riwayat transaksi terbaru.';
                    }
                }else if(stockChanged){
                    text='Ada perubahan stok menu. Cek persediaan terbaru jika diperlukan.';
                }
                // Peristiwa penting muncul tanpa menunggu siklus idle 30 detik;
                // jeda minimum 7 detik mencegah notifikasi bertubi-tubi.
                this.showProactive(text,true);
            }catch(e){}finally{this.activityBusy=false;}
        },
        showProactive(text,urgent=false){
            if(!this.allowProactive || this.open || this.dragging || document.hidden || !text)return false;
            const now=Date.now();
            if(this.lastProactiveAt && now-this.lastProactiveAt < (urgent?7000:29000))return false;
            clearTimeout(this.hideTimer);
            this.currentInsight=text;
            this.proactiveVisible=true;
            this.lastProactiveAt=now;
            this.$nextTick(()=>this.positionBubble());
            // 5 detik hanya durasi tampil, bukan interval kemunculan.
            this.hideTimer=setTimeout(()=>{this.proactiveVisible=false;},5000);
            this.scheduleIdleInsight();
            return true;
        },
        dismissProactive(){
            clearTimeout(this.hideTimer);
            this.proactiveVisible=false;
            this.lastProactiveAt=Date.now();
            this.scheduleIdleInsight();
        },
        openFromProactive(){
            this.dismissProactive();
            this.openPanel();
        },
        viewport(){
            const v=window.visualViewport;
            return {left:v?.offsetLeft||0,top:v?.offsetTop||0,width:v?.width||window.innerWidth,height:v?.height||window.innerHeight};
        },
        fitOverlay(width,height){
            const v=this.viewport(),margin=12,gap=12,size=66;
            const minX=v.left+margin,minY=v.top+margin;
            const maxX=Math.max(minX,v.left+v.width-width-margin),maxY=Math.max(minY,v.top+v.height-height-margin);
            const above=this.top-height-gap,below=this.top+size+gap;
            let x=this.left+size-width,y=above;
            if(above<minY){
                if(below+height<=v.top+v.height-margin)y=below;
                else {y=this.top+(size-height)/2;x=this.left+size+gap;
                    if(x+width>v.left+v.width-margin)x=this.left-width-gap;}
            }
            return {left:Math.max(minX,Math.min(x,maxX))+'px',top:Math.max(minY,Math.min(y,maxY))+'px',right:'auto',bottom:'auto'};
        },
        positionBubble(){
            if(!this.proactiveVisible || !this.$refs.bubble)return;
            const v=this.viewport(),width=Math.max(1,Math.min(310,v.width-24));
            const height=Math.min(this.$refs.bubble.offsetHeight||110,Math.max(1,v.height-24));
            this.bubbleStyle={...this.fitOverlay(width,height),width:width+'px',maxHeight:Math.max(1,v.height-24)+'px'};
        },
        prefersLessMotion(){
            return Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches);
        },
        animatedValue(key, value){
            if(value === null || value === undefined || value === '') return '—';
            const number=Number(value);
            if(!Number.isFinite(number)) return String(value);
            const animated=this.animatedKpis[key];
            return (Number.isFinite(animated)?animated:number).toLocaleString('id-ID');
        },
        cancelMotion(){
            this.motionObserver?.disconnect();this.motionObserver=null;
            if(this.motionFrame!==null)cancelAnimationFrame(this.motionFrame);
            if(this.motionStartFrame!==null)cancelAnimationFrame(this.motionStartFrame);
            this.motionFrame=null;this.motionStartFrame=null;
            this.motionOn=false;
        },
        runKpiMotion(){
            const summary=this.analytics?.summary;
            if(!summary || !this.open)return;
            const keys=['transactions','sold_qty','active_days'];
            const targets={};
            for(const key of keys){
                const value=Number(summary[key]);
                if(Number.isFinite(value) && value>=0)targets[key]=Math.round(value);
            }
            if(!Object.keys(targets).length)return;
            if(this.prefersLessMotion()){
                this.animatedKpis={...targets};return;
            }
            const initial={};
            for(const key of Object.keys(targets))initial[key]=0;
            this.animatedKpis=initial;
            let start=null;
            const duration=1100;
            const tick=(time)=>{
                if(!this.open)return;
                if(start===null)start=time;
                const progress=Math.min(1,(time-start)/duration);
                const eased=1-Math.pow(1-progress,4);
                const values={};
                for(const key of Object.keys(targets))values[key]=Math.round(targets[key]*eased);
                this.animatedKpis=values;
                if(progress<1)this.motionFrame=requestAnimationFrame(tick);
                else this.motionFrame=null;
            };
            this.motionFrame=requestAnimationFrame(tick);
        },
        runVisualMotion(){
            this.cancelMotion();
            if(!this.open)return;
            if(this.prefersLessMotion()){
                this.runKpiMotion();return;
            }
            this.$nextTick(()=>{
                if(!this.open)return;
                this.motionStartFrame=requestAnimationFrame(()=>{
                    this.motionStartFrame=null;
                    if(!this.open)return;
                    const dashboard=this.$refs.panel?.querySelector('.smart-v2-dashboard');
                    if(!dashboard){this.runKpiMotion();return;}
                    const elements=Array.from(dashboard.querySelectorAll('[data-j5-reveal]'));
                    // Use visible-on-failure fallback when observer is unavailable.
                    for(const el of elements)el.classList.remove('j5-in-view');
                    this.motionOn=true;
                    if(window.IntersectionObserver){
                        this.motionObserver=new IntersectionObserver(entries=>{
                            for(const entry of entries){
                                if(!entry.isIntersecting)continue;
                                entry.target.classList.add('j5-in-view');
                                this.motionObserver?.unobserve(entry.target);
                            }
                        },{root:dashboard,threshold:0.04,rootMargin:'0px 0px 40px 0px'});
                        elements.forEach((el,index)=>{
                            el.style.setProperty('--j5-delay',Math.min(index%4,3)*75+'ms');
                            this.motionObserver.observe(el);
                        });
                    }else elements.forEach(el=>el.classList.add('j5-in-view'));
                    this.runKpiMotion();
                });
            });
        },
        moveCardGlow(event){
            if(this.prefersLessMotion() || event.pointerType==='touch')return;
            const card=event.target.closest('.smart-v2-chart-card');
            if(!card)return;
            const r=card.getBoundingClientRect();
            card.style.setProperty('--j5-mouse-x',((event.clientX-r.left)/r.width*100).toFixed(1)+'%');
            card.style.setProperty('--j5-mouse-y',((event.clientY-r.top)/r.height*100).toFixed(1)+'%');
        },
        destroy(){
            this.cancelMotion();
            if(this.open)document.body.style.overflow=this.savedBodyOverflow ?? '';
            clearTimeout(this.proactiveTimer);clearInterval(this.refreshTimer);clearInterval(this.activityTimer);
            clearTimeout(this.firstTimer);clearTimeout(this.hideTimer);
            this.layoutObserver?.disconnect();
            window.removeEventListener('resize',this.viewportHandler);
            window.visualViewport?.removeEventListener('resize',this.viewportHandler);
            window.visualViewport?.removeEventListener('scroll',this.viewportHandler);
            window.removeEventListener('pointermove',this.moveHandler);
            window.removeEventListener('pointerup',this.endHandler);
            window.removeEventListener('pointercancel',this.endHandler);
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
            const size=66, margin=8,v=this.viewport();
            this.left=Math.max(v.left+margin,Math.min(Number(this.left)||margin,v.left+v.width-size-margin));
            this.top=Math.max(v.top+margin,Math.min(Number(this.top)||margin,v.top+v.height-size-margin));
        },
        applyPosition(){
            this.rootStyle={left:this.left+'px',top:this.top+'px',right:'auto',bottom:'auto'};
            this.positionBubble();
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
                try{localStorage.setItem('jimnySmartAssistantPosition',JSON.stringify({left:this.left,top:this.top}));}catch(e){}
                setTimeout(()=>this.suppressClick=false,80);
            }
        },
        toggleFromClick(){
            if(this.suppressClick)return;
            if(this.open)this.closePanel();
            else this.openPanel();
        },
        openPanel(){
            if(this.open)return;
            this.open=true;
            this.activeTab='chat';
            this.proactiveVisible=false;
            this.savedBodyOverflow=document.body.style.overflow;
            document.body.style.overflow='hidden';
            if(this.canAnalytics){this.activeTab='analytics';window.JimnyOwnerV20?.load();}
            this.$nextTick(()=>{
                this.runVisualMotion();
                this.positionPanel();
                this.$refs.chatInput?.focus({preventScroll:true});
            });
        },
        closePanel(){
            if(!this.open)return;
            this.open=false;
            this.cancelMotion();
            document.body.style.overflow=this.savedBodyOverflow ?? '';
            this.savedBodyOverflow=null;
            this.$nextTick(()=>this.$refs.root?.querySelector('.smart-fab')?.focus({preventScroll:true}));
        },
        setActiveTab(tab){
            if(!this.canAnalytics && tab==='analytics')return;
            this.activeTab=tab==='analytics'?'analytics':'chat';
            if(this.activeTab==='analytics'){window.JimnyOwnerV20?.load();}
            this.$nextTick(()=>this.positionPanel());
        },
        trapFocus(event){
            if(!this.open || event.key!=='Tab')return;
            const items=Array.from(this.$refs.panel.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]'))
                .filter(el=>el.getClientRects().length && window.getComputedStyle(el).visibility!=='hidden');
            if(!items.length)return;
            const first=items[0],last=items[items.length-1];
            if(event.shiftKey && document.activeElement===first){event.preventDefault();last.focus();}
            else if(!event.shiftKey && document.activeElement===last){event.preventDefault();first.focus();}
        },
        positionPanel(){
            if(!this.open)return;
            const v=this.viewport();
            const mobile=v.width<=760;
            const width=mobile?v.width:(this.canAnalytics ? Math.min(1440,Math.max(1,v.width-36)) : Math.min(540,Math.max(1,v.width-36)));
            const height=mobile?v.height:Math.min(840,Math.max(1,v.height-36));
            this.panelStyle={
                left:(v.left+(v.width-width)/2)+'px',
                top:(v.top+(v.height-height)/2)+'px',
                right:'auto', bottom:'auto',
                width:width+'px', height:height+'px',
                boxSizing:'border-box'
            };
        },
        async loadMlDecisions(force=false){
            if(!this.canAnalytics || this.mlLoading)return;
            if(!force && this.mlStatus !== 'idle')return;
            this.mlLoading=true;
            this.mlMessage='';
            try{
                const response=await fetch('{{ route('smart-assistant.decisions') }}',{
                    headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Cache-Control':'no-cache'},
                    cache:'no-store'
                });
                if(response.status===409){
                    this.mlDecision=null;
                    this.mlStatus='blocked';
                    this.mlMessage='Belum ada hasil pipeline Fase 4 yang dipublikasikan, atau verifikasi artefak gagal. Jalankan pipeline Python secara terpisah; tidak ada prediksi yang direkayasa.';
                    return;
                }
                if(response.status===403){throw new Error('Akun ini tidak memiliki akses ke paket keputusan ML.');}
                if(!response.ok){throw new Error('Gagal mengambil paket ML (HTTP '+response.status+').');}
                const data=await response.json();
                if(!data || data.phase!==5 || !['READY_FOR_REVIEW','REVIEW_REQUIRED'].includes(data.status) ||
                    data.integrity?.verified !== true || data.safety?.read_only !== true ||
                    data.safety?.auto_restock !== false || data.safety?.stock_mutated !== false ||
                    !Array.isArray(data.restock?.recommendations)){
                    throw new Error('Struktur atau deklarasi keamanan paket ML tidak sesuai.');
                }
                this.mlDecision=data;
                this.mlStatus='verified';
                this.mlShowAll=false;
                this.mlDetailsOpen=false;
            }catch(error){
                this.mlDecision=null;
                this.mlStatus='error';
                this.mlMessage=error.message||'Tidak dapat menghubungi layanan ML.';
            }finally{this.mlLoading=false;}
        },
        mlForecast(){return this.mlDecision?.forecast?.forecast || null;},
        mlRows(){return Array.isArray(this.mlDecision?.restock?.recommendations) ? this.mlDecision.restock.recommendations : [];},
        mlDetailRows(){
            const rows=this.mlRows().filter(row=>row && row.status!=='inactive_or_deleted');
            return rows.slice().sort((a,b)=>{
                const priority=x=>{
                    const q=Number(x.restock_qty);
                    return x.status==='restock' && Number.isFinite(q) && q>0 ? q : 0;
                };
                const delta=priority(b)-priority(a);
                if(delta!==0)return delta;
                const demand=x=>{const n=Number(x.point);return Number.isFinite(n) && n>=0 ? n : -1;};
                return demand(b)-demand(a);
            });
        },
        mlQty(value,suffix=''){
            if(value===null || value===undefined || value==='')return '—';
            const n=Number(value);
            return Number.isFinite(n) && n>=0 ? n.toLocaleString('id-ID',{maximumFractionDigits:1})+suffix : '—';
        },
        mlRecommendedCount(){return this.mlRows().filter(x=>x.status==='restock' && Number(x.restock_qty)>0).length;},
        mlOperational(){
            const d=this.mlDecision;if(!d)return false;
            let today='';
            try{today=new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());}catch(e){return false;}
            return d.restock?.mode==='operational_input' && d.restock?.snapshot_source==='actual' &&
                d.restock?.date===today && d.forecast?.historical_replay===false && d.status==='READY_FOR_REVIEW';
        },
        mlRowStatus(status){
            return ({restock:'Perlu dipertimbangkan',sufficient:'Stok mencukupi',insufficient_calibration:'Kalibrasi belum cukup',
                inactive_or_deleted:'Menu nonaktif',missing_snapshot:'Snapshot stok tidak ada',missing_forecast:'Forecast tidak ada'})[status] || (status || 'Periksa manual');
        },
        mlChatResponse(){
            if(this.mlStatus!=='verified' || !this.mlDecision){
                return 'Paket ML belum tersedia. '+(this.mlStatus==='error' ? this.mlMessage : 'Jalankan pipeline JSA, lalu sinkronkan.');
            }
            const f=this.mlForecast(),n=this.mlRecommendedCount();
            const status=this.mlOperational() ? 'Siap ditinjau; tetap perlu persetujuan.' : 'DEMO / perlu review — bukan untuk pembelian stok.';
            return 'Hasil ML JSA ✓\nModel: '+(f?.model||'—')+'\nPrediksi: '+this.mlQty(f?.total?.point,' porsi')+'\nPrioritas restock: '+n+' menu\n'+status;
        },
        async askMl(text='Hasil ML JSA'){
            if(this.loading)return;
            this.messages.push({role:'user',text});this.loading=true;this.input='';this.scroll();
            try{
                await this.loadMlDecisions(true);
                this.messages.push({role:'bot',text:this.mlChatResponse()});
            }finally{this.loading=false;this.scroll();}
        },
        async loadAnalytics(force=false){
            if(!this.canAnalytics || this.analyticsLoading)return;
            if(!force && this.analytics)return;

            this.trendHover=null;
            this.analyticsLoading=true;
            this.analyticsError='';

            try{
                const params=new URLSearchParams({
                    days:String(this.analyticsDays)
                });
                if(this.analyticsMenu!==''){
                    params.set('menu_id',this.analyticsMenu);
                }
                const response=await fetch(
                    '/smart-assistant/analytics?'+params.toString(),{
                    headers:{
                        'Accept':'application/json',
                        'X-Requested-With':'XMLHttpRequest'
                    }
                });

                if(!response.ok){
                    if(response.status===404){
                        throw new Error(
                            'Data analitik belum tersedia. Silakan coba Perbarui setelah layanan terhubung.'
                        );
                    }
                    if(response.status===403){
                        throw new Error('Akses analitik tidak diizinkan.');
                    }
                    throw new Error('Gagal membaca analitik (HTTP '+response.status+').');
                }

                const data=await response.json();

                if(!data || !Array.isArray(data.sales_trend)){
                    throw new Error('Format respons API analitik belum sesuai.');
                }

                this.analyticsMenus=Array.isArray(data.menu_options)
                    ? data.menu_options
                    : [];
                this.analytics=data;
                if(this.open)this.$nextTick(()=>this.runVisualMotion());
            }catch(error){
                this.analyticsError=error.message ||
                    'Tidak dapat membaca data analitik.';
            }finally{
                this.analyticsLoading=false;
            }
        },
        onTrendPointer(event){
            const series=this.analytics?.sales_trend;
            if(!Array.isArray(series) || series.length < 2)return;
            const rect=event.currentTarget.getBoundingClientRect();
            if(!rect.width)return;
            const x=(event.clientX-rect.left)*520/rect.width;
            const index=Math.max(0,Math.min(series.length-1,Math.round((x-24)*(series.length-1)/476)));
            this.trendHover=this.trendMarkers(series,'qty')[index] || null;
        },
        stepTrendHover(direction){
            const series=this.analytics?.sales_trend;
            if(!Array.isArray(series) || series.length < 2)return;
            const points=this.trendMarkers(series,'qty');
            const prev=this.trendHover?.index;
            const index=prev===undefined ? points.length-1 : Math.max(0,Math.min(points.length-1,prev+direction));
            this.trendHover=points[index] || null;
        },
        totalGraphQty(series){
            if(!Array.isArray(series))return 0;

            return series.reduce((sum,item)=>
                sum+Math.max(0,Number(item.qty)||0),0
            );
        },
        periodScope(){
            if(!this.analytics)return '';

            const days=Number(this.analytics.period_days)||
                Number(this.analyticsDays)||30;

            const id=this.analytics.selected_menu_id;

            let menu='Semua menu';

            if(id!==null && id!==undefined){
                const found=this.analyticsMenus.find(
                    item=>String(item.id)===String(id)
                );

                menu=found?.name||'Menu terpilih';
            }

            return 'Total '+days+' hari - '+menu;
        },
        visiblePeakHours(){
            const hours=this.analytics?.peak_hours||[];

            if(this.showAllHours)return hours;

            return hours.filter(item=>
                Number(item.transactions)>0
            );
        },        trendMarkers(series,key){
            if(!Array.isArray(series) || !series.length)return [];

            const values=series.map(item =>
                Math.max(0,Number(item[key])||0)
            );
            const maximum=Math.max(1,...values);

            return series.map((item,index)=>{
                const x=series.length===1
                    ? 260
                    : 24+(index*476/(series.length-1));

                const y=165-(values[index]/maximum)*130;

                return {
                    index,
                    x:x.toFixed(2),
                    y:y.toFixed(2),
                    qty:values[index],
                    date:String(item.date||'')
                };
            });
        },
        trendPeak(series){
            if(!Array.isArray(series) || !series.length)return 0;
            return Math.max(
                0,
                ...series.map(item=>Math.max(0,Number(item.qty)||0))
            );
        },
        shortDate(value){
            if(!value)return '—';
            const parts=String(value).split('-');
            if(parts.length!==3)return String(value);
            return parts[2]+'/'+parts[1];
        },
        peakHourShort(){
            const hours=(this.analytics?.peak_hours || []).filter(row=>Number(row.transactions)>0);
            if(!hours.length)return '—';
            const top=hours.reduce((best,row)=>Number(row.transactions)>Number(best.transactions)?row:best);
            return String(top.hour).padStart(2,'0')+':00';
        },
        peakSummary(){
            const hours=this.analytics?.peak_hours||[];
            const valid=hours.filter(item=>
                Number(item.transactions)>0
            );

            if(!valid.length)return 'Belum ada transaksi pada periode ini';

            const peak=valid.reduce((best,item)=>
                Number(item.transactions)>Number(best.transactions)
                    ? item : best
            );

            return 'Transaksi terbanyak tercatat pukul '+
                String(peak.hour).padStart(2,'0')+
                ':00 ('+peak.transactions+' transaksi)';
        },        chartPoints(series,key){
            if(!Array.isArray(series) || !series.length)return '';

            const values=series.map(item=>
                Math.max(0,Number(item[key])||0)
            );
            const maximum=Math.max(1,...values);

            return values.map((value,index)=>{
                const x=series.length===1
                    ? 260
                    : 24+(index*476/(series.length-1));
                const y=165-(value/maximum)*130;
                return x.toFixed(1)+','+y.toFixed(1);
            }).join(' ');
        },
        stockBarWidth(item){
            const stock=Math.max(0,Number(item.stock)||0);
            const reference=Math.max(
                1,
                Number(item.minimum_stock)||1
            );
            return Math.min(100,(stock/reference)*100);
        },
        hourBarHeight(item){
            const hours=this.analytics?.peak_hours||[];
            const maximum=Math.max(
                1,
                ...hours.map(hour=>Number(hour.transactions)||0)
            );
            return Math.max(
                0,
                Math.min(100,(Number(item.transactions)||0)/maximum*100)
            );
        },        async ask(text){
            text=(text||'').trim(); if(!text||this.loading)return;
            if(!this.canAnalytics && /(hasil ml|model ml|rekomendasi ml|restok ml|restock ml|forecast ml|fase [2-5]|hasil pipeline|prediksi per menu ml)/i.test(text)){
                return this.askMl(text);
            }
            const history=this.messages.slice(-6).map(m=>({role:m.role==='user'?'user':'assistant',content:String(m.text||'').slice(0,400)}));
            this.messages.push({role:'user',text});this.input='';this.loading=true;this.streamingReply=false;this.scroll();
            const controller=new AbortController();
            const timeout=setTimeout(()=>controller.abort(),58000);
            let replyIndex=-1;
            const append=(piece)=>{
                if(replyIndex<0){
                    this.messages.push({role:'bot',text:''});
                    replyIndex=this.messages.length-1;
                    this.streamingReply=true;
                }
                this.messages[replyIndex].text+=piece;this.scroll();
            };
            try{
                const response=await fetch('{{ route('smart-assistant.ai-chat') }}',{
                    method:'POST',
                    headers:{'Content-Type':'application/json','Accept':'application/x-ndjson','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                    body:JSON.stringify({message:text,history,owner_days:window.JimnyOwnerV20?.days||30,owner_horizon:window.JimnyOwnerV20?.horizon||7}),
                    signal:controller.signal
                });
                if(!response.ok){throw new Error('Status '+response.status);}
                const contentType=response.headers.get('content-type')||'';
                if(!contentType.includes('ndjson')){
                    const data=await response.json();
                    append(String(data.answer||'AI belum dapat menjawab.'));
                }else if(response.body && response.body.getReader){
                    const reader=response.body.getReader();
                    const decoder=new TextDecoder('utf-8');
                    let buffered='';
                    const onLine=(line)=>{
                        if(!line.trim())return;
                        let event;try{event=JSON.parse(line);}catch(_){return;}
                        if(event.type==='delta'||event.type==='fallback')append(String(event.text||''));
                        else if(event.type==='error')append('\n'+String(event.text||'Respons terputus.'));
                    };
                    while(true){
                        const {done,value}=await reader.read();
                        if(done)break;
                        buffered+=decoder.decode(value,{stream:true});
                        let index;
                        while((index=buffered.indexOf('\n'))>=0){
                            const line=buffered.slice(0,index);buffered=buffered.slice(index+1);
                            onLine(line);
                        }
                    }
                    buffered+=decoder.decode();
                    if(buffered.trim())onLine(buffered);
                }else{
                    const raw=await response.text();
                    for(const line of raw.split('\n')){
                        if(!line.trim())continue;
                        try{
                            const event=JSON.parse(line);
                            if(event.type==='delta'||event.type==='fallback')append(String(event.text||''));
                        }catch(_){}
                    }
                }
                if(replyIndex<0||!this.messages[replyIndex].text.trim())append('AI belum menghasilkan jawaban. Coba lagi.');
            }catch(e){
                append(e.name==='AbortError'?'AI membutuhkan waktu terlalu lama. Coba pertanyaan lebih singkat.':'Koneksi ke AI terputus. Periksa layanan Ollama dan Laravel.');
            }finally{
                clearTimeout(timeout);this.loading=false;this.streamingReply=false;this.scroll();
            }
        },
        scroll(){this.$nextTick(()=>{if(this.$refs.messages)this.$refs.messages.scrollTop=this.$refs.messages.scrollHeight;});}
    }
}
</script>
@endauth
