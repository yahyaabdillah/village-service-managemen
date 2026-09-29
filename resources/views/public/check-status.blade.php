@extends('layouts.app', ['title' => 'Cek Status'])
@section('content')
<div class="auth-shell">
    <div class="card auth-card status-card">
        <span class="brand-mark auth-mark"><i data-lucide="scan-search"></i></span>
        <h1>Cek status pengajuan</h1>
        <p class="muted">Masukkan kode pengajuan yang dikirim ke WhatsApp Anda beserta NIK yang dipakai saat mengajukan.</p>
        <form method="POST" action="{{ route('status.check') }}" novalidate>
            @csrf
            <div class="field">
                <label for="request-code">Kode pengajuan</label>
                <input id="request-code" name="request_code" value="{{ old('request_code') }}" placeholder="REQ-{{ now()->format('Ymd') }}-ABC123" autocomplete="off" autocapitalize="characters" required>
                <p class="field-help">Diawali REQ- diikuti tanggal dan 6 huruf/angka.</p>
            </div>
            <div class="field">
                <label for="status-nik">NIK</label>
                <input id="status-nik" name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" autocomplete="off" required>
            </div>
            <button class="btn full" type="submit"><i data-lucide="search"></i> Lihat status</button>
        </form>
        <p class="muted small-print"><i data-lucide="lock-keyhole"></i> Status hanya ditampilkan bila kode dan NIK cocok.</p>
    </div>
</div>
@endsection
