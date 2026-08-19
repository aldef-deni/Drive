<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Dekorasi Drive</title>
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
            <p class="text-white/70">Secure File Storage</p>
        </div>
        
        <!-- Login Card -->
        <div class="glass-card rounded-3xl p-8 shadow-2xl">
            <h2 class="text-xl font-semibold text-white mb-6">Sign In</h2>
            
            @if($errors->any())
            <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-xl text-red-200 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                {{ $errors->first() }}
            </div>
            @endif
            
            <form method="POST" action="{{ route('login') }}">
                @csrf
                
                <div class="mb-5">
                    <label class="block text-white/80 text-sm mb-2">Email Address</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-white/50">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                            class="input-field w-full pl-12 pr-4 py-3 rounded-xl text-white placeholder-white/50 outline-none"
                            placeholder="Enter your email">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-white/80 text-sm mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-white/50">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" required
                            class="input-field w-full pl-12 pr-4 py-3 rounded-xl text-white placeholder-white/50 outline-none"
                            placeholder="Enter your password">
                    </div>
                </div>
                
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center text-white/70 text-sm">
                        <input type="checkbox" name="remember" class="mr-2 rounded">
                        Remember me
                    </label>
                </div>
                
                <button type="submit" class="btn-gradient w-full py-3 rounded-xl text-white font-semibold">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-white/60 text-sm">
                    Don't have an account? 
                    <a href="{{ route('register') }}" class="text-indigo-300 hover:text-indigo-200 font-medium">Create Account</a>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <p class="text-center text-white/40 text-sm mt-8">
            &copy; {{ date('Y') }} Dekorasi.me - Premium File Storage
        </p>
    </div>
</body>
</html>
