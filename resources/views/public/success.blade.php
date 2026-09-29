@extends('layouts.app', ['title' => 'Pengajuan Terkirim'])
@section('content')
<div class="auth-shell">
    <div class="card auth-card success-card">
        <span class="brand-mark auth-mark success-mark"><i data-lucide="circle-check-big"></i></span>
        <h1>Pengajuan terkirim</h1>
        <p class="muted">{{ $serviceRequest->serviceType?->name }} atas nama <strong>{{ $serviceRequest->applicant_name }}</strong> sudah masuk ke kantor desa.</p>

        <div class="code-card">
            <small>Kode pengajuan</small>
            <strong class="tabular">{{ $serviceRequest->request_code }}</strong>
            <span>Simpan kode ini. Bersama NIK, kode ini dipakai untuk mengecek status dan mengunduh surat.</span>
        </div>

        <ol class="next-steps">
            <li><strong>Kode dikirim ke WhatsApp</strong><span>Nomor {{ $serviceRequest->phone }} menerima kode ini dan setiap perubahan status.</span></li>
            <li><strong>Petugas memeriksa berkas</strong><span>Bila ada kekurangan, Anda diberi tahu apa yang perlu dilengkapi.</span></li>
            <li><strong>Surat terbit</strong><span>Unduh dari halaman cek status, atau terima langsung lewat WhatsApp.</span></li>
        </ol>

        <div class="form-actions">
            <a class="btn" href="{{ route('status.form') }}"><i data-lucide="search-check"></i> Cek status pengajuan</a>
            <a class="btn ghost" href="{{ route('home') }}">Ke beranda</a>
        </div>
    </div>
</div>
@endsection
