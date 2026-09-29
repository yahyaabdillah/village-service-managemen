@extends('layouts.admin', ['title' => 'Pengajuan'])
@section('content')
<div class="page-head">
    <div>
        <h1>Pengajuan surat</h1>
        <p class="muted">Tinjau berkas warga, terbitkan dokumen, dan pantau setiap pengajuan dari satu tempat.</p>
    </div>
</div>

<div class="status-strip" aria-label="Ringkasan status">
    @foreach(['submitted' => 'Perlu diperiksa', 'verified' => 'Siap diterbitkan', 'processing' => 'Sedang diproses', 'completed' => 'Selesai', 'rejected' => 'Ditolak'] as $key => $label)
        <a class="status-strip-item {{ request('status') === $key ? 'active' : '' }}" href="{{ route('admin.service-requests.index', array_filter(['status' => $key, 'q' => request('q'), 'service_type_id' => request('service_type_id')])) }}">
            <span class="badge {{ \App\Models\ServiceRequest::statusTone($key) }} plain">{{ $label }}</span>
            <strong class="tabular">{{ number_format($counts[$key] ?? 0, 0, ',', '.') }}</strong>
        </a>
    @endforeach
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
        <button class="btn secondary" type="submit"><i data-lucide="list-filter"></i> Terapkan</button>
        @if(request()->hasAny(['q', 'status', 'service_type_id']))<a class="btn ghost" href="{{ route('admin.service-requests.index') }}">Reset</a>@endif
    </div>
</form>

<div class="card table-card">
    <div class="table-wrap">
        <table class="request-table">
            <thead><tr><th>Pengajuan</th><th>Pemohon</th><th>Layanan</th><th>Status</th><th>Dokumen</th><th>Diperbarui</th><th class="action-cell"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
            @forelse($requests as $serviceRequest)
                @php
                    $hasDocument = $serviceRequest->generated_documents_count > 0 || $serviceRequest->uploaded_document_path;
                    $needsAction = in_array($serviceRequest->status, ['submitted', 'verified', 'processing'], true);
                @endphp
                <tr>
                    <td><a class="request-code" href="{{ route('admin.service-requests.show', $serviceRequest) }}">{{ $serviceRequest->request_code }}</a><small>{{ $serviceRequest->created_at->translatedFormat('d M Y, H:i') }}</small></td>
                    <td><span class="cell-title">{{ $serviceRequest->applicant_name }}</span><small class="cell-sub tabular">{{ \Illuminate\Support\Str::mask($serviceRequest->nik, '•', 4, max(0, strlen($serviceRequest->nik) - 8)) }}</small></td>
                    <td>{{ $serviceRequest->serviceType?->name ?? '—' }}</td>
                    <td><span class="badge {{ \App\Models\ServiceRequest::statusTone($serviceRequest->status) }}">{{ $serviceRequest->publicStatusLabel() }}</span></td>
                    <td><span class="document-state {{ $hasDocument ? 'ready' : '' }}"><i data-lucide="{{ $hasDocument ? 'file-check-2' : 'file-clock' }}"></i>{{ $hasDocument ? ($serviceRequest->document_source === 'manual' ? 'Unggahan manual' : 'Terbit') : 'Belum ada' }}</span></td>
                    <td><span title="{{ $serviceRequest->updated_at->translatedFormat('d M Y, H:i') }}">{{ $serviceRequest->updated_at->diffForHumans() }}</span></td>
                    <td class="action-cell"><a class="btn {{ $needsAction ? '' : 'secondary' }} small" href="{{ route('admin.service-requests.show', $serviceRequest) }}">{{ $needsAction ? 'Proses' : 'Lihat' }} <i data-lucide="arrow-right"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><span class="empty-icon"><i data-lucide="inbox"></i></span><strong>{{ request()->hasAny(['q', 'status', 'service_type_id']) ? 'Tidak ada pengajuan yang cocok' : 'Belum ada pengajuan' }}</strong><span>{{ request()->hasAny(['q', 'status', 'service_type_id']) ? 'Ubah filter atau hapus pencarian.' : 'Pengajuan yang dikirim warga dari situs akan tampil di sini.' }}</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())<div class="pagination-wrap">{{ $requests->links() }}</div>@endif
</div>
@endsection
