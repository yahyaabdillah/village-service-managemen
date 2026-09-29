@extends('layouts.admin', ['title' => $serviceRequest->request_code])
@section('content')
@php
    $activeDocument = $serviceRequest->activeDocument();
    $status = $serviceRequest->status;
    $isOpen = ! in_array($status, ['completed', 'rejected', 'cancelled'], true);
    $canVerify = auth()->user()->can('service-requests.verify');
    $canProcess = auth()->user()->can('service-requests.process');
    $canReject = auth()->user()->can('service-requests.reject');
    $canComplete = auth()->user()->can('service-requests.complete');
    $canGenerate = auth()->user()->can('service-requests.generate-document');
    $canUpload = auth()->user()->can('service-requests.upload-document');
    $canSend = auth()->user()->can('service-requests.send-whatsapp');
    $formatValue = function ($value) {
        if ($value === null || $value === '') return '—';
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return \Illuminate\Support\Carbon::parse($value)->translatedFormat('d F Y');
        if (is_string($value) && str_starts_with($value, '[')) { $decoded = json_decode($value, true); if (is_array($decoded)) return implode(', ', $decoded); }
        return $value;
    };
@endphp
<div class="page-head request-detail-head">
    <div>
        <a class="back-link" href="{{ route('admin.service-requests.index') }}"><i data-lucide="arrow-left"></i> Pengajuan</a>
        <h1>{{ $serviceRequest->applicant_name }}</h1>
        <p class="muted"><strong class="tabular">{{ $serviceRequest->request_code }}</strong> · {{ $serviceRequest->serviceType?->name ?? 'Layanan sudah dihapus' }} · masuk {{ $serviceRequest->submitted_at?->translatedFormat('d M Y, H:i') }}</p>
    </div>
    <div class="actions">
        <span class="badge {{ \App\Models\ServiceRequest::statusTone($status) }}">{{ $serviceRequest->publicStatusLabel() }}</span>
    </div>
</div>

<div class="request-detail-grid">
    <div>
        <section class="card detail-section">
            <div class="section-compact-head"><div><small>Pemohon</small><h2>Data pemohon</h2></div><span class="avatar large">{{ strtoupper(substr($serviceRequest->applicant_name, 0, 1)) }}</span></div>
            <dl class="detail-list">
                <div><dt>Nama lengkap</dt><dd>{{ $serviceRequest->applicant_name }}</dd></div>
                <div><dt>NIK</dt><dd class="tabular">{{ $serviceRequest->nik }}</dd></div>
                <div><dt>Nomor HP</dt><dd class="tabular">{{ $serviceRequest->phone }}</dd></div>
                <div><dt>Dusun · RT/RW</dt><dd>{{ $serviceRequest->hamlet ?: '—' }} · RT {{ $serviceRequest->rt ?: '-' }}/RW {{ $serviceRequest->rw ?: '-' }}</dd></div>
                <div class="wide"><dt>Alamat</dt><dd>{{ $serviceRequest->address }}</dd></div>
            </dl>
            @if($serviceRequest->fieldValues->isNotEmpty())
                <h3 class="detail-subhead">Keterangan {{ $serviceRequest->serviceType?->name }}</h3>
                <dl class="detail-list">
                    @foreach($serviceRequest->fieldValues as $field)
                        <div @class(['wide' => strlen((string) $field->value) > 60])><dt>{{ $field->label }}</dt><dd>{{ $formatValue($field->value) }}</dd></div>
                    @endforeach
                </dl>
            @endif
        </section>

        <section class="card detail-section">
            <div class="section-compact-head"><div><small>Lampiran</small><h2>Berkas persyaratan</h2></div><span class="count-pill">{{ $serviceRequest->files->count() }} berkas</span></div>
            <div class="file-list">
                @forelse($serviceRequest->files as $file)
                    @php
                        $previewable = in_array($file->mime_type, ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
                            || in_array(strtolower($file->file_type), ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true);
                    @endphp
                    <div class="file-row">
                        <span class="file-icon"><i data-lucide="{{ $previewable ? ($file->file_type === 'pdf' ? 'file-text' : 'image') : 'file' }}"></i></span>
                        <div class="file-info"><strong>{{ $file->original_name }}</strong><small>{{ strtoupper($file->file_type) }} · {{ number_format($file->file_size / 1024, 0, ',', '.') }} KB</small></div>
                        @if($previewable)
                            <a class="btn secondary small file-preview-action" href="{{ route('admin.service-requests.files.preview', [$serviceRequest, $file]) }}" target="_blank" rel="noopener"><i data-lucide="eye"></i> Lihat</a>
                        @else
                            <a class="btn secondary small file-preview-action" href="{{ route('admin.service-requests.files.download', [$serviceRequest, $file]) }}"><i data-lucide="download"></i> Unduh</a>
                        @endif
                    </div>
                @empty
                    <div class="empty-inline"><i data-lucide="file-x"></i><span>Warga tidak melampirkan berkas.</span></div>
                @endforelse
            </div>
        </section>

        @if($serviceRequest->generatedDocuments->isNotEmpty())
            <section class="card detail-section">
                <div class="section-compact-head"><div><small>Hasil</small><h2>Riwayat dokumen</h2></div><span class="count-pill">{{ $serviceRequest->generatedDocuments->count() }} versi</span></div>
                <div class="table-wrap">
                    <table class="compact-table">
                        <thead><tr><th>Dokumen</th><th>Sumber</th><th>Status</th><th>Dibuat</th><th class="action-cell"><span class="sr-only">Aksi</span></th></tr></thead>
                        <tbody>
                        @foreach($serviceRequest->generatedDocuments->sortByDesc('generated_at') as $document)
                            <tr>
                                <td><strong>{{ $document->original_file_name ?: basename($document->file_path) }}</strong><small>{{ strtoupper($document->file_type ?: 'FILE') }} · {{ number_format(($document->file_size ?: 0) / 1024, 0, ',', '.') }} KB</small></td>
                                <td>{{ $document->source === 'generated' ? 'Template v'.($document->documentTemplate?->version ?: 1) : 'Unggahan manual' }}</td>
                                <td><span class="badge {{ $document->is_active ? 'success' : 'muted' }}">{{ $document->is_active ? 'Berlaku' : 'Digantikan' }}</span></td>
                                <td>{{ $document->generated_at?->translatedFormat('d M Y, H:i') ?: '—' }}</td>
                                <td class="action-cell"><a class="btn ghost small" href="{{ route('admin.service-requests.documents.download', [$serviceRequest, $document]) }}"><i data-lucide="download"></i> Unduh</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    <aside>
        <section class="card document-panel">
            <div class="section-compact-head"><div><small>Langkah berikutnya</small><h2>{{ $serviceRequest->publicStatusLabel() }}</h2></div></div>
            <p class="muted status-hint">{{ \App\Models\ServiceRequest::statusHint($status) }}</p>

            @if($status === 'submitted' && $canVerify)
                <form method="POST" action="{{ route('admin.service-requests.verify', $serviceRequest) }}" class="action-stack">
                    @csrf @method('PATCH')
                    <label for="verify-note">Catatan untuk warga <span class="muted">(opsional)</span></label>
                    <input id="verify-note" name="public_note" maxlength="500" placeholder="Contoh: berkas lengkap, surat diproses hari ini">
                    <button class="btn full" type="submit"><i data-lucide="check-check"></i> Verifikasi berkas</button>
                </form>
            @endif

            @if(in_array($status, ['verified', 'processing'], true))
                @if($canGenerate)
                    @if($defaultTemplate)
                        <form method="POST" action="{{ route('admin.service-requests.publish', $serviceRequest) }}" class="action-stack">
                            @csrf @method('PATCH')
                            <label for="letter-number">Nomor surat<span class="req">*</span></label>
                            <input id="letter-number" name="letter_number" value="{{ old('letter_number', $serviceRequest->letter_number) }}" placeholder="470/001/DS/{{ now()->year }}" required @error('letter_number') aria-invalid="true" @enderror>
                            @error('letter_number')<p class="field-error">{{ $message }}</p>@else<p class="field-help">Dicetak pada surat. Harus unik untuk setiap surat yang terbit.</p>@enderror
                            <button class="btn full" type="submit"><i data-lucide="badge-check"></i> Setujui & Terbitkan</button>
                            <p class="field-help">Surat dibuat dari template <strong>{{ $defaultTemplate->name }}</strong> dan pengajuan langsung selesai.</p>
                        </form>
                    @else
                        <div class="notice info"><i data-lucide="file-warning"></i><div><strong>Belum ada template aktif</strong> untuk layanan ini. @can('service-types.update')<a href="{{ route('admin.service-types.edit', [$serviceRequest->service_type_id, 'tab' => 'template']) }}">Atur template</a> atau @endcan unggah dokumen yang ditandatangani secara manual di bawah.</div></div>
                    @endif
                @endif

                @if($status === 'verified' && $canProcess)
                    <form method="POST" action="{{ route('admin.service-requests.process', $serviceRequest) }}" class="action-stack">
                        @csrf @method('PATCH')
                        <button class="btn secondary full" type="submit"><i data-lucide="loader"></i> Tandai sedang diproses</button>
                    </form>
                @endif

                @if($canUpload)
                    <details class="manual-fallback" @if($errors->has('document')) open @endif>
                        <summary>Unggah dokumen yang ditandatangani manual</summary>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.service-requests.manual-document', $serviceRequest) }}">
                            @csrf
                            @include('components.dropzone-file', [
                                'name' => 'document',
                                'id' => 'final-document',
                                'label' => 'Dokumen final',
                                'accept' => '.pdf,.docx,.jpg,.jpeg,.png',
                                'required' => true,
                                'icon' => 'file-check-2',
                                'help' => 'PDF, DOCX, atau foto hasil pindaian. Maksimal 5 MB.',
                            ])
                            <button class="btn secondary full" type="submit"><i data-lucide="upload"></i> Simpan dokumen</button>
                        </form>
                    </details>
                @endif

                @if($activeDocument && $canComplete)
                    <form method="POST" action="{{ route('admin.service-requests.complete', $serviceRequest) }}" class="action-stack">
                        @csrf @method('PATCH')
                        <button class="btn full" type="submit"><i data-lucide="badge-check"></i> Tandai selesai</button>
                        <p class="field-help">Dokumen yang berlaku akan dapat diunduh warga.</p>
                    </form>
                @endif
            @endif

            @if($activeDocument)
                <div class="document-ready">
                    <dl class="document-meta">
                        <div><dt>Dokumen berlaku</dt><dd>{{ $activeDocument->source === 'generated' ? 'Dari template' : 'Unggahan manual' }}</dd></div>
                        <div><dt>Nomor surat</dt><dd>{{ $serviceRequest->letter_number ?: '—' }}</dd></div>
                        <div><dt>Dibuat</dt><dd>{{ $activeDocument->generated_at?->translatedFormat('d M Y, H:i') }}</dd></div>
                    </dl>
                    <a class="btn secondary full" href="{{ route('admin.service-requests.documents.download', [$serviceRequest, $activeDocument]) }}"><i data-lucide="download"></i> Unduh dokumen</a>
                    @if($canSend && $activeDocument->status === 'valid' && filled($serviceRequest->phone))
                        <form method="POST" action="{{ route('admin.service-requests.documents.send-whatsapp', $serviceRequest) }}" data-confirm="Kirim dokumen ke WhatsApp {{ $serviceRequest->phone }}?" data-confirm-text="Warga akan menerima berkas ini sebagai lampiran pesan." data-confirm-label="Ya, kirim" data-confirm-tone="">
                            @csrf
                            <button class="btn secondary full" type="submit"><i data-lucide="send"></i> Kirim ke WhatsApp warga</button>
                        </form>
                        @unless($whatsappEnabled)<p class="field-help">Pengiriman WhatsApp belum diaktifkan pada sistem ini; hubungi administrator.</p>@endunless
                    @endif
                </div>
            @endif

            @if($status === 'completed' && $canGenerate && $availableTemplates->isNotEmpty())
                <details class="manual-fallback">
                    <summary>Buat ulang dokumen dari template</summary>
                    <form method="POST" action="{{ route('admin.service-requests.generate-document', $serviceRequest) }}" class="action-stack">
                        @csrf
                        <label for="regenerate-template">Template</label>
                        <select id="regenerate-template" name="document_template_id" required>@foreach($availableTemplates as $candidate)<option value="{{ $candidate->id }}" @selected($candidate->is_default)>{{ $candidate->name }} · v{{ $candidate->version }}{{ $candidate->is_default ? ' · utama' : '' }}</option>@endforeach</select>
                        <label for="regenerate-number">Nomor surat</label><input id="regenerate-number" name="letter_number" value="{{ old('letter_number', $serviceRequest->letter_number) }}" required>
                        <label for="regenerate-reason">Alasan pembuatan ulang</label><textarea id="regenerate-reason" name="reason" rows="2" required placeholder="Contoh: memperbaiki penulisan alamat"></textarea>
                        <button class="btn secondary full" type="submit"><i data-lucide="refresh-cw"></i> Buat ulang</button>
                    </form>
                </details>
            @endif

            @if($isOpen && $canReject)
                <div class="decision-panel">
                    <details @if($errors->has('rejection_reason')) open @endif>
                        <summary>Tolak pengajuan ini</summary>
                        <form method="POST" action="{{ route('admin.service-requests.reject', $serviceRequest) }}" class="action-stack" data-confirm="Tolak pengajuan {{ $serviceRequest->request_code }}?" data-confirm-text="Alasan penolakan dikirim ke warga dan pengajuan tidak dapat dibuka kembali." data-confirm-label="Ya, tolak">
                            @csrf @method('PATCH')
                            <label for="rejection-reason">Alasan penolakan<span class="req">*</span></label>
                            <textarea id="rejection-reason" name="rejection_reason" rows="3" required minlength="10" placeholder="Jelaskan apa yang kurang dan apa yang perlu dilakukan warga" @error('rejection_reason') aria-invalid="true" @enderror>{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')<p class="field-error">{{ $message }}</p>@else<p class="field-help">Ditampilkan kepada warga pada halaman cek status dan pesan WhatsApp.</p>@enderror
                            <button class="btn danger full" type="submit"><i data-lucide="x-circle"></i> Tolak pengajuan</button>
                        </form>
                    </details>
                </div>
            @endif

            @if($status === 'rejected' && $serviceRequest->rejection_reason)
                <div class="notice errors"><i data-lucide="x-circle"></i><div><strong>Alasan penolakan</strong><p>{{ $serviceRequest->rejection_reason }}</p></div></div>
            @endif
        </section>

        <section class="card detail-section">
            <div class="section-compact-head"><div><small>Riwayat</small><h2>Riwayat proses</h2></div></div>
            <ol class="status-timeline compact">
                @foreach($serviceRequest->statusHistories as $history)
                    <li>
                        <span></span>
                        <div>
                            <strong>{{ \App\Models\ServiceRequest::statuses()[$history->to_status] ?? $history->to_status }}</strong>
                            @if($history->note)<p>{{ $history->note }}</p>@endif
                            <small>{{ $history->created_at->translatedFormat('d M Y, H:i') }} · {{ $history->changedBy?->name ?? 'Sistem' }}</small>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </aside>
</div>
@endsection
