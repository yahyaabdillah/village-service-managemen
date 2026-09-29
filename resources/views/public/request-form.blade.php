@extends('layouts.app', ['title' => 'Ajukan '.$serviceType->name])
@section('content')
@php
    $hasFields = $serviceType->fields->count() > 0;
    $hasFiles = $serviceType->requirements->count() > 0;
    $steps = array_values(array_filter(['Data pemohon', 'Alamat', $hasFields ? 'Keterangan surat' : null, $hasFiles ? 'Berkas' : null]));
    $err = fn (string $name) => $errors->has($name) ? 'aria-invalid="true" aria-describedby="err-'.\Illuminate\Support\Str::slug($name).'"' : '';
@endphp
<div class="page-head public-form-head">
    <div>
        <a class="back-link" href="{{ route('services.show', $serviceType) }}"><i data-lucide="arrow-left"></i> {{ $serviceType->name }}</a>
        <h1>Ajukan {{ $serviceType->name }}</h1>
        <p class="muted">Isi bertahap dalam {{ count($steps) }} langkah. Kolom bertanda <span class="req">*</span> wajib diisi. Pastikan nomor WhatsApp aktif — kode pengajuan dan setiap perubahan status dikirim ke sana.</p>
    </div>
</div>

<form class="card form-card stepper public-form" data-stepper method="POST" enctype="multipart/form-data" action="{{ route('requests.store') }}" novalidate>
    @csrf
    <input type="hidden" name="service_type_id" value="{{ $serviceType->id }}">
    <div class="stepper-steps" role="tablist" aria-label="Langkah pengajuan">
        @foreach($steps as $i => $step)<button type="button" class="stepper-dot" role="tab">{{ $i + 1 }}. {{ $step }}</button>@endforeach
    </div>

    <fieldset class="form-section step-panel">
        <legend class="form-section-title">Data pemohon</legend>
        <div class="field-grid">
            <div class="field">
                <label for="applicant-nik">NIK<span class="req">*</span></label>
                <input id="applicant-nik" name="nik" value="{{ old('nik') }}" inputmode="numeric" autocomplete="off" maxlength="16" pattern="[0-9]{16}" required {!! $err('nik') !!}>
                @error('nik')<p class="field-error" id="err-nik">{{ $message }}</p>@else<p class="field-help">16 digit sesuai KTP. Dipakai bersama kode pengajuan untuk mengecek status.</p>@enderror
            </div>
            <div class="field">
                <label for="applicant-name">Nama lengkap<span class="req">*</span></label>
                <input id="applicant-name" name="applicant_name" value="{{ old('applicant_name') }}" autocomplete="name" required {!! $err('applicant_name') !!}>
                @error('applicant_name')<p class="field-error" id="err-applicant-name">{{ $message }}</p>@else<p class="field-help">Sesuai KTP, tanpa gelar.</p>@enderror
            </div>
            <div class="field span-2">
                <label for="phone-local">Nomor WhatsApp<span class="req">*</span></label>
                <div class="phone-input">
                    <select class="phone-country" aria-label="Kode negara">
                        <option value="+62" selected>+62 Indonesia</option>
                        <option value="+60">+60 Malaysia</option>
                        <option value="+65">+65 Singapura</option>
                    </select>
                    <input id="phone-local" class="phone-local" name="phone_number" value="{{ ltrim(preg_replace('/\D+/', '', preg_replace('/^\+?62/', '', (string) old('phone'))), '0') }}" inputmode="numeric" autocomplete="tel-national" pattern="[1-9][0-9]{6,14}" placeholder="81234567890" required {!! $err('phone') !!}>
                    <input class="phone-combined" type="hidden" name="phone" value="{{ old('phone') }}">
                </div>
                @error('phone')<p class="field-error" id="err-phone">{{ $message }}</p>@else<p class="field-help">Tulis tanpa angka 0 di depan, misalnya 81234567890.</p>@enderror
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section step-panel">
        <legend class="form-section-title">Alamat tempat tinggal</legend>
        <div class="field-grid">
            <div class="field span-2">
                <label for="applicant-address">Alamat<span class="req">*</span></label>
                <textarea id="applicant-address" name="address" rows="3" autocomplete="street-address" required {!! $err('address') !!}>{{ old('address') }}</textarea>
                @error('address')<p class="field-error" id="err-address">{{ $message }}</p>@else<p class="field-help">Nama jalan dan nomor rumah sesuai KTP.</p>@enderror
            </div>
            <div class="field"><label for="hamlet">Dusun</label><input id="hamlet" name="hamlet" value="{{ old('hamlet') }}"></div>
            <div class="field rt-rw">
                <label for="rt">RT / RW</label>
                <div class="rt-rw-inputs">
                    <input id="rt" name="rt" value="{{ old('rt') }}" inputmode="numeric" maxlength="3" placeholder="RT" aria-label="RT">
                    <span aria-hidden="true">/</span>
                    <input id="rw" name="rw" value="{{ old('rw') }}" inputmode="numeric" maxlength="3" placeholder="RW" aria-label="RW">
                </div>
            </div>
        </div>
    </fieldset>

    @if($hasFields)
        <fieldset class="form-section step-panel">
            <legend class="form-section-title">Keterangan untuk {{ $serviceType->name }}</legend>
            <div class="field-grid">
                @foreach($serviceType->fields as $field)
                    @php
                        $name = 'fields.'.$field->field_key;
                        $fieldId = 'field-'.\Illuminate\Support\Str::slug($field->field_key);
                        $fieldValue = old($name);
                        $wide = in_array($field->field_type, ['textarea'], true);
                    @endphp
                    <div class="field {{ $wide ? 'span-2' : '' }}">
                        <label for="{{ $fieldId }}">{{ $field->label }}@if($field->is_required)<span class="req">*</span>@endif</label>
                        @if($field->field_type === 'textarea')
                            <textarea id="{{ $fieldId }}" name="fields[{{ $field->field_key }}]" rows="3" placeholder="{{ $field->placeholder }}" @required($field->is_required) {!! $err($name) !!}>{{ $fieldValue }}</textarea>
                        @elseif($field->field_type === 'select')
                            <select id="{{ $fieldId }}" name="fields[{{ $field->field_key }}]" @required($field->is_required) {!! $err($name) !!}>
                                <option value="">Pilih {{ strtolower($field->label) }}</option>
                                @foreach($field->options ?: [] as $option)
                                    <option value="{{ $option }}" @selected($fieldValue === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                id="{{ $fieldId }}"
                                name="fields[{{ $field->field_key }}]"
                                type="{{ in_array($field->field_type, ['date', 'number', 'email', 'tel']) ? $field->field_type : 'text' }}"
                                value="{{ $fieldValue }}"
                                placeholder="{{ $field->placeholder }}"
                                @if($field->field_type === 'number') inputmode="numeric" @endif
                                @required($field->is_required)
                                {!! $err($name) !!}
                            >
                        @endif
                        @error($name)<p class="field-error" id="err-{{ \Illuminate\Support\Str::slug($name) }}">{{ $message }}</p>@elseif($field->help_text)<p class="field-help">{{ $field->help_text }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endif

    @if($hasFiles)
        <fieldset class="form-section step-panel">
            <legend class="form-section-title">Berkas persyaratan</legend>
            <p class="muted">Foto dengan kamera ponsel sudah cukup, asalkan seluruh dokumen terbaca.</p>
            @foreach($serviceType->requirements as $req)
                @php
                    $allowedTypes = array_values(array_diff($req->allowed_file_types ?: ['pdf', 'jpg', 'jpeg', 'png'], ['jpeg']));
                    $accept = collect($req->allowed_file_types ?: ['pdf', 'jpg', 'jpeg', 'png'])->map(fn ($type) => '.'.ltrim($type, '.'))->implode(',');
                    $maxKb = min((int) ($req->max_file_size_kb ?: 6144), 6144);
                    $name = 'requirements.'.$req->id;
                @endphp
                <div class="field span-2 {{ $errors->has($name) ? 'has-error' : '' }}">
                    @include('components.dropzone-file', [
                        'name' => 'requirements['.$req->id.']',
                        'id' => 'requirement-'.$req->id,
                        'label' => $req->name,
                        'required' => $req->is_required,
                        'accept' => $accept,
                        'icon' => 'file-up',
                        'help' => trim(($req->description ? $req->description.' ' : '').'Format '.strtoupper(implode(', ', $allowedTypes)).', maksimal '.round($maxKb / 1024, 1).' MB.'),
                    ])
                    @error($name)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </fieldset>
    @endif

    <div class="form-actions step-actions">
        <button type="button" class="btn secondary" data-prev><i data-lucide="arrow-left"></i> Sebelumnya</button>
        <span class="form-actions-spacer"></span>
        <button type="button" class="btn" data-next>Berikutnya <i data-lucide="arrow-right"></i></button>
        <button class="btn" type="submit" data-submit><i data-lucide="send"></i> Kirim pengajuan</button>
    </div>
</form>
@endsection
