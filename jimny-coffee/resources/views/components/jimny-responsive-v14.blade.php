<style id="jimny-responsive-v14-smart">
/* JIMNY_RESPONSIVE_V14_SMART
 * Loaded AFTER the original Smart Assistant V3-V13 styles via Blade include.
 * Applies to both Owner/Admin and Kasir without modifying chat/AI logic.
 */

.jimny-glass-v9 .smart-panel.smart-v3-unified {
    box-sizing: border-box;
    min-width: 0;
    max-width: calc(100vw - 16px);
    max-height: calc(100vh - 16px);
    max-height: calc(100dvh - 16px);
}
.jimny-glass-v9 .smart-panel.smart-v3-unified :is(.smart-v2-chat, .smart-v2-dashboard, .smart-messages, .smart-v3-brand, .j20-host) {
    min-width: 0;
    min-height: 0;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-header {
    flex-wrap: nowrap;
    align-items: center;
    min-width: 0;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-brand {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified :is(.smart-title, .smart-subtitle, .smart-v3-overline) {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-header-status {
    display: inline-flex !important;
    flex: 0 0 auto;
    min-width: 0;
    max-width: 136px;
    white-space: nowrap;
    align-items: center;
    overflow: hidden;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-header-status > span:last-child {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-close {
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    margin-left: 0;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v2-chat-head-title {
    color: #f5e5d3 !important;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v2-chat-head-desc {
    color: #d7bb9f !important;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-messages {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v10-message-row,
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v10-bubble {
    min-width: 0;
    max-width: 100%;
    overflow-wrap: anywhere;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v10-message-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-form {
    min-width: 0;
    flex: 0 0 auto;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-form input {
    min-width: 0;
    flex: 1 1 auto;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-form button {
    min-width: 72px;
    min-height: 44px;
    flex-shrink: 0;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-suggestions {
    min-width: 0;
    overscroll-behavior-x: contain;
    -webkit-overflow-scrolling: touch;
}
.jimny-glass-v9 .smart-panel.smart-v3-unified .smart-suggestions button {
    min-height: 38px;
}

/* Owner/Admin dashboard cards, tabs and charts retain semantic table/graph structure. */
.jimny-glass-v9 .j20-host,
.jimny-glass-v9 #jimny-owner-v20,
.jimny-glass-v9 :is(.j20-kpis, .j20-actions, .j20-card, .j20-trend, .j20-table-wrap) {
    min-width: 0;
    max-width: 100%;
}
.jimny-glass-v9 .j20-table-wrap {
    overflow-x: auto;
    overscroll-behavior-x: contain;
    -webkit-overflow-scrolling: touch;
}
.jimny-glass-v9 .j20-table-wrap table { min-width: 560px; }
.jimny-glass-v9 #jimny-owner-v20 svg,
.jimny-glass-v9 #jimny-owner-v20 canvas { max-width: 100%; }
.jimny-glass-v9 .j20-tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.jimny-glass-v9 .j20-tabs button { flex: 0 0 auto; white-space: nowrap; }
.jimny-glass-v9 .j20-filters { flex-wrap: wrap; min-width: 0; }

/* Tablet and compact laptop widths: one Owner view at a time via the existing
 * Alpine activeTab / smart-v3-mobile-analytics state. Do not invent new JS state. */
@media (max-width: 1199px) {
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only) {
        display: flex !important;
        flex-direction: column !important;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only) .smart-v3-mobile-tabs {
        display: flex !important;
        flex: 0 0 auto;
        gap: 8px;
        align-items: stretch;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only) .smart-v3-mobile-tabs button {
        min-height: 44px;
        flex: 1 1 0;
        min-width: 0;
        white-space: normal;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only) .smart-v2-chat,
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only) .smart-v2-dashboard {
        min-width: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        min-height: 0;
        flex: 1 1 auto;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only):not(.smart-v3-mobile-analytics) .smart-v2-dashboard {
        display: none !important;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only).smart-v3-mobile-analytics .smart-v2-chat {
        display: none !important;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only).smart-v3-mobile-analytics .smart-v2-dashboard {
        display: block !important;
        overflow-y: auto;
        overscroll-behavior-y: contain;
    }
    .jimny-glass-v9 #jimny-owner-v20 { padding-bottom: 12px; }
    .jimny-glass-v9 .j20-heading {
        flex-wrap: wrap;
        gap: 12px;
    }
    .jimny-glass-v9 .j20-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

/* Mobile, narrow foldables and compact tablet portrait. Use a true full-screen
 * dialog so the on-screen keyboard and safe area cannot clip the send bar. */
@media (max-width: 767px) {
    .jimny-glass-v9 .smart-panel.smart-v3-unified {
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        transform: none !important;
        width: 100vw !important;
        max-width: 100vw !important;
        height: 100vh !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        min-height: 0 !important;
        border-radius: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-header {
        flex: 0 0 auto;
        padding: max(10px, env(safe-area-inset-top)) 12px 10px;
        min-height: 64px;
        gap: 8px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-avatar {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-title {
        font-size: clamp(12px, 3.8vw, 15px);
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-subtitle {
        font-size: 10px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-overline {
        font-size: 8px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-header-status {
        max-width: 110px !important;
        padding: 6px 7px;
        font-size: 9px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-close {
        flex: 0 0 36px;
        width: 36px;
        height: 36px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v2-chat {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow: hidden !important;
        display: flex;
        flex-direction: column;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v10-cashier-hero {
        flex: 0 0 auto;
        padding: 12px 14px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-insights {
        flex: 0 0 auto;
        max-height: min(24dvh, 150px);
        overflow-y: auto;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v2-chat-head {
        flex: 0 0 auto;
        padding: 10px 14px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-messages {
        flex: 1 1 auto !important;
        min-height: 70px;
        padding: 12px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-suggestions {
        flex: 0 0 auto;
        display: flex;
        gap: 7px;
        padding: 9px 12px;
        max-height: 94px;
        overflow-x: auto;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-form {
        flex: 0 0 auto;
        padding: 10px 12px max(10px, env(safe-area-inset-bottom));
        gap: 8px;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-form input {
        min-height: 44px;
        font-size: 16px !important;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-form button {
        min-height: 44px;
    }
    .jimny-glass-v9 .j20-heading { display: flex; flex-direction: column; align-items: stretch; }
    .jimny-glass-v9 .j20-filters { width: 100%; }
    .jimny-glass-v9 .j20-filters :is(button, select) { min-height: 44px; }
    .jimny-glass-v9 .j20-card { padding: 12px; }
    .jimny-glass-v9 .j20-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
}

/* Very narrow phones: keep header actions in view and dashboard metrics legible. */
@media (max-width: 389px) {
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-header { padding-inline: 8px; gap: 5px; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-avatar { flex-basis: 32px; width: 32px; height: 32px; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-overline { display: none; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-header-status { max-width: 93px !important; font-size: 8px; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-close { flex-basis: 32px; width: 32px; height: 32px; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-subtitle { font-size: 9px; }
    .jimny-glass-v9 .j20-kpis { grid-template-columns: minmax(0, 1fr) !important; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v10-cashier-hero { padding: 10px; }
}

/* Tablet widths: keep dialog within viewport, but retain generous breathing room. */
@media (min-width: 768px) and (max-width: 1199px) {
    .jimny-glass-v9 .smart-panel.smart-v3-unified {
        max-width: calc(100vw - 20px) !important;
        max-height: calc(100dvh - 20px) !important;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified:not(.smart-v3-chat-only) {
        left: 50% !important;
        top: 50% !important;
        right: auto !important;
        bottom: auto !important;
        transform: translate(-50%, -50%) !important;
        width: min(900px, calc(100vw - 20px)) !important;
        height: min(810px, calc(100dvh - 20px)) !important;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v2-dashboard {
        overscroll-behavior-y: contain;
        -webkit-overflow-scrolling: touch;
    }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v3-header-status {
        max-width: 128px;
    }
}

/* Landscape phones and split-screen window use height instead of device labels. */
@media (max-height: 650px) and (max-width: 900px) {
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v10-cashier-hero { display: none !important; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-insights { max-height: 75px; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-v2-chat-head { padding-block: 7px; }
    .jimny-glass-v9 .smart-panel.smart-v3-unified .smart-suggestions { padding-block: 6px; }
}

/* Preserve focus semantics for keyboard navigation and accessibility. */
.jimny-glass-v9 .smart-panel.smart-v3-unified :is(button,input,select):focus-visible {
    outline: 2px solid #d69b4f;
    outline-offset: 2px;
}

</style>
