@extends('layouts.admin', ['title' => 'Keamanan & Akses'])
@section('content')
<div class="page-head">
    <div>
        <h1>Jejak Audit</h1>
        <p class="muted">Percobaan masuk, unduhan surat oleh warga, berkas yang ditolak pemindai, dan kegagalan pengiriman WhatsApp dari tujuh hari terakhir.</p>
    </div>
</div>

@include('admin.observability.partials.log-tabs')

<div class="card table-card">
    <form class="toolbar table-toolbar filters" method="GET" action="{{ route('admin.security-logs.index') }}">
        <label class="sr-only" for="sec-event">Jenis kejadian</label>
        <select id="sec-event" name="event"><option value="">Semua kejadian</option>@foreach($events as $key => $label)<option value="{{ $key }}" @selected(($filters['event'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
        <button class="btn secondary" type="submit"><i data-lucide="list-filter"></i> Terapkan</button>
        @if(array_filter($filters))<a class="btn ghost" href="{{ route('admin.security-logs.index') }}">Reset</a>@endif
        <span class="filters-count muted">{{ $rows->count() }} kejadian terbaru</span>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Waktu</th><th>Kejadian</th><th>Siapa / apa</th><th>Alamat IP</th><th>Keterangan</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    @php $ctx = $row['context']; @endphp
                    <tr>
                        <td class="nowrap"><span class="cell-title">{{ $row['time']->translatedFormat('d M Y') }}</span><small class="cell-sub">{{ $row['time']->format('H:i:s') }}</small></td>
                        <td><span class="badge {{ $row['tone'] }}">{{ $row['label'] }}</span></td>
                        <td>
                            @if(isset($ctx['user_id']))<span class="cell-title">{{ $userNames[$ctx['user_id']] ?? 'Pengguna #'.$ctx['user_id'] }}</span>@endif
                            @if(isset($ctx['email']))<span class="cell-title">{{ $ctx['email'] }}</span>@endif
                            @if(isset($ctx['service_request_id']))
                                @if(isset($requestCodes[$ctx['service_request_id']]))<a class="audit-subject" href="{{ route('admin.service-requests.show', $ctx['service_request_id']) }}">{{ $requestCodes[$ctx['service_request_id']] }}</a>@else<span class="muted">Pengajuan #{{ $ctx['service_request_id'] }}</span>@endif
                            @endif
                            @if(isset($ctx['phone']))<span class="cell-title">{{ $ctx['phone'] }}</span>@endif
                            @if(isset($ctx['file']) || isset($ctx['original_name']))<span class="cell-title">{{ $ctx['file'] ?? $ctx['original_name'] }}</span>@endif
                            @if(! array_intersect_key($ctx, array_flip(['user_id', 'email', 'service_request_id', 'phone', 'file', 'original_name'])))<span class="muted">—</span>@endif
                        </td>
                        <td class="mono">{{ $ctx['ip'] ?? '—' }}</td>
                        <td class="muted">{{ $ctx['error'] ?? $ctx['reason'] ?? $ctx['signature'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state"><span class="empty-icon"><i data-lucide="shield-check"></i></span><strong>Belum ada kejadian keamanan</strong><span>Percobaan masuk dan unduhan surat akan tercatat di sini.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
