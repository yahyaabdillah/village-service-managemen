@php $current = request()->routeIs('admin.security-logs.*') ? 'security' : 'activity'; @endphp
<div class="tabs log-tabs">
    <div class="tab-list" role="tablist">
        <a class="tab {{ $current === 'activity' ? 'active' : '' }}" role="tab" aria-selected="{{ $current === 'activity' ? 'true' : 'false' }}" href="{{ route('admin.activity-logs.index') }}"><i data-lucide="history"></i> Perubahan data</a>
        <a class="tab {{ $current === 'security' ? 'active' : '' }}" role="tab" aria-selected="{{ $current === 'security' ? 'true' : 'false' }}" href="{{ route('admin.security-logs.index') }}"><i data-lucide="shield-check"></i> Keamanan &amp; akses</a>
    </div>
</div>
