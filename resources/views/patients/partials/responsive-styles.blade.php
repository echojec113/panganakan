{{-- Patient workflow only; the application shell and shared components stay unchanged. --}}
<style>
    .patient-module { width: 100%; min-width: 0; max-width: 100%; overflow-wrap: anywhere; }
    .patient-module :where(.flex, .inline-flex, .grid) > * { min-width: 0; }
    .patient-module :where(.justify-between, .justify-end) { flex-wrap: wrap; }
    .patient-module :where(input, select, textarea) { min-width: 0; max-width: 100%; }
    .patient-module :where(input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select, textarea) { min-height: 44px; }
    .patient-module :where(img, video, iframe) { max-width: 100%; }
    .patient-module :where(button, a) { max-width: 100%; }
    .patient-module :where(.btn, .status-badge) { white-space: normal; overflow-wrap: anywhere; }
    .patient-module .btn { height: auto; min-height: 40px; padding-top: 0.5rem; padding-bottom: 0.5rem; line-height: 1.4; }
    .patient-module .patient-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; }
    .patient-module .patient-actions > :is(a, button) { min-height: 44px; }
    .patient-module .patient-actions > :not([hidden]) ~ :not([hidden]) { margin-left: 0; }
    .patient-module nav[aria-label] { flex-wrap: wrap; }
    .patient-module nav[aria-label="Breadcrumb"] ol { flex-wrap: wrap; row-gap: 0.5rem; }

    /* Form columns follow the actual space available, including inside dialogs. */
    .patient-module form .grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr)); }
    .patient-module form .grid > [class*="col-span-"] { grid-column: 1 / -1; }
    .patient-module #reportDropZone { height: auto; min-height: 8rem; padding: 0.75rem; text-align: center; }

    /* One set of rows preserves search, restore forms, and expanded-detail hooks. */
    .patient-module .patient-data-table { width: 100%; min-width: 0; table-layout: fixed; }
    .patient-module .patient-data-table :is(th, td) { padding: 0.75rem 0.5rem; white-space: normal; overflow-wrap: anywhere; vertical-align: top; }
    .patient-module .patient-data-table td .whitespace-nowrap { white-space: normal; }
    .patient-module .patient-data-table :is(a, button) { min-height: 40px; }
    .patient-module .patient-data-table a { display: inline-flex; align-items: center; }
    .patient-module .patient-list-table td:last-child > div { flex-wrap: wrap; }
    /* The Patient Records column reserves space for all three desktop actions. */
    .patient-module .patient-list-table td:last-child > .patient-record-actions { flex-wrap: nowrap; }
    .patient-module .patient-list-table td:last-child :is(a, button) { flex-shrink: 0; }
    .patient-module .patient-card-identity { flex-wrap: wrap; }
    .patient-module .patient-card-identity > :first-child { flex: 1 1 10rem; }
    .patient-module .patient-card-identity > :last-child { flex-wrap: wrap; }
    .patient-module .patient-card-identity :is(a, button) { width: 44px; height: 44px; }

    /* Profile navigation wraps instead of requiring a horizontal swipe. */
    .patient-module .patient-profile-theme .patient-profile-nav > div { min-width: 0; flex-wrap: wrap; }
    .patient-module .patient-profile-theme .patient-profile-nav a { white-space: normal; min-height: 44px; }
    .patient-module .kv-row { flex-wrap: wrap; }
    .patient-module .kv-label { flex-shrink: 1; }
    .patient-module pre { max-width: 100%; overflow-x: auto; }
    .patient-module .patient-overview { grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1440px) {
        .patient-module .patient-overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    /* Keep all confirmation content and actions reachable on short viewports. */
    .patient-module .patient-dialog { padding: 1rem; overflow-y: auto; }
    .patient-module .patient-dialog > div {
        min-width: 0; width: 100%; margin-left: 0; margin-right: 0;
        max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem); overflow-y: auto;
    }
    .patient-module #deliveryModal > div { min-height: 100%; padding: 1rem; }
    .patient-module #deliveryModal .inline-block { width: 100%; max-width: 42rem; min-width: 0; }
    .patient-module #deliveryModal .grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr)); }
    /* This Patient notification is appended to body by the existing baby editor. */
    body:has(.patient-module) > .baby-message-notification { max-width: calc(100vw - 2rem); overflow-wrap: anywhere; }

    @media (max-width: 639px) {
        .patient-module .patient-page { padding-left: 0; padding-right: 0; }
        .patient-module :is(form.p-6, form.px-6) { padding-left: 1rem; padding-right: 1rem; }
        .patient-module .patient-actions { flex-direction: column; align-items: stretch; }
        .patient-module .patient-actions > :is(a, button) {
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .patient-module :is(button, a.inline-flex, a.btn) { min-height: 44px; }
        .patient-module .patient-profile-theme .patient-profile-nav { position: static; }
        .patient-module .patient-profile-theme .patient-profile-nav a { flex: 1 1 9rem; }
        .patient-module .patient-profile-theme :is(#overview, #prenatal-visits, #medical-history, #ultrasound, #birth-plan, #risk-assessment, #referral-follow-through) { scroll-margin-top: 72px; }
        .patient-module .kv-value { text-align: left; }
    }

    @media (max-width: 1023px) {
        .patient-module .patient-stack-table,
        .patient-module .patient-stack-table tbody { display: block; width: 100%; }
        .patient-module .patient-stack-table thead { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip-path: inset(50%); }
        .patient-module .patient-stack-table tbody > tr:not(.hidden) { display: block; padding: 0.75rem; }
        .patient-module .patient-stack-table tbody > tr > td { display: block; width: 100%; padding: 0.5rem; text-align: left; }
        .patient-module .patient-stack-table td[data-label]::before { content: attr(data-label); display: block; margin-bottom: 0.25rem; font-size: 0.75rem; font-weight: 600; color: #657083; }
        .patient-module .patient-stack-table td > .justify-end { justify-content: flex-start; }
    }
</style>
