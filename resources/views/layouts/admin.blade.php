<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#12372f">
    @php($villageName = \App\Models\VillageProfile::activeName())
    <title>{{ isset($title) ? $title.' · ' : '' }}Panel Petugas · {{ $villageName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
<div class="admin-layout">
    <aside class="admin-side" id="admin-navigation">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <span class="brand-mark brand-mark-light"><i data-lucide="landmark"></i></span>
            <span><strong>{{ $villageName }}</strong><small>Panel petugas</small></span>
        </a>

        <nav class="side-nav" aria-label="Navigasi admin">
            @foreach(\App\Support\AdminNavigation::visibleTo(auth()->user()) as $section => $links)
                <div class="nav-section">{{ $section }}</div>
                @foreach($links as $link)
                    <a class="{{ request()->routeIs(...$link['patterns']) ? 'active' : '' }}" @if(request()->routeIs(...$link['patterns'])) aria-current="page" @endif href="{{ route($link['route']) }}"><i data-lucide="{{ $link['icon'] }}"></i><span>{{ $link['label'] }}</span></a>
                @endforeach
            @endforeach
        </nav>

        <div class="side-footer">
            <div class="side-user">
                <span class="avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}</span>
                <span><strong>{{ auth()->user()?->name }}</strong><small>{{ auth()->user()?->role_label ?: 'Petugas' }}</small></span>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="side-logout" type="submit" aria-label="Keluar dari panel"><i data-lucide="log-out"></i></button></form>
        </div>
    </aside>

    <button class="side-overlay" type="button" aria-label="Tutup menu navigasi" data-menu-close></button>

    <main class="admin-main">
        <div class="admin-topbar">
            <div class="topbar-left">
                <button class="menu-toggle icon-button" type="button" aria-label="Buka menu" aria-controls="admin-navigation" aria-expanded="false" data-menu-toggle><i data-lucide="menu"></i></button>
                <div class="breadcrumb"><i data-lucide="landmark"></i><span>{{ $villageName }}</span></div>
            </div>
            <div class="topbar-meta"><time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, d F Y') }}</time></div>
        </div>

        @if(session('status')) <div class="alert notice" role="status"><i data-lucide="circle-check"></i><span>{{ session('status') }}</span></div> @endif
        @if($errors->any()) <div class="errors notice" role="alert"><i data-lucide="circle-alert"></i><div><strong>Data belum dapat disimpan</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div> @endif
        @yield('content')
    </main>
</div>

<dialog class="confirm-dialog" data-confirm-dialog aria-labelledby="confirm-title">
    <form method="dialog" class="dialog-card">
        <h2 id="confirm-title" data-confirm-title>Hapus data ini?</h2>
        <p data-confirm-text>Tindakan ini tidak dapat dibatalkan.</p>
        <div class="dialog-actions">
            <button class="btn secondary" value="cancel" type="submit">Batal</button>
            <button class="btn danger" value="confirm" type="submit" data-confirm-accept>Ya, hapus</button>
        </div>
    </form>
</dialog>
</body>
</html>
