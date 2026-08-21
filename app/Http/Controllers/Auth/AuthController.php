<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
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
        $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ], [], ['email' => 'email atau username']);

        // Superadministrator masuk memakai username, pengguna lain memakai email.
        // Satu kolom isian menerima keduanya agar tidak perlu dua form terpisah.
        $isian = trim($request->email);
        $kolom = filter_var($isian, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [$kolom => $isian, 'password' => $request->password];

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
            'email' => 'Email/username atau password salah.',
        ])->onlyInput('email');
    }

    /**
     * Show registration form.
     */
    public function showRegister()
    {
        return view('auth.register', [
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Handle registration.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'company_id' => ['required', 'exists:companies,id'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['company_id' => 'perusahaan']);

        $company = Company::where('id', $request->company_id)
            ->where('is_active', true)
            ->first();

        if (!$company) {
            return back()->withInput()
                ->withErrors(['company_id' => 'Perusahaan tidak tersedia atau sedang nonaktif.']);
        }

        if ($company->isFull()) {
            return back()->withInput()
                ->withErrors(['company_id' => 'Jumlah akun di perusahaan ini sudah penuh. Hubungi admin Anda.']);
        }

        // Akun dibuat non-aktif — sesuai alur di halaman register, aktivasi
        // dilakukan admin lewat menu User Management.
        $user = User::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'storage_quota' => $company->default_quota,
            'is_active' => false,
        ]);

        // Beri tahu admin perusahaan yang bersangkutan, plus superadministrator.
        // Admin perusahaan lain tidak perlu tahu — itu bukan urusan mereka.
        $admins = User::where('is_active', true)
            ->where(function ($q) use ($company) {
                $q->where(fn ($w) => $w->where('role', User::ROLE_ADMIN)->where('company_id', $company->id))
                    ->orWhere('role', User::ROLE_SUPERADMIN);
            })
            ->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'type'    => 'new_registration',
                'title'   => 'Pengguna Baru Mendaftar',
                'message' => $request->name . ' (' . $request->email . ') mendaftar di ' . $company->name . ' dan menunggu aktivasi.',
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
