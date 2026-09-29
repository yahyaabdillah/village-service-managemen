@extends('layouts.app', ['title' => $serviceType->name])
@section('content')
<div class="page-head">
    <div>
        <a class="back-link" href="{{ route('services.index') }}"><i data-lucide="arrow-left"></i> Semua layanan</a>
        <h1>{{ $serviceType->name }}</h1>
        <p class="muted">{{ $serviceType->description ?: 'Lengkapi data dan persyaratan berikut untuk mengajukan layanan ini.' }}</p>
    </div>
    <div class="actions"><a class="btn" href="{{ route('requests.create', $serviceType) }}"><i data-lucide="send"></i> Ajukan sekarang</a></div>
</div>

<div class="service-detail-grid">
    <section class="card">
        <div class="section-compact-head"><div><small>Langkah 1</small><h2>Siapkan berkas ini</h2></div><span class="count-pill">{{ $serviceType->requirements->count() }} berkas</span></div>
        @forelse($serviceType->requirements as $requirement)
            <div class="checklist-row">
                <i data-lucide="{{ $requirement->is_required ? 'circle-check' : 'circle-dashed' }}"></i>
                <div><strong>{{ $requirement->name }}</strong> <span class="badge plain {{ $requirement->is_required ? '' : 'muted' }}">{{ $requirement->is_required ? 'Wajib' : 'Bila ada' }}</span>@if($requirement->description)<p>{{ $requirement->description }}</p>@endif</div>
            </div>
        @empty
            <p class="muted">Tidak ada berkas yang perlu dilampirkan.</p>
        @endforelse
    </section>

    <section class="card">
        <div class="section-compact-head"><div><small>Langkah 2</small><h2>Data yang akan ditanyakan</h2></div></div>
        <ul class="plain-list">
            <li>Identitas pemohon: NIK, nama lengkap, nomor WhatsApp, dan alamat.</li>
            @foreach($serviceType->fields as $field)
                <li>{{ $field->label }}{{ $field->is_required ? '' : ' (bila ada)' }}</li>
            @endforeach
        </ul>
        <p class="muted">Setelah dikirim, Anda menerima kode pengajuan melalui WhatsApp untuk memantau prosesnya.</p>
    </section>
</div>
@endsection
