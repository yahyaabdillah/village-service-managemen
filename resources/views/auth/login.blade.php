@extends('layouts.app', ['title' => 'Masuk Petugas'])
@section('content')
<div class="auth-shell">
    <div class="card auth-card">
        <span class="brand-mark auth-mark"><i data-lucide="landmark"></i></span>
        <h1>Masuk ke panel petugas</h1>
        <p class="muted">Gunakan akun yang diberikan oleh administrator desa.</p>
        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <div class="field">
                <label for="email">Alamat email</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Kata sandi</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <label class="check-row"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
            <button class="btn full" type="submit"><i data-lucide="log-in"></i> Masuk</button>
        </form>
    </div>
</div>
@endsection
