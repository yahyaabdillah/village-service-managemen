@extends('layouts.admin', ['title' => 'Riwayat Notifikasi'])
@section('content')
@php
    $statusMeta = [
        'sent' => ['Terkirim', 'success'],
        'failed' => ['Gagal', 'danger'],
        'pending' => ['Menunggu', 'warning'],
    ];
@endphp
<div class="page-head">
    <div>
        <h1>Riwayat Notifikasi</h1>
        <p class="muted">Setiap pesan WhatsApp yang dikirim sistem ke warga: kode pengajuan, perubahan status, dan lampiran surat.</p>
    </div>
</div>

<div class="status-strip status-strip-3">
    @foreach($statusMeta as $key => [$label, $tone])
        <a class="status-strip-item {{ request('status') === $key ? 'active' : '' }}" href="{{ route('admin.notification-logs.index', array_filter(['status' => request('status') === $key ? null : $key, 'q' => request('q')])) }}">
            <span><span class="badge {{ $tone }} plain">{{ $label }}</span></span>
            <strong>{{ number_format($counts[$key] ?? 0, 0, ',', '.') }}</strong>
        </a>
    @endforeach
</div>

<div class="card table-card">
    <form class="toolbar table-toolbar filters" method="GET" action="{{ route('admin.notification-logs.index') }}">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <label class="sr-only" for="notif-q">Cari</label>
        <input id="notif-q" type="search" name="q" value="{{ request('q') }}" placeholder="Nomor HP, kode pengajuan, atau nama…">
        <button class="btn secondary" type="submit"><i data-lucide="search"></i> Cari</button>
        @if(request()->hasAny(['q', 'status']))<a class="btn ghost" href="{{ route('admin.notification-logs.index') }}">Reset</a>@endif
        <span class="filters-count muted">{{ number_format($logs->total(), 0, ',', '.') }} pesan</span>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Waktu</th><th>Penerima</th><th>Pengajuan</th><th>Status</th><th>Pesan</th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                    @php [$label, $tone] = $statusMeta[$log->status] ?? [ucfirst($log->status), 'muted']; @endphp
                    <tr>
                        <td class="nowrap"><span class="cell-title">{{ ($log->sent_at ?? $log->created_at)->translatedFormat('d M Y') }}</span><small class="cell-sub">{{ ($log->sent_at ?? $log->created_at)->format('H:i') }}</small></td>
                        <td class="mono">{{ $log->recipient }}</td>
                        <td>
                            @if($log->serviceRequest)
                                <a class="audit-subject" href="{{ route('admin.service-requests.show', $log->service_request_id) }}">{{ $log->serviceRequest->request_code }}</a>
                                <small class="cell-sub">{{ $log->serviceRequest->applicant_name }}</small>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $tone }}">{{ $label }}</span>
                            @if($log->status === 'failed' && $log->error_message)<small class="cell-sub danger-text">{{ \Illuminate\Support\Str::limit($log->error_message, 80) }}</small>@endif
                        </td>
                        <td class="notif-message">
                            <details><summary>{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::of($log->message)->squish(), 70) }}</summary><p>{{ $log->message }}</p></details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state"><span class="empty-icon"><i data-lucide="bell-ring"></i></span><strong>{{ request()->hasAny(['q', 'status']) ? 'Tidak ada pesan yang cocok' : 'Belum ada notifikasi' }}</strong><span>Pesan WhatsApp ke warga akan tercatat di sini setelah ada pengajuan.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="pagination-wrap">{{ $logs->links() }}</div>@endif
</div>
@endsection
