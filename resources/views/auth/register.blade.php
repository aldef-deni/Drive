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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --navy-900: #0a1628;
            --navy-800: #0f1f3d;
            --navy-700: #162a52;
            --navy-600: #1d3566;
            --gold-500: #d4a843;
            --gold-400: #e4be5a;
            --gold-600: #b8912e;
        }
        
        .login-bg {
            background: linear-gradient(135deg, #0a1628 0%, #0f1f3d 50%, #162a52 100%);
            min-height: 100vh;
        }
        
        /* Embossed Card */
        .embossed-card {
            background: linear-gradient(145deg, rgba(22, 42, 82, 0.95) 0%, rgba(15, 31, 61, 0.98) 100%);
            border: 1px solid rgba(212, 168, 67, 0.3);
            border-radius: 28px;
            box-shadow: 
                0 0 80px rgba(212, 168, 67, 0.12),
                0 30px 60px -15px rgba(0, 0, 0, 0.5),
                0 50px 100px -25px rgba(0, 0, 0, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                inset 0 -1px 0 rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .embossed-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212, 168, 67, 0.6), transparent);
        }
        
        /* Premium Input Field */
        .input-premium {
            position: relative;
            background: linear-gradient(145deg, #080e1a 0%, #0c1526 100%);
            border: 1px solid rgba(212, 168, 67, 0.2);
            border-radius: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                inset 3px 3px 6px rgba(0, 0, 0, 0.5),
                inset -2px -2px 4px rgba(255, 255, 255, 0.03),
                0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .input-premium:focus-within {
            border-color: rgba(212, 168, 67, 0.5);
            box-shadow: 
                inset 3px 3px 6px rgba(0, 0, 0, 0.5),
                inset -2px -2px 4px rgba(255, 255, 255, 0.03),
                0 0 0 3px rgba(212, 168, 67, 0.15),
                0 4px 15px rgba(212, 168, 67, 0.1);
        }
        
        .input-premium input {
            background: transparent;
            border: none;
            color: white;
            width: 100%;
            padding: 16px 16px 16px 48px;
            font-size: 15px;
            outline: none;
        }
        
        .input-premium input::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }
        
        .input-premium .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #d4a843;
            font-size: 16px;
            opacity: 0.8;
            transition: all 0.3s ease;
        }
        
        .input-premium:focus-within .input-icon {
            opacity: 1;
            color: #e4be5a;
        }
        
        /* Gold Accent Line */
        .input-premium::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #d4a843, transparent);
            transition: width 0.3s ease;
            border-radius: 0 0 14px 14px;
        }
        
        .input-premium:focus-within::after {
            width: 80%;
        }
        
        /* Premium Button */
        .btn-premium {
            background: linear-gradient(145deg, #e4be5a 0%, #d4a843 40%, #c99a35 70%, #b8912e 100%);
            color: #0a1628;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.5px;
            border-radius: 14px;
            padding: 18px 24px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 6px 20px rgba(212, 168, 67, 0.4),
                0 12px 35px rgba(212, 168, 67, 0.2),
                inset 0 2px 0 rgba(255, 255, 255, 0.35),
                inset 0 -2px 0 rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .btn-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.25) 0%, transparent 100%);
            border-radius: 14px 14px 0 0;
            pointer-events: none;
        }
        
        .btn-premium:hover {
            background: linear-gradient(145deg, #f0d06a 0%, #e4be5a 40%, #d4a843 70%, #c99a35 100%);
            transform: translateY(-3px);
            box-shadow: 
                0 10px 30px rgba(212, 168, 67, 0.5),
                0 20px 50px rgba(212, 168, 67, 0.25),
                inset 0 2px 0 rgba(255, 255, 255, 0.4),
                inset 0 -2px 0 rgba(0, 0, 0, 0.15);
        }
        
        .btn-premium:active {
            transform: translateY(-1px);
            box-shadow: 
                0 4px 12px rgba(212, 168, 67, 0.3),
                inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Logo */
        .logo-emboss {
            box-shadow: 
                0 12px 40px rgba(212, 168, 67, 0.35),
                0 25px 70px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.2),
                inset 0 -1px 0 rgba(0, 0, 0, 0.3);
        }
        
        .title-glow {
            text-shadow: 0 0 50px rgba(212, 168, 67, 0.4);
        }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo" class="w-20 h-20 mx-auto rounded-2xl logo-emboss mb-4">
            <h1 class="text-3xl font-bold text-white mb-2 title-glow">Dekorasi Drive</h1>
            <p class="text-white/50 text-sm">Create Your Account</p>
        </div>
        
        <!-- Register Card -->
        <div class="embossed-card p-8 md:p-10">
            <h2 class="text-2xl font-bold text-white mb-8 title-glow">Sign Up</h2>
            
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
                    <label class="block text-white/70 text-sm mb-3 font-medium">Full Name</label>
                    <div class="input-premium">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus
                            placeholder="Enter your name">
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="block text-white/70 text-sm mb-3 font-medium">Email Address</label>
                    <div class="input-premium">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            placeholder="Enter your email">
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="block text-white/70 text-sm mb-3 font-medium">Password</label>
                    <div class="input-premium">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" required
                            placeholder="Create a password">
                    </div>
                </div>
                
                <div class="mb-8">
                    <label class="block text-white/70 text-sm mb-3 font-medium">Confirm Password</label>
                    <div class="input-premium">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password_confirmation" required
                            placeholder="Confirm your password">
                    </div>
                </div>
                
                <button type="submit" class="btn-premium w-full">
                    <i class="fas fa-user-plus mr-2"></i> Create Account
                </button>
            </form>
            
            <div class="mt-8 pt-6 border-t border-white/10 text-center">
                <p class="text-white/40 text-sm">
                    Already have an account? 
                    <a href="{{ route('login') }}" class="text-[#d4a843] hover:text-[#e4be5a] font-semibold transition">Sign In</a>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <p class="text-center text-white/25 text-xs mt-8">
            &copy; {{ date('Y') }} Dekorasi.me - Premium File Storage
        </p>
    </div>

    <!-- Verification Pending Modal -->
    @if(session('registered'))
    <div id="verifyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(10, 22, 40, 0.95); backdrop-filter: blur(12px);">
        <div class="embossed-card p-8 md:p-10 max-w-md w-full text-center" id="verifyCard">
            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center shadow-lg shadow-green-500/30">
                <i class="fas fa-check text-white text-4xl"></i>
            </div>
            
            <h2 class="text-2xl font-bold text-white mb-3 title-glow">Registrasi Berhasil!</h2>
            
            <div class="w-16 h-0.5 bg-gradient-to-r from-transparent via-[#d4a843] to-transparent mx-auto mb-6"></div>
            
            <p class="text-white/70 text-base mb-6">
                Akun Anda telah berhasil dibuat.
            </p>
            
            <div class="input-premium rounded-2xl p-6 mb-8">
                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-amber-500/20 flex items-center justify-center">
                    <i class="fas fa-clock text-amber-400 text-2xl animate-pulse"></i>
                </div>
                <p class="text-white font-semibold text-lg">Mohon Tunggu</p>
                <p class="text-white/50 text-sm mt-1">Untuk verifikasi oleh Admin Dekorasi</p>
            </div>
            
            <a href="{{ route('login') }}" class="btn-premium block w-full">
                <i class="fas fa-sign-in-alt mr-2"></i> Menuju Halaman Login
            </a>
            
            <p class="text-white/30 text-xs mt-6">
                <i class="fas fa-shield-alt mr-1"></i> Akun akan aktif setelah diverifikasi admin
            </p>
        </div>
    </div>
    @endif

    <script>
    @if(session('registered'))
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
