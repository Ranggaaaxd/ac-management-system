<script>
    document.addEventListener('livewire:navigated', () => {
        document.documentElement.classList.toggle('ac-login-page', window.location.pathname.endsWith('/login'));
    });
</script>

<style>
    :root {
        --livewire-progress-bar-color: #38bdf8;
    }

    .fi-page .fi-wi-widget,
    .fi-page .fi-ta-ctn,
    .fi-page .fi-section,
    .fi-page .fi-fo-component-ctn {
        animation: ac-masterpiece-fade-up .65s cubic-bezier(.16, 1, .3, 1) both;
    }

    .fi-ta-ctn {
        overflow: visible !important;
    }

    .fi-page-header form,
    [wire\:key*="filters"] {
        position: relative !important;
        z-index: 50 !important;
        margin-bottom: 1.5rem !important;
    }

    .fi-dropdown-panel,
    .fi-popover-panel,
    .fi-select-input-options,
    [role="listbox"] {
        z-index: 99999 !important;
    }

    .dashboard-quick-actions {
        position: relative;
        z-index: 1;
    }

    .fi-page .fi-wi-widget:nth-child(2),
    .fi-page .fi-ta-ctn:nth-child(2),
    .fi-page .fi-section:nth-child(2) {
        animation-delay: 70ms;
    }

    .fi-page .fi-wi-widget:nth-child(3),
    .fi-page .fi-ta-ctn:nth-child(3),
    .fi-page .fi-section:nth-child(3) {
        animation-delay: 120ms;
    }

    .fi-wi-stats-overview-stat-card,
    .fi-wi-stats-overview-stat {
        border: 1px solid rgba(148, 163, 184, .2);
        transition: transform .35s cubic-bezier(.4, 0, .2, 1), box-shadow .35s cubic-bezier(.4, 0, .2, 1), border-color .35s ease;
        will-change: transform;
    }

    @media (hover: hover) {
        .fi-wi-stats-overview-stat-card:hover,
        .fi-wi-stats-overview-stat:hover {
            transform: translateY(-5px);
            border-color: rgba(56, 189, 248, .35);
            box-shadow: 0 12px 25px -5px rgba(0, 0, 0, .1), 0 8px 10px -6px rgba(0, 0, 0, .1);
        }

        .fi-ta-row:hover {
            background-color: rgba(14, 165, 233, .045);
            box-shadow: inset 3px 0 0 rgba(14, 165, 233, .38);
        }
    }

    .fi-ta-row {
        transition: background-color .2s ease, box-shadow .2s ease;
    }

    .fi-wi-chart canvas {
        display: block;
        width: 100% !important;
        max-width: 460px;
        max-height: 260px;
        margin-inline: auto;
    }

    .fi-loading-indicator {
        filter: drop-shadow(0 0 5px rgba(56, 189, 248, .38));
        animation: spin 1s linear infinite, ac-masterpiece-pulse 1.6s ease-in-out infinite;
    }

    .fi-btn.fi-color-primary,
    .fi-icon-btn.fi-color-primary {
        position: relative;
        overflow: hidden;
        transition: transform .2s ease, box-shadow .25s ease, filter .25s ease;
    }

    .fi-btn.fi-color-primary::after,
    .fi-icon-btn.fi-color-primary::after {
        position: absolute;
        inset: 0;
        transform: translateX(-130%);
        background: linear-gradient(110deg, transparent 22%, rgba(255, 255, 255, .3) 50%, transparent 78%);
        content: "";
        pointer-events: none;
        transition: transform .7s ease;
    }

    @media (hover: hover) {
        .fi-btn.fi-color-primary:hover,
        .fi-icon-btn.fi-color-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 18px rgba(217, 119, 6, .18);
        }

        .fi-btn.fi-color-primary:hover::after,
        .fi-icon-btn.fi-color-primary:hover::after {
            transform: translateX(130%);
        }
    }

    @keyframes ac-masterpiece-fade-up {
        from {
            transform: translateY(15px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes ac-masterpiece-pulse {
        50% {
            filter: drop-shadow(0 0 9px rgba(56, 189, 248, .65));
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-page .fi-wi-widget,
        .fi-page .fi-ta-ctn,
        .fi-page .fi-section,
        .fi-page .fi-fo-component-ctn,
        .fi-wi-stats-overview-stat-card,
        .fi-wi-stats-overview-stat,
        .fi-loading-indicator {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: .01ms !important;
        }

        .fi-wi-stats-overview-stat-card,
        .fi-wi-stats-overview-stat {
            will-change: auto;
        }
    }
</style>
