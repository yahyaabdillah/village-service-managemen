@extends('layouts.admin')
@section('content')
<div class="page-head">
    <div>
        <span class="eyebrow">Pelayanan warga</span>
        <h1>Pengajuan Masuk</h1>
        <p class="muted">Tinjau berkas, terbitkan dokumen, dan pantau progres dari satu tempat.</p>
    </div>
</div>

<form class="card request-filters" method="GET" role="search">
    <div class="filter-search">
        <label for="request-search">Cari pengajuan</label>
        <div class="input-icon"><i data-lucide="search"></i><input id="request-search" name="q" value="{{ request('q') }}" placeholder="Kode, nama, atau NIK"></div>
    </div>
    <div>
        <label for="request-status">Status</label>
        <select id="request-status" name="status">
            <option value="">Semua status</option>
            @foreach(\App\Models\ServiceRequest::statuses() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="request-service">Layanan</label>
        <select id="request-service" name="service_type_id">
            <option value="">Semua layanan</option>
            @foreach($serviceTypes as $serviceType)
                <option value="{{ $serviceType->id }}" @selected((string) request('service_type_id') === (string) $serviceType->id)>{{ $serviceType->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="filter-actions">
        <button class="btn" type="submit"><i data-lucide="list-filter"></i> Terapkan</button>
        @if(request()->hasAny(['q', 'status', 'service_type_id']))<a class="btn ghost" href="{{ route('admin.service-requests.index') }}">Reset</a>@endif
    </div>
</form>

<div class="card table-card">
    <div class="table-scroll">
        <table class="request-table">
            <thead><tr><th>Pengajuan</th><th>Pemohon</th><th>Layanan</th><th>Status</th><th>Dokumen</th><th>Diperbarui</th><th class="action-cell">Aksi</th></tr></thead>
            <tbody>
            @forelse($requests as $serviceRequest)
                @php
                    $hasDocument = $serviceRequest->generated_documents_count > 0 || $serviceRequest->generated_document_path || $serviceRequest->uploaded_document_path;
                    $statusClass = match($serviceRequest->status) { 'completed' => 'success', 'rejected', 'cancelled' => 'danger', 'submitted' => 'warning', default => '' };
                @endphp
                <tr>
                    <td><a class="request-code" href="{{ route('admin.service-requests.show', $serviceRequest) }}">{{ $serviceRequest->request_code }}</a><small>{{ $serviceRequest->created_at->translatedFormat('d M Y, H:i') }}</small></td>
                    <td><strong>{{ $serviceRequest->applicant_name }}</strong><small>{{ \Illuminate\Support\Str::mask($serviceRequest->nik, '*', 4, max(0, strlen($serviceRequest->nik) - 8)) }}</small></td>
                    <td>{{ $serviceRequest->serviceType?->name }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ $serviceRequest->publicStatusLabel() }}</span></td>
                    <td><span class="document-state {{ $hasDocument ? 'ready' : '' }}"><i data-lucide="{{ $hasDocument ? 'file-check-2' : 'file-clock' }}"></i>{{ $hasDocument ? ($serviceRequest->document_source === 'manual' ? 'Manual' : 'Siap') : 'Belum dibuat' }}</span></td>
                    <td><span title="{{ $serviceRequest->updated_at->translatedFormat('d M Y, H:i') }}">{{ $serviceRequest->updated_at->diffForHumans() }}</span></td>
                    <td class="action-cell"><a class="btn small {{ in_array($serviceRequest->status, ['verified', 'processing']) && ! $hasDocument ? '' : 'light' }}" href="{{ route('admin.service-requests.show', $serviceRequest) }}">{{ in_array($serviceRequest->status, ['verified', 'processing']) && ! $hasDocument ? 'Terbitkan' : 'Detail' }} <i data-lucide="arrow-right"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><span class="empty-icon"><i data-lucide="inbox"></i></span><strong>Tidak ada pengajuan</strong><span>Coba ubah filter atau tunggu pengajuan baru dari warga.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $requests->links() }}</div>
</div>
@endsection
