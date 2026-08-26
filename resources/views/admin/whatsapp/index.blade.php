@extends('layouts.admin')
@section('content')
@php
    $isRunning = (bool) ($status['running'] ?? false);
    $isReady = (bool) ($status['ready'] ?? false) && $isRunning;
    $state = $status['state'] ?? 'unknown';
    $statusClass = $isReady ? 'success' : (! $isRunning ? 'danger' : 'warning');
@endphp
<div class="page-head">
    <div>
        <h1>Tautkan WhatsApp</h1>
        <p class="muted">
            {{ $isReady
                ? 'WhatsApp sudah terhubung dan bridge Baileys siap digunakan.'
                : (! $isRunning
                    ? 'Bridge WhatsApp tidak dapat dihubungi. Pastikan service-nya jalan di VPS.'
                    : 'Scan QR di bawah dari WhatsApp mobile untuk memulai pairing.') }}
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
    @elseif(! $isRunning)
        <div class="notice alert danger" role="alert">
            <i data-lucide="triangle-alert"></i>
            <div><strong>Bridge tidak terjangkau</strong><br><span>Cek apakah service WhatsApp di VPS sedang berjalan, lalu tekan Refresh.</span></div>
        </div>
    @endif

    <p class="muted">Ready: {{ $isReady ? 'Ya' : 'Belum' }}</p>
    <p class="muted">Bridge terjangkau: {{ $isRunning ? 'Ya' : 'Tidak' }}</p>
    <div class="actions">
        @if($isReady)
            <form method="POST" action="{{ route('admin.whatsapp.disconnect') }}" onsubmit="return confirm('Putuskan WhatsApp dari aplikasi ini? Pairing ulang akan memerlukan QR baru.')">
                @csrf
                <button class="btn danger" type="submit"><i data-lucide="unlink"></i> Putuskan WhatsApp</button>
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
    @elseif(! $isRunning)
        <p>QR tidak dapat diambil karena bridge tidak terjangkau.</p>
    @elseif($qrImage)
        <img src="{{ $qrImage }}" alt="QR untuk menautkan WhatsApp" width="320" height="320" style="display:block;max-width:100%;height:auto">
        <p class="muted">Buka WhatsApp di ponsel, pilih Perangkat tertaut, lalu scan QR ini.</p>
    @elseif($qr)
        <p>QR sedang diproses. Tekan <strong>Refresh Status</strong> beberapa detik lagi.</p>
    @else
        <p>QR belum tersedia. Tekan <strong>Refresh Status</strong> beberapa detik lagi.</p>
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
