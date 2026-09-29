@extends('layouts.admin', ['title' => 'Dashboard'])
@section('content')
<div class="page-head">
    <div>
        <h1>Selamat datang, {{ auth()->user()->name }}</h1>
        <p class="muted">{{ $newRequests ? $newRequests.' pengajuan menunggu verifikasi' : 'Tidak ada pengajuan yang menunggu verifikasi' }}{{ $oldestWaitingDays !== null && $oldestWaitingDays >= 2 ? ', yang terlama sudah '.$oldestWaitingDays.' hari' : '' }}. {{ $weekTotal }} pengajuan masuk dalam 7 hari terakhir.</p>
    </div>
    <div class="actions"><a class="btn" href="{{ route('admin.service-requests.index', $newRequests ? ['status' => 'submitted'] : []) }}"><i data-lucide="inbox"></i> {{ $newRequests ? 'Verifikasi pengajuan baru' : 'Buka daftar pengajuan' }}</a></div>
</div>

<section class="metric-grid" aria-label="Ringkasan">
    <a class="card metric-card metric-link" href="{{ route('admin.service-requests.index', ['status' => 'submitted']) }}" style="--metric-color:#b57019;--metric-tint:#fff0d5"><div class="metric-top"><small>Menunggu verifikasi</small><span class="metric-icon"><i data-lucide="file-clock"></i></span></div><div class="metric-value">{{ number_format($newRequests, 0, ',', '.') }}</div><div class="metric-caption">Pengajuan baru dari warga</div></a>
    <a class="card metric-card metric-link" href="{{ route('admin.service-requests.index', ['status' => 'processing']) }}" style="--metric-color:#3577a8;--metric-tint:#e2f0f8"><div class="metric-top"><small>Sedang diproses</small><span class="metric-icon"><i data-lucide="loader"></i></span></div><div class="metric-value">{{ number_format($processingRequests, 0, ',', '.') }}</div><div class="metric-caption">Berkas lengkap, surat belum terbit</div></a>
    <a class="card metric-card metric-link" href="{{ route('admin.service-requests.index', ['status' => 'completed']) }}" style="--metric-color:#27845f;--metric-tint:#ddf4e8"><div class="metric-top"><small>Selesai</small><span class="metric-icon"><i data-lucide="badge-check"></i></span></div><div class="metric-value">{{ number_format($completedRequests, 0, ',', '.') }}</div><div class="metric-caption">{{ number_format($completedThisMonth, 0, ',', '.') }} bulan ini · {{ number_format($generatedDocuments, 0, ',', '.') }} surat terbit</div></a>
    <a class="card metric-card metric-link" href="{{ route('admin.residents.index') }}" style="--metric-color:#216352;--metric-tint:#e0efe5"><div class="metric-top"><small>Penduduk terdaftar</small><span class="metric-icon"><i data-lucide="users-round"></i></span></div><div class="metric-value">{{ number_format($totalResidents, 0, ',', '.') }}</div><div class="metric-caption">{{ $activeServices }} layanan dapat diajukan warga</div></a>
</section>

<section class="dashboard-grid">
    <article class="card chart-card">
        <div class="chart-head"><div><h2>Pengajuan masuk</h2><p class="muted">Per hari, 7 hari terakhir</p></div><span class="badge plain muted">{{ $weekTotal }} minggu ini</span></div>
        <div class="chart-wrap"><canvas id="request-trend-chart" aria-label="Grafik pengajuan masuk tujuh hari terakhir"></canvas></div>
    </article>
    <article class="card chart-card">
        <div class="chart-head"><div><h2>Posisi seluruh pengajuan</h2><p class="muted">{{ number_format($totalRequests, 0, ',', '.') }} pengajuan sejak awal</p></div></div>
        @php
            $statusMeta = [
                'submitted' => ['Menunggu verifikasi', '#e7a33e'],
                'verified' => ['Berkas diverifikasi', '#4c8eae'],
                'processing' => ['Sedang diproses', '#2d7a64'],
                'completed' => ['Selesai', '#27845f'],
                'rejected' => ['Ditolak', '#c8493a'],
            ];
            $maxStatus = max(1, (int) $statusBreakdown->max());
        @endphp
        <div class="status-list">
            @foreach($statusMeta as $key => [$label, $color])
                @php($value = (int) ($statusBreakdown[$key] ?? 0))
                <a class="status-row" href="{{ route('admin.service-requests.index', ['status' => $key]) }}"><span class="status-swatch" style="background:{{ $color }}"></span><div><div class="toolbar"><small>{{ $label }}</small><small>{{ $value }}</small></div><div class="status-track"><div class="status-fill" style="width:{{ ($value / $maxStatus) * 100 }}%;background:{{ $color }}"></div></div></div><strong>{{ $totalRequests ? round(($value / $totalRequests) * 100) : 0 }}%</strong></a>
            @endforeach
        </div>
        @if($topServices->isNotEmpty() && $totalRequests)
            <div class="top-services">
                <p class="panel-label">Layanan paling diminta</p>
                <ol>@foreach($topServices as $service)<li><span>{{ $service->name }}</span><strong>{{ number_format($service->requests_count, 0, ',', '.') }}</strong></li>@endforeach</ol>
            </div>
        @endif
    </article>
</section>

<article class="card recent-card">
    <div class="recent-head"><div><h2>Pengajuan terbaru</h2><p class="muted">Delapan pengajuan yang paling baru masuk</p></div><a class="text-link" href="{{ route('admin.service-requests.index') }}">Lihat semua <i data-lucide="arrow-right"></i></a></div>
    @if($latestRequests->isEmpty())
        <div class="empty-state"><span class="empty-icon"><i data-lucide="inbox"></i></span><strong>Belum ada pengajuan</strong><span>Pengajuan warga yang masuk akan tampil di sini.</span></div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pemohon</th><th>Layanan</th><th>Status</th><th>Masuk</th><th class="action-cell"><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>@foreach($latestRequests as $request)
                    <tr>
                        <td><div class="request-person"><span class="avatar">{{ strtoupper(substr($request->applicant_name, 0, 1)) }}</span><span><strong>{{ $request->applicant_name }}</strong><small class="mono">{{ $request->request_code }}</small></span></div></td>
                        <td>{{ $request->serviceType?->name }}</td>
                        <td><span class="badge {{ \App\Models\ServiceRequest::statusTone($request->status) }}">{{ $request->publicStatusLabel() }}</span></td>
                        <td class="muted nowrap">{{ $request->created_at->diffForHumans() }}</td>
                        <td class="action-cell"><a class="btn secondary small" href="{{ route('admin.service-requests.show', $request) }}">{{ $request->status === 'submitted' ? 'Periksa' : 'Buka' }}</a></td>
                    </tr>
                @endforeach</tbody>
            </table>
        </div>
    @endif
</article>

<script type="application/json" id="dashboard-chart-data">@json(['labels' => $trendLabels, 'values' => $trendData])</script>
@endsection
