@extends('layouts.app')
@section('content')
<div class="page-head">
    <div><span class="eyebrow">Katalog layanan</span><h1>Layanan untuk warga</h1><p class="muted">Temukan kebutuhan administrasi Anda, lihat persyaratannya, lalu ajukan secara daring.</p></div>
    <a class="btn ghost" href="{{ route('status.form') }}"><i data-lucide="search-check"></i> Cek status</a>
</div>
<div class="service-grid">
    @php($icons = ['file-badge', 'house', 'briefcase-business', 'heart-handshake', 'users-round', 'scroll-text'])
    @forelse($services as $service)
        <article class="service-card">
            <span class="service-card-icon"><i data-lucide="{{ $icons[$loop->index % count($icons)] }}"></i></span>
            <h2 style="font-family:var(--font-body);font-size:1.12rem;letter-spacing:0">{{ $service->name }}</h2>
            <p>{{ $service->description ?: 'Layanan administrasi desa yang dapat diajukan secara daring.' }}</p>
            <p><span class="badge"><i data-lucide="paperclip"></i>{{ $service->requirements->count() }} persyaratan</span></p>
            <a class="text-link" href="{{ route('services.show', $service) }}">Lihat detail <i data-lucide="arrow-right"></i></a>
        </article>
    @empty
        <div class="empty-illustration" style="grid-column:1/-1"><div><i data-lucide="folder-search-2"></i><strong>Belum ada layanan aktif</strong></div></div>
    @endforelse
</div>
@endsection
