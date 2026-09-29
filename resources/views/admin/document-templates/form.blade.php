@extends('layouts.admin', ['title' => 'Unggah Template Surat'])
@section('content')
<div class="page-head">
    <div>
        <a class="back-link" href="{{ route('admin.document-templates.index') }}"><i data-lucide="arrow-left"></i> Template surat</a>
        <h1>Unggah template surat</h1>
        <p class="muted">Siapkan surat sebagai PDF dengan kop, judul, dan isi tetap — biarkan kosong bagian yang nanti diisi data warga. Di langkah berikutnya Anda menempatkan data di atas halaman.</p>
    </div>
</div>

<form class="card form-card" method="POST" enctype="multipart/form-data" action="{{ route('admin.document-templates.store') }}" novalidate>
    @csrf
    @if($errors->has('error'))<div class="notice danger" role="alert"><i data-lucide="alert-triangle"></i><div>{{ $errors->first('error') }}</div></div>@endif
    <div class="field-grid">
        <div class="field">
            <label for="service-type">Layanan<span class="req">*</span></label>
            <select id="service-type" name="service_type_id" required @error('service_type_id') aria-invalid="true" @enderror>
                <option value="">Pilih layanan</option>
                @foreach($serviceTypes as $service)<option value="{{ $service->id }}" @selected($selectedServiceId === $service->id)>{{ $service->name }}</option>@endforeach
            </select>
            @error('service_type_id')<p class="field-error">{{ $message }}</p>@else<p class="field-help">Surat yang dicetak dengan template ini.</p>@enderror
        </div>
        <div class="field">
            <label for="template-name">Nama template<span class="req">*</span></label>
            <input id="template-name" name="name" value="{{ old('name') }}" required placeholder="Contoh: SKD kop baru 2026" @error('name') aria-invalid="true" @enderror>
            @error('name')<p class="field-error">{{ $message }}</p>@else<p class="field-help">Hanya terlihat oleh petugas.</p>@enderror
        </div>
        <div class="field span-2">
            <label for="template-description">Catatan</label>
            <textarea id="template-description" name="description" rows="2" placeholder="Contoh: dipakai sejak pergantian kepala desa">{{ old('description') }}</textarea>
        </div>
        <div class="field span-2 {{ $errors->has('template') ? 'has-error' : '' }}">
            @include('components.dropzone-file', [
                'name' => 'template',
                'id' => 'template-pdf',
                'label' => 'File PDF',
                'accept' => 'application/pdf,.pdf',
                'required' => true,
                'icon' => 'file-text',
                'help' => 'Satu file PDF, maksimal 5 MB. Ukuran A4 atau F4, tidak terkunci kata sandi.',
            ])
            @error('template')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit"><i data-lucide="arrow-right"></i> Lanjut menempatkan data</button>
        <a class="btn ghost" href="{{ route('admin.document-templates.index') }}">Batal</a>
    </div>
</form>
@endsection
