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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            min-height: 100%;
            /* Jangan kunci scroll: pada layar pendek kartu form bisa terpotong. */
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        :root {
            --navy-900: #0a1628;
            --navy-800: #0f1f3d;
            --navy-700: #162a52;
            --navy-600: #1d3566;
            --gold-500: #d4a843;
            --gold-400: #e4be5a;
            --gold-600: #b8912e;
        }
        
        /* Premium Background Navy + Gold */
        .login-bg {
            background: 
                radial-gradient(ellipse at 20% 20%, rgba(212, 168, 67, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(212, 168, 67, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(22, 42, 82, 0.5) 0%, transparent 70%),
                linear-gradient(135deg, #060d18 0%, #0a1628 25%, #0f1f3d 50%, #162a52 75%, #0f1f3d 100%);
            min-height: 100vh;
            display: flex;
            padding: 2rem 0;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated gold particles */
        .login-bg::before {
            content: '';
            /* fixed: partikel dekoratif tidak boleh ikut menambah tinggi halaman */
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            z-index: 0;
            background: 
                radial-gradient(2px 2px at 20% 30%, rgba(212, 168, 67, 0.3) 0%, transparent 100%),
                radial-gradient(2px 2px at 40% 70%, rgba(212, 168, 67, 0.2) 0%, transparent 100%),
                radial-gradient(2px 2px at 60% 20%, rgba(212, 168, 67, 0.25) 0%, transparent 100%),
                radial-gradient(2px 2px at 80% 50%, rgba(212, 168, 67, 0.15) 0%, transparent 100%),
                radial-gradient(1px 1px at 10% 80%, rgba(212, 168, 67, 0.2) 0%, transparent 100%),
                radial-gradient(1px 1px at 70% 90%, rgba(212, 168, 67, 0.2) 0%, transparent 100%),
                radial-gradient(1px 1px at 90% 10%, rgba(212, 168, 67, 0.15) 0%, transparent 100%);
            animation: float 20s ease-in-out infinite;
            pointer-events: none;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        
        /* Gold line accent */
        .login-bg::after {
            content: '';
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212, 168, 67, 0.4), transparent);
        }
        
        /* Embossed Card */
        .embossed-card {
            background: linear-gradient(145deg, rgba(15, 31, 61, 0.98) 0%, rgba(10, 22, 40, 0.99) 100%);
            border: 1px solid rgba(212, 168, 67, 0.35);
            border-radius: 24px;
            box-shadow: 
                0 0 100px rgba(212, 168, 67, 0.15),
                0 25px 50px -10px rgba(0, 0, 0, 0.5),
                0 40px 80px -20px rgba(0, 0, 0, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                inset 0 -1px 0 rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .embossed-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(212, 168, 67, 0.7), transparent);
        }
        
        /* Premium Input Field */
        .input-premium {
            position: relative;
            background: linear-gradient(145deg, #060d18 0%, #0a1628 100%);
            border: 1px solid rgba(212, 168, 67, 0.25);
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                inset 4px 4px 8px rgba(0, 0, 0, 0.5),
                inset -2px -2px 6px rgba(255, 255, 255, 0.03),
                0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .input-premium:focus-within {
            border-color: rgba(212, 168, 67, 0.6);
            box-shadow: 
                inset 4px 4px 8px rgba(0, 0, 0, 0.5),
                inset -2px -2px 6px rgba(255, 255, 255, 0.03),
                0 0 0 4px rgba(212, 168, 67, 0.12),
                0 8px 20px rgba(212, 168, 67, 0.1);
        }
        
        .input-premium input {
            background: transparent;
            border: none;
            color: white;
            width: 100%;
            padding: 14px 50px 14px 50px;
            font-size: 15px;
            outline: none;
            border-radius: 16px;
        }
        
        .input-premium input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .input-premium .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #d4a843;
            font-size: 15px;
            opacity: 0.9;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .input-premium:focus-within .input-icon {
            opacity: 1;
            color: #e4be5a;
        }
        
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #d4a843;
            background: rgba(212, 168, 67, 0.1);
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
            border-radius: 0 0 16px 16px;
        }
        
        .input-premium:focus-within::after {
            width: 70%;
        }
        
        /* Premium Button */
        .btn-premium {
            background: linear-gradient(145deg, #e4be5a 0%, #d4a843 30%, #c99a35 60%, #b8912e 100%);
            color: #0a1628;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.5px;
            border-radius: 16px;
            padding: 16px 24px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 6px 20px rgba(212, 168, 67, 0.35),
                inset 0 2px 0 rgba(255, 255, 255, 0.4);
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
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.3) 0%, transparent 100%);
            border-radius: 16px 16px 0 0;
            pointer-events: none;
        }
        
        .btn-premium:hover {
            background: linear-gradient(145deg, #f0d06a 0%, #e4be5a 30%, #d4a843 60%, #c99a35 100%);
            transform: translateY(-2px);
            box-shadow: 
                0 10px 30px rgba(212, 168, 67, 0.45),
                inset 0 2px 0 rgba(255, 255, 255, 0.45);
        }
        
        .btn-premium:active {
            transform: translateY(0);
            box-shadow: 
                0 2px 10px rgba(212, 168, 67, 0.3),
                inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Logo */
        .logo-emboss {
            box-shadow: 
                0 15px 45px rgba(212, 168, 67, 0.4),
                0 30px 80px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.25),
                inset 0 -1px 0 rgba(0, 0, 0, 0.3);
        }
        
        .title-glow {
            text-shadow: 
                0 0 60px rgba(212, 168, 67, 0.4),
                0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        /* Divider */
        .divider-gold {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212, 168, 67, 0.3), transparent);
        }
        
        /* Scrollbar for card if needed */
        .embossed-card::-webkit-scrollbar {
            width: 4px;
        }
        .embossed-card::-webkit-scrollbar-track {
            background: transparent;
        }
        .embossed-card::-webkit-scrollbar-thumb {
            background: rgba(212, 168, 67, 0.3);
            border-radius: 2px;
        }
    </style>
</head>
<body class="login-bg">
    <div class="w-full max-w-md px-4 relative z-10">
        <!-- Logo -->
        <div class="text-center mb-5">
            <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo" class="w-16 h-16 mx-auto rounded-2xl logo-emboss mb-3">
            <h1 class="text-2xl font-bold text-white title-glow">Dekorasi Drive</h1>
            <p class="text-white/40 text-sm mt-1">Buat Akun Baru</p>
        </div>
        
        <!-- Register Card -->
        <div class="embossed-card p-6 md:p-7">
            <h2 class="text-xl font-bold text-white mb-5 title-glow">Daftar</h2>
            
            @if($errors->any())
            <div class="mb-4 p-3 bg-red-500/20 border border-red-500/50 rounded-xl text-red-200 text-sm">
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
                
                <div class="mb-3">
                    <label class="block text-white/60 text-sm mb-1.5 font-medium">Nama Lengkap</label>
                    <div class="input-premium">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus
                            placeholder="Masukkan nama lengkap">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="block text-white/60 text-sm mb-1.5 font-medium">Alamat Email</label>
                    <div class="input-premium">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            placeholder="Masukkan email Anda">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="block text-white/60 text-sm mb-1.5 font-medium">Password</label>
                    <div class="input-premium">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" required
                            placeholder="Minimal 8 karakter">
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="block text-white/60 text-sm mb-1.5 font-medium">Konfirmasi Password</label>
                    <div class="input-premium">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password_confirmation" id="password_confirmation" required
                            placeholder="Ulangi password">
                        <span class="password-toggle" onclick="togglePasswordConfirm()">
                            <i class="fas fa-eye" id="eyeIconConfirm"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn-premium w-full">
                    <i class="fas fa-user-plus mr-2"></i> Buat Akun
                </button>
            </form>
            
            <div class="mt-5 pt-4 divider-gold">
                <p class="text-center text-white/35 text-sm">
                    Sudah punya akun? 
                    <a href="{{ route('login') }}" class="text-[#d4a843] hover:text-[#e4be5a] font-semibold transition">Masuk</a>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <p class="text-center text-white/20 text-xs mt-4">
            &copy; {{ date('Y') }} Dekorasi.me
        </p>
    </div>

    <!-- Verification Pending Modal -->
    @if(session('registered'))
    <div id="verifyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(6, 13, 24, 0.95); backdrop-filter: blur(12px);">
        <div class="embossed-card p-7 md:p-8 max-w-sm w-full text-center" id="verifyCard">
            <div class="w-20 h-20 mx-auto mb-5 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center shadow-lg shadow-green-500/30">
                <i class="fas fa-check text-white text-3xl"></i>
            </div>
            
            <h2 class="text-xl font-bold text-white mb-2 title-glow">Registrasi Berhasil!</h2>
            
            <div class="w-12 h-0.5 bg-gradient-to-r from-transparent via-[#d4a843] to-transparent mx-auto my-4"></div>
            
            <p class="text-white/60 text-sm mb-5">
                Akun Anda telah berhasil dibuat.
            </p>
            
            <div class="input-premium rounded-2xl p-5 mb-6">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-amber-500/20 flex items-center justify-center">
                    <i class="fas fa-clock text-amber-400 text-xl animate-pulse"></i>
                </div>
                <p class="text-white font-semibold">Mohon Tunggu</p>
                <p class="text-white/40 text-xs mt-1">Untuk verifikasi oleh Admin Dekorasi</p>
            </div>
            
            <a href="{{ route('login') }}" class="btn-premium block w-full">
                <i class="fas fa-sign-in-alt mr-2"></i> Menuju Halaman Login
            </a>
            
            <p class="text-white/25 text-xs mt-4">
                <i class="fas fa-shield-alt mr-1"></i> Akun akan aktif setelah diverifikasi admin
            </p>
        </div>
    </div>
    @endif

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
        
        function togglePasswordConfirm() {
            const passwordInput = document.getElementById('password_confirmation');
            const eyeIcon = document.getElementById('eyeIconConfirm');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
        
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
