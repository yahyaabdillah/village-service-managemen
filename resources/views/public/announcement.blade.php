@extends('layouts.app', ['title' => $announcement->title])
@section('content')
<article class="card article-card">
    <a class="back-link" href="{{ route('home') }}"><i data-lucide="arrow-left"></i> Beranda</a>
    <h1>{{ $announcement->title }}</h1>
    <p class="announcement-date"><i data-lucide="calendar-days"></i>{{ $announcement->published_at?->translatedFormat('l, d F Y') }}</p>
    <div class="article-body">
        {!! nl2br(e($announcement->content)) !!}
    </div>
</article>
@endsection
