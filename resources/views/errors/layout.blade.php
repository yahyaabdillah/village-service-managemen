<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#12372f">
    <title>{{ $code }} · @yield('title') · Layanan Desa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-body error-body">
    <main class="error-page">
        <p class="error-code" aria-hidden="true">{{ $code }}</p>
        <h1>@yield('title')</h1>
        <p class="error-lead">@yield('lead')</p>
        <div class="error-actions">
            @if(auth()->check())
                <a class="btn" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.dashboard') }}"><i data-lucide="arrow-left"></i> Kembali</a>
                <a class="btn ghost" href="{{ route('admin.dashboard') }}">Ke dashboard</a>
            @else
                <a class="btn" href="{{ route('home') }}"><i data-lucide="house"></i> Ke beranda</a>
                <a class="btn ghost" href="{{ route('status.form') }}">Cek status pengajuan</a>
            @endif
        </div>
        @hasSection('detail')
            <p class="error-detail">@yield('detail')</p>
        @endif
    </main>
</body>
</html>
