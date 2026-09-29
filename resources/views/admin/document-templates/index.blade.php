@extends('layouts.admin', ['title' => 'Template Surat'])
@section('content')
<div class="page-head">
    <div>
        <h1>Template Surat</h1>
        <p class="muted">Halaman PDF berkop yang diisi otomatis dengan data pengajuan saat petugas menerbitkan surat. Satu template <em>dipakai</em> per layanan.</p>
    </div>
    @can('document-templates.create')
        <div class="actions"><a class="btn" href="{{ route('admin.document-templates.create') }}"><i data-lucide="plus"></i> Unggah template</a></div>
    @endcan
</div>

@if($servicesWithoutLive->isNotEmpty())
    <div class="notice info" role="status">
        <i data-lucide="info"></i>
        <div>
            <strong>{{ $servicesWithoutLive->count() }} layanan belum punya template yang dipakai:</strong>
            {{ $servicesWithoutLive->pluck('name')->join(', ') }}. Petugas hanya bisa mengunggah surat secara manual untuk layanan tersebut.
        </div>
    </div>
@endif

<div class="card table-card">
    <form class="toolbar table-toolbar filters" method="GET" action="{{ route('admin.document-templates.index') }}">
        <label class="sr-only" for="tpl-q">Cari template</label>
        <input id="tpl-q" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama template…">
        <label class="sr-only" for="tpl-service">Layanan</label>
        <select id="tpl-service" name="service_type_id">
            <option value="">Semua layanan</option>
            @foreach($serviceTypes as $service)<option value="{{ $service->id }}" @selected((int) request('service_type_id') === $service->id)>{{ $service->name }}</option>@endforeach
        </select>
        <button class="btn secondary" type="submit"><i data-lucide="search"></i> Terapkan</button>
        @if(request()->hasAny(['q', 'service_type_id']))<a class="btn ghost" href="{{ route('admin.document-templates.index') }}">Reset</a>@endif
        <span class="filters-count muted">{{ number_format($templates->total(), 0, ',', '.') }} template</span>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Template</th>
                    <th>Layanan</th>
                    <th class="num">Teks</th>
                    <th>Status</th>
                    <th>Diperbarui</th>
                    <th class="action-cell"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td>
                            <span class="cell-title">{{ $template->name }}</span>
                            <small class="cell-sub">Versi {{ $template->version }} · {{ $template->page_count }} halaman</small>
                        </td>
                        <td>{{ $template->serviceType?->name ?? '—' }}</td>
                        <td class="num">{{ $template->fields_count }}</td>
                        <td><span class="badge {{ $template->statusTone() }}">{{ $template->statusLabel() }}</span></td>
                        <td>{{ $template->updated_at?->translatedFormat('d M Y') }}</td>
                        <td class="action-cell">
                            <div class="row-actions">
                                <a class="btn secondary small" href="{{ route('admin.document-templates.builder', $template) }}"><i data-lucide="pen-line"></i> {{ $template->fields_count ? 'Atur' : 'Susun' }}</a>
                                @if($template->fields_count)<a class="btn ghost icon" href="{{ route('admin.document-templates.sample', $template) }}" target="_blank" rel="noopener" aria-label="Lihat contoh hasil" title="Lihat contoh hasil"><i data-lucide="eye"></i></a>@endif
                                @can('document-templates.delete')
                                    <form method="POST" action="{{ route('admin.document-templates.destroy', $template) }}" data-confirm="Hapus template “{{ $template->name }}”?" data-confirm-text="{{ $template->isLive() ? 'Template ini sedang dipakai. Setelah dihapus, petugas tidak bisa menerbitkan '.$template->serviceType?->name.' sampai template lain diaktifkan.' : 'Surat yang sudah diterbitkan dengan template ini tetap tersimpan.' }}" data-confirm-label="Ya, hapus">
                                        @csrf @method('DELETE')
                                        <button class="btn ghost icon danger-text" type="submit" aria-label="Hapus template"><i data-lucide="trash-2"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><span class="empty-icon"><i data-lucide="file-pen-line"></i></span><strong>Belum ada template</strong><span>Unggah PDF berkop lalu tempatkan data yang ingin dicetak.</span>@can('document-templates.create')<a class="btn" href="{{ route('admin.document-templates.create') }}"><i data-lucide="plus"></i> Unggah template</a>@endcan</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($templates->hasPages())<div class="table-footer">{{ $templates->links() }}</div>@endif
</div>
@endsection
