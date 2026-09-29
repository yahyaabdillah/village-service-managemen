@extends('layouts.admin', ['title' => $schema['title']])
@section('content')
@php($canCreate = auth()->user()?->can($permission.'.create'))
@php($canUpdate = auth()->user()?->can($permission.'.update'))
@php($canDelete = auth()->user()?->can($permission.'.delete'))
<div class="page-head">
    <div>
        <h1>{{ $schema['title'] }}</h1>
        <p class="muted">{{ $schema['description'] }}</p>
    </div>
    <div class="actions">
        @if($resource === 'residents')
            @can('residents.export')<a class="btn secondary" href="{{ route('admin.residents.export') }}"><i data-lucide="download"></i> Ekspor CSV</a>@endcan
        @endif
        @if($canCreate)<a class="btn" href="{{ route('admin.'.$resource.'.create') }}"><i data-lucide="plus"></i> Tambah {{ $schema['singular'] }}</a>@endif
    </div>
</div>

@if($resource === 'residents')
    @can('residents.import')
        @include('admin.crud.partials.residents-import')
    @endcan
@endif

<div class="card table-card">
    <form method="GET" class="filters" role="search">
        <label class="sr-only" for="{{ $resource }}-search">Cari {{ $schema['singular'] }}</label>
        <div class="input-icon"><i data-lucide="search"></i><input id="{{ $resource }}-search" name="q" value="{{ request('q') }}" placeholder="Cari {{ $schema['singular'] }}…"></div>
        <button class="btn secondary" type="submit">Cari</button>
        @if(request('q'))<a class="btn ghost" href="{{ route('admin.'.$resource.'.index') }}">Hapus pencarian</a>@endif
        <span class="muted filters-count">{{ number_format($items->total(), 0, ',', '.') }} data</span>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    @foreach($schema['columns'] as $key => $column)<th @class(['num' => ($column['type'] ?? '') === 'number'])>{{ $column['label'] }}</th>@endforeach
                    @if($canUpdate || $canDelete)<th class="action-cell"><span class="sr-only">Aksi</span></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        @foreach($schema['columns'] as $key => $column)
                            @php($cell = \App\Support\CrudSchema::cell($column, $item, $key))
                            <td @class(['num' => $cell['num'], 'tabular' => $cell['mono']])>
                                @if($cell['chip'])
                                    <span class="badge {{ $cell['chip'] }}">{{ $cell['text'] }}</span>
                                @elseif(($column['type'] ?? '') === 'title')
                                    <span class="cell-title">{{ $cell['text'] }}</span>
                                @else
                                    {{ $cell['text'] }}
                                @endif
                                @if($cell['sub'])<small class="cell-sub">{{ $cell['sub'] }}</small>@endif
                            </td>
                        @endforeach
                        @if($canUpdate || $canDelete)
                            <td class="action-cell">
                                <div class="row-actions">
                                    @if($canUpdate)<a class="btn ghost icon" href="{{ route('admin.'.$resource.'.edit', $item->id) }}" aria-label="Ubah {{ $schema['singular'] }}" title="Ubah"><i data-lucide="pencil"></i></a>@endif
                                    @if($canDelete)
                                        <form method="POST" action="{{ route('admin.'.$resource.'.destroy', $item->id) }}" data-confirm="Hapus {{ $schema['singular'] }} ini?" data-confirm-text="Data yang dihapus tidak tampil lagi di panel." data-confirm-label="Ya, hapus">
                                            @csrf @method('DELETE')
                                            <button class="btn ghost icon danger-text" type="submit" aria-label="Hapus {{ $schema['singular'] }}" title="Hapus"><i data-lucide="trash-2"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ count($schema['columns']) + 1 }}">
                        <div class="empty-state">
                            <span class="empty-icon"><i data-lucide="inbox"></i></span>
                            <strong>{{ request('q') ? 'Tidak ada '.$schema['singular'].' yang cocok' : 'Belum ada '.$schema['singular'] }}</strong>
                            <span>{{ request('q') ? 'Coba kata kunci lain atau hapus pencarian.' : 'Data yang ditambahkan akan tampil di sini.' }}</span>
                            @if($canCreate && ! request('q'))<a class="btn" href="{{ route('admin.'.$resource.'.create') }}"><i data-lucide="plus"></i> Tambah {{ $schema['singular'] }}</a>@endif
                        </div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())<div class="pagination-wrap">{{ $items->links() }}</div>@endif
</div>
@endsection
