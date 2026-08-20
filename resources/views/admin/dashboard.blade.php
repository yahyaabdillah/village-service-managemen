@extends('layouts.admin')
@section('content')
<div class="page-head">
    <div><span class="eyebrow">Ringkasan hari ini</span><h1>Dashboard Layanan Desa</h1><p class="muted">Pantau beban kerja, progres pengajuan, dan aktivitas layanan dalam satu layar.</p></div>
    <a class="btn" href="{{ route('admin.service-requests.index') }}"><i data-lucide="inbox"></i> Kelola Pengajuan</a>
</div>

<section class="metric-grid" aria-label="Statistik utama">
    <article class="card metric-card" style="--metric-color:#216352;--metric-tint:#e0efe5"><div class="metric-top"><small>Total Penduduk</small><span class="metric-icon"><i data-lucide="users-round"></i></span></div><div class="metric-value">{{ number_format($totalResidents) }}</div><div class="metric-caption">Data warga terdaftar</div></article>
    <article class="card metric-card" style="--metric-color:#3577a8;--metric-tint:#e2f0f8"><div class="metric-top"><small>Total pengajuan</small><span class="metric-icon"><i data-lucide="files"></i></span></div><div class="metric-value">{{ number_format($totalRequests) }}</div><div class="metric-caption">{{ $serviceTypes }} jenis layanan aktif</div></article>
    <article class="card metric-card" style="--metric-color:#b57019;--metric-tint:#fff0d5"><div class="metric-top"><small>Pengajuan Baru</small><span class="metric-icon"><i data-lucide="file-clock"></i></span></div><div class="metric-value">{{ number_format($newRequests) }}</div><div class="metric-caption">Menunggu verifikasi petugas</div></article>
    <article class="card metric-card" style="--metric-color:#27845f;--metric-tint:#ddf4e8"><div class="metric-top"><small>Selesai</small><span class="metric-icon"><i data-lucide="badge-check"></i></span></div><div class="metric-value">{{ number_format($completedRequests) }}</div><div class="metric-caption">{{ $generatedDocuments }} dokumen telah dibuat</div></article>
</section>

<section class="dashboard-grid">
    <article class="card chart-card">
        <div class="chart-head"><div><h2>Tren pengajuan</h2><p class="muted">Pengajuan masuk dalam 7 hari terakhir</p></div><span class="badge"><i data-lucide="trending-up"></i> Mingguan</span></div>
        <div class="chart-wrap"><canvas id="request-trend-chart" aria-label="Grafik tren pengajuan tujuh hari terakhir"></canvas></div>
    </article>
    <article class="card chart-card">
        <div class="chart-head"><div><h2>Status pengajuan</h2><p class="muted">Distribusi seluruh proses</p></div><span class="badge">{{ $totalRequests }} total</span></div>
        @php
            $statusMeta = [
                'submitted' => ['Baru', '#e7a33e'],
                'verified' => ['Terverifikasi', '#4c8eae'],
                'processing' => ['Diproses', '#2d7a64'],
                'completed' => ['Selesai', '#27845f'],
                'rejected' => ['Ditolak', '#c8493a'],
            ];
            $maxStatus = max(1, (int) $statusBreakdown->max());
        @endphp
        <div class="status-list">
            @foreach($statusMeta as $key => [$label, $color])
                @php($value = (int) ($statusBreakdown[$key] ?? 0))
                <div class="status-row"><span class="status-swatch" style="background:{{ $color }}"></span><div><div class="toolbar"><small>{{ $label }}</small><small>{{ $value }}</small></div><div class="status-track"><div class="status-fill" style="width:{{ ($value / $maxStatus) * 100 }}%;background:{{ $color }}"></div></div></div><strong>{{ $totalRequests ? round(($value / $totalRequests) * 100) : 0 }}%</strong></div>
            @endforeach
        </div>
    </article>
</section>

<article class="card recent-card">
    <div class="recent-head"><div><h2>Pengajuan terbaru</h2><p class="muted">Data yang baru masuk ke sistem</p></div><a class="text-link" href="{{ route('admin.service-requests.index') }}">Lihat semua <i data-lucide="arrow-right"></i></a></div>
    @if($latestRequests->isEmpty())
        <div class="empty-state"><span class="empty-icon"><i data-lucide="inbox"></i></span><strong>Belum ada pengajuan</strong><span>Pengajuan warga yang masuk akan tampil di sini.</span></div>
    @else
        <table>
            <thead><tr><th>Pemohon</th><th>Kode</th><th>Layanan</th><th>Status</th><th>Waktu</th></tr></thead>
            <tbody>@foreach($latestRequests as $request)
                @php($statusClass = match($request->status) { 'completed' => 'success', 'rejected' => 'danger', 'submitted' => 'warning', default => '' })
                <tr>
                    <td><div class="request-person"><span class="avatar">{{ strtoupper(substr($request->applicant_name, 0, 1)) }}</span><span><strong>{{ $request->applicant_name }}</strong><small>{{ \Illuminate\Support\Str::mask($request->nik, '*', 4, 8) }}</small></span></div></td>
                    <td><code>{{ $request->request_code }}</code></td><td>{{ $request->serviceType?->name }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ $request->publicStatusLabel() }}</span></td>
                    <td class="muted">{{ $request->created_at->diffForHumans() }}</td>
                </tr>
            @endforeach</tbody>
        </table>
    @endif
</article>

<script type="application/json" id="dashboard-chart-data">@json(['labels' => $trendLabels, 'values' => $trendData])</script>
@endsection
