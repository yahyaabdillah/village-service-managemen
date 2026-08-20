@extends('layouts.admin')
@section('content')
@php
    $latestDocument = $serviceRequest->generatedDocuments->where('is_active', true)->sortByDesc('generated_at')->first()
        ?: $serviceRequest->generatedDocuments->sortByDesc('generated_at')->first();
    $hasDocument = $latestDocument || $serviceRequest->generated_document_path || $serviceRequest->uploaded_document_path;
    $statusClass = match($serviceRequest->status) { 'completed' => 'success', 'rejected', 'cancelled' => 'danger', 'submitted' => 'warning', default => '' };
@endphp
<div class="page-head request-detail-head">
    <div>
        <a class="back-link" href="{{ route('admin.service-requests.index') }}"><i data-lucide="arrow-left"></i> Kembali ke Pengajuan</a>
        <h1>{{ $serviceRequest->request_code }}</h1>
        <p class="muted">{{ $serviceRequest->serviceType->name }} · Masuk {{ $serviceRequest->submitted_at?->translatedFormat('d M Y, H:i') }}</p>
    </div>
    <span class="badge {{ $statusClass }}">{{ $serviceRequest->publicStatusLabel() }}</span>
</div>

<div class="request-detail-grid">
    <div>
        <section class="card detail-section">
            <div class="section-compact-head"><div><small>Data pengajuan</small><h2>Informasi Pemohon</h2></div><span class="avatar large">{{ strtoupper(substr($serviceRequest->applicant_name, 0, 1)) }}</span></div>
            <dl class="detail-list">
                <div><dt>Nama lengkap</dt><dd>{{ $serviceRequest->applicant_name }}</dd></div>
                <div><dt>NIK</dt><dd>{{ $serviceRequest->nik }}</dd></div>
                <div><dt>Nomor HP</dt><dd>{{ $serviceRequest->phone }}</dd></div>
                <div class="wide"><dt>Alamat</dt><dd>{{ $serviceRequest->address }} @if($serviceRequest->hamlet)· {{ $serviceRequest->hamlet }}@endif @if($serviceRequest->rt || $serviceRequest->rw)· RT {{ $serviceRequest->rt ?: '-' }}/RW {{ $serviceRequest->rw ?: '-' }}@endif</dd></div>
                @foreach($serviceRequest->fieldValues as $field)
                    <div class="wide"><dt>{{ $field->label }}</dt><dd>{{ $field->value ?: '-' }}</dd></div>
                @endforeach
            </dl>
        </section>

        <section class="card detail-section">
            <div class="section-compact-head"><div><small>Histori hasil</small><h2>Artefak Dokumen</h2></div><span class="count-pill">{{ $serviceRequest->generatedDocuments->count() }} file</span></div>
            <div class="table-wrap">
                <table class="compact-table">
                    <thead><tr><th>File</th><th>Sumber</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse($serviceRequest->generatedDocuments->sortByDesc('generated_at') as $document)
                        <tr>
                            <td><strong>{{ $document->original_file_name ?: basename($document->file_path) }}</strong><small>{{ strtoupper($document->file_type ?: 'FILE') }} · {{ number_format(($document->file_size ?: 0) / 1024, 0) }} KB</small></td>
                            <td>{{ $document->source === 'generated' ? 'Template v'.($document->documentTemplate?->version ?: 1) : 'Manual' }}</td>
                            <td><span class="badge {{ $document->is_active ? 'success' : '' }}">{{ $document->is_active ? 'Aktif' : 'Digantikan' }}</span></td>
                            <td>{{ $document->generated_at?->translatedFormat('d M Y, H:i') ?: '-' }}</td>
                            <td><a class="btn light small" href="{{ route('admin.service-requests.documents.download', [$serviceRequest, $document]) }}"><i data-lucide="download"></i> Unduh</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-inline"><i data-lucide="file-clock"></i><span>Belum ada artefak.</span></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card detail-section">
            <div class="section-compact-head"><div><small>Kelengkapan</small><h2>Berkas Persyaratan</h2></div><span class="count-pill">{{ $serviceRequest->files->count() }} berkas</span></div>
            <div class="file-list">
                @forelse($serviceRequest->files as $file)
                    @php
                        $previewable = in_array($file->mime_type, ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
                            || in_array(strtolower($file->file_type), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                    @endphp
                    <div class="file-row">
                        <span class="file-icon"><i data-lucide="{{ $previewable ? ($file->file_type === 'pdf' ? 'file-text' : 'image') : 'file' }}"></i></span>
                        <div class="file-info"><strong>{{ $file->original_name }}</strong><small>{{ strtoupper($file->file_type) }} · {{ number_format($file->file_size / 1024, 0) }} KB</small></div>
                        @if($previewable)
                            <a class="btn light small file-preview-action" href="{{ route('admin.service-requests.files.preview', [$serviceRequest, $file]) }}" target="_blank" rel="noopener" aria-label="Lihat berkas {{ $file->original_name }}">
                                <i data-lucide="eye"></i> Lihat
                            </a>
                        @else
                            <a class="btn light small file-preview-action" href="{{ route('admin.service-requests.files.download', [$serviceRequest, $file]) }}" aria-label="Unduh berkas {{ $file->original_name }}">
                                <i data-lucide="download"></i> Unduh
                            </a>
                        @endif
                    </div>
                @empty
                    <div class="empty-inline"><i data-lucide="file-x"></i><span>Tidak ada berkas persyaratan.</span></div>
                @endforelse
            </div>
        </section>
    </div>

    <aside>
        <section class="card document-panel">
            <div class="section-compact-head"><div><small>Hasil layanan</small><h2>Dokumen Final</h2></div><span class="document-state {{ $hasDocument ? 'ready' : '' }}"><i data-lucide="{{ $hasDocument ? 'file-check-2' : 'file-clock' }}"></i>{{ $hasDocument ? 'Siap' : 'Belum dibuat' }}</span></div>

            @if($latestDocument)
                <dl class="document-meta"><div><dt>Sumber</dt><dd>{{ $latestDocument->source === 'generated' ? 'Otomatis' : 'Manual' }}</dd></div><div><dt>Nomor surat</dt><dd>{{ $serviceRequest->letter_number ?: '-' }}</dd></div><div><dt>Dibuat</dt><dd>{{ $latestDocument->generated_at?->translatedFormat('d M Y, H:i') }}</dd></div></dl>
                <a class="btn full" href="{{ route('admin.service-requests.documents.download', [$serviceRequest, $latestDocument]) }}"><i data-lucide="download"></i> Unduh Dokumen Aktif</a>
                @if($latestDocument->is_active && $latestDocument->status === 'valid' && filled($serviceRequest->phone))
                    <form method="POST" action="{{ route('admin.service-requests.documents.send-whatsapp', $serviceRequest) }}" onsubmit="return confirm('Kirim dokumen final ke WhatsApp {{ $serviceRequest->phone }}?')">
                        @csrf
                        <button class="btn light full" type="submit"><i data-lucide="send"></i> Kirim Dokumen via WhatsApp</button>
                    </form>
                @endif
            @endif

            @if(in_array($serviceRequest->status, ['verified', 'processing']) && ! $latestDocument)
                <p class="muted">Setujui pengajuan untuk membuat PDF dari template layanan secara otomatis.</p>
                <form method="POST" action="{{ route('admin.service-requests.publish', $serviceRequest) }}">
                    @csrf @method('PATCH')
                    <label for="letter-number">Nomor surat</label>
                    <input id="letter-number" name="letter_number" value="{{ old('letter_number', $serviceRequest->letter_number) }}" placeholder="Contoh: 470/001/DS/2026" required>
                    <button class="btn full" type="submit"><i data-lucide="badge-check"></i> Setujui & Terbitkan</button>
                </form>
            @elseif($serviceRequest->status === 'submitted')
                <p class="muted">Periksa data dan berkas sebelum melanjutkan.</p>
                <form method="POST" action="{{ route('admin.service-requests.verify', $serviceRequest) }}">@csrf @method('PATCH')<button class="btn full" type="submit"><i data-lucide="check-check"></i> Verifikasi Berkas</button></form>
            @endif

            @if($latestDocument && ! in_array($serviceRequest->status, ['rejected', 'cancelled']))
                <details class="manual-fallback">
                    <summary>Generate ulang dari template</summary>
                    <form method="POST" action="{{ route('admin.service-requests.generate-document', $serviceRequest) }}">
                        @csrf
                        <label for="regenerate-template">Template aktif</label>
                        <select id="regenerate-template" name="document_template_id" required>@foreach($availableTemplates as $candidate)<option value="{{ $candidate->id }}" @selected($candidate->is_default)>{{ $candidate->name }} · v{{ $candidate->version }}{{ $candidate->is_default ? ' · default' : '' }}</option>@endforeach</select>
                        <label for="regenerate-number">Nomor surat</label><input id="regenerate-number" name="letter_number" value="{{ old('letter_number', $serviceRequest->letter_number) }}" required>
                        <label for="regenerate-reason">Alasan generate ulang</label><textarea id="regenerate-reason" name="reason" rows="2" required placeholder="Contoh: memperbaiki mapping alamat"></textarea>
                        <button class="btn light full" type="submit"><i data-lucide="refresh-cw"></i> Generate Ulang</button>
                    </form>
                </details>
            @endif

            @if(! $hasDocument && ! in_array($serviceRequest->status, ['completed', 'rejected', 'cancelled']))
                <details class="manual-fallback">
                    <summary>Upload manual (fallback)</summary>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.service-requests.manual-document', $serviceRequest) }}">
                        @csrf
                        @include('components.dropzone-file', [
                            'name' => 'document',
                            'id' => 'final-document',
                            'label' => 'Pilih dokumen final',
                            'accept' => '.pdf,.docx,.jpg,.jpeg,.png,.mp4,.mov,.webm',
                            'required' => true,
                            'icon' => 'FILE',
                            'help' => 'PDF, DOCX, foto, atau video. Maksimal 5 MB.',
                        ])
                        <button class="btn light full" type="submit">Upload Manual</button>
                    </form>
                </details>
            @endif
        </section>

        <section class="card detail-section">
            <div class="section-compact-head"><div><small>Jejak proses</small><h2>Timeline</h2></div></div>
            <ol class="status-timeline compact">
                @foreach($serviceRequest->statusHistories as $history)
                    <li><span></span><div><strong>{{ \App\Models\ServiceRequest::statuses()[$history->to_status] ?? $history->to_status }}</strong><p>{{ $history->note }}</p><small>{{ $history->created_at->translatedFormat('d M Y, H:i') }}</small></div></li>
                @endforeach
            </ol>
        </section>
    </aside>
</div>
@endsection
