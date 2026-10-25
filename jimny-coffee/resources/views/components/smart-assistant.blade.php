@auth
<div x-data="smartAssistant()" x-init="init()" x-ref="root" class="smart-assistant-root" :style="rootStyle">
    <div x-cloak x-show="proactiveVisible && !open" x-transition.opacity class="smart-proactive" x-ref="bubble" :style="bubbleStyle" @click="openFromProactive()">
        <button type="button" class="smart-proactive-close" @click.stop="dismissProactive()" aria-label="Tutup notifikasi">×</button>
        <div class="smart-proactive-label">Smart Insight</div>
        <div class="smart-proactive-text" x-text="currentInsight"></div>
        <div class="smart-proactive-hint">Klik untuk melihat detail · otomatis hilang dalam 5 detik</div>
    </div>

    <button type="button" @pointerdown="startDrag($event)" @click="toggleFromClick()" class="smart-fab" :class="{'is-dragging': dragging}" aria-label="Buka JIMNY Smart Assistant" title="Geser untuk memindahkan · Klik untuk membuka"> 
        <img src="{{ asset('images/smart-assistant-barista.jpg') }}" class="smart-fab-logo" alt="" draggable="false">
        <span class="smart-fab-pulse"></span>
    </button>

    <div x-cloak x-show="open && expanded"
         class="smart-v2-backdrop"
         @click="expanded = false; positionPanel()"></div>

    <section x-cloak x-show="open" x-transition
             class="smart-panel"
             :class="{'smart-v2-expanded': expanded}"
             x-ref="panel"
             :style="panelStyle"
             @click.outside="open = false; expanded = false">
        <div class="smart-header">
            <div class="smart-avatar"><img src="{{ asset('images/smart-assistant-barista.jpg') }}" alt="" draggable="false"></div>
            <div class="min-w-0">
                <div class="smart-title">JIMNY Smart Assistant</div>
                <div class="smart-subtitle">Mode {{ ucfirst(auth()->user()->role) }} · berbasis data POS</div>
            </div>
            @if(in_array(auth()->user()->role, ['owner', 'admin']))
            <button type="button"
                    class="smart-v2-expand-button"
                    @click="toggleExpanded()"
                    :aria-label="expanded ? 'Perkecil JSA' : 'Perbesar JSA'"
                    :title="expanded ? 'Perkecil panel' : 'Lihat analitik'">
                <span x-text="expanded ? 'Perkecil' : 'Perbesar'"></span>
            </button>
            @endif
            <button type="button"
                    @click="open = false; expanded = false"
                    class="smart-close"
                    aria-label="Tutup Smart Assistant">×</button>
        </div>



        <div class="smart-v2-chat">

        <div class="smart-insights"
             x-show="insights.length && !expanded"
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

        <div class="smart-v2-chat-head" x-show="expanded">
            <div class="smart-v2-chat-head-title">
                <span class="smart-v2-chat-dot"></span>
                Percakapan Smart Assistant
            </div>
            <div class="smart-v2-chat-head-desc">
                Tanyakan penjualan, stok, menu, atau prediksi.
            </div>
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
        </div>

        <div class="smart-v2-dashboard"
             x-show="expanded && canAnalytics">

            <div class="smart-v2-heading">
                <div>
                    <div class="smart-v2-eyebrow">JIMNY INTELLIGENCE</div>
                    <h2>Analitik & Rekomendasi Bisnis</h2>
                    <p>Grafik dan keputusan berdasarkan data POS.</p>
                </div>
                <div class="smart-v2-actions">
                    <label class="smart-v2-filter">
                        <span>Periode</span>
                        <select x-model="analyticsDays"
                                :disabled="analyticsLoading"
                                @change="loadAnalytics(true)">
                            <option value="7">7 hari</option>
                            <option value="14">14 hari</option>
                            <option value="30">30 hari</option>
                        </select>
                    </label>

                    <label class="smart-v2-filter">
                        <span>Menu</span>
                        <select x-model="analyticsMenu"
                                :disabled="analyticsLoading"
                                @change="loadAnalytics(true)">
                            <option value="">Semua menu</option>
                            <template x-for="menu in analyticsMenus"
                                      :key="menu.id">
                                <option :value="String(menu.id)"
                                        x-text="menu.name"></option>
                            </template>
                        </select>
                    </label>

                    <button type="button"
                            class="smart-v2-refresh"
                            :disabled="analyticsLoading"
                            @click="loadAnalytics(true)">
                        Perbarui
                    </button>
                </div>
            </div>

            <div x-show="analyticsLoading" class="smart-v2-notice">
                Membaca data analitik dari server...
            </div>

            <div x-show="analyticsError"
                 class="smart-v2-notice"
                 role="status"
                 x-text="analyticsError"></div>

            <div x-show="!analytics && !analyticsLoading"
                 class="smart-v2-charts smart-v2-preview">

                <article class="smart-v2-chart-card">
                    <h3>Tren Penjualan</h3>
                    <p>Histori jumlah porsi terjual</p>
                    <div class="smart-v2-empty">
                        Grafik akan membaca transaksi valid
                        dari API analitik JIMNY COFFEE.
                    </div>
                </article>

                <article class="smart-v2-chart-card">
                    <h3>Aktual vs Prediksi</h3>
                    <p>Perbandingan hasil forecasting</p>
                    <div class="smart-v2-empty">
                        Prediksi akan ditampilkan setelah
                        model dan evaluasinya tersedia.
                    </div>
                </article>

                <article class="smart-v2-chart-card">
                    <h3>Rekomendasi Restok</h3>
                    <p>Prioritas kebutuhan persediaan</p>
                    <div class="smart-v2-empty">
                        Rekomendasi berdasarkan kondisi
                        stok dan permintaan menu.
                    </div>
                </article>

                <article class="smart-v2-chart-card">
                    <h3>Pola Jam Ramai</h3>
                    <p>Distribusi transaksi per jam</p>
                    <div class="smart-v2-empty">
                        Grafik akan tersedia setelah
                        data analitik diterima.
                    </div>
                </article>

            </div>
            <template x-if="analytics">
                <div class="smart-v2-content">

                    <div class="smart-v2-kpi-scope">
                        Ringkasan seluruh riwayat transaksi valid.
                        Filter periode dan menu berlaku untuk grafik penjualan.
                    </div>
                    <div class="smart-v2-kpis">
                        <div class="smart-v2-kpi">
                            <span>Total transaksi valid</span>
                            <strong x-text="analytics.summary?.transactions ?? '—'"></strong>
                        </div>
                        <div class="smart-v2-kpi">
                            <span>Total porsi terjual</span>
                            <strong x-text="analytics.summary?.sold_qty ?? '—'"></strong>
                        </div>
                        <div class="smart-v2-kpi">
                            <span>Hari dengan transaksi</span>
                            <strong x-text="analytics.summary?.active_days ?? '—'"></strong>
                        </div>
                        <div class="smart-v2-kpi">
                            <span>Status prediksi</span>
                            <strong class="smart-v2-status"
                                    x-text="analytics.forecast?.status === 'experimental' ? 'Eksperimental (WMA-7)' : (analytics.forecast?.status === 'ready' ? 'Tersedia' : 'Data terbatas')"></strong>
                        </div>
                    </div>

                    <div class="smart-v2-charts">

                        <article class="smart-v2-chart-card">
                            <h3>Tren Penjualan Menu</h3>
                            <p>Jumlah porsi terjual berdasarkan tanggal</p>
                            <div class="smart-v2-chart-meta"
                                 x-show="(analytics.sales_trend || []).length >= 2">
                                <span>Nilai tertinggi</span>
                                <strong x-text="trendPeak(analytics.sales_trend) + ' porsi/hari'"></strong>
                            </div>

                            <div class="smart-v2-chart-scope"
                                 x-show="(analytics.sales_trend || []).length">
                                <span x-text="periodScope()"></span>
                                <strong x-text="totalGraphQty(analytics.sales_trend) + ' porsi'"></strong>
                            </div>

                            <template x-if="(analytics.sales_trend || []).length >= 2">
                                <svg class="smart-v2-line-chart"
                                     viewBox="0 0 520 190"
                                     role="img"
                                     aria-label="Grafik tren jumlah porsi terjual">
                                    <line x1="24" y1="165" x2="500" y2="165"
                                          stroke="#D8C6B5" stroke-width="1"/>
                                    <line x1="24" y1="100" x2="500" y2="100"
                                          stroke="#E8DDD3" stroke-dasharray="4 5"/>
                                    <line x1="24" y1="35" x2="500" y2="35"
                                          stroke="#E8DDD3" stroke-dasharray="4 5"/>
                                    <text x="18" y="168"
                                          text-anchor="end"
                                          class="smart-v2-svg-label">0</text>

                                    <text x="18" y="103"
                                          text-anchor="end"
                                          class="smart-v2-svg-label"
                                          x-text="Math.round(trendPeak(analytics.sales_trend)/2)">
                                    </text>

                                    <text x="18" y="38"
                                          text-anchor="end"
                                          class="smart-v2-svg-label"
                                          x-text="trendPeak(analytics.sales_trend)">
                                    </text>

                                    <polyline
                                        :points="chartPoints(analytics.sales_trend, 'qty')"
                                        fill="none"
                                        stroke="#A66A43"
                                        stroke-width="4"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"/>
                                    <template
                                        x-for="point in trendMarkers(analytics.sales_trend, 'qty')"
                                        :key="point.index">
                                        <circle
                                            :cx="point.x"
                                            :cy="point.y"
                                            r="5"
                                            fill="#fff"
                                            stroke="#A66A43"
                                            stroke-width="3">
                                            <title x-text="point.date + ': ' + point.qty + ' porsi'"></title>
                                        </circle>
                                    </template>
                                </svg>
                            </template>

                            <div class="smart-v2-axis"
                                 x-show="(analytics.sales_trend || []).length >= 2">
                                <span x-text="shortDate(analytics.sales_trend[0]?.date)"></span>
                                <span>Tanggal transaksi</span>
                                <span x-text="shortDate(analytics.sales_trend[analytics.sales_trend.length - 1]?.date)"></span>
                            </div>

                            <div class="smart-v2-empty"
                                 x-show="(analytics.sales_trend || []).length < 2">
                                Grafik tersedia setelah terdapat minimal dua titik data.
                            </div>
                        </article>

                        <article class="smart-v2-chart-card">
    <h3>Aktual vs Prediksi</h3>
    <p>Prediksi permintaan menggunakan WMA-7</p>

    <div class="smart-v2-empty"
         x-show="!['experimental', 'ready'].includes(analytics.forecast?.status)"
         x-text="analytics.forecast?.message || 'Data historis belum mencukupi untuk prediksi.'">
    </div>

    <template x-if="['experimental', 'ready'].includes(analytics.forecast?.status)">
        <div>
            <div class="smart-v2-chart-meta">
                <span x-text="analytics.forecast?.method || 'Metode tidak tersedia'"></span>
                <strong x-text="analytics.forecast?.status === 'experimental' ? 'Eksperimental' : 'Tersedia'"></strong>
            </div>

            <div class="smart-v2-kpis">
                <div class="smart-v2-kpi">
                    <span>Prediksi hari berikutnya</span>
                    <strong x-text="(analytics.forecast?.values?.[0]?.qty ?? '—') + ' porsi'"></strong>
                </div>

                <div class="smart-v2-kpi">
                    <span>Tanggal prediksi</span>
                    <strong x-text="analytics.forecast?.values?.[0]?.date || '—'"></strong>
                </div>
            </div>

            <template x-if="analytics.model_evaluation">
                <div>
                    <div class="smart-v2-chart-meta">
                        <span>MAE WMA-7</span>
                        <strong x-text="analytics.model_evaluation.wma_mae ?? '—'"></strong>
                    </div>

                    <div class="smart-v2-chart-meta">
                        <span>MAE Naive</span>
                        <strong x-text="analytics.model_evaluation.naive_mae ?? '—'"></strong>
                    </div>

                    <div class="smart-v2-chart-meta">
                        <span>MAE Seasonal-naive (lag-7)</span>
                        <strong x-text="analytics.model_evaluation.seasonal_naive_mae ?? '—'"></strong>
                    </div>

                    <div class="smart-v2-chart-meta">
                        <span>Metode paling akurat (backtest)</span>
                        <strong x-text="({ wma: 'WMA-7', seasonal_naive: 'Seasonal-naive (lag-7)', naive: 'Naive (kemarin)' })[analytics.model_evaluation.best_by_mae] ?? '—'"></strong>
                    </div>
                </div>
            </template>

            <div class="smart-v2-chart-meta"
                 x-show="(analytics.forecast?.backtest || []).length > 0">
                <span>Evaluasi historis</span>
                <strong x-text="(analytics.forecast?.backtest || []).length + ' hari'"></strong>
            </div>

            <div class="smart-v2-empty"
                 x-text="analytics.forecast?.message || 'Prediksi eksperimental, bukan keputusan restok otomatis.'">
            </div>
        </div>
    </template>
</article>

                        <article class="smart-v2-chart-card">
                            <h3>Rekomendasi Stok</h3>
                            <p>Prioritas berdasarkan kondisi persediaan</p>
                            <div class="smart-v2-bar-list">
                                <template x-for="(item, index) in
                                    (analytics.stock_recommendations || []).slice(0, 5)"
                                    :key="item.menu_id ?? index">
                                    <div class="smart-v2-bar-item">
                                        <div class="smart-v2-bar-label">
                                            <span x-text="item.name"></span>
                                            <strong x-text="'Stok: ' + item.stock"></strong>
                                        </div>
                                        <div class="smart-v2-stock-note"
                                             x-text="'Batas minimum: ' + item.minimum_stock +
                                             ' | Kekurangan menuju batas: ' +
                                             Math.max(0, Number(item.minimum_stock || 0) - Number(item.stock || 0))">
                                        </div>
                                        <div class="smart-v2-track">
                                            <div class="smart-v2-fill"
                                                 :class="{'smart-v2-fill-warning': Number(item.stock) <= Number(item.minimum_stock)}"
                                                 :style="'width:' + stockBarWidth(item) + '%'"></div>
                                        </div>
                                    </div>
                                </template>
                                <div class="smart-v2-empty"
                                     x-show="!(analytics.stock_recommendations || []).length">
                                    Belum ada rekomendasi stok.
                                </div>
                            </div>
                        </article>

                        <article class="smart-v2-chart-card">
                            <h3>Pola Jam Ramai</h3>
                            <p>Distribusi transaksi sukses per jam</p>
                            <div class="smart-v2-chart-meta">
                                <span x-text="peakSummary()"></span>
                            </div>
                            <div class="smart-v2-hours-toolbar">
                                <span x-text="showAllHours ?
                                    'Distribusi seluruh 24 jam' :
                                    'Jam yang memiliki transaksi'"></span>

                                <button type="button"
                                        x-show="(analytics.peak_hours || []).length"
                                        @click="showAllHours = !showAllHours"
                                        x-text="showAllHours ?
                                            'Jam aktif saja' :
                                            'Lihat 24 jam'">
                                </button>
                            </div>

                            <div class="smart-v2-hours">
                                <template x-for="(item, index) in
                                    visiblePeakHours()"
                                    :key="item.hour ?? index">
                                    <div class="smart-v2-hour"
                                         :title="String(item.hour).padStart(2, '0') +
                                         ':00 - ' + item.transactions + ' transaksi'">
                                        <span class="smart-v2-hour-value"
                                              x-text="item.transactions"></span>
                                        <div class="smart-v2-hour-track">
                                            <div :style="'height:' + hourBarHeight(item) + '%'"></div>
                                        </div>
                                        <span x-text="String(item.hour).padStart(2, '0')"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="smart-v2-empty"
                                 x-show="!visiblePeakHours().length">
                                Belum ada data jam transaksi.
                            </div>
                        </article>

                    </div>

                    <div class="smart-v2-disclaimer">
                        Angka diambil dari API analitik JIMNY COFFEE.
                        Prediksi hanya ditampilkan setelah memenuhi
                        persyaratan data dan evaluasi model.
                    </div>

                </div>
            </template>

        </div>
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
</style>

<script>
function smartAssistant(){
    return {
        allowProactive: {{ request()->routeIs('owner.dashboard') ? 'false' : 'true' }},
        open:false, loading:false, input:'', messages:[], insights:[], proactiveVisible:false, currentInsight:'', proactiveTimer:null, proactiveDismissed:false,
        expanded:false, showAllInsights:false, showAllHours:false,
        canAnalytics: {{ in_array(auth()->user()->role, ['owner', 'admin']) ? 'true' : 'false' }},
        analytics:null, analyticsLoading:false, analyticsError:'', analyticsDays:'30', analyticsMenu:'', analyticsMenus:[],
        dragging:false, moved:false, dragStartX:0, dragStartY:0, startLeft:0, startTop:0,
        left:null, top:null, suppressClick:false, panelStyle:{}, bubbleStyle:{}, rootStyle:{},
        initialized:false, hideTimer:null, refreshTimer:null, firstTimer:null, layoutObserver:null, viewportHandler:null, moveHandler:null, endHandler:null,
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
            // Perbarui insight berkala agar assistant tetap mengikuti kondisi POS.
            this.refreshTimer=setInterval(()=>this.refreshInsights(), 60000);
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
            if(!this.allowProactive || !this.insights.length)return;
            const path=window.location.pathname.toLowerCase();
            const role='{{ auth()->user()->role }}';
            let preferred=this.insights.filter(text=>{
                if(role==='kasir' && (path.includes('transaksi') || path.includes('kasir'))) return /stok|transaksi|menu/i.test(text);
                if(role==='admin' && (path.includes('admin') || path.includes('menu') || path.includes('stok'))) return /stok|menu|transaksi/i.test(text);
                if(role==='owner' && path.includes('owner')) return /omzet|menu|ramai|penjualan|prediksi/i.test(text);
                return true;
            });
            const pool=preferred.length?preferred:this.insights;
            this.firstTimer=setTimeout(()=>this.showProactive(pool[0]),900);
            let index=0;
            this.proactiveTimer=setInterval(()=>{
                if(!this.insights.length)return;
                index=(index+1)%this.insights.length;
                this.showProactive(this.insights[index]);
            },12000);
        },
        showProactive(text){
            if(!this.allowProactive || this.open || this.dragging || document.hidden || !text)return;
            clearTimeout(this.hideTimer);
            this.currentInsight=text;
            this.proactiveVisible=true;
            this.$nextTick(()=>this.positionBubble());
            this.hideTimer=setTimeout(()=>{this.proactiveVisible=false;},5000);
        },
        dismissProactive(){
            clearTimeout(this.hideTimer);
            this.proactiveVisible=false;
        },
        openFromProactive(){
            this.dismissProactive();
            this.open=true;
            this.$nextTick(()=>this.positionPanel());
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
        destroy(){
            clearInterval(this.proactiveTimer);clearInterval(this.refreshTimer);
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
            this.open=!this.open;
            this.proactiveVisible=false;
            if(this.open)this.$nextTick(()=>this.positionPanel());
        },
        positionPanel(){
            if(!this.open)return;

            const v=this.viewport();

            if(this.expanded){
                const width=Math.max(1,Math.min(1120,v.width-24));
                const height=Math.max(1,Math.min(780,v.height-24));

                this.panelStyle={
                    left:(v.left+(v.width-width)/2)+'px',
                    top:(v.top+(v.height-height)/2)+'px',
                    right:'auto',
                    bottom:'auto',
                    width:width+'px',
                    height:height+'px',
                    boxSizing:'border-box'
                };
                return;
            }

            const width=Math.max(1,Math.min(390,v.width-24));
            const height=Math.max(1,Math.min(650,v.height-24));

            this.panelStyle={
                ...this.fitOverlay(width,height),
                width:width+'px',
                height:height+'px',
                boxSizing:'border-box'
            };
        },
        toggleExpanded(){
            if(!this.canAnalytics)return;
            this.expanded=!this.expanded;
            if(this.expanded)this.loadAnalytics();
            this.$nextTick(()=>this.positionPanel());
        },
        async loadAnalytics(force=false){
            if(!this.canAnalytics || this.analyticsLoading)return;
            if(!force && this.analytics)return;

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
            }catch(error){
                this.analyticsError=error.message ||
                    'Tidak dapat membaca data analitik.';
            }finally{
                this.analyticsLoading=false;
            }
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
