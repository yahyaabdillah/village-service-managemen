@extends('layouts.admin')
@section('content')
@php
    $fieldCount = count($fields);
    $mode = $fieldCount <= 4 ? 'modal' : ($fieldCount <= 10 ? 'drawer' : 'stepper');
    $shellClass = $mode === 'modal' ? 'modal-shell' : ($mode === 'drawer' ? 'drawer-shell' : '');
    $renderField = function ($field) use ($item) {
        $value = old($field, is_array($item->{$field}) ? implode(',', $item->{$field}) : $item->{$field});
    };
@endphp
<div class="page-head">
    <div>
        <h1>{{ $title }}</h1>
        <p class="muted">Mode form: <span class="badge">{{ $mode === 'modal' ? 'Modal untuk input sedikit' : ($mode === 'drawer' ? 'Drawer untuk input sedang' : 'Stepper untuk input banyak') }}</span></p>
    </div>
</div>

<form class="card {{ $shellClass }} {{ $mode === 'stepper' ? 'stepper' : '' }}" @if($mode === 'stepper') data-stepper @endif method="POST" action="{{ $item->exists ? route('admin.'.$resource.'.update', $item->id) : route('admin.'.$resource.'.store') }}">
    @csrf
    @if($item->exists) @method('PATCH') @endif

    @if($mode === 'stepper')
        @php($chunks = collect($fields)->chunk(5)->values())
        <div class="stepper-steps">
            @foreach($chunks as $idx => $chunk)
                <button type="button" class="stepper-dot">{{ $idx + 1 }}. Bagian {{ $idx + 1 }}</button>
            @endforeach
        </div>
        @foreach($chunks as $chunk)
            <section class="step-panel field-grid">
                @foreach($chunk as $field)
                    <div>
                        @include('admin.crud.partials.field', ['field' => $field, 'item' => $item])
                    </div>
                @endforeach
            </section>
        @endforeach
        <div class="step-actions">
            <button type="button" class="btn secondary" data-prev>Kembali</button>
            <button type="button" class="btn" data-next>Lanjut</button>
            <button class="btn" data-submit>Simpan</button>
        </div>
    @else
        <div class="field-grid">
            @foreach($fields as $field)
                <div>
                    @include('admin.crud.partials.field', ['field' => $field, 'item' => $item])
                </div>
            @endforeach
        </div>
        <p><button class="btn">Simpan</button></p>
    @endif
</form>
@endsection
