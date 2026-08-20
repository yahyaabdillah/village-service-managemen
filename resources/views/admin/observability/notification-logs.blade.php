@extends('layouts.admin')
@section('content')
<div class="page-head"><div><h1>Notification Logs</h1><p class="muted">Riwayat pengiriman notifikasi WhatsApp.</p></div></div>
<div class="card">
    <form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Cari nomor...">
        <select name="status">
            <option value="">Semua status</option>
            @foreach(['pending', 'sent', 'failed'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="btn">Filter</button>
    </form>
</div>
<div class="card">
<table><thead><tr><th>Waktu</th><th>Channel</th><th>Recipient</th><th>Status</th><th>Pesan</th><th>Error</th></tr></thead><tbody>
@forelse($logs as $log)
<tr><td>{{ $log->created_at }}</td><td>{{ $log->channel }}</td><td>{{ $log->recipient }}</td><td><span class="badge">{{ $log->status }}</span></td><td>{{ $log->message }}</td><td>{{ $log->error_message }}</td></tr>
@empty<tr><td colspan="6">Belum ada notifikasi.</td></tr>@endforelse
</tbody></table>{{ $logs->links() }}</div>
@endsection
