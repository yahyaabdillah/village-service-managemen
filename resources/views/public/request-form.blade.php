@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <span class="badge">Form Pengajuan</span>
        <h1>Form Pengajuan: {{ $serviceType->name }}</h1>
        <!-- <p class="muted">Isi data bertahap. Nomor HP otomatis hanya angka dan angka 0 di depan akan dihapus setelah kode negara.</p> -->
    </div>
</div>

<form class="card stepper" data-stepper method="POST" enctype="multipart/form-data" action="{{ route('requests.store') }}">
    @csrf
    <input type="hidden" name="service_type_id" value="{{ $serviceType->id }}">
    <div class="stepper-steps">
        <button type="button" class="stepper-dot">1. Data Pemohon</button>
        <button type="button" class="stepper-dot">2. Detail Alamat</button>
        @if($serviceType->fields->count()) <button type="button" class="stepper-dot">3. Data Tambahan</button> @endif
        @if($serviceType->requirements->count()) <button type="button" class="stepper-dot">{{ $serviceType->fields->count() ? '4' : '3' }}. Berkas</button> @endif
    </div>

    <section class="step-panel">
        <h2>Data Pemohon</h2>
        <label for="applicant-nik">NIK</label>
        <input id="applicant-nik" name="nik" value="{{ old('nik') }}" inputmode="numeric" autocomplete="off" required>
        <label for="applicant-name">Nama Pemohon</label>
        <input id="applicant-name" name="applicant_name" value="{{ old('applicant_name') }}" autocomplete="name" required>
        <label for="phone-local">No HP</label>
        <div class="phone-input">
            <select class="phone-country" aria-label="Kode negara">
                <option value="+62" selected>🇮🇩 +62</option>
                <option value="+60">🇲🇾 +60</option>
                <option value="+65">🇸🇬 +65</option>
                <option value="+673">🇧🇳 +673</option>
            </select>
            <input id="phone-local" class="phone-local" name="phone_number" value="{{ old('phone_number') }}" inputmode="numeric" autocomplete="tel-national" pattern="[1-9][0-9]{6,14}" placeholder="81234567890" required>
            <input class="phone-combined" type="hidden" name="phone" value="{{ old('phone') }}">
        </div>
        <p class="muted">Contoh Indonesia: pilih 🇮🇩 +62 lalu isi 81234567890, bukan 081234567890.</p>
    </section>

    <section class="step-panel">
        <h2>Alamat</h2>
        <label for="applicant-address">Alamat</label>
        <textarea id="applicant-address" name="address" autocomplete="street-address" required>{{ old('address') }}</textarea>
        <div class="grid">
            <div><label for="hamlet">Dusun</label><input id="hamlet" name="hamlet" value="{{ old('hamlet') }}"></div>
            <div><label for="rt">RT</label><input id="rt" name="rt" value="{{ old('rt') }}" inputmode="numeric"></div>
            <div><label for="rw">RW</label><input id="rw" name="rw" value="{{ old('rw') }}" inputmode="numeric"></div>
        </div>
    </section>

    @if($serviceType->fields->count())
        <section class="step-panel">
            <h2>Data Tambahan</h2>
            @foreach($serviceType->fields as $field)
                @php
                    $fieldId = 'field-'.\Illuminate\Support\Str::slug($field->field_key);
                    $fieldValue = old('fields.'.$field->field_key);
                @endphp
                <label for="{{ $fieldId }}">{{ $field->label }}</label>
                @if($field->field_type === 'textarea')
                    <textarea id="{{ $fieldId }}" name="fields[{{ $field->field_key }}]" placeholder="{{ $field->placeholder }}" @required($field->is_required)>{{ $fieldValue }}</textarea>
                @elseif($field->field_type === 'select')
                    <select id="{{ $fieldId }}" name="fields[{{ $field->field_key }}]" @required($field->is_required)>
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
                        @if($field->field_type === 'number') inputmode="decimal" @endif
                        @required($field->is_required)
                    >
                @endif
                @if($field->help_text)<p class="muted">{{ $field->help_text }}</p>@endif
            @endforeach
        </section>
    @endif

    @if($serviceType->requirements->count())
        <section class="step-panel">
            <h2>Berkas Persyaratan</h2>
            @foreach($serviceType->requirements as $req)
                @php
                    $allowedTypes = $req->allowed_file_types ?: ['pdf','jpg','jpeg','png','docx','mp4','mov','webm'];
                    $accept = collect($allowedTypes)->map(fn ($type) => '.'.ltrim($type, '.'))->implode(',');
                    $maxSize = min((int) ($req->max_file_size_kb ?: 5120), 5120);
                @endphp
                @include('components.dropzone-file', [
                    'name' => 'requirements['.$req->id.']',
                    'id' => 'requirement-'.$req->id,
                    'label' => $req->name,
                    'required' => $req->is_required,
                    'accept' => $accept,
                    'icon' => '🗂️',
                    'help' => trim(($req->description ? $req->description.' ' : '').'Format: '.implode(', ', $allowedTypes).'. Maks: '.$maxSize.' KB.'),
                ])
            @endforeach
        </section>
    @endif

    <div class="step-actions">
        <button type="button" class="btn light" data-prev>Kembali</button>
        <button type="button" class="btn" data-next>Lanjut</button>
        <button class="btn" data-submit>Submit Pengajuan</button>
    </div>
</form>
@endsection
