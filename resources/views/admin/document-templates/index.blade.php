@extends('layouts.admin')
@section('content')
<div class="page-head"><div><h1>Template Dokumen</h1><p class="muted">Kelola variable, mapping, dan versi dokumen tiap layanan.</p></div><a class="btn" href="{{ route('admin.document-templates.create') }}"><i data-lucide="plus"></i> Buat Template</a></div>
<div class="card table-wrap">
    <table><thead><tr><th>Template</th><th>Layanan</th><th>Versi</th><th>Status</th><th>Default</th><th>Diperbarui</th><th>Aksi</th></tr></thead>
    <tbody>@forelse($templates as $template)<tr><td><strong>{{ $template->name }}</strong><small>{{ $template->original_file_name }}</small></td><td>{{ $template->serviceType?->name }}</td><td>v{{ $template->version }}</td><td><span class="badge {{ $template->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($template->status) }}</span></td><td>{{ $template->is_default ? 'Ya' : '—' }}</td><td>{{ $template->updated_at?->translatedFormat('d M Y, H:i') }}</td><td><a class="btn light small" href="{{ route('admin.document-templates.builder', $template) }}"><i data-lucide="layout-template"></i> Buka Builder</a></td></tr>@empty<tr><td colspan="7"><div class="empty-inline">Belum ada template.</div></td></tr>@endforelse</tbody></table>
    {{ $templates->links() }}
</div>
@endsection
