<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#173f35">
    <title>{{ $title ?? 'Sistem Layanan Desa' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-body">
<header class="public-header">
    <div class="public-nav">
        <a class="public-brand" href="{{ route('home') }}" aria-label="Kembali ke beranda">
            <span class="brand-mark"><i data-lucide="landmark"></i></span>
            <span>
                <strong>{{ ($profile ?? null)?->village_name ?? 'Layanan Desa' }}</strong>
                <small>Pelayanan warga terpadu</small>
            </span>
        </a>
        <button class="public-menu-toggle icon-button" type="button" aria-label="Buka menu" aria-expanded="false" data-public-menu-toggle>
            <i data-lucide="menu"></i>
        </button>
        <nav class="public-links" data-public-menu>
            <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Beranda</a>
            <a class="{{ request()->routeIs('services.*', 'requests.*') ? 'active' : '' }}" href="{{ route('services.index') }}">Layanan</a>
            <a class="{{ request()->routeIs('status.*') ? 'active' : '' }}" href="{{ route('status.form') }}">Cek Status</a>
            <a class="nav-admin" href="{{ route('login') }}"><i data-lucide="shield-check"></i> Portal Admin</a>
        </nav>
    </div>
</header>

<main class="public-main">
    @if($errors->any())
        <div class="errors notice" role="alert">
            <i data-lucide="circle-alert"></i>
            <div><strong>Periksa kembali data Anda</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
    @endif
    @yield('content')
</main>

<footer class="public-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <span class="brand-mark"><i data-lucide="landmark"></i></span>
            <div><strong>{{ ($profile ?? null)?->village_name ?? 'Layanan Desa' }}</strong><p>Pelayanan publik yang mudah, transparan, dan aman.</p></div>
        </div>
        <div class="footer-security"><i data-lucide="lock-keyhole"></i><span>Dokumen warga dilindungi dengan kode pengajuan dan NIK.</span></div>
        <small>© {{ date('Y') }} Pemerintah Desa</small>
    </div>
</footer>
</body>
</html>
