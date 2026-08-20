@extends('layouts.admin')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $title }}</h1>
        <p class="muted">{{ $resource === 'residents' ? 'Kelola data penduduk desa.' : 'Kelola '.strtolower($title).'.' }}</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('admin.'.$resource.'.create') }}">Tambah</a>
        @if($resource === 'residents')
            <a class="btn secondary" href="{{ route('admin.residents.export') }}">Export CSV</a>
            <a class="btn secondary" href="{{ route('admin.residents.template') }}">Template Excel</a>
        @endif
    </div>
</div>

<div class="card toolbar">
    <form method="GET" class="filters">
        <label class="sr-only" for="{{ $resource }}-search">Cari data</label>
        <input id="{{ $resource }}-search" name="q" value="{{ request('q') }}" placeholder="Cari data...">
        <button class="btn" type="submit">Cari</button>
        @if(request('q')) <a class="btn secondary" href="{{ route('admin.'.$resource.'.index') }}">Reset</a> @endif
    </form>
</div>

@if($resource === 'residents')
    <section class="card import-panel" aria-labelledby="resident-import-title">
        <div class="import-panel-head">
            <div>
                <h2 id="resident-import-title">Import data penduduk</h2>
                <p class="muted">Pilih satu file, validasi isinya, lalu konfirmasi import.</p>
            </div>
            <span class="badge">CSV / Excel</span>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.residents.import-preview') }}" class="filters import-form">
            @csrf
            @include('components.dropzone-file', [
                'name' => 'csv',
                'id' => 'residents-import-file',
                'label' => 'File data penduduk',
                'accept' => '.csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'required' => true,
                'icon' => '📊',
                'help' => 'Maksimal 5 MB. File akan diperiksa sebelum data disimpan.',
            ])
            <button class="btn secondary" type="submit">Validasi File</button>
        </form>

        @if(session('import_preview'))
            <div class="import-preview" aria-live="polite">
                <div>
                    <h3>Hasil validasi</h3>
                    <p><strong>{{ session('import_preview.file_name') }}</strong></p>
                    <p class="muted">
                        {{ session('import_preview.valid_rows') }} dari
                        {{ session('import_preview.total_rows') }} baris valid.
                    </p>
                </div>

                @if(session('import_preview.can_import') && session('resident_import.token'))
                    <div class="notice alert">
                        File valid dan siap diimport. Periksa nama file serta jumlah baris sebelum melanjutkan.
                    </div>
                    <form method="POST" action="{{ route('admin.residents.import') }}">
                        @csrf
                        <input type="hidden" name="import_token" value="{{ session('resident_import.token') }}">
                        <button class="btn secondary" type="submit">Import {{ session('import_preview.valid_rows') }} Baris</button>
                    </form>
                @else
                    <div class="notice errors" role="alert">
                        <div>
                            <strong>File belum dapat diimport.</strong>
                            <ul>@foreach(session('import_preview.errors', []) as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </section>
@endif

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>@foreach($columns as $col)<th>{{ $col }}</th>@endforeach<th>Aksi</th></tr></thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        @foreach($columns as $col)
                            <td>{{ is_array($item->{$col}) ? json_encode($item->{$col}) : $item->{$col} }}</td>
                        @endforeach
                        <td class="actions">
                            <a class="btn secondary" href="{{ route('admin.'.$resource.'.edit', $item->id) }}">Edit</a>
                            <form method="POST" style="display:inline" action="{{ route('admin.'.$resource.'.destroy', $item->id) }}">
                                @csrf @method('DELETE')
                                <button class="btn danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) + 1 }}">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $items->links() }}
</div>
@endsection
