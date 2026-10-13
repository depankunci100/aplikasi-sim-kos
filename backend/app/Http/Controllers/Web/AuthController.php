<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        // Dukungan login dengan email atau nomor HP
        $field = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (Auth::attempt([$field => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true], $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();
            if ($user->isTenant()) {
                // Tenant diarahkan bahwa web dashboard dikhususkan untuk operasional staf/owner
                return redirect()->intended(route('dashboard.index'))
                    ->with('info', 'Selamat datang! Anda masuk sebagai penyewa.');
            }

            return redirect()->intended(route('dashboard.index'))
                ->with('success', 'Selamat datang kembali, ' . $user->name . ' (' . $user->role->label() . ')');
        }

        return back()->withErrors([
            'email' => 'Email/No. HP atau kata sandi tidak cocok, atau akun dinonaktifkan.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.');
    }
}
