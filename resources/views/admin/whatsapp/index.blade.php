@extends('layouts.admin')
@section('content')
@php
    $isRunning = (bool) ($status['running'] ?? false);
    $isReady = (bool) ($status['ready'] ?? false) && $isRunning;
    $isStale = (bool) ($status['stale'] ?? false);
    $state = $status['state'] ?? 'unknown';
    $statusClass = $isReady ? 'success' : ($isStale || in_array($state, ['error', 'logged_out'], true) ? 'danger' : 'warning');
@endphp
<div class="page-head">
    <div>
        <h1>Tautkan WhatsApp</h1>
        <p class="muted">
            {{ $isReady
                ? 'WhatsApp sudah terhubung dan bridge Baileys siap digunakan.'
                : ($isStale
                    ? 'Bridge tidak merespons, tetapi sesi lama masih tercatat. Bersihkan sesi sebelum pairing ulang.'
                    : 'Klik tombol pairing untuk menjalankan bridge Baileys. Setelah QR muncul, scan dari WhatsApp mobile.') }}
        </p>
    </div>
</div>

<div class="card">
    <h2>Status</h2>
    <p><span class="badge {{ $statusClass }}">{{ $state }}</span></p>

    @if($isReady)
        <div class="notice alert" role="status">
            <i data-lucide="circle-check"></i>
            <div><strong>WhatsApp berhasil terhubung</strong><br><span>Bridge siap mengirim notifikasi dari aplikasi.</span></div>
        </div>
    @elseif($isStale)
        <div class="notice alert danger" role="alert">
            <i data-lucide="triangle-alert"></i>
            <div><strong>Sesi WhatsApp terdeteksi stale</strong><br><span>Bridge tidak merespons. Gunakan pembersihan fallback untuk menghapus sesi lama.</span></div>
        </div>
    @endif

    <p class="muted">Ready: {{ $isReady ? 'Ya' : 'Belum' }}</p>
    <p class="muted">Bridge berjalan: {{ $isRunning ? 'Ya' : 'Belum' }}</p>
    <div class="actions">
        @if($isReady || $isStale)
            <form method="POST" action="{{ route('admin.whatsapp.disconnect') }}" onsubmit="return confirm('Putuskan WhatsApp dari aplikasi ini? Pairing ulang akan memerlukan QR baru.')">
                @csrf
                <button class="btn danger" type="submit"><i data-lucide="unlink"></i> {{ $isStale ? 'Bersihkan Sesi Stale' : 'Putuskan WhatsApp' }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.whatsapp.start') }}">
                @csrf
                <button class="btn" type="submit"><i data-lucide="qr-code"></i> Mulai Pairing / Tampilkan QR</button>
            </form>
        @endif
        <a class="btn secondary" href="{{ route('admin.whatsapp.index') }}">Refresh Status</a>
    </div>
</div>

<div class="card">
    <h2>QR Pairing</h2>
    @if($isReady)
        <p><strong>Sesi WhatsApp sudah aktif.</strong></p>
        <p class="muted">QR disembunyikan setelah pairing berhasil. Gunakan tombol <strong>Putuskan WhatsApp</strong> jika ingin menautkan akun lain.</p>
    @elseif($isStale)
        <p><strong>QR tidak ditampilkan karena sesi lama belum dibersihkan.</strong></p>
        <p class="muted">Klik <strong>Bersihkan Sesi Stale</strong>, lalu mulai pairing kembali.</p>
    @elseif($qrImage)
        <img src="{{ $qrImage }}" alt="QR untuk menautkan WhatsApp" width="320" height="320" style="display:block;max-width:100%;height:auto">
        <p class="muted">Buka WhatsApp di ponsel, pilih Perangkat tertaut, lalu scan QR ini.</p>
    @elseif($qr)
        <p>QR sedang diproses. Tekan <strong>Refresh Status</strong> beberapa detik lagi.</p>
    @else
        <p>QR belum tersedia.</p>
        <p class="muted">Klik <strong>Mulai Pairing / Tampilkan QR</strong>, tunggu beberapa detik, lalu tekan <strong>Refresh Status</strong>.</p>
    @endif
</div>

<div class="card">
    <h2>Konfigurasi</h2>
    <ul>
        <li>Notifikasi: <span class="badge {{ config('whatsapp.enabled') ? 'success' : 'warning' }}">{{ config('whatsapp.enabled') ? 'Aktif' : 'Nonaktif' }}</span></li>
        <li>Bridge: <code>{{ config('whatsapp.bridge_url') }}</code></li>
        <li>Token bridge: <span class="badge {{ filled(config('whatsapp.bridge_token')) ? 'success' : 'danger' }}">{{ filled(config('whatsapp.bridge_token')) ? 'Tersedia' : 'Belum diatur' }}</span></li>
    </ul>
    <p class="muted">Aktifkan <code>WHATSAPP_NOTIFICATIONS_ENABLED=true</code> agar perubahan status pengajuan otomatis mengirim pesan.</p>
</div>
@endsection
