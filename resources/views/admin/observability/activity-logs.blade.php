@extends('layouts.admin')
@section('content')
<div class="page-head">
    <div><span class="eyebrow">Akuntabilitas</span><h1>Jejak audit</h1><p class="muted">Riwayat perubahan data bisnis: siapa melakukan apa, terhadap data mana, dan kapan.</p></div>
</div>

<div class="audit-explainer">
    <span class="audit-explainer-icon"><i data-lucide="history"></i></span>
    <div><strong>Ini bukan pengganti monitoring server.</strong><p class="muted">Jejak audit dipakai untuk investigasi perubahan data dan akuntabilitas petugas. Error aplikasi, security, dan WhatsApp kini dikirim ke Grafana Loki.</p><a class="btn small" href="{{ config('observability.grafana_url') }}" target="_blank" rel="noopener noreferrer"><i data-lucide="external-link"></i> Buka dashboard Grafana</a></div>
</div>

<div class="card">
    <form method="GET" class="filters">
        <label class="sr-only" for="activity-search">Cari deskripsi</label><input id="activity-search" name="q" value="{{ request('q') }}" placeholder="Cari aktivitas...">
        <label class="sr-only" for="activity-log">Jenis log</label><select id="activity-log" name="log_name"><option value="">Semua log</option>@foreach(['business-model'] as $log)<option value="{{ $log }}" @selected(request('log_name') === $log)>{{ $log }}</option>@endforeach</select>
        <label class="sr-only" for="activity-event">Jenis kejadian</label><select id="activity-event" name="event"><option value="">Semua kejadian</option>@foreach(['created', 'updated', 'deleted'] as $event)<option value="{{ $event }}" @selected(request('event') === $event)>{{ ucfirst($event) }}</option>@endforeach</select>
        <button class="btn" type="submit"><i data-lucide="list-filter"></i> Terapkan</button><a class="btn light" href="{{ route('admin.activity-logs.index') }}">Reset</a>
    </form>
</div>

<div class="card">
    <table>
        <thead><tr><th>Waktu</th><th>Aktivitas</th><th>Objek data</th><th>Petugas</th><th>Keterangan</th></tr></thead>
        <tbody>
            @forelse($activities as $activity)
                <tr>
                    <td><strong>{{ $activity->created_at->translatedFormat('d M Y') }}</strong><br><small class="muted">{{ $activity->created_at->format('H:i:s') }}</small></td>
                    <td><span class="log-event {{ $activity->event }}"><i data-lucide="{{ match($activity->event) {'created' => 'circle-plus', 'deleted' => 'trash-2', default => 'pencil'} }}"></i>{{ ucfirst($activity->event) }}</span></td>
                    <td><span class="badge">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span></td>
                    <td>{{ $activity->causer_id ? class_basename($activity->causer_type).' #'.$activity->causer_id : 'Sistem' }}</td>
                    <td>{{ $activity->description }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><span class="empty-icon"><i data-lucide="history"></i></span><strong>Belum ada aktivitas</strong><span>Perubahan data akan tercatat otomatis di sini.</span></div></td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $activities->links() }}
</div>
@endsection
