<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Show login form.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle login.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Akun Anda belum aktif. Mohon tunggu verifikasi dari Admin Dekorasi.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            // If there's a share redirect in session, go there
            if ($shareToken = session('share_redirect')) {
                session()->forget('share_redirect');
                return redirect('/share/' . $shareToken);
            }

            return redirect()->intended('/drive');
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    /**
     * Show registration form.
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle registration.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Akun dibuat non-aktif — sesuai alur di halaman register, aktivasi
        // dilakukan admin lewat menu User Management.
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'storage_quota' => User::DEFAULT_STORAGE_QUOTA,
            'is_active' => false,
        ]);

        // Notify all admins about new registration
        $admins = \App\Models\User::where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'type'    => 'new_registration',
                'title'   => 'Pengguna Baru Mendaftar',
                'message' => $request->name . ' (' . $request->email . ') telah mendaftar dan menunggu aktivasi. Periksa menu User Management.',
                'icon'    => 'fas fa-user-plus',
                'color'   => 'blue',
                'url'     => '/admin/users',
            ]);
        }

        return redirect()->route('register')->with('registered', true);
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
