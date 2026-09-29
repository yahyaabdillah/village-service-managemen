@extends('layouts.app', ['title' => 'Layanan'])
@section('content')
<div class="page-head">
    <div><h1>Layanan untuk warga</h1><p class="muted">Pilih surat yang Anda perlukan, siapkan berkasnya, lalu ajukan dari rumah.</p></div>
    <div class="actions"><a class="btn secondary" href="{{ route('status.form') }}"><i data-lucide="search-check"></i> Cek status pengajuan</a></div>
</div>
<div class="service-grid">
    @php($icons = ['file-badge', 'house', 'briefcase-business', 'heart-handshake', 'users-round', 'scroll-text'])
    @forelse($services as $service)
        <article class="service-card">
            <span class="service-card-icon"><i data-lucide="{{ $icons[$loop->index % count($icons)] }}"></i></span>
            <h2 class="service-card-title">{{ $service->name }}</h2>
            <p>{{ $service->description ?: 'Layanan administrasi desa yang dapat diajukan secara daring.' }}</p>
            <p><span class="badge plain"><i data-lucide="paperclip"></i>{{ $service->requirements->count() }} berkas</span></p>
            <a class="text-link" href="{{ route('services.show', $service) }}">Lihat persyaratan <i data-lucide="arrow-right"></i></a>
        </article>
    @empty
        <div class="empty-illustration span-all"><div><i data-lucide="folder-search-2"></i><strong>Belum ada layanan yang dibuka</strong><p>Silakan kembali beberapa saat lagi.</p></div></div>
    @endforelse
</div>
@endsection
