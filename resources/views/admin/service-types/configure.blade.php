@extends('layouts.admin', ['title' => 'Atur '.$service->name])
@section('content')
@php
    $tabs = [
        'informasi' => ['Informasi', 'info'],
        'isian' => ['Isian formulir', 'list-checks'],
        'berkas' => ['Syarat berkas', 'paperclip'],
        'template' => ['Template surat', 'file-pen-line'],
    ];
    $activeTab = array_key_exists($tab, $tabs) ? $tab : 'informasi';
    $fileTypes = ['pdf' => 'PDF', 'jpg' => 'JPG', 'png' => 'PNG', 'docx' => 'DOCX'];
@endphp
<div class="page-head">
    <div>
        <a class="back-link" href="{{ route('admin.service-types.index') }}"><i data-lucide="arrow-left"></i> Konfigurasi Layanan</a>
        <h1>{{ $service->name }}</h1>
        <p class="muted">{{ $service->description ?: 'Belum ada deskripsi.' }}</p>
    </div>
    <div class="actions">
        @if($service->is_active)<span class="badge success">Aktif untuk warga</span>@else<span class="badge muted">Nonaktif</span>@endif
        <a class="btn secondary" href="{{ route('services.show', $service) }}" target="_blank" rel="noopener"><i data-lucide="external-link"></i> Lihat halaman warga</a>
    </div>
</div>

<div class="tabs" data-tabs>
    <div class="tab-list" role="tablist">
        @foreach($tabs as $key => [$label, $icon])
            <a class="tab {{ $key === $activeTab ? 'active' : '' }}" role="tab" aria-selected="{{ $key === $activeTab ? 'true' : 'false' }}" href="{{ route('admin.service-types.edit', [$service, 'tab' => $key]) }}" data-tab="{{ $key }}">
                <i data-lucide="{{ $icon }}"></i> {{ $label }}
                @if($key === 'isian')<span class="tab-count">{{ $service->fields->count() }}</span>@elseif($key === 'berkas')<span class="tab-count">{{ $service->requirements->count() }}</span>@elseif($key === 'template')<span class="tab-count">{{ $service->templates->count() }}</span>@endif
            </a>
        @endforeach
    </div>

    {{-- Informasi --}}
    <section class="tab-panel {{ $activeTab === 'informasi' ? 'active' : '' }}" data-tab-panel="informasi">
        <form class="card form-card" method="POST" action="{{ route('admin.service-types.update', $service) }}" novalidate>
            @csrf @method('PATCH')
            <div class="field-grid">
                <div class="field"><label for="svc-name">Nama layanan<span class="req">*</span></label><input id="svc-name" name="name" value="{{ old('name', $service->name) }}" required @error('name') aria-invalid="true" @enderror>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="svc-slug">Alamat halaman</label><input id="svc-slug" name="slug" value="{{ old('slug', $service->slug) }}">@error('slug')<p class="field-error">{{ $message }}</p>@else<p class="field-help">{{ url('/layanan') }}/<strong>{{ $service->slug }}</strong></p>@enderror</div>
                <div class="field span-2"><label for="svc-desc">Deskripsi untuk warga</label><textarea id="svc-desc" name="description" rows="3">{{ old('description', $service->description) }}</textarea><p class="field-help">Tampil di daftar layanan dan halaman pengajuan. Jelaskan untuk keperluan apa surat ini biasanya dipakai.</p></div>
                <div class="field"><label for="svc-order">Urutan tampil</label><input id="svc-order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $service->sort_order) }}"></div>
                <div class="field"><input type="hidden" name="is_active" value="0"><label class="check-row" for="svc-active"><input id="svc-active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active))> Dapat diajukan warga</label><p class="field-help">Nonaktifkan sementara bila layanan sedang ditutup.</p></div>
            </div>
            <div class="form-actions"><button class="btn" type="submit"><i data-lucide="check"></i> Simpan informasi</button></div>
        </form>
    </section>

    {{-- Isian formulir --}}
    <section class="tab-panel {{ $activeTab === 'isian' ? 'active' : '' }}" data-tab-panel="isian">
        <div class="card table-card">
            <div class="toolbar table-toolbar">
                <div>
                    <strong>Pertanyaan tambahan untuk warga</strong>
                    <p class="muted">Data pemohon standar (nama, NIK, HP, alamat) selalu ditanyakan. Tambahkan isian khusus surat ini; setiap isian bisa dicetak ke template sebagai variabel.</p>
                </div>
                <button class="btn" type="button" data-dialog-open="#dialog-field-new"><i data-lucide="plus"></i> Tambah isian</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th class="num">#</th><th>Pertanyaan</th><th>Tipe</th><th>Variabel template</th><th>Wajib</th><th>Status</th><th class="action-cell"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @forelse($service->fields as $field)
                            <tr>
                                <td class="num muted">{{ $field->sort_order }}</td>
                                <td><span class="cell-title">{{ $field->label }}</span>@if($field->help_text)<small class="cell-sub">{{ $field->help_text }}</small>@endif</td>
                                <td>{{ $fieldTypes[$field->field_type] ?? $field->field_type }}@if($field->field_type === 'select' && $field->options)<small class="cell-sub">{{ implode(' · ', $field->options) }}</small>@endif</td>
                                <td><code>{{ '{'.'{ '.$field->field_key.' }'.'}' }}</code></td>
                                <td>@if($field->is_required)<span class="badge plain">Wajib</span>@else<span class="muted">Opsional</span>@endif</td>
                                <td>@if($field->is_active)<span class="badge success">Tampil</span>@else<span class="badge muted">Disembunyikan</span>@endif</td>
                                <td class="action-cell">
                                    <div class="row-actions">
                                        <button class="btn ghost icon" type="button" data-dialog-open="#dialog-field-{{ $field->id }}" aria-label="Ubah isian" title="Ubah"><i data-lucide="pencil"></i></button>
                                        <form method="POST" action="{{ route('admin.service-types.fields.destroy', [$service, $field]) }}" data-confirm="Hapus isian &ldquo;{{ $field->label }}&rdquo;?" data-confirm-text="Jawaban pada pengajuan yang sudah masuk tetap tersimpan. Template yang memakai variabel ini perlu diperbarui." data-confirm-label="Ya, hapus">
                                            @csrf @method('DELETE')
                                            <button class="btn ghost icon danger-text" type="submit" aria-label="Hapus isian" title="Hapus"><i data-lucide="trash-2"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><span class="empty-icon"><i data-lucide="list-checks"></i></span><strong>Belum ada isian khusus</strong><span>Warga hanya akan mengisi data pemohon standar. Tambahkan pertanyaan yang dibutuhkan surat ini.</span><button class="btn" type="button" data-dialog-open="#dialog-field-new"><i data-lucide="plus"></i> Tambah isian</button></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.service-types.partials.field-dialog', ['service' => $service, 'field' => null, 'fieldTypes' => $fieldTypes])
        @foreach($service->fields as $field)
            @include('admin.service-types.partials.field-dialog', ['service' => $service, 'field' => $field, 'fieldTypes' => $fieldTypes])
        @endforeach
    </section>

    {{-- Syarat berkas --}}
    <section class="tab-panel {{ $activeTab === 'berkas' ? 'active' : '' }}" data-tab-panel="berkas">
        <div class="card table-card">
            <div class="toolbar table-toolbar">
                <div>
                    <strong>Berkas yang dilampirkan warga</strong>
                    <p class="muted">Setiap berkas diperiksa jenis dan ukurannya saat diunggah, lalu dipindai sebelum disimpan.</p>
                </div>
                <button class="btn" type="button" data-dialog-open="#dialog-req-new"><i data-lucide="plus"></i> Tambah syarat</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th class="num">#</th><th>Berkas</th><th>Jenis diterima</th><th class="num">Maks.</th><th>Wajib</th><th class="action-cell"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @forelse($service->requirements as $requirement)
                            <tr>
                                <td class="num muted">{{ $requirement->sort_order }}</td>
                                <td><span class="cell-title">{{ $requirement->name }}</span>@if($requirement->description)<small class="cell-sub">{{ $requirement->description }}</small>@endif</td>
                                <td>{{ strtoupper(implode(', ', array_diff($requirement->allowed_file_types ?: [], ['jpeg']))) }}</td>
                                <td class="num">{{ round(($requirement->max_file_size_kb ?: 5120) / 1024, 1) }} MB</td>
                                <td>@if($requirement->is_required)<span class="badge plain">Wajib</span>@else<span class="muted">Opsional</span>@endif</td>
                                <td class="action-cell">
                                    <div class="row-actions">
                                        <button class="btn ghost icon" type="button" data-dialog-open="#dialog-req-{{ $requirement->id }}" aria-label="Ubah syarat" title="Ubah"><i data-lucide="pencil"></i></button>
                                        <form method="POST" action="{{ route('admin.service-types.requirements.destroy', [$service, $requirement]) }}" data-confirm="Hapus syarat &ldquo;{{ $requirement->name }}&rdquo;?" data-confirm-label="Ya, hapus">
                                            @csrf @method('DELETE')
                                            <button class="btn ghost icon danger-text" type="submit" aria-label="Hapus syarat" title="Hapus"><i data-lucide="trash-2"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="empty-state"><span class="empty-icon"><i data-lucide="paperclip"></i></span><strong>Belum ada syarat berkas</strong><span>Warga dapat mengajukan tanpa lampiran. Tambahkan berkas yang harus disertakan.</span><button class="btn" type="button" data-dialog-open="#dialog-req-new"><i data-lucide="plus"></i> Tambah syarat</button></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.service-types.partials.requirement-dialog', ['service' => $service, 'requirement' => null, 'fileTypes' => $fileTypes])
        @foreach($service->requirements as $requirement)
            @include('admin.service-types.partials.requirement-dialog', ['service' => $service, 'requirement' => $requirement, 'fileTypes' => $fileTypes])
        @endforeach
    </section>

    {{-- Template surat --}}
    <section class="tab-panel {{ $activeTab === 'template' ? 'active' : '' }}" data-tab-panel="template">
        <div class="card table-card">
            <div class="toolbar table-toolbar">
                <div>
                    <strong>Template yang mencetak surat ini</strong>
                    <p class="muted">Template aktif yang ditandai <em>utama</em> dipakai saat petugas menerbitkan dokumen. Isian formulir di tab sebelumnya tersedia sebagai variabel di dalam template.</p>
                </div>
                @can('document-templates.create')<a class="btn" href="{{ route('admin.document-templates.create', ['service_type_id' => $service->id]) }}"><i data-lucide="plus"></i> Unggah template</a>@endcan
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Template</th><th>Versi</th><th>Status</th><th>Diperbarui</th><th class="action-cell"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @forelse($service->templates as $template)
                            <tr>
                                <td><span class="cell-title">{{ $template->name }}</span><small class="cell-sub">{{ $template->original_file_name }} · {{ $template->page_count }} halaman</small></td>
                                <td class="tabular">v{{ $template->version }}</td>
                                <td>@if($template->is_active)<span class="badge success">Aktif{{ $template->is_default ? ' · utama' : '' }}</span>@else<span class="badge warning">Draf</span>@endif</td>
                                <td>{{ $template->updated_at?->translatedFormat('d M Y') }}</td>
                                <td class="action-cell">@can('document-templates.view')<a class="btn secondary small" href="{{ route('admin.document-templates.builder', $template) }}"><i data-lucide="layout-template"></i> Buka</a>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state"><span class="empty-icon"><i data-lucide="file-pen-line"></i></span><strong>Belum ada template</strong><span>Tanpa template aktif, petugas hanya bisa mengunggah dokumen secara manual.</span>@can('document-templates.create')<a class="btn" href="{{ route('admin.document-templates.create', ['service_type_id' => $service->id]) }}"><i data-lucide="plus"></i> Unggah template</a>@endcan</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection
