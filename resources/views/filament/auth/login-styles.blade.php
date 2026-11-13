<script>
    document.documentElement.classList.add('ac-login-page');
</script>

<style>
    html.ac-login-page,
    html.ac-login-page body {
        min-height: 100%;
        background-color: #f0f4f8 !important;
    }

    html.ac-login-page body {
        position: relative;
        isolation: isolate;
        overflow-x: hidden;
        background-image: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #dbeafe 100%) !important;
    }

    html.ac-login-page body::before,
    html.ac-login-page body::after {
        position: fixed;
        z-index: -1;
        width: min(42vw, 460px);
        height: min(42vw, 460px);
        border-radius: 9999px;
        filter: blur(80px);
        opacity: .58;
        pointer-events: none;
        content: "";
        animation: ac-login-float 17s ease-in-out infinite alternate;
    }

    html.ac-login-page body::before {
        top: 4%;
        left: -12%;
        background: radial-gradient(circle, rgba(186, 230, 253, .95), rgba(125, 211, 252, .38) 58%, transparent 72%);
    }

    html.ac-login-page body::after {
        right: -12%;
        bottom: 1%;
        background: radial-gradient(circle, rgba(254, 215, 170, .92), rgba(251, 146, 60, .28) 58%, transparent 72%);
        animation-delay: -8s;
        animation-duration: 21s;
    }

    html.ac-login-page .fi-simple-layout,
    html.ac-login-page .fi-simple-main-ctn {
        position: relative;
        z-index: 1;
    }

    html.ac-login-page .fi-simple-layout {
        padding: 1.5rem;
    }

    html.ac-login-page .fi-simple-main {
        position: relative;
        overflow: hidden;
        max-width: 28rem;
        padding: 2.5rem;
        border: 1px solid rgba(255, 255, 255, .6);
        border-radius: 1.5rem;
        background: rgba(255, 255, 255, .85) !important;
        box-shadow: 0 20px 40px -15px rgba(0, 0, 0, .08), 0 8px 20px rgba(15, 23, 42, .04) !important;
        color: #1e293b !important;
        -webkit-backdrop-filter: blur(20px);
        backdrop-filter: blur(20px);
        animation: ac-login-fade-up .75s cubic-bezier(.2, .8, .2, 1) both;
    }

    html.ac-login-page .fi-simple-main::before {
        position: absolute;
        top: 0;
        right: 12%;
        left: 12%;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(56, 189, 248, .55), rgba(251, 191, 36, .58), transparent);
        content: "";
    }

    html.ac-login-page .fi-simple-header {
        gap: .25rem;
    }

    html.ac-login-page .fi-simple-header .fi-logo {
        max-width: 14rem;
        max-height: 3.5rem;
        margin-bottom: 1.25rem;
        object-fit: contain;
    }

    html.ac-login-page .fi-simple-header-heading {
        background: linear-gradient(105deg, #1e293b 8%, #2563eb 58%, #d97706 100%);
        background-clip: text;
        color: transparent !important;
        -webkit-background-clip: text;
    }

    html.ac-login-page .fi-simple-header-subheading {
        max-width: 22rem;
        color: #64748b !important;
        line-height: 1.65;
    }

    html.ac-login-page .fi-fo-field-wrp-label,
    html.ac-login-page .fi-checkbox-label {
        color: #334155 !important;
    }

    html.ac-login-page .fi-input-wrp {
        border-color: #cbd5e1 !important;
        background: rgba(255, 255, 255, .96) !important;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, .04);
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    html.ac-login-page .fi-input-wrp:focus-within {
        border-color: #3b82f6 !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .14), 0 0 18px rgba(59, 130, 246, .1) !important;
    }

    html.ac-login-page .fi-input {
        color: #1e293b !important;
    }

    html.ac-login-page .fi-input::placeholder {
        color: #94a3b8 !important;
    }

    html.ac-login-page .fi-input-wrp-suffix,
    html.ac-login-page .fi-input-wrp-prefix,
    html.ac-login-page .fi-checkbox {
        color: #64748b !important;
    }

    html.ac-login-page .fi-simple-main a:not(.fi-btn) {
        color: #2563eb !important;
    }

    html.ac-login-page .fi-simple-main .fi-fo-field-wrp-error-message,
    html.ac-login-page .fi-simple-main .fi-input-wrp-error-message {
        color: #dc2626 !important;
    }

    html.ac-login-page .fi-form-actions .fi-btn {
        position: relative;
        overflow: hidden;
        min-height: 2.9rem;
        border: 1px solid rgba(255, 255, 255, .72);
        background: linear-gradient(110deg, #d97706, #f59e0b 54%, #f97316) !important;
        box-shadow: 0 10px 24px rgba(217, 119, 6, .24), inset 0 1px 0 rgba(255, 255, 255, .24);
        color: #1c1917 !important;
        transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
    }

    html.ac-login-page .fi-form-actions .fi-btn::before {
        position: absolute;
        inset: 0;
        transform: translateX(-120%);
        background: linear-gradient(110deg, transparent 20%, rgba(255, 255, 255, .38) 50%, transparent 80%);
        content: "";
        transition: transform .65s ease;
    }

    html.ac-login-page .fi-form-actions .fi-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 30px rgba(217, 119, 6, .32), inset 0 1px 0 rgba(255, 255, 255, .3);
        filter: saturate(1.08);
    }

    html.ac-login-page .fi-form-actions .fi-btn:hover::before {
        transform: translateX(120%);
    }

    .ac-login-loading {
        align-items: center;
        justify-content: center;
        gap: .7rem;
        padding-top: .25rem;
        color: #475569;
        font-size: .82rem;
        letter-spacing: .02em;
    }

    .ac-login-loading-indicator {
        width: 1.1rem;
        height: 1.1rem;
        border: 2px solid rgba(59, 130, 246, .2);
        border-top-color: #f59e0b;
        border-radius: 9999px;
        animation: ac-login-spin .8s linear infinite, ac-login-pulse 1.4s ease-in-out infinite;
    }

    @keyframes ac-login-float {
        from { transform: translate3d(-2%, -1%, 0) scale(.94); }
        to { transform: translate3d(7%, 8%, 0) scale(1.08); }
    }

    @keyframes ac-login-fade-up {
        from { transform: translateY(18px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    @keyframes ac-login-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes ac-login-pulse {
        50% { box-shadow: 0 0 14px rgba(59, 130, 246, .28); }
    }

    @media (max-width: 640px) {
        html.ac-login-page .fi-simple-layout {
            padding: 1rem;
        }

        html.ac-login-page .fi-simple-main {
            padding: 2rem 1.35rem;
            border-radius: 1.25rem;
        }

        html.ac-login-page body::before,
        html.ac-login-page body::after {
            width: 70vw;
            height: 70vw;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        html.ac-login-page body::before,
        html.ac-login-page body::after,
        html.ac-login-page .fi-simple-main,
        .ac-login-loading-indicator {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
        }

        html.ac-login-page *,
        html.ac-login-page *::before,
        html.ac-login-page *::after {
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>
