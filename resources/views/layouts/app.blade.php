<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102238">
    <title>{{ $title ?? 'Imprint Customs Costing' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    <script>
        /* Runs before the body renders: a stored choice wins, otherwise the
           stylesheet's media query decides and nothing is stamped. */
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.dataset.theme = saved;
            }
        } catch (e) { /* private mode: fall back to the OS preference */ }
    </script>
</head>
<body class="app-body">
@php
    $initials = collect(explode(' ', auth()->user()->name ?? 'IC'))
        ->filter()->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))->join('');
@endphp

<div class="app-shell" id="appShell">
    <div class="sidebar-overlay" data-sidebar-close></div>
    <aside class="sidebar" id="appSidebar" aria-label="Primary navigation">
        <div class="sidebar-brand">
            <div class="brand-mark" aria-hidden="true">IC</div>
            <div class="brand-copy"><strong>Imprint Customs</strong><span>Costing System</span></div>
            <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">×</button>
        </div>

        <nav class="nav-list">
            <div class="nav-section-label">Workspace</div>
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 10.8 12 3l9 7.8v9.7a.5.5 0 0 1-.5.5H15v-7H9v7H3.5a.5.5 0 0 1-.5-.5z"/></svg></span><span>Dashboard</span>
            </a>

            @role('SUPER ADMIN|ADMIN|SALES / STAFF')
                <a class="nav-link nav-link-create {{ request()->routeIs('quotations.create') ? 'active' : '' }}" href="{{ route('quotations.create') }}">
                <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span><span>New quotation</span>
            </a>
            <a class="nav-link {{ request()->routeIs('quotations.index', 'quotations.show') ? 'active' : '' }}" href="{{ route('quotations.index') }}">
                <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 12h7M9 16h7"/></svg></span><span>Quotations</span>
            </a>
            <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M16 19c0-3-2-5-5-5s-5 2-5 5M11 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg></span><span>Customers</span>
            </a>
            <a class="nav-link {{ request()->routeIs('artwork.*') ? 'active' : '' }}" href="{{ route('artwork.index') }}">
                <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM4 16l5-5 4 4 2-2 5 5M16.5 9a1.5 1.5 0 1 1 0-.01"/></svg></span><span>Artwork</span>
            </a>
            @endrole

            @role('SUPER ADMIN|ADMIN')
            <div class="nav-section-label">Catalog</div>
                <a class="nav-link {{ request()->routeIs('admin.materials.*') ? 'active' : '' }}" href="{{ route('admin.materials.index') }}">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M4 7h16v13H4zM8 7V4h8v3M9 12h7M9 16h5"/></svg></span><span>Materials</span>
                </a>
                <a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="m12 3 9 4.5-9 4.5-9-4.5zM3 12l9 4.5 9-4.5M3 16.5l9 4.5 9-4.5"/></svg></span><span>Products</span>
                </a>
            @endrole

            @role('SUPER ADMIN')
                <div class="nav-section-label">System</div>
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2 21c0-4 2-7 6-7s6 3 6 7M17 8v6M14 11h6"/></svg></span><span>Users</span>
                </a>
                <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19 12l2-1-2-4-2 .5L15.5 6 15 4h-6l-.5 2L7 7.5 5 7l-2 4 2 1v2l-2 1 2 4 2-.5L8.5 20l.5 2h6l.5-2 1.5-1.5 2 .5 2-4-2-1z"/></svg></span><span>Settings</span>
                </a>
            @endrole
        </nav>

        <div class="sidebar-footer">
            <div class="user-avatar">{{ $initials ?: 'IC' }}</div>
            <div class="user-meta"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->getRoleNames()->first() ?: 'User' }}</span></div>
            <button type="button" class="theme-toggle" data-theme-toggle title="Switch theme" aria-label="Switch between light and dark theme">
                <svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
            <form method="post" action="{{ route('logout') }}">@csrf
                <button class="logout-button" title="Log out" aria-label="Log out"><svg viewBox="0 0 24 24"><path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"/></svg></button>
            </form>
        </div>
    </aside>

    <section class="app-content">
        <header class="mobile-header">
            <button class="menu-button" type="button" data-sidebar-open aria-controls="appSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
            <div class="mobile-brand"><span>IC</span> Imprint Customs</div>
            <div class="user-avatar user-avatar-small">{{ $initials ?: 'IC' }}</div>
        </header>
        <main class="main">
            @if(session('success'))
                <div class="flash flash-success" role="status"><span class="flash-icon">✓</span><div><strong>Success</strong><span>{{ session('success') }}</span></div></div>
            @endif
            @yield('content')
        </main>
    </section>
</div>

<script>
(() => {
    const shell = document.getElementById('appShell');
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButtons = document.querySelectorAll('[data-sidebar-close]');
    const setOpen = open => {
        shell.classList.toggle('sidebar-open', open);
        openButton?.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('no-scroll', open);
    };
    openButton?.addEventListener('click', () => setOpen(true));
    closeButtons.forEach(button => button.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', event => event.key === 'Escape' && setOpen(false));
})();
</script>
@stack('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var button = document.querySelector('[data-theme-toggle]');
        if (!button) return;

        button.addEventListener('click', function () {
            var root = document.documentElement;
            var showingDark = root.dataset.theme
                ? root.dataset.theme === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            var next = showingDark ? 'light' : 'dark';

            root.dataset.theme = next;
            try { localStorage.setItem('theme', next); } catch (e) {}
        });
    });
</script>
</body>
</html>
