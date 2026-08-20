@extends('layouts.app')
@section('content')
<div class="card modal-shell" style="padding:clamp(1.25rem,4vw,2.25rem)">
    <div style="width:3.5rem;height:3.5rem;display:grid;place-items:center;border-radius:1rem;background:var(--sage-100);color:var(--forest-700);margin-bottom:1rem"><i data-lucide="scan-search" style="width:1.6rem;height:1.6rem"></i></div>
    <span class="eyebrow">Pelacakan mandiri</span>
    <h1 style="font-size:clamp(2rem,4vw,3rem)">Cek status pengajuan</h1>
    <p class="muted">Masukkan kode pengajuan dan NIK yang digunakan saat mendaftar. Informasi hanya ditampilkan setelah keduanya cocok.</p>
    <form method="POST" action="{{ route('status.check') }}">
        @csrf
        <label for="request-code">Kode Pengajuan</label>
        <input id="request-code" name="request_code" value="{{ old('request_code') }}" placeholder="Contoh: REQ-2026-XXXX" autocomplete="off" required>
        <label for="status-nik">NIK</label>
        <input id="status-nik" name="nik" value="{{ old('nik') }}" placeholder="Masukkan NIK" inputmode="numeric" autocomplete="off" required>
        <button class="btn" style="width:100%;margin-top:1rem" type="submit"><i data-lucide="search"></i> Cek Status</button>
    </form>
    <p class="muted" style="display:flex;gap:.45rem;margin:1rem 0 0;font-size:.75rem"><i data-lucide="lock-keyhole"></i> Data Anda tidak disimpan di perangkat ini.</p>
</div>
@endsection
