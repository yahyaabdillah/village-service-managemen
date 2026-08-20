@extends('layouts.app')
@section('content')
<div class="card modal-shell">
    <span class="badge">Modal form</span>
    <h1>Login Admin</h1>
    <!-- <p class="muted">Form input sedikit ditampilkan ringkas seperti modal.</p> -->
    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}">
        @error('email')<p style="color:#dc2626">{{ $message }}</p>@enderror
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <p><button class="btn">Login</button></p>
    </form>
</div>
@endsection
