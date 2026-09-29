@extends('layouts.admin', ['title' => 'WhatsApp'])
@section('content')
@php
    $isRunning = (bool) ($status['running'] ?? false);
    $isReady = (bool) ($status['ready'] ?? false) && $isRunning;
    $isStale = (bool) ($status['stale'] ?? false);
    $state = $status['state'] ?? 'unknown';
    $isLoggedOut = $state === 'logged_out';
    $isWaiting = ! $isReady && ! $isStale && in_array($state, ['starting', 'qr'], true);
    $stateMeta = match (true) {
        $isReady => ['Terhubung', 'success', 'Nomor WhatsApp desa aktif. Setiap perubahan status pengajuan dikirim otomatis ke warga.'],
        $isStale => ['Tidak merespons', 'danger', 'Sesi lama masih tercatat, tetapi layanan penghubung tidak menjawab. Bersihkan sesi lalu tautkan ulang.'],
        $isLoggedOut => ['Keluar dari ponsel', 'danger', 'Perangkat tertaut dihapus dari ponsel. Bersihkan sesi lalu pindai QR baru.'],
        $state === 'qr' => ['Menunggu pemindaian', 'warning', 'Pindai kode QR di bawah dari ponsel yang memakai nomor WhatsApp desa.'],
        $state === 'starting' => ['Menyiapkan…', 'warning', 'Layanan penghubung sedang dijalankan. Kode QR muncul dalam beberapa detik.'],
        $state === 'error' => ['Gagal', 'danger', 'Layanan penghubung berhenti karena kesalahan. Coba mulai lagi; bila berulang, periksa catatan di server.'],
        in_array($state, ['disconnected', 'stopped'], true) => ['Terputus', 'warning', 'Koneksi ke WhatsApp terputus. Mulai lagi untuk menautkan ulang.'],
        default => ['Belum ditautkan', 'muted', 'Pesan ke warga baru terkirim setelah nomor WhatsApp desa ditautkan.'],
    };
    [$stateLabel, $stateTone, $stateHint] = $stateMeta;
    $notificationsOn = (bool) config('whatsapp.enabled');
@endphp
<div class="page-head">
    <div>
        <h1>Tautkan WhatsApp</h1>
        <p class="muted">Nomor WhatsApp desa yang dipakai sistem untuk mengirim kode pengajuan, perubahan status, dan surat jadi kepada warga.</p>
    </div>
    <div class="actions"><a class="btn ghost" href="{{ route('admin.whatsapp.index') }}"><i data-lucide="refresh-cw"></i> Muat ulang status</a></div>
</div>

<div class="wa-grid">
    <section class="card wa-card">
        <div class="wa-state">
            <span class="wa-state-icon {{ $stateTone }}"><i data-lucide="{{ $isReady ? 'circle-check' : ($isWaiting ? 'qr-code' : 'message-circle-more') }}"></i></span>
            <div>
                <p class="panel-label">Status koneksi</p>
                <h2 class="wa-state-title">{{ $stateLabel }} <span class="badge {{ $stateTone }} plain">{{ $notificationsOn ? 'Notifikasi aktif' : 'Notifikasi nonaktif' }}</span></h2>
                <p class="muted">{{ $stateHint }}</p>
            </div>
        </div>

        @if($isReady)
            <div class="notice alert" role="status"><i data-lucide="circle-check"></i><div><strong>WhatsApp berhasil terhubung</strong> — sistem siap mengirim pesan ke warga.</div></div>
        @elseif($isStale)
            <div class="notice danger" role="alert"><i data-lucide="triangle-alert"></i><div><strong>Sesi WhatsApp terdeteksi stale.</strong> Bersihkan sesi lama sebelum menautkan ulang.</div></div>
        @elseif($state === 'error' && ! empty($status['error']))
            <div class="notice danger" role="alert"><i data-lucide="triangle-alert"></i><div>{{ $status['error'] }}</div></div>
        @endif

        @if(! $notificationsOn)
            <div class="notice warning" role="status"><i data-lucide="info"></i><div>Pengiriman otomatis dimatikan di pengaturan server (<code>WHATSAPP_NOTIFICATIONS_ENABLED</code>). Menautkan nomor saja belum mengirim pesan.</div></div>
        @endif

        <div class="actions wa-actions">
            @if($isReady || $isStale || $isLoggedOut)
                <form method="POST" action="{{ route('admin.whatsapp.disconnect') }}" data-confirm="{{ $isReady ? 'Putuskan WhatsApp dari sistem?' : 'Bersihkan sesi WhatsApp?' }}" data-confirm-text="{{ $isReady ? 'Warga tidak menerima pesan sampai nomor ditautkan kembali dengan QR baru.' : 'Sesi lama dihapus; setelah itu tautkan ulang dengan memindai QR baru.' }}" data-confirm-label="{{ $isReady ? 'Ya, putuskan' : 'Ya, bersihkan' }}">
                    @csrf
                    <button class="btn danger" type="submit"><i data-lucide="unlink"></i> {{ $isStale ? 'Bersihkan Sesi Stale' : ($isLoggedOut ? 'Bersihkan Sesi Logout' : 'Putuskan WhatsApp') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.whatsapp.start') }}">
                    @csrf
                    <button class="btn" type="submit"><i data-lucide="qr-code"></i> {{ $isWaiting ? 'Mulai ulang pairing' : 'Mulai Pairing / Tampilkan QR' }}</button>
                </form>
            @endif
        </div>

        <dl class="wa-facts">
            <div><dt>Alamat layanan penghubung</dt><dd class="mono">{{ config('whatsapp.bridge_url') }}</dd></div>
            <div><dt>Kunci akses</dt><dd>{{ filled(config('whatsapp.bridge_token')) ? 'Terpasang' : 'Belum diatur' }}</dd></div>
            <div><dt>Batas kirim</dt><dd>{{ config('whatsapp.rate_limit_per_minute') }} pesan/menit · {{ config('whatsapp.rate_limit_per_recipient') }} per nomor</dd></div>
            <div><dt>Riwayat</dt><dd><a class="text-link" href="{{ route('admin.notification-logs.index') }}">Lihat pesan terkirim <i data-lucide="arrow-right"></i></a></dd></div>
        </dl>
    </section>

    <section class="card wa-qr-card" aria-live="polite">
        <p class="panel-label">Kode QR</p>
        @if($isReady)
            <div class="wa-qr-empty"><i data-lucide="circle-check"></i><strong>Sudah tertaut</strong><p class="muted">QR hanya diperlukan saat menautkan nomor. Putuskan koneksi bila ingin memakai nomor lain.</p></div>
        @elseif($isStale || $isLoggedOut)
            <div class="wa-qr-empty"><i data-lucide="unlink"></i><strong>Bersihkan sesi dulu</strong><p class="muted">QR baru tersedia setelah sesi lama dibersihkan.</p></div>
        @elseif($qrImage)
            <img class="wa-qr" src="{{ $qrImage }}" alt="Kode QR untuk menautkan WhatsApp" width="288" height="288">
            <ol class="wa-steps">
                <li>Buka WhatsApp di ponsel nomor desa.</li>
                <li>Menu <strong>Perangkat tertaut</strong> → <strong>Tautkan perangkat</strong>.</li>
                <li>Arahkan kamera ke kode ini. Halaman memuat ulang sendiri setelah tertaut.</li>
            </ol>
        @elseif($isWaiting || $qr)
            <div class="wa-qr-empty"><span class="spinner" aria-hidden="true"></span><strong>Menyiapkan kode QR…</strong><p class="muted">Halaman memuat ulang otomatis setiap beberapa detik.</p></div>
        @else
            <div class="wa-qr-empty"><i data-lucide="qr-code"></i><strong>Belum ada kode QR</strong><p class="muted">Klik <em>Mulai Pairing / Tampilkan QR</em>; kode muncul di sini dalam beberapa detik.</p></div>
        @endif
    </section>
</div>

@if($isWaiting || (! $isReady && $qr))
    <script>window.setTimeout(function () { window.location.reload(); }, 7000);</script>
@endif
@endsection
