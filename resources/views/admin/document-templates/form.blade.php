@extends('layouts.admin')
@section('content')
<div class="page-head"><div><h1>Tambah Template Dokumen</h1><p class="muted">Form sedang ditampilkan dengan pola drawer.</p></div></div>
<form class="card drawer-shell" method="POST" enctype="multipart/form-data" action="{{ route('admin.document-templates.store') }}">
    @csrf
    <span class="badge">Drawer form</span>
    <label for="service-type">Jenis Layanan</label>
    <select id="service-type" name="service_type_id">
        @foreach($serviceTypes as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach
    </select>
    <label for="template-name">Nama Template</label>
    <input id="template-name" name="name" value="{{ old('name') }}" required>
    <label for="template-description">Deskripsi</label>
    <textarea id="template-description" name="description">{{ old('description') }}</textarea>
    @include('components.dropzone-file', [
        'name' => 'template',
        'id' => 'template-pdf',
        'label' => 'File PDF',
        'accept' => 'application/pdf,.pdf',
        'required' => true,
        'icon' => '📄',
        'help' => 'Upload template dokumen PDF. Maksimal 5 MB.',
    ])
    <p><button class="btn">Upload</button></p>
</form>
@endsection
