@extends('layouts.admin', ['title' => 'Konfigurasi Layanan'])
@section('content')
<div class="page-head">
    <div>
        <h1>Konfigurasi Layanan</h1>
        <p class="muted">Jenis surat yang dapat diajukan warga. Tiap layanan punya isian formulir, syarat berkas, dan template suratnya sendiri.</p>
    </div>
    @can('service-types.create')
        <div class="actions"><a class="btn" href="{{ route('admin.service-types.create') }}"><i data-lucide="plus"></i> Tambah layanan</a></div>
    @endcan
</div>

<div class="card table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Layanan</th>
                    <th class="num">Isian</th>
                    <th class="num">Syarat berkas</th>
                    <th>Template</th>
                    <th class="num">Pengajuan</th>
                    <th>Status</th>
                    <th class="action-cell"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                    <tr>
                        <td><span class="cell-title">{{ $service->name }}</span><small class="cell-sub">{{ \Illuminate\Support\Str::limit($service->description, 90) }}</small></td>
                        <td class="num">{{ $service->fields_count }}</td>
                        <td class="num">{{ $service->requirements_count }}</td>
                        <td>@if($service->active_templates_count)<span class="badge success">Siap cetak</span>@else<span class="badge warning">Belum ada template</span>@endif</td>
                        <td class="num">{{ number_format($service->requests_count, 0, ',', '.') }}</td>
                        <td>@if($service->is_active)<span class="badge success">Aktif</span>@else<span class="badge muted">Nonaktif</span>@endif</td>
                        <td class="action-cell">
                            <div class="row-actions">
                                @can('service-types.update')<a class="btn secondary small" href="{{ route('admin.service-types.edit', $service) }}"><i data-lucide="settings-2"></i> Atur</a>@endcan
                                @can('service-types.delete')
                                    <form method="POST" action="{{ route('admin.service-types.destroy', $service->id) }}" data-confirm="Hapus layanan {{ $service->name }}?" data-confirm-text="Pengajuan yang sudah masuk tetap tersimpan, tetapi warga tidak dapat mengajukan layanan ini lagi." data-confirm-label="Ya, hapus">
                                        @csrf @method('DELETE')
                                        <button class="btn ghost icon danger-text" type="submit" aria-label="Hapus layanan"><i data-lucide="trash-2"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state"><span class="empty-icon"><i data-lucide="grid-2x2-check"></i></span><strong>Belum ada layanan</strong><span>Tambahkan jenis surat pertama agar warga dapat mengajukan.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
