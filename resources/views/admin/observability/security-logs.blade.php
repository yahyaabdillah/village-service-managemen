@extends('layouts.admin')
@section('content')
<div class="card">
    <h1>Security Logs</h1>
    <p class="muted">200 baris terakhir dari <code>storage/logs/security.log</code>.</p>
    <table>
        <thead><tr><th>Log line</th></tr></thead>
        <tbody>
            @forelse($lines as $line)
                <tr><td><code>{{ $line }}</code></td></tr>
            @empty
                <tr><td>Belum ada security log.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
