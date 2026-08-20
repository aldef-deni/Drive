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
            padding: 16px 50px 16px 50px;
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
            font-size: 16px;
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
            right: 16px;
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
            padding: 18px 24px;
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
        
        /* Checkbox */
        .checkbox-premium {
            appearance: none;
            width: 22px;
            height: 22px;
            background: linear-gradient(145deg, #060d18 0%, #0a1628 100%);
            border: 1px solid rgba(212, 168, 67, 0.35);
            border-radius: 7px;
            cursor: pointer;
            position: relative;
            box-shadow: 
                inset 2px 2px 5px rgba(0, 0, 0, 0.5),
                inset -1px -1px 3px rgba(255, 255, 255, 0.05);
            transition: all 0.2s ease;
        }
        
        .checkbox-premium:checked {
            background: linear-gradient(145deg, #d4a843 0%, #b8912e 100%);
            border-color: #d4a843;
        }
        
        .checkbox-premium:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #0a1628;
            font-size: 13px;
            font-weight: bold;
        }
        
        /* Divider */
        .divider-gold {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212, 168, 67, 0.3), transparent);
        }
    </style>
</head>
<body class="login-bg">
    <div class="w-full max-w-md px-4 relative z-10">
        <!-- Logo -->
        <div class="text-center mb-6">
            <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo" class="w-16 h-16 mx-auto rounded-2xl logo-emboss mb-3">
            <h1 class="text-2xl font-bold text-white title-glow">Dekorasi Drive</h1>
            <p class="text-white/40 text-sm mt-1">Penyimpanan File Aman</p>
        </div>
        
        <!-- Login Card -->
        <div class="embossed-card p-7 md:p-8">
            <h2 class="text-xl font-bold text-white mb-6 title-glow">Masuk</h2>
            
            @if($errors->any())
            <div class="mb-5 p-3 bg-red-500/20 border border-red-500/50 rounded-xl text-red-200 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                {{ $errors->first() }}
            </div>
            @endif
            
            <form method="POST" action="{{ route('login') }}">
                @csrf
                
                <div class="mb-4">
                    <label class="block text-white/60 text-sm mb-2 font-medium">Alamat Email</label>
                    <div class="input-premium">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                            placeholder="Masukkan email Anda">
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="block text-white/60 text-sm mb-2 font-medium">Password</label>
                    <div class="input-premium">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" required
                            placeholder="Masukkan password Anda">
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>
                
                <div class="flex items-center mb-6">
                    <input type="checkbox" name="remember" id="remember" class="checkbox-premium">
                    <label for="remember" class="text-white/50 text-sm ml-3 cursor-pointer select-none">Ingat saya</label>
                </div>
                
                <button type="submit" class="btn-premium w-full">
                    <i class="fas fa-right-to-bracket mr-2"></i> Masuk
                </button>
            </form>
            
            <div class="mt-6 pt-5 divider-gold">
                <p class="text-center text-white/35 text-sm">
                    Belum punya akun? 
                    <a href="{{ route('register') }}" class="text-[#d4a843] hover:text-[#e4be5a] font-semibold transition">Daftar Sekarang</a>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <p class="text-center text-white/20 text-xs mt-5">
            &copy; {{ date('Y') }} Dekorasi.me
        </p>
    </div>

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
    </script>
</body>
</html>
