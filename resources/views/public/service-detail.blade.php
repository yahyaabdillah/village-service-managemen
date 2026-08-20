@extends('layouts.app')
@section('content')
<a class="text-link" href="{{ route('services.index') }}"><i data-lucide="arrow-left"></i> Kembali ke layanan</a>
<section class="hero" style="padding-top:2.5rem;padding-bottom:1.5rem">
    <div class="hero-copy">
        <span class="eyebrow">Detail layanan</span>
        <h1>{{ $serviceType->name }}</h1>
        <p>{{ $serviceType->description ?: 'Lengkapi data dan persyaratan berikut untuk mengajukan layanan ini.' }}</p>
        <a class="btn" href="{{ route('requests.create', $serviceType) }}"><i data-lucide="send"></i> Ajukan Sekarang</a>
    </div>
    <div class="card" style="background:var(--forest-900);color:#fff;border:0">
        <span class="badge warning"><i data-lucide="clipboard-list"></i> Persyaratan</span>
        <h2 style="font-size:1.5rem;margin-top:1rem">Siapkan sebelum mengajukan</h2>
        @forelse($serviceType->requirements as $requirement)
            <div style="display:flex;gap:.65rem;margin:.8rem 0;color:#d9e7df"><i data-lucide="circle-check" style="color:var(--amber);flex:0 0 auto"></i><span><strong style="display:block;color:#fff">{{ $requirement->name }}</strong><small>{{ $requirement->description ?: ($requirement->is_required ? 'Dokumen wajib' : 'Dokumen opsional') }}</small></span></div>
        @empty
            <p style="color:#b9cec5">Tidak ada dokumen tambahan yang dipersyaratkan.</p>
        @endforelse
    </div>
</section>
@endsection
