@extends('layouts.app', ['title' => 'Status Pengajuan'])
@section('content')
@if($serviceRequest)
    <div class="card status-result">
        <div class="section-compact-head">
            <div><small>Pengajuan</small><h2 class="status-result-title">{{ $serviceRequest->serviceType?->name ?? 'Layanan' }}</h2></div>
            <span class="badge {{ \App\Models\ServiceRequest::statusTone($serviceRequest->status) }}">{{ $serviceRequest->publicStatusLabel() }}</span>
        </div>
        <div class="status-summary">
            <div><small>Kode pengajuan</small><strong class="tabular">{{ $serviceRequest->request_code }}</strong></div>
            <div><small>Atas nama</small><strong>{{ $serviceRequest->applicant_name }}</strong></div>
            <div><small>Diajukan</small><strong>{{ $serviceRequest->submitted_at?->translatedFormat('d F Y') }}</strong></div>
        </div>

        @if($documentReady)
            <div class="download-ready" role="status">
                <span class="download-ready-icon"><i data-lucide="file-check-2"></i></span>
                <div><strong>Surat Anda sudah terbit</strong><p>Unduh dan simpan berkasnya. Surat yang sama juga dikirim ke WhatsApp Anda bila nomornya aktif.</p></div>
                <form method="POST" action="{{ route('documents.download.authorize', $serviceRequest) }}">
                    @csrf
                    <input type="hidden" name="request_code" value="{{ $serviceRequest->request_code }}">
                    <input type="hidden" name="nik" value="{{ $serviceRequest->nik }}">
                    <button class="btn" type="submit"><i data-lucide="download"></i> Unduh surat</button>
                </form>
            </div>
        @elseif($serviceRequest->status === 'rejected')
            <div class="notice errors"><i data-lucide="x-circle"></i><div><strong>Pengajuan tidak dapat diproses</strong><p>{{ $serviceRequest->rejection_reason ?: 'Silakan hubungi kantor desa untuk keterangan lebih lanjut.' }}</p><p>Anda dapat mengajukan kembali setelah melengkapi kekurangannya.</p></div></div>
        @elseif(in_array($serviceRequest->status, ['submitted', 'verified', 'processing'], true))
            <div class="notice info"><i data-lucide="clock-3"></i><div><strong>Sedang ditangani petugas desa.</strong> Kami mengirim pesan WhatsApp setiap kali status berubah; halaman ini juga dapat dibuka kembali kapan saja.</div></div>
        @endif

        <h3 class="detail-subhead">Riwayat proses</h3>
        <ol class="status-timeline">
            @foreach($serviceRequest->publicStatusHistories as $history)
                <li><span></span><div><strong>{{ \App\Models\ServiceRequest::statuses()[$history->to_status] ?? $history->to_status }}</strong>@if($history->note)<p>{{ $history->note }}</p>@endif<small>{{ $history->created_at->translatedFormat('d F Y, H:i') }}</small></div></li>
            @endforeach
        </ol>
        <div class="form-actions"><a class="btn secondary" href="{{ route('status.form') }}"><i data-lucide="search"></i> Cek pengajuan lain</a></div>
    </div>
@else
    <div class="auth-shell">
        <div class="card auth-card">
            <span class="brand-mark auth-mark"><i data-lucide="search-x"></i></span>
            <h1>Pengajuan tidak ditemukan</h1>
            <p class="muted">Kode pengajuan dan NIK tidak cocok dengan data kami. Periksa kembali pesan WhatsApp berisi kode Anda, atau pastikan NIK yang dimasukkan sama dengan saat mengajukan.</p>
            <div class="form-actions">
                <a class="btn" href="{{ route('status.form') }}"><i data-lucide="arrow-left"></i> Coba lagi</a>
                <a class="btn ghost" href="{{ route('services.index') }}">Ajukan surat baru</a>
            </div>
        </div>
    </div>
@endif
@endsection
