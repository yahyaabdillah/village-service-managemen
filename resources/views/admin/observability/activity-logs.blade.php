@extends('layouts.admin', ['title' => 'Jejak Audit'])
@section('content')
@php use App\Support\AuditPresenter; @endphp
<div class="page-head">
    <div>
        <h1>Jejak Audit</h1>
        <p class="muted">Siapa mengubah data apa, kapan, dan dari nilai apa menjadi apa. Tercatat otomatis untuk setiap pengajuan, data desa, pengguna, dan role.</p>
    </div>
    <div class="actions"><span class="badge plain muted">{{ number_format($todayCount, 0, ',', '.') }} perubahan hari ini</span></div>
</div>

@include('admin.observability.partials.log-tabs')

<form class="card audit-filter-card" method="GET" action="{{ route('admin.activity-logs.index') }}">
    <div class="audit-filters">
        <div class="field"><label for="audit-q">Cari</label><input id="audit-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode pengajuan, nama, NIK…"></div>
        <div class="field"><label for="audit-subject">Jenis data</label><select id="audit-subject" name="subject"><option value="">Semua</option>@foreach($subjects as $key => $label)<option value="{{ $key }}" @selected(($filters['subject'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="audit-event">Tindakan</label><select id="audit-event" name="event"><option value="">Semua</option>@foreach(AuditPresenter::EVENTS as $key => $meta)<option value="{{ $key }}" @selected(($filters['event'] ?? '') === $key)>{{ $meta['label'] }}</option>@endforeach</select></div>
        <div class="field"><label for="audit-user">Petugas</label><select id="audit-user" name="user"><option value="">Semua</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((int) ($filters['user'] ?? 0) === $user->id)>{{ $user->name }}</option>@endforeach</select></div>
        <div class="field"><label for="audit-from">Dari tanggal</label><input id="audit-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="field"><label for="audit-to">Sampai</label><input id="audit-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="field audit-filter-actions"><button class="btn secondary" type="submit"><i data-lucide="list-filter"></i> Terapkan</button>@if(array_filter($filters))<a class="btn ghost" href="{{ route('admin.activity-logs.index') }}">Reset</a>@endif</div>
    </div>
</form>

@forelse($days as $date => $items)
    <h2 class="audit-day">{{ \Illuminate\Support\Carbon::parse($date)->isToday() ? 'Hari ini' : (\Illuminate\Support\Carbon::parse($date)->isYesterday() ? 'Kemarin' : '') }} <span>{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d F Y') }}</span></h2>
    <ol class="audit-list">
        @foreach($items as $activity)
            @php $subject = AuditPresenter::subject($activity); $event = AuditPresenter::event($activity); $changes = AuditPresenter::changes($activity); @endphp
            <li class="audit-item">
                <time class="audit-time" datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('H:i') }}</time>
                <div class="audit-body">
                    <p class="audit-line">
                        <strong>{{ AuditPresenter::actor($activity) }}</strong>
                        <span class="badge {{ $event['tone'] }} plain">{{ $event['label'] }}</span>
                        <span class="muted">{{ strtolower($subject['label']) }}</span>
                        @if($subject['url'])<a class="audit-subject" href="{{ $subject['url'] }}">{{ $subject['name'] }}</a>@else<span class="audit-subject">{{ $subject['name'] }}</span>@endif
                    </p>
                    @if($changes)
                        <ul class="audit-diff">
                            @foreach($changes as $change)
                                <li>
                                    <span class="diff-key">{{ $change['label'] }}</span>
                                    <span>
                                        @if($activity->event === 'updated')
                                            <span class="diff-old">{{ $change['old'] ?? '—' }}</span><span class="diff-arrow" aria-hidden="true">→</span><span class="diff-new">{{ $change['new'] ?? '—' }}</span>
                                        @else
                                            <span class="{{ $activity->event === 'deleted' ? 'diff-old' : 'diff-new' }}">{{ $change['new'] }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif($activity->event === 'updated')
                        <p class="audit-summary">Rincian perubahan tidak tercatat.</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@empty
    <div class="card"><div class="empty-state"><span class="empty-icon"><i data-lucide="history"></i></span><strong>{{ array_filter($filters) ? 'Tidak ada aktivitas yang cocok' : 'Belum ada aktivitas' }}</strong><span>{{ array_filter($filters) ? 'Longgarkan filter atau pilih rentang tanggal lain.' : 'Setiap perubahan data akan tercatat otomatis di sini.' }}</span></div></div>
@endforelse

@if($activities->hasPages())<div class="pagination-wrap audit-pagination">{{ $activities->links() }}</div>@endif
@endsection
