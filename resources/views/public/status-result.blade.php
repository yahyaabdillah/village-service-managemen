@extends('layouts.app')
@section('content')
@if($serviceRequest)
    <div class="card status-result">
        <span class="eyebrow">Hasil pencarian</span>
        <h1>Status Pengajuan</h1>
        <div class="status-summary">
            <div><small>Kode pengajuan</small><strong>{{ $serviceRequest->request_code }}</strong></div>
            <div><small>Layanan</small><strong>{{ $serviceRequest->serviceType->name }}</strong></div>
            <div><small>Status</small><span class="badge">{{ $serviceRequest->publicStatusLabel() }}</span></div>
        </div>

        @if($documentReady)
            <div class="download-ready" role="status">
                <span class="download-ready-icon"><i data-lucide="file-check-2"></i></span>
                <div><strong>Dokumen siap diunduh</strong><p>Dokumen telah diterbitkan dan tersimpan dengan aman.</p></div>
                <form method="POST" action="{{ route('documents.download.authorize', $serviceRequest) }}">
                    @csrf
                    <input type="hidden" name="request_code" value="{{ $serviceRequest->request_code }}">
                    <input type="hidden" name="nik" value="{{ $serviceRequest->nik }}">
                    <button class="btn" type="submit"><i data-lucide="download"></i> Unduh Dokumen</button>
                </form>
            </div>
        @endif

        <h2>Riwayat Proses</h2>
        <ol class="status-timeline">
            @foreach($serviceRequest->publicStatusHistories as $history)
                <li><span></span><div><strong>{{ \App\Models\ServiceRequest::statuses()[$history->to_status] ?? $history->to_status }}</strong><p>{{ $history->note }}</p><small>{{ $history->created_at->translatedFormat('d M Y, H:i') }}</small></div></li>
            @endforeach
        </ol>
    </div>
@else
    <div class="card empty-state"><span class="empty-icon"><i data-lucide="search-x"></i></span><h1>Pengajuan tidak ditemukan</h1><p>Periksa kembali kode pengajuan dan NIK.</p></div>
@endif
@endsection
