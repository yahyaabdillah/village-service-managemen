@extends('layouts.admin', ['title' => $title])
@section('content')
@php
    $sections = $schema['sections'];
    $fieldDefs = \App\Support\CrudSchema::fields($resource);
    $stepper = count($sections) > 1 && count($fieldDefs) > 8;
    $errorSection = null;
    foreach ($sections as $i => $section) {
        foreach ($section['fields'] as $name) {
            if ($errors->has($name) || $errors->has($name.'.*')) { $errorSection ??= $i; }
        }
    }
@endphp
<div class="page-head">
    <div>
        <a class="back-link" href="{{ route('admin.'.$resource.'.index') }}"><i data-lucide="arrow-left"></i> {{ $schema['title'] }}</a>
        <h1>{{ $title }}</h1>
        @if(! $item->exists)<p class="muted">Kolom bertanda <span class="req">*</span> wajib diisi.</p>@endif
    </div>
</div>

<form class="card form-card {{ $stepper ? 'stepper' : '' }}" @if($stepper) data-stepper @endif method="POST" action="{{ $item->exists ? route('admin.'.$resource.'.update', $item->id) : route('admin.'.$resource.'.store') }}" novalidate>
    @csrf
    @if($item->exists) @method('PATCH') @endif

    @if($stepper)
        <div class="stepper-steps" role="tablist" aria-label="Bagian formulir">
            @foreach($sections as $i => $section)
                <button type="button" class="stepper-dot" role="tab">{{ $i + 1 }}. {{ $section['title'] }}</button>
            @endforeach
        </div>
    @endif

    @foreach($sections as $i => $section)
        <fieldset class="form-section {{ $stepper ? 'step-panel' : '' }}">
            <legend class="form-section-title">{{ $section['title'] }}</legend>
            <div class="field-grid">
                @foreach($section['fields'] as $name)
                    @include('admin.crud.partials.field', ['name' => $name, 'def' => $fieldDefs[$name], 'item' => $item, 'options' => $options ?? []])
                @endforeach
            </div>
        </fieldset>
    @endforeach

    <div class="form-actions {{ $stepper ? 'step-actions' : '' }}">
        @if($stepper)
            <button type="button" class="btn secondary" data-prev><i data-lucide="arrow-left"></i> Sebelumnya</button>
            <span class="form-actions-spacer"></span>
            <button type="button" class="btn" data-next>Berikutnya <i data-lucide="arrow-right"></i></button>
            <button class="btn" type="submit" data-submit><i data-lucide="check"></i> {{ $item->exists ? 'Simpan perubahan' : 'Simpan '.$schema['singular'] }}</button>
        @else
            <button class="btn" type="submit"><i data-lucide="check"></i> {{ $item->exists ? 'Simpan perubahan' : 'Simpan '.$schema['singular'] }}</button>
            <a class="btn ghost" href="{{ route('admin.'.$resource.'.index') }}">Batal</a>
        @endif
    </div>
</form>
@endsection
