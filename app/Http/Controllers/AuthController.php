<?php

namespace App\Http\Controllers;

use App\Support\AdminNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            Log::channel('security')->warning('auth.login_failed', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        // A role may grant nothing in the admin panel, which would drop the user on a
        // bare 403 with no navigation to escape it. Say so at the login screen instead.
        $landing = AdminNavigation::landingRoute($request->user());
        if ($landing === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Akun Anda belum diberi izin apa pun. Hubungi administrator desa.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        Log::channel('security')->info('auth.login_success', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->intended($landing);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
