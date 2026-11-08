<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * 1. MENAMPILKAN FORM LOGIN
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * 2. PROSES LOGIN (AUTENTIKASI)
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->redirectByRole(Auth::user()->role));
    }

    /**
     * 3. MENAMPILKAN FORM REGISTER (KHUSUS PELANGGAN)
     */
    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    /**
     * 4. PROSES REGISTER PELANGGAN
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'no_telepon' => ['required', 'string', 'max:20'],
            'alamat'     => ['required', 'string'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'nama'     => $validated['nama'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role'     => 'pelanggan',
            ]);

            Pelanggan::create([
                'id_user'    => $user->id_user,
                'no_telepon' => $validated['no_telepon'],
                'alamat'     => $validated['alamat'],
            ]);

            return $user;
        });

        Auth::login($user);

        return redirect()
            ->route('pelanggan.dashboard')
            ->with('success', 'Akun pelanggan berhasil dibuat! Selamat datang, '.$user->nama.'.');
    }

    /**
     * 5. LOGOUT
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah logout.');
    }

    /**
     * HELPER: Tentukan URL dashboard berdasarkan role (mendukung null)
     */
    private function redirectByRole(?string $role): string
    {
        return match ($role) {
            'admin'     => route('admin.dashboard'),
            'karyawan'  => route('karyawan.dashboard'),
            'pelanggan' => route('pelanggan.dashboard'),
            default     => route('login'),
        };
    }
}