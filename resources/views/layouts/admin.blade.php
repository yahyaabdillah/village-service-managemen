<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#12372f">
    <title>{{ $title ?? 'Admin Desa' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
<div class="admin-layout">
    <aside class="admin-side" id="admin-navigation">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <span class="brand-mark brand-mark-light"><i data-lucide="landmark"></i></span>
            <span><strong>Ruang Desa</strong><small>Panel administrasi</small></span>
        </a>

        <nav class="side-nav" aria-label="Navigasi admin">
            <div class="nav-section">Pelayanan</div>
            <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
            <a class="{{ request()->routeIs('admin.service-requests.*') ? 'active' : '' }}" href="{{ route('admin.service-requests.index') }}"><i data-lucide="inbox"></i><span>Pengajuan</span></a>

            <div class="nav-section">Data Desa</div>
            <a class="{{ request()->routeIs('admin.residents.*') ? 'active' : '' }}" href="{{ route('admin.residents.index') }}"><i data-lucide="users"></i><span>Penduduk</span></a>
            <a class="{{ request()->routeIs('admin.family-cards.*') ? 'active' : '' }}" href="{{ route('admin.family-cards.index') }}"><i data-lucide="contact-round"></i><span>Kartu Keluarga</span></a>
            <a class="{{ request()->routeIs('admin.service-types.*', 'admin.service-requirements.*', 'admin.service-type-fields.*') ? 'active' : '' }}" href="{{ route('admin.service-types.index') }}"><i data-lucide="grid-2x2-check"></i><span>Konfigurasi Layanan</span></a>
            <a class="{{ request()->routeIs('admin.document-templates.*') ? 'active' : '' }}" href="{{ route('admin.document-templates.index') }}"><i data-lucide="file-pen-line"></i><span>Template Dokumen</span></a>
            <a class="{{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('admin.announcements.index') }}"><i data-lucide="megaphone"></i><span>Pengumuman</span></a>

            <div class="nav-section">Sistem</div>
            <a class="{{ request()->routeIs('admin.users.*', 'admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i data-lucide="user-cog"></i><span>Akses Pengguna</span></a>
            <a class="{{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}"><i data-lucide="history"></i><span>Jejak Audit</span></a>
            <!-- <a class="{{ request()->routeIs('admin.security-logs.*', 'admin.notification-logs.*') ? 'active' : '' }}" href="{{ route('admin.security-logs.index') }}"><i data-lucide="scan-search"></i><span>Log Sistem</span></a> -->
            <!-- <a href="{{ config('observability.grafana_url') }}" target="_blank" rel="noopener noreferrer"><i data-lucide="external-link"></i><span>Grafana</span></a> -->
            <a class="{{ request()->routeIs('admin.whatsapp.*') ? 'active' : '' }}" href="{{ route('admin.whatsapp.index') }}"><i data-lucide="message-circle-more"></i><span>WhatsApp</span></a>
        </nav>

        <div class="side-footer">
            <div class="side-user">
                <span class="avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}</span>
                <span><strong>{{ auth()->user()?->name }}</strong><small>Administrator</small></span>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="side-logout" type="submit" aria-label="Keluar"><i data-lucide="log-out"></i></button></form>
        </div>
    </aside>

    <button class="side-overlay" type="button" aria-label="Tutup menu navigasi" data-menu-close></button>

    <main class="admin-main">
        <div class="admin-topbar">
            <div class="topbar-left">
                <button class="menu-toggle icon-button" type="button" aria-label="Buka menu admin" aria-controls="admin-navigation" aria-expanded="false" data-menu-toggle><i data-lucide="menu"></i></button>
                <div class="breadcrumb"><i data-lucide="sparkles"></i><span>Selamat bekerja, {{ explode(' ', auth()->user()?->name ?? 'Admin')[0] }}</span></div>
            </div>
            <div class="topbar-meta"><span class="system-dot"></span><span>Sistem aktif</span><time>{{ now()->translatedFormat('d M Y, H:i') }}</time></div>
        </div>

        @if(session('status')) <div class="alert notice" role="status"><i data-lucide="circle-check"></i><span>{{ session('status') }}</span></div> @endif
        @if($errors->any()) <div class="errors notice" role="alert"><i data-lucide="circle-alert"></i><div><strong>Ada data yang perlu diperbaiki</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div> @endif
        @yield('content')
    </main>
</div>
</body>
</html>
