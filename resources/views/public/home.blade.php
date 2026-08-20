@extends('layouts.app')
@section('content')
<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">{{ $profile?->village_name ?? 'Portal Pelayanan Desa' }}</span>
        <h1>Urus kebutuhan desa, <em>tanpa antre panjang.</em></h1>
        <p>Ajukan surat, lengkapi persyaratan, dan pantau prosesnya dari mana saja. Lebih ringkas untuk warga, lebih tertata untuk perangkat desa.</p>
        <div class="hero-actions">
            <a class="btn" href="{{ route('services.index') }}"><i data-lucide="send"></i> Mulai Pengajuan</a>
            <a class="btn ghost" href="{{ route('status.form') }}"><i data-lucide="search-check"></i> Cek Status Pengajuan</a>
        </div>
        <div class="hero-trust">
            <span><i data-lucide="shield-check"></i> Data terlindungi</span>
            <span><i data-lucide="clock-3"></i> Bisa diakses 24 jam</span>
            <span><i data-lucide="badge-check"></i> Status transparan</span>
        </div>
    </div>
    <div class="hero-visual">
        <img src="{{ asset('images/village-service-hero.svg') }}" alt="Ilustrasi balai desa dan layanan administrasi digital" width="760" height="620">
        <div class="hero-note">
            <span class="hero-note-icon"><i data-lucide="circle-check-big"></i></span>
            <span><strong>Proses terpantau</strong><small>Notifikasi setiap perubahan status</small></span>
        </div>
    </div>
</section>

<section class="quick-strip" aria-label="Keunggulan layanan">
    <div class="quick-item"><span class="quick-icon"><i data-lucide="mouse-pointer-click"></i></span><span><strong>100% daring</strong><small>Ajukan dari rumah</small></span></div>
    <div class="quick-item"><span class="quick-icon"><i data-lucide="list-checks"></i></span><span><strong>{{ $services->count() }} layanan</strong><small>Tersedia untuk warga</small></span></div>
    <div class="quick-item"><span class="quick-icon"><i data-lucide="bell-ring"></i></span><span><strong>Update langsung</strong><small>Lewat WhatsApp</small></span></div>
    <div class="quick-item"><span class="quick-icon"><i data-lucide="file-down"></i></span><span><strong>Dokumen aman</strong><small>Unduh dengan verifikasi</small></span></div>
</section>

<section aria-labelledby="layanan-title">
    <div class="section-heading section-title">
        <div><span class="eyebrow">Layanan untuk warga</span><h2 id="layanan-title">Apa yang ingin Anda urus?</h2><p>Pilih layanan, lihat persyaratan, lalu ajukan dalam beberapa langkah.</p></div>
        <a class="text-link" href="{{ route('services.index') }}">Lihat semua <i data-lucide="arrow-up-right"></i></a>
    </div>
    <div class="service-grid">
        @php($serviceIcons = ['file-badge', 'house', 'briefcase-business', 'heart-handshake', 'users-round', 'scroll-text'])
        @forelse($services->take(6) as $service)
            <article class="service-card">
                <span class="service-card-icon"><i data-lucide="{{ $serviceIcons[$loop->index % count($serviceIcons)] }}"></i></span>
                <h3>{{ $service->name }}</h3>
                <p>{{ \Illuminate\Support\Str::limit($service->description ?: 'Layanan administrasi desa yang dapat diajukan secara daring.', 105) }}</p>
                <a class="text-link" href="{{ route('services.show', $service) }}">Lihat persyaratan <i data-lucide="arrow-right"></i></a>
            </article>
        @empty
            <div class="empty-illustration"><div><i data-lucide="folder-search-2"></i><strong>Belum ada layanan aktif</strong><p>Layanan akan muncul setelah diaktifkan perangkat desa.</p></div></div>
        @endforelse
    </div>
</section>

<section class="process-section" aria-labelledby="alur-title">
    <div class="process-head">
        <h2 id="alur-title">Empat langkah, urusan selesai.</h2>
        <p>Alur dibuat sederhana tanpa mengurangi verifikasi. Warga selalu tahu posisi pengajuan dan tindakan berikutnya.</p>
    </div>
    <div class="process-grid">
        <div class="process-step"><span>1</span><h3>Pilih layanan</h3><p>Baca detail dan siapkan berkas persyaratan.</p></div>
        <div class="process-step"><span>2</span><h3>Isi pengajuan</h3><p>Lengkapi identitas dan unggah berkas secara aman.</p></div>
        <div class="process-step"><span>3</span><h3>Petugas memproses</h3><p>Status dapat dilacak dengan kode pengajuan.</p></div>
        <div class="process-step"><span>4</span><h3>Dokumen diterima</h3><p>Unduh hasil akhir setelah proses dinyatakan selesai.</p></div>
    </div>
</section>

<section aria-labelledby="pengumuman-title">
    <div class="section-heading">
        <div><span class="eyebrow">Kabar desa</span><h2 id="pengumuman-title">Pengumuman terbaru</h2></div>
    </div>
    <div class="announcement-grid">
        @forelse($announcements as $announcement)
            <article class="announcement-card">
                <span class="announcement-date"><i data-lucide="calendar-days"></i>{{ $announcement->published_at?->translatedFormat('d F Y') ?? 'Informasi terbaru' }}</span>
                <h3>{{ $announcement->title }}</h3>
                <p>{{ $announcement->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($announcement->content), 150) }}</p>
            </article>
        @empty
            <div class="empty-illustration" style="grid-column:1/-1"><div><i data-lucide="megaphone"></i><strong>Belum ada pengumuman baru</strong><p>Informasi penting desa akan tampil di sini.</p></div></div>
        @endforelse
    </div>
</section>
@endsection
