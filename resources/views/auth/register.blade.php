<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - Dekorasi Drive</title>
    <link rel="icon" href="{{ asset('logo-dekorasi.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .login-bg {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .input-field {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.3);
        }
        .btn-gradient {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            transition: all 0.3s ease;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo" class="w-20 h-20 mx-auto rounded-2xl shadow-2xl mb-4">
            <h1 class="text-3xl font-bold text-white mb-2">Dekorasi Drive</h1>
            <p class="text-white/70">Create Your Account</p>
        </div>
        
        <!-- Register Card -->
        <div class="glass-card rounded-3xl p-8 shadow-2xl">
            <h2 class="text-xl font-semibold text-white mb-6">Sign Up</h2>
            
            @if($errors->any())
            <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-xl text-red-200 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            
            <form method="POST" action="{{ route('register') }}">
                @csrf
                
                <div class="mb-5">
                    <label class="block text-white/80 text-sm mb-2">Full Name</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-white/50">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus
                            class="input-field w-full pl-12 pr-4 py-3 rounded-xl text-white placeholder-white/50 outline-none"
                            placeholder="Enter your name">
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="block text-white/80 text-sm mb-2">Email Address</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-white/50">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="input-field w-full pl-12 pr-4 py-3 rounded-xl text-white placeholder-white/50 outline-none"
                            placeholder="Enter your email">
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="block text-white/80 text-sm mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-white/50">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" required
                            class="input-field w-full pl-12 pr-4 py-3 rounded-xl text-white placeholder-white/50 outline-none"
                            placeholder="Create a password">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-white/80 text-sm mb-2">Confirm Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-white/50">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password_confirmation" required
                            class="input-field w-full pl-12 pr-4 py-3 rounded-xl text-white placeholder-white/50 outline-none"
                            placeholder="Confirm your password">
                    </div>
                </div>
                
                <button type="submit" class="btn-gradient w-full py-3 rounded-xl text-white font-semibold">
                    <i class="fas fa-user-plus mr-2"></i> Create Account
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-white/60 text-sm">
                    Already have an account? 
                    <a href="{{ route('login') }}" class="text-indigo-300 hover:text-indigo-200 font-medium">Sign In</a>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <p class="text-center text-white/40 text-sm mt-8">
            &copy; {{ date('Y') }} Dekorasi.me - Premium File Storage
        </p>
    </div>

    <!-- Verification Pending Modal -->
    @if(session('registered'))
    <div id="verifyModal" class="fixed inset-0 z-50 flex items-center justify-center" style="background: rgba(0,0,0,0.7); backdrop-filter: blur(8px);">
        <div class="glass-card rounded-3xl p-8 max-w-md mx-4 text-center shadow-2xl transform transition-all" id="verifyCard">
            <!-- Animated check icon -->
            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center shadow-lg shadow-green-500/30">
                <i class="fas fa-check text-white text-3xl"></i>
            </div>
            
            <!-- Success animation -->
            <h2 class="text-2xl font-bold text-white mb-3">Registrasi Berhasil!</h2>
            
            <div class="w-16 h-0.5 bg-gradient-to-r from-transparent via-indigo-400 to-transparent mx-auto mb-6"></div>
            
            <p class="text-white/80 text-base leading-relaxed mb-2">
                Akun Anda telah berhasil dibuat.
            </p>
            <div class="bg-white/10 rounded-2xl p-5 mb-8 border border-white/10">
                <div class="flex items-center justify-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-amber-400/20 flex items-center justify-center">
                        <i class="fas fa-clock text-amber-300 text-lg animate-pulse"></i>
                    </div>
                </div>
                <p class="text-white font-semibold text-lg">Mohon Tunggu</p>
                <p class="text-white/70 text-sm mt-1">Untuk verifikasi oleh Admin Dekorasi</p>
            </div>
            
            <a href="{{ route('login') }}" 
                class="block w-full py-3.5 rounded-xl text-white font-semibold text-center transition-all duration-300 hover:-translate-y-0.5"
                style="background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: 0 8px 25px rgba(99,102,241,0.4);">
                <i class="fas fa-sign-in-alt mr-2"></i> Menuju Halaman Login
            </a>
            
            <p class="text-white/40 text-xs mt-6">
                <i class="fas fa-shield-alt mr-1"></i> Akun akan aktif setelah diverifikasi admin
            </p>
        </div>
    </div>
    @endif

    <script>
    @if(session('registered'))
    // Entrance animation
    document.addEventListener('DOMContentLoaded', function() {
        const card = document.getElementById('verifyCard');
        card.style.opacity = '0';
        card.style.transform = 'scale(0.9) translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
            card.style.opacity = '1';
            card.style.transform = 'scale(1) translateY(0)';
        }, 100);
    });
    @endif
    </script>
</body>
</html>
