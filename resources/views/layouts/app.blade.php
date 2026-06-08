<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Mario') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    @vite(['resources/sass/app.scss'])

    <style>
        :root {
            --c-bg:         #0a0a0c;
            --c-surface:    #111114;
            --c-surface-2:  #18181c;
            --c-surface-3:  #222228;
            --c-border:     rgba(255,255,255,0.06);
            --c-border-2:   rgba(255,255,255,0.10);
            --c-text:       #e2e0da;
            --c-text-2:     #90908a;
            --c-text-3:     #505048;
            --c-accent:     #c8a255;
            --c-accent-h:   #d4b06e;
            --c-accent-dim: rgba(200,162,85,0.10);
            --c-success:    #4a9c6d;
            --c-danger:     #c04848;
            --c-warning:    #c07830;
            --c-info:       #3a80c0;
            --font:         'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --radius:       8px;
            --radius-sm:    5px;
            --ease:         150ms ease;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: var(--font);
            background: var(--c-bg);
            color: var(--c-text);
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            margin: 0;
        }

        /* ── NAV ───────────────────────────────────────────────────── */
        .app-nav {
            background: var(--c-surface);
            border-bottom: 1px solid var(--c-border);
            height: 54px;
            display: flex;
            align-items: center;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 200;
        }

        .app-nav-inner {
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            gap: 1.5rem;
        }

        .app-brand {
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--c-accent);
            text-decoration: none;
            white-space: nowrap;
        }
        .app-brand:hover { color: var(--c-accent-h); }

        .app-brand-sep {
            width: 1px;
            height: 18px;
            background: var(--c-border-2);
        }

        .nav-links {
            display: flex;
            gap: 2px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.7rem;
            border-radius: var(--radius-sm);
            color: var(--c-text-2);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all var(--ease);
        }
        .nav-links a:hover { background: var(--c-surface-2); color: var(--c-text); }
        .nav-links a.active { background: var(--c-surface-2); color: var(--c-accent); }

        .nav-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-btn {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.32rem 0.7rem;
            border-radius: var(--radius-sm);
            color: var(--c-text-2);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--c-border);
            background: transparent;
            transition: all var(--ease);
        }
        .user-btn:hover { background: var(--c-surface-2); border-color: var(--c-border-2); color: var(--c-text); }

        /* ── MAIN ──────────────────────────────────────────────────── */
        main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ── CARDS ─────────────────────────────────────────────────── */
        .card {
            background: var(--c-surface) !important;
            border: 1px solid var(--c-border) !important;
            border-radius: var(--radius) !important;
            color: var(--c-text) !important;
        }

        .card-header {
            background: transparent !important;
            border-bottom: 1px solid var(--c-border) !important;
            padding: 0.9rem 1.25rem !important;
            color: var(--c-text) !important;
        }

        .card-body {
            padding: 1.25rem !important;
        }

        .card-footer {
            background: transparent !important;
            border-top: 1px solid var(--c-border) !important;
            color: var(--c-text-2) !important;
            font-size: 12px !important;
        }

        /* ── TABLES ────────────────────────────────────────────────── */
        .table {
            color: var(--c-text) !important;
            border-color: var(--c-border) !important;
        }

        .table thead th {
            background: var(--c-surface-2) !important;
            color: var(--c-text-2) !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            letter-spacing: 0.08em !important;
            text-transform: uppercase !important;
            border-color: var(--c-border) !important;
            padding: 0.7rem 1rem !important;
        }

        .table tbody tr {
            border-color: var(--c-border) !important;
            transition: background var(--ease);
        }

        .table tbody tr:hover { background: rgba(255,255,255,0.025) !important; }

        .table tbody td {
            border-color: var(--c-border) !important;
            padding: 0.8rem 1rem !important;
            vertical-align: middle !important;
        }

        /* ── FORMS ─────────────────────────────────────────────────── */
        .form-control, .form-select {
            background: var(--c-surface-2) !important;
            border: 1px solid var(--c-border-2) !important;
            color: var(--c-text) !important;
            border-radius: var(--radius-sm) !important;
            font-family: var(--font) !important;
            font-size: 14px !important;
            transition: all var(--ease) !important;
        }

        .form-control:focus, .form-select:focus {
            background: var(--c-surface-3) !important;
            border-color: var(--c-accent) !important;
            color: var(--c-text) !important;
            box-shadow: 0 0 0 2px var(--c-accent-dim) !important;
            outline: none !important;
        }

        .form-control::placeholder { color: var(--c-text-3) !important; }

        .form-control[readonly] {
            background: var(--c-surface-3) !important;
            color: var(--c-text-2) !important;
        }

        .form-label {
            color: var(--c-text-2) !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            letter-spacing: 0.07em !important;
            text-transform: uppercase !important;
            margin-bottom: 0.4rem !important;
        }

        .form-text { color: var(--c-text-3) !important; font-size: 12px !important; }

        .input-group-text {
            background: var(--c-surface-3) !important;
            border: 1px solid var(--c-border-2) !important;
            color: var(--c-text-2) !important;
        }

        .form-select option { background: var(--c-surface-2); }

        /* ── BUTTONS ───────────────────────────────────────────────── */
        .btn {
            font-family: var(--font) !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            border-radius: var(--radius-sm) !important;
            transition: all var(--ease) !important;
            padding: 0.42rem 1rem !important;
            letter-spacing: 0.01em !important;
        }

        .btn-sm { font-size: 12px !important; padding: 0.28rem 0.65rem !important; }

        .btn-primary {
            background: var(--c-accent) !important;
            border-color: var(--c-accent) !important;
            color: #0a0a0c !important;
            font-weight: 600 !important;
        }
        .btn-primary:hover { background: var(--c-accent-h) !important; border-color: var(--c-accent-h) !important; }

        .btn-success { background: var(--c-success) !important; border-color: var(--c-success) !important; color: #fff !important; }
        .btn-success:hover { filter: brightness(1.1); }

        .btn-danger  { background: var(--c-danger)  !important; border-color: var(--c-danger)  !important; color: #fff !important; }
        .btn-danger:hover  { filter: brightness(1.1); }

        .btn-warning { background: var(--c-warning) !important; border-color: var(--c-warning) !important; color: #fff !important; }
        .btn-warning:hover { filter: brightness(1.1); }

        .btn-info    { background: var(--c-info)    !important; border-color: var(--c-info)    !important; color: #fff !important; }

        .btn-secondary {
            background: var(--c-surface-3) !important;
            border-color: var(--c-border-2) !important;
            color: var(--c-text-2) !important;
        }
        .btn-secondary:hover { background: var(--c-surface-2) !important; color: var(--c-text) !important; }

        .btn-outline-primary  { border-color: var(--c-accent) !important; color: var(--c-accent) !important; background: transparent !important; }
        .btn-outline-primary:hover  { background: var(--c-accent-dim) !important; color: var(--c-accent-h) !important; }

        .btn-outline-secondary { border-color: var(--c-border-2) !important; color: var(--c-text-2) !important; background: transparent !important; }
        .btn-outline-secondary:hover { background: var(--c-surface-2) !important; color: var(--c-text) !important; }

        .btn-outline-danger { border-color: var(--c-danger) !important; color: var(--c-danger) !important; background: transparent !important; }
        .btn-outline-danger:hover { background: rgba(192,72,72,0.10) !important; }

        .btn-outline-info { border-color: var(--c-info) !important; color: var(--c-info) !important; background: transparent !important; }
        .btn-outline-info:hover { background: rgba(58,128,192,0.10) !important; }

        .btn-link { color: var(--c-accent) !important; text-decoration: none !important; }
        .btn-link:hover { color: var(--c-accent-h) !important; }

        /* ── BADGES ────────────────────────────────────────────────── */
        .badge {
            font-size: 11px !important;
            font-weight: 600 !important;
            letter-spacing: 0.04em !important;
            padding: 0.28em 0.55em !important;
            border-radius: 4px !important;
        }

        .bg-success   { background: var(--c-success) !important; }
        .bg-danger    { background: var(--c-danger)  !important; }
        .bg-warning   { background: var(--c-warning) !important; }
        .bg-info      { background: var(--c-info)    !important; }
        .bg-primary   { background: var(--c-accent)  !important; color: #0a0a0c !important; }
        .bg-secondary { background: var(--c-surface-3) !important; color: var(--c-text-2) !important; }

        /* ── ALERTS ────────────────────────────────────────────────── */
        .alert {
            border-radius: var(--radius) !important;
            font-size: 13px !important;
            padding: 0.75rem 1rem !important;
            border-width: 1px !important;
            border-style: solid !important;
        }

        .alert-success { background: rgba(74,156,109,0.08) !important; border-color: rgba(74,156,109,0.20) !important; color: #6abf8a !important; }
        .alert-danger  { background: rgba(192,72,72,0.08)  !important; border-color: rgba(192,72,72,0.20)  !important; color: #d47070 !important; }
        .alert-warning { background: rgba(192,120,48,0.08) !important; border-color: rgba(192,120,48,0.20) !important; color: #d09050 !important; }
        .alert-info    { background: rgba(58,128,192,0.08) !important; border-color: rgba(58,128,192,0.20) !important; color: #5a9ec8 !important; }

        .btn-close { filter: invert(1) !important; opacity: 0.4 !important; }

        /* ── MODALS ────────────────────────────────────────────────── */
        .modal-content {
            background: var(--c-surface) !important;
            border: 1px solid var(--c-border-2) !important;
            border-radius: var(--radius) !important;
            color: var(--c-text) !important;
        }

        .modal-header { border-bottom: 1px solid var(--c-border) !important; padding: 1rem 1.25rem !important; }
        .modal-footer { border-top:    1px solid var(--c-border) !important; padding: 1rem 1.25rem !important; }
        .modal-backdrop.show { opacity: 0.65 !important; }

        /* ── DROPDOWN ──────────────────────────────────────────────── */
        .dropdown-menu {
            background: var(--c-surface-2) !important;
            border: 1px solid var(--c-border-2) !important;
            border-radius: var(--radius) !important;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5) !important;
            padding: 0.25rem !important;
            min-width: 160px !important;
        }

        .dropdown-item {
            color: var(--c-text-2) !important;
            font-size: 13px !important;
            padding: 0.45rem 0.75rem !important;
            border-radius: var(--radius-sm) !important;
            border: none !important;
            background: transparent !important;
            width: 100% !important;
            text-align: left !important;
            cursor: pointer !important;
            transition: all var(--ease) !important;
        }
        .dropdown-item:hover { background: var(--c-surface-3) !important; color: var(--c-text) !important; }

        /* ── BREADCRUMB ────────────────────────────────────────────── */
        .breadcrumb { background: transparent !important; padding: 0 !important; margin: 0 !important; }
        .breadcrumb-item a { color: var(--c-accent) !important; text-decoration: none !important; }
        .breadcrumb-item a:hover { color: var(--c-accent-h) !important; }
        .breadcrumb-item.active { color: var(--c-text-2) !important; }
        .breadcrumb-item + .breadcrumb-item::before { color: var(--c-text-3) !important; }

        /* ── UTILITIES ─────────────────────────────────────────────── */
        .text-muted { color: var(--c-text-2) !important; }
        h1,h2,h3,h4,h5,h6 { color: var(--c-text); }
        a { color: var(--c-accent); }
        hr { border-color: var(--c-border) !important; opacity: 1 !important; }

        .is-invalid { border-color: var(--c-danger) !important; }
        .invalid-feedback { color: #d47070 !important; font-size: 12px !important; }

        /* ── LAYOUT HELPERS ────────────────────────────────────────── */
        .section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--c-text);
            margin: 0;
            line-height: 1.3;
        }

        .section-sub {
            font-size: 13px;
            color: var(--c-text-2);
            margin: 0.2rem 0 0;
        }

        .stat-block {
            background: var(--c-surface-2);
            border: 1px solid var(--c-border);
            border-radius: var(--radius);
            padding: 1.25rem 1rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--c-text-2);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 13px;
            color: var(--c-text-2);
            text-decoration: none;
            transition: color var(--ease);
            margin-bottom: 1.25rem;
        }
        .back-link:hover { color: var(--c-text); }

        .action-group { display: flex; gap: 3px; }

        /* ── SCROLLBAR ─────────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--c-bg); }
        ::-webkit-scrollbar-thumb { background: var(--c-surface-3); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--c-border-2); }

        @media (max-width: 768px) {
            main { padding: 1rem; }
            .app-nav { padding: 0 1rem; }
            .section-header { flex-direction: column; }
        }
    </style>
</head>
<body>
    <nav class="app-nav">
        <div class="app-nav-inner">
            <a href="{{ url('/') }}" class="app-brand">{{ config('app.name', 'Mario') }}</a>

            @auth
            <div class="app-brand-sep"></div>
            <ul class="nav-links">
                <li>
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2"></i> Tableau de bord
                    </a>
                </li>
                <li>
                    <a href="{{ route('films.index') }}" class="{{ request()->routeIs('films.*') ? 'active' : '' }}">
                        <i class="bi bi-film"></i> Films
                    </a>
                </li>
                <li>
                    <a href="{{ route('stocks.index') }}" class="{{ request()->routeIs('stocks.*') ? 'active' : '' }}">
                        <i class="bi bi-box-seam"></i> Stock DVD
                    </a>
                </li>
            </ul>
            @endauth

            <div class="nav-right">
                @guest
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm">Connexion</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm">S'inscrire</a>
                    @endif
                @else
                    <div class="dropdown">
                        <button class="user-btn dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            {{ Auth::user()->name }}
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item" style="color: var(--c-danger) !important;">
                                    <i class="bi bi-box-arrow-right me-2"></i>Se déconnecter
                                </button>
                            </form>
                        </div>
                    </div>
                @endguest
            </div>
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
                new bootstrap.Dropdown(el);
            });
        });
    </script>
    @vite(['resources/js/app.js'])
    @stack('scripts')
</body>
</html>
