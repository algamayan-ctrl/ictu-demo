<!doctype html>
<html lang="en" @auth data-theme-storage-key="spmu-acpmp.appearance.{{ hash('sha256', (string) auth()->id()) }}" @endauth>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/cspc-logo.png') }}">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    <script>
        (() => {
            const root = document.documentElement;
            const storageKey = root.dataset.themeStorageKey;
            let preference = 'light';

            if (storageKey) {
                try {
                    const stored = localStorage.getItem(storageKey);
                    if (['light', 'dark', 'system'].includes(stored)) preference = stored;
                } catch (_) {}
            }

            const resolved = preference === 'dark' ? 'dark' : 'light';

            root.dataset.theme = resolved;
            root.dataset.themePreference = preference;
            root.style.colorScheme = resolved;
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">

    <style>
        .app-sidebar {
            background: #E5F2FC !important;
            border-right: 1px solid #d6e8f5 !important;
            color: #17324d !important;
        }

        .sidebar-brand-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 14px 10px;
            border-bottom: 1px solid #e6eff8;
        }

        .sidebar-brand.sidebar-brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 58px;
            padding: 6px 8px;
            border: 0 !important;
            border-radius: 0;
            background: transparent !important;
            text-decoration: none;
            overflow: hidden;
            box-shadow: none !important;
        }

        .sidebar-brand.sidebar-brand-logo:hover {
            background: transparent !important;
            border-color: transparent !important;
            box-shadow: none !important;
        }

        .sidebar-brand-logo-image {
            display: block;
            width: 100%;
            max-width: 182px;
            height: auto;
            max-height: 50px;
            object-fit: contain;
            object-position: center;
        }

        .sidebar-label {
            margin: 14px 18px 10px !important;
            color: #6f8096 !important;
            font-size: 0.73rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.14em !important;
            text-transform: uppercase !important;
        }

        .sidebar-nav {
            display: grid;
            gap: 8px;
            padding: 0 10px 14px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 48px;
            padding: 0 14px;
            border: 1px solid transparent;
            border-radius: 14px;
            background: transparent !important;
            color: #17324d !important;
            text-decoration: none;
            font-weight: 700;
            transition: background-color .16s ease, color .16s ease, border-color .16s ease, box-shadow .16s ease, transform .12s ease;
        }

        .sidebar-nav a:hover {
            background: #edf5ff !important;
            border-color: #cfe1f4;
            color: #0d63d8 !important;
        }

        .sidebar-nav a.active,
        .sidebar-nav a[aria-current="page"] {
            background: linear-gradient(135deg, #1f6fe5 0%, #0d63d8 100%) !important;
            border-color: #0d63d8 !important;
            color: #ffffff !important;
            box-shadow: 0 10px 24px rgba(13, 99, 216, 0.22);
        }

        .sidebar-nav a .nav-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            min-width: 18px;
        }

        .sidebar-nav a svg {
            color: currentColor;
            stroke: currentColor;
        }

        .sidebar-nav .nav-icon-dashboard { color: #1f6fe5; }
        .sidebar-nav .nav-icon-approval { color: #19a36b; }
        .sidebar-nav .nav-icon-upload { color: #e9870c; }
        .sidebar-nav .nav-icon-custody { color: #7a5af8; }
        .sidebar-nav .nav-icon-success { color: #00a3bf; }
        .sidebar-nav .nav-icon-requests { color: #e55353; }
        .sidebar-nav .nav-icon-inventory { color: #0f9488; }
        .sidebar-nav .nav-icon-calendar { color: #2f80ed; }
        .sidebar-nav .nav-icon-accountability { color: #d97706; }

        /* Additional sidebar module icon colors */
        .sidebar-nav .nav-icon-users { color: #7A5AF8; }
        .sidebar-nav .nav-icon-settings { color: #0F9488; }
        .sidebar-nav .nav-icon-analytics { color: #7A5AF8; }
        .sidebar-nav .nav-icon-reports,
        .sidebar-nav .nav-icon-report-document { color: #2F80ED; }
        .sidebar-nav .nav-icon-notifications { color: #E9870C; }

        .sidebar-nav a.active .nav-icon,
        .sidebar-nav a[aria-current="page"] .nav-icon,
        .sidebar-nav a.active svg,
        .sidebar-nav a[aria-current="page"] svg {
            color: #ffffff !important;
            stroke: currentColor !important;
        }

        .sidebar-foot {
            margin-top: auto;
            padding: 16px 18px 18px !important;
            border-top: 1px solid #e6eff8 !important;
            color: #6c7f96 !important;
            background: #E5F2FC !important;
        }

        .sidebar-foot span {
            display: block;
            margin-bottom: 4px;
            color: #17324d;
            font-weight: 700;
        }

        .sidebar-foot small {
            display: block;
            line-height: 1.45;
        }

        .sidebar-close {
            color: #6c7f96;
        }

        /* DARK MODE: sidebar follows the selected appearance */
        html[data-theme="dark"] .app-sidebar {
            background: #070518 !important;
            border-right-color: rgba(255, 255, 255, .06) !important;
            color: #dce8f5 !important;
        }

        html[data-theme="dark"] .sidebar-brand-row {
            border-bottom-color: rgba(255, 255, 255, .06);
        }

        html[data-theme="dark"] .sidebar-brand.sidebar-brand-logo {
            background: transparent !important;
        }

        html[data-theme="dark"] .sidebar-brand-logo-image {
            filter: brightness(0) invert(1);
            opacity: .96;
        }

        html[data-theme="dark"] .sidebar-label {
            color: #8fa7c3 !important;
        }

        html[data-theme="dark"] .sidebar-nav a {
            background: transparent !important;
            border-color: transparent !important;
            color: #dce8f6 !important;
        }

        html[data-theme="dark"] .sidebar-nav a:hover {
            background: #102a45 !important;
            border-color: #294966 !important;
            color: #ffffff !important;
        }

        html[data-theme="dark"] .sidebar-nav a.active,
        html[data-theme="dark"] .sidebar-nav a[aria-current="page"] {
            background: linear-gradient(135deg, #1f6fe5 0%, #0d63d8 100%) !important;
            border-color: #2c7bed !important;
            color: #ffffff !important;
            box-shadow: 0 10px 24px rgba(13, 99, 216, .28);
        }

        html[data-theme="dark"] .sidebar-nav a.active .nav-icon,
        html[data-theme="dark"] .sidebar-nav a[aria-current="page"] .nav-icon,
        html[data-theme="dark"] .sidebar-nav a.active svg,
        html[data-theme="dark"] .sidebar-nav a[aria-current="page"] svg {
            color: #ffffff !important;
            stroke: currentColor !important;
        }

        html[data-theme="dark"] .sidebar-foot {
            background: #070518 !important;
            border-top-color: #1b324b !important;
            color: #8fa7c3 !important;
        }

        html[data-theme="dark"] .sidebar-foot span {
            color: #eaf2fb !important;
        }

        html[data-theme="dark"] .sidebar-foot small {
            color: #8fa7c3 !important;
        }

        html[data-theme="dark"] .sidebar-close {
            color: #b6c8db !important;
        }

        @media (max-width: 760px) {
            .sidebar-brand-row {
                padding: 12px;
            }

            .sidebar-brand.sidebar-brand-logo {
                min-height: 54px;
                padding: 4px 6px;
            }

            .sidebar-brand-logo-image {
                max-width: 168px;
                max-height: 44px;
            }

            .sidebar-nav {
                padding-inline: 8px;
            }
        }

        /* SIDEBAR HIDDEN SCROLLBAR
           Keep scrolling functional without showing a slider. */
        .app-sidebar,
        .sidebar-nav {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }

        .app-sidebar::-webkit-scrollbar,
        .sidebar-nav::-webkit-scrollbar {
            width: 0 !important;
            height: 0 !important;
            display: none !important;
        }
</style>

    @guest
        <style>
            .public-header {
                position: relative;
                z-index: 50;
                min-height: 74px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 28px;
                padding: 10px clamp(28px, 4vw, 64px);
                border-bottom: 1px solid #e6edf6;
                background: rgba(255, 255, 255, 0.98);
                box-shadow: 0 4px 18px rgba(7, 27, 53, 0.04);
                color: #071b35;
            }

            .public-header .brand {
                display: inline-flex;
                align-items: center;
                min-width: 0;
                text-decoration: none;
                line-height: 1;
            }

            .public-header .brand:hover {
                text-decoration: none;
            }

            .public-header .brand-logo-image {
                display: block;
                width: clamp(210px, 18vw, 260px);
                height: auto;
                max-height: 52px;
                object-fit: contain;
                object-position: left center;
            }

            .public-header nav {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .public-header nav a {
                position: relative;
                display: inline-flex;
                align-items: center;
                min-height: 42px;
                padding: 0 13px;
                border-radius: 9px;
                color: #152a47;
                text-decoration: none;
                font-size: .82rem;
                font-weight: 700;
                transition:
                    color .16s ease,
                    background-color .16s ease,
                    transform .12s ease;
            }

            .public-header nav a:hover {
                color: #0d63d8;
                background: #f4f8ff;
            }

            .public-header nav a:active {
                transform: scale(.98);
            }

            .public-header nav a.public-nav-home {
                color: #0d63d8;
            }

            .public-header nav a.public-nav-home::after {
                content: "";
                position: absolute;
                left: 13px;
                right: 13px;
                bottom: 5px;
                height: 2px;
                border-radius: 99px;
                background: #0d63d8;
            }

            .public-header nav a.public-signin {
                min-height: 40px;
                margin-left: 5px;
                padding-inline: 18px;
                border: 1px solid #0d63d8;
                background: #0d63d8;
                color: #ffffff;
                box-shadow: 0 7px 16px rgba(13, 99, 216, .18);
            }

            .public-header nav a.public-signin:hover {
                background: #0758c9;
                color: #ffffff;
            }

            @media (max-width: 760px) {
                .public-header .brand-logo-image {
                    width: 190px;
                    max-height: 44px;
                }

                .public-header {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 10px;
                    padding: 12px 18px;
                }

                .public-header nav {
                    width: 100%;
                    overflow-x: auto;
                    padding-bottom: 2px;
                }

                .public-header nav a {
                    flex: 0 0 auto;
                }

                .public-header nav a.public-signin {
                    margin-left: auto;
                }
            }

        /* SIDEBAR HIDDEN SCROLLBAR
           Keep scrolling functional without showing a slider. */
        .app-sidebar,
        .sidebar-nav {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }

        .app-sidebar::-webkit-scrollbar,
        .sidebar-nav::-webkit-scrollbar {
            width: 0 !important;
            height: 0 !important;
            display: none !important;
        }
</style>
    @endguest
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
@auth
    @php
        $activeWorkspace = auth()->user()->primaryWorkspace();
        $unread = App\Models\NotificationDelivery::where('recipient_user_id', auth()->id())->where('channel', 'SYSTEM')->whereNull('read_at')->count();
        $classification = auth()->user()->access_classification;
        $navigation = match ($activeWorkspace) {
            'BORROWER' => [
                ['dashboard', 'dashboard', 'Dashboard', 'dashboard'],
                ['inventory.index', 'inventory.*', 'Available Items', 'inventory'],
                ['requests.index', 'requests.*', 'My Requests', 'requests'],
                ['custody.index', 'custody.*', 'My Borrowings', 'custody'],
                ['calendar.index', 'calendar.*', 'Borrowing Calendar', 'calendar'],
                ['accountability.index', 'accountability.*', 'My Obligations', 'accountability'],
            ],
            'SPMU' => $classification === App\Enums\AccessClassification::SpmuHead
                ? [
                    ['dashboard', 'dashboard', 'Dashboard', 'dashboard'],
                    ['approvals.index', 'approvals.*', 'For Approval', 'approval'],
                    ['requests.index', 'requests.*', 'Request Records', 'requests'],
                    ['custody.index', 'custody.*', 'Release & Return', 'custody'],
                    ['inventory.index', 'inventory.*', 'Inventory', 'inventory'],
                    ['calendar.index', 'calendar.*', 'Operations Calendar', 'calendar'],
                    ['accountability.index', 'accountability.*', 'Accountability', 'accountability'],
                    ['analytics.index', 'analytics.*', 'Analytics', 'analytics'],
                    ['reports.index', 'reports.index', 'Reports', 'report-document'],
                    ['policies.index', 'policies.*', 'Configuration', 'settings'],
                ]
                : [
                    ['dashboard', 'dashboard', 'Dashboard', 'dashboard'],
                    ['verifications.index', 'verifications.*', 'For Verification', 'approval'],
                    ['requests.index', 'requests.*', 'Request Records', 'requests'],
                    ['custody.release.index', 'custody.release.*', 'Release', 'upload'],
                    ['custody.return.index', 'custody.return.*', 'Return', 'custody'],
                    ['laundry.index', 'laundry.*', 'Laundry', 'success'],
                    ['gate-passes.index', 'gate-passes.*', 'Gate Pass', 'requests'],
                    ['inventory.index', 'inventory.*', 'Inventory', 'inventory'],
                    ['calendar.index', 'calendar.*', 'Operations Calendar', 'calendar'],
                    ['accountability.index', 'accountability.*', 'Accountability', 'accountability'],
                ],
            'ICTU' => [
                ['dashboard', 'dashboard', 'Dashboard', 'dashboard'],
                ['administration.users.index', 'administration.users.*', 'User Accounts', 'users'],
                ['administration.settings.index', 'administration.settings.*', 'Configuration', 'settings'],
                ['reports.audit', 'reports.audit', 'Audit Trail', 'reports'],
                ['reports.notifications', 'reports.notifications', 'Delivery Records', 'notifications'],
            ],
            default => [],
        };
    @endphp
    <div class="app-shell">
        <aside class="app-sidebar" id="primary-sidebar">
            <div class="sidebar-brand-row">
                <a class="brand sidebar-brand sidebar-brand-logo" href="{{ route('dashboard') }}" aria-label="SPMU-ACPMP dashboard">
                    <img
                        class="sidebar-brand-logo-image"
                        src="{{ asset('images/spmu-acpmp-logo.png') }}"
                        alt="SPMU-ACPMP — Supply and Property Management Unit, Asset Custody Monitoring Program"
                    >
                </a>
                <button class="icon-button sidebar-close" type="button" aria-label="Close main menu" title="Close main menu" data-sidebar-close><x-icon name="close" /></button>
            </div>
            <p class="sidebar-label">Main menu</p>
            <nav class="sidebar-nav" aria-label="Primary navigation">
                @foreach($navigation as [$routeName, $routePattern, $label, $icon])
                    <a class="interactive {{ request()->routeIs($routePattern) ? 'active' : '' }}" href="{{ route($routeName) }}" @if(request()->routeIs($routePattern)) aria-current="page" @endif>
                        <span class="nav-icon nav-icon-{{ $icon }}"><x-icon :name="$icon" /></span><span>{{ $label }}</span>
                    </a>
                @endforeach
            </nav>

        </aside>
        <button class="sidebar-backdrop" type="button" aria-label="Close main menu" tabindex="-1" data-sidebar-close></button>
        <div class="app-stage">
            <header class="app-topbar">
                <button class="icon-button menu-toggle" type="button" aria-label="Open main menu" title="Open main menu" aria-controls="primary-sidebar" aria-expanded="false" data-sidebar-toggle><x-icon name="menu" /></button>
                <div class="topbar-title"><strong>{{ isset($topbarTitle) ? $topbarTitle : (isset($title) ? $title : 'Dashboard') }}</strong></div>
                <nav class="account-nav" aria-label="Account navigation">
                    <a class="icon-button interactive notification-control" href="{{ route('notifications.index') }}" aria-label="Notifications{{ $unread ? ': '.$unread.' unread' : '' }}" title="Notifications">
                        <x-icon name="notifications" />
                        @if($unread)<span class="notification-count" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                    </a>
                    <x-account-menu :user="auth()->user()" />
                </nav>
            </header>
            <main class="app-main" id="main-content" tabindex="-1">
                @unless($inlinePageNotices ?? false)
                @if(session('status'))<div class="notice success" role="status"><x-icon name="success" /><div>{{ session('status') }}</div></div>@endif
                @if($errors->any())
                    <div class="notice error" role="alert"><x-icon name="error" /><div><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
                @endif
                @endunless
                @yield('content')
            </main>
        </div>
    </div>
@else
    {{-- LOGIN PORTAL HEADER VISIBILITY START --}}
    @unless(request()->routeIs('home', 'login'))
    <header class="site-header public-header">
        <a class="brand" href="{{ route('home') }}" aria-label="SPMU-ACPMP home">
            <img
                class="brand-logo-image"
                src="{{ asset('images/spmu-acpmp-logo.png') }}"
                alt="SPMU-ACPMP — Supply and Property Management Unit, Asset Custody Monitoring Program"
            >
        </a>
                @unless(request()->routeIs('home', 'login'))
        <nav aria-label="Public navigation">
            <a class="public-signin" href="{{ route('login') }}">Sign in</a>
        </nav>
        @endunless
    </header>
    @endunless
    {{-- LOGIN PORTAL HEADER VISIBILITY END --}}
    <main id="main-content" tabindex="-1">
        @unless(request()->routeIs('home', 'login'))
        @if(session('status'))<div class="notice success" role="status"><x-icon name="success" /><div>{{ session('status') }}</div></div>@endif
        @if($errors->any())<div class="notice error" role="alert"><x-icon name="error" /><div><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
        @endunless
        @yield('content')
    </main>
    <footer><span>Camarines Sur Polytechnic Colleges</span><span>Official operational time: Asia/Manila</span></footer>
@endauth

{{-- Universal transaction/detail typography: keep operational details readable and compact. --}}
<style id="universal-detail-typography">
    :root {
        --detail-section-title-size: 17px;
        --detail-label-size: 12px;
        --detail-value-size: 13px;
        --detail-meta-size: 12px;
    }

    /* Section/card headings: consistent with the compact Return workspace. */
    .release-flow-page .release-card-title h2,
    .request-operational-page .request-section-title,
    .request-operational-page .request-section-title h2,
    .borrower-custody-stack .card-header h2,
    .laundry-detail h2,
    .gate-pass-detail-grid .card-header h2,
    .dashboard-balanced-grid > .queue-card > .card-header h2,
    .dashboard-recent-card > .card-header h2 {
        font-size: var(--detail-section-title-size);
        line-height: 1.4;
    }

    /* Detail labels and values use one readable scale; layout remains page-specific. */
    .release-flow-page .release-context-list dt,
    .request-operational-page .request-information-list dt,
    .borrower-custody-stack .borrower-summary-fact small,
    .laundry-detail .laundry-linen-facts dt,
    .gate-pass-detail-grid .detail-list dt {
        font-size: var(--detail-label-size);
        line-height: 1.5;
    }

    .release-flow-page .release-context-list dd,
    .request-operational-page .request-information-list dd,
    .borrower-custody-stack .borrower-summary-fact strong,
    .laundry-detail .laundry-linen-facts dd,
    .gate-pass-detail-grid .detail-list dd {
        font-size: var(--detail-value-size);
        line-height: 1.5;
    }

    /* Supporting copy should never compete visually with the actual transaction values. */
    .release-flow-page .release-page-heading > div > p:last-child,
    .request-operational-page .request-operational-identity > p,
    .laundry-detail .laundry-detail-heading p:not(.eyebrow),
    .dashboard-balanced-grid > .queue-card > .card-header .meta,
    .dashboard-recent-card > .card-header .meta,
    .dashboard-role-section-note {
        font-size: var(--detail-meta-size);
        line-height: 1.5;
    }

    /* Dashboard figures/statuses stay intentionally distinct; only record text is normalized. */
    .dashboard-watch-copy strong,
    .dashboard-recent-copy strong,
    .dashboard-primary-queue-row > div strong {
        font-size: 12px;
    }

    .dashboard-watch-copy span,
    .dashboard-recent-copy span,
    .dashboard-primary-queue-row > div span {
        font-size: var(--detail-value-size);
    }

    .dashboard-watch-copy small,
    .dashboard-recent-copy small,
    .dashboard-primary-queue-row > div small {
        font-size: 11.5px;
    }

    @media (max-width: 760px) {
        :root { --detail-section-title-size: 16px; }
    }
</style>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
    {{-- SPMU-ACPMP ANTI-DUPLICATE-SUBMISSION START --}}
    <script>
    (() => {
        'use strict';

        const TOKEN_NAME = '_action_token';

        function createActionToken() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            return [
                Date.now().toString(36),
                Math.random().toString(36).slice(2),
                Math.random().toString(36).slice(2)
            ].join('-');
        }

        function ensureActionToken(form) {
            let input = form.querySelector(`input[name="${TOKEN_NAME}"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = TOKEN_NAME;
                input.value = createActionToken();
                form.appendChild(input);
            }
            if (!input.value) input.value = createActionToken();
            return input.value;
        }

        function isStateChangingForm(form) {
            return (form.getAttribute('method') || 'GET').toUpperCase() !== 'GET';
        }

        // A submit request that never navigates away (e.g. a dropped
        // connection) would otherwise leave the form locked forever. This
        // safety net re-enables it automatically; it never fires on a normal
        // submission because the page has already navigated away by then.
        const STUCK_SUBMIT_TIMEOUT_MS = 20000;

        function lockForm(form) {
            form.dataset.submitLocked = '1';
            form.setAttribute('aria-busy', 'true');

            // The clicked submit button keeps its normal visible label - only
            // its interactivity is locked - and every other submit control in
            // the same form is locked with it so a second, conflicting action
            // cannot be started while the first is still in flight.
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                control.style.pointerEvents = 'none';
                control.setAttribute('aria-disabled', 'true');
            });

            form.dataset.submitLockTimer = String(window.setTimeout(() => {
                unlockForm(form);
            }, STUCK_SUBMIT_TIMEOUT_MS));
        }

        function unlockForm(form) {
            delete form.dataset.submitLocked;
            form.removeAttribute('aria-busy');

            if (form.dataset.submitLockTimer) {
                window.clearTimeout(Number(form.dataset.submitLockTimer));
                delete form.dataset.submitLockTimer;
            }

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                control.style.pointerEvents = '';
                control.removeAttribute('aria-disabled');
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form').forEach((form) => {
                if (isStateChangingForm(form)) ensureActionToken(form);
            });
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (!isStateChangingForm(form)) return;
            if (form.dataset.allowRepeatedSubmit === 'true') return;

            ensureActionToken(form);

            if (form.dataset.submitLocked === '1') {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            lockForm(form);
        }, true);

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) return;
                    if (node.matches('form') && isStateChangingForm(node)) ensureActionToken(node);
                    node.querySelectorAll?.('form').forEach((form) => {
                        if (isStateChangingForm(form)) ensureActionToken(form);
                    });
                });
            });
        });

        observer.observe(document.documentElement, { childList: true, subtree: true });

        window.addEventListener('pageshow', (event) => {
            if (!event.persisted) return;

            document.querySelectorAll('form[data-submit-locked="1"]').forEach((form) => {
                unlockForm(form);
                const token = form.querySelector(`input[name="${TOKEN_NAME}"]`);
                if (token) token.value = createActionToken();
            });
        });
    })();
    </script>
    {{-- SPMU-ACPMP ANTI-DUPLICATE-SUBMISSION END --}}

    {{-- SPMU-ACPMP NUMBER INPUT SAFETY START --}}
    <style>
        /*
         * Hide native spinner arrows on all numeric fields so a stray click
         * cannot silently bump a manually-typed value.
         */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
        }
    </style>
    <script>
    (() => {
        'use strict';

        /*
         * Prevent mouse-wheel / touchpad scrolling from changing the value of
         * a focused <input type="number">. Only acts while the field is
         * focused, so normal page scrolling while the pointer happens to
         * pass over a number field elsewhere in the app is never affected.
         */
        document.addEventListener('wheel', (event) => {
            const input = event.target;
            if (
                input instanceof HTMLInputElement &&
                input.type === 'number' &&
                document.activeElement === input
            ) {
                event.preventDefault();
            }
        }, { passive: false, capture: true });

        /*
         * Prevent Arrow Up / Arrow Down from stepping the value of a focused
         * <input type="number">. Every other key (digits, Backspace, Delete,
         * Tab, Shift+Tab, arrow-left/right for cursor movement, etc.) is left
         * untouched, so typing and keyboard navigation keep working normally.
         */
        document.addEventListener('keydown', (event) => {
            const input = event.target;
            if (
                (event.key === 'ArrowUp' || event.key === 'ArrowDown') &&
                input instanceof HTMLInputElement &&
                input.type === 'number'
            ) {
                event.preventDefault();
            }
        }, { capture: true });
    })();
    </script>
    {{-- SPMU-ACPMP NUMBER INPUT SAFETY END --}}
</body>
</html>
