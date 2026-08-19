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
        
        /* Embossed Card Effect */
        .embossed-card {
            background: linear-gradient(145deg, rgba(22, 42, 82, 0.9) 0%, rgba(15, 31, 61, 0.95) 100%);
            border: 1px solid rgba(212, 168, 67, 0.25);
            border-radius: 24px;
            box-shadow: 
                /* Outer glow */
                0 0 60px rgba(212, 168, 67, 0.15),
                /* Main shadow */
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                /* Bottom shadow for depth */
                0 40px 80px -20px rgba(0, 0, 0, 0.6),
                /* Inset highlight top */
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                /* Inset shadow bottom */
                inset 0 -1px 0 rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
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
            background: linear-gradient(90deg, transparent, rgba(212, 168, 67, 0.5), transparent);
        }
        
        .embossed-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(212, 168, 67, 0.03) 0%, transparent 50%);
            pointer-events: none;
        }
        
        /* Embossed Input Fields */
        .embossed-input {
            background: linear-gradient(145deg, rgba(10, 22, 40, 0.8) 0%, rgba(10, 22, 40, 0.6) 100%);
            border: 1px solid rgba(212, 168, 67, 0.2);
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                inset 2px 2px 4px rgba(0, 0, 0, 0.3),
                inset -1px -1px 2px rgba(255, 255, 255, 0.05),
                0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .embossed-input::placeholder { 
            color: rgba(255, 255, 255, 0.35); 
        }
        
        .embossed-input:focus {
            background: linear-gradient(145deg, rgba(10, 22, 40, 0.9) 0%, rgba(10, 22, 40, 0.7) 100%);
            border-color: #d4a843;
            box-shadow: 
                inset 2px 2px 4px rgba(0, 0, 0, 0.3),
                inset -1px -1px 2px rgba(255, 255, 255, 0.05),
                0 0 0 3px rgba(212, 168, 67, 0.2),
                0 4px 12px rgba(212, 168, 67, 0.15);
        }
        
        /* Embossed Button */
        .embossed-btn {
            background: linear-gradient(145deg, #e4be5a 0%, #d4a843 50%, #b8912e 100%);
            color: #0a1628;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 4px 15px rgba(212, 168, 67, 0.4),
                0 8px 25px rgba(212, 168, 67, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.3),
                inset 0 -1px 0 rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .embossed-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.2) 0%, transparent 100%);
            border-radius: 24px 24px 0 0;
            pointer-events: none;
        }
        
        .embossed-btn:hover {
            background: linear-gradient(145deg, #f0d06a 0%, #e4be5a 50%, #d4a843 100%);
            transform: translateY(-2px);
            box-shadow: 
                0 8px 25px rgba(212, 168, 67, 0.5),
                0 15px 40px rgba(212, 168, 67, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.4),
                inset 0 -1px 0 rgba(0, 0, 0, 0.1);
        }
        
        .embossed-btn:active {
            transform: translateY(0);
            box-shadow: 
                0 2px 8px rgba(212, 168, 67, 0.3),
                inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Embossed Input Container */
        .input-container {
            background: linear-gradient(145deg, rgba(10, 22, 40, 0.6) 0%, rgba(10, 22, 40, 0.4) 100%);
            border: 1px solid rgba(212, 168, 67, 0.15);
            border-radius: 16px;
            box-shadow: 
                inset 2px 2px 5px rgba(0, 0, 0, 0.3),
                inset -1px -1px 3px rgba(255, 255, 255, 0.03),
                4px 4px 10px rgba(0, 0, 0, 0.2),
                -2px -2px 8px rgba(255, 255, 255, 0.02);
            transition: all 0.3s ease;
        }
        
        .input-container:focus-within {
            border-color: rgba(212, 168, 67, 0.4);
            box-shadow: 
                inset 2px 2px 5px rgba(0, 0, 0, 0.3),
                inset -1px -1px 3px rgba(255, 255, 255, 0.03),
                4px 4px 10px rgba(0, 0, 0, 0.2),
                -2px -2px 8px rgba(255, 255, 255, 0.02),
                0 0 20px rgba(212, 168, 67, 0.1);
        }
        
        /* Icon container emboss */
        .icon-emboss {
            background: linear-gradient(145deg, rgba(212, 168, 67, 0.15) 0%, rgba(212, 168, 67, 0.08) 100%);
            box-shadow: 
                inset 1px 1px 2px rgba(255, 255, 255, 0.1),
                inset -1px -1px 2px rgba(0, 0, 0, 0.2);
        }
        
        /* Logo emboss */
        .logo-emboss {
            box-shadow: 
                0 10px 40px rgba(212, 168, 67, 0.3),
                0 20px 60px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2),
                inset 0 -1px 0 rgba(0, 0, 0, 0.2);
        }
        
        /* Title text glow */
        .title-glow {
            text-shadow: 0 0 40px rgba(212, 168, 67, 0.3);
        }
        
        /* Subtitle */
        .subtitle-emboss {
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        /* Checkbox emboss */
        .checkbox-emboss {
            background: linear-gradient(145deg, rgba(10, 22, 40, 0.8) 0%, rgba(10, 22, 40, 0.6) 100%);
            border: 1px solid rgba(212, 168, 67, 0.3);
            box-shadow: 
                inset 1px 1px 2px rgba(0, 0, 0, 0.3),
                inset -1px -1px 1px rgba(255, 255, 255, 0.05);
        }
        
        .checkbox-emboss:checked {
            background: linear-gradient(145deg, #d4a843 0%, #b8912e 100%);
            border-color: #d4a843;
        }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo" class="w-20 h-20 mx-auto rounded-2xl logo-emboss mb-4">
            <h1 class="text-3xl font-bold text-white mb-2 title-glow">Dekorasi Drive</h1>
            <p class="text-white/60 subtitle-emboss">Secure File Storage</p>
        </div>
        
        <!-- Login Card - Embossed -->
        <div class="embossed-card p-8 md:p-10">
            <h2 class="text-xl font-semibold text-white mb-8 title-glow">Sign In</h2>
            
            @if($errors->any())
            <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-xl text-red-200 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                {{ $errors->first() }}
            </div>
            @endif
            
            <form method="POST" action="{{ route('login') }}">
                @csrf
                
                <div class="mb-5">
                    <label class="block text-white/80 text-sm mb-2 font-medium">Email Address</label>
                    <div class="input-container">
                        <div class="flex items-center">
                            <div class="pl-4 icon-emboss w-10 h-10 rounded-xl flex items-center justify-center">
                                <i class="fas fa-envelope text-[#d4a843]"></i>
                            </div>
                            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                                class="embossed-input w-full px-4 py-3.5 rounded-xl text-white outline-none"
                                placeholder="Enter your email">
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-white/80 text-sm mb-2 font-medium">Password</label>
                    <div class="input-container">
                        <div class="flex items-center">
                            <div class="pl-4 icon-emboss w-10 h-10 rounded-xl flex items-center justify-center">
                                <i class="fas fa-lock text-[#d4a843]"></i>
                            </div>
                            <input type="password" name="password" required
                                class="embossed-input w-full px-4 py-3.5 rounded-xl text-white outline-none"
                                placeholder="Enter your password">
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between mb-8">
                    <label class="flex items-center text-white/70 text-sm cursor-pointer">
                        <input type="checkbox" name="remember" class="checkbox-emboss w-4 h-4 rounded mr-3 accent-[#d4a843]">
                        Remember me
                    </label>
                </div>
                
                <button type="submit" class="embossed-btn w-full py-4 rounded-xl font-bold text-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </form>
            
            <div class="mt-8 text-center">
                <p class="text-white/50 text-sm">
                    Don't have an account? 
                    <a href="{{ route('register') }}" class="text-[#d4a843] hover:text-[#e4be5a] font-semibold transition">Create Account</a>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <p class="text-center text-white/30 text-sm mt-8">
            &copy; {{ date('Y') }} Dekorasi.me - Premium File Storage
        </p>
    </div>
</body>
</html>
