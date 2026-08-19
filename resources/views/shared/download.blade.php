<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download - {{ $file->original_name }}</title>
    <link rel="icon" href="{{ asset('logo-dekorasi.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .download-bg {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);
            min-height: 100vh;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
    </style>
</head>
<body class="download-bg flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo" class="w-16 h-16 mx-auto rounded-2xl shadow-2xl mb-4">
            <h1 class="text-2xl font-bold text-white mb-2">Dekorasi Drive</h1>
        </div>
        
        <!-- Download Card -->
        <div class="glass-card rounded-3xl p-8 shadow-2xl">
            <!-- File Info -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-white/10 flex items-center justify-center">
                    <i class="fas fa-file text-4xl text-white/80"></i>
                </div>
                <h2 class="text-xl font-semibold text-white mb-2">{{ $file->original_name }}</h2>
                <p class="text-white/60 text-sm">
                    {{ \App\Models\File::formatSize($file->size) }} • 
                    {{ $file->mime_type }}
                </p>
            </div>
            
            <!-- Share Info -->
            <div class="mb-6 p-4 bg-white/5 rounded-xl">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-white/60">Downloads</span>
                    <span class="text-white">{{ $share->download_count }} {{ $share->download_limit ? '/ ' . $share->download_limit : '' }}</span>
                </div>
                @if($share->expires_at)
                <div class="flex items-center justify-between text-sm mt-2">
                    <span class="text-white/60">Expires</span>
                    <span class="text-white">{{ $share->expires_at->format('d M Y H:i') }}</span>
                </div>
                @endif
            </div>
            
            @if(session('error'))
            <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-xl text-red-200 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                {{ session('error') }}
            </div>
            @endif
            
            <!-- Password Form -->
            @if($share->hasPassword())
            <form action="{{ route('share.download', $share->share_token) }}" method="POST" class="mb-6">
                @csrf
                <div class="mb-4">
                    <label class="block text-white/80 text-sm mb-2">
                        <i class="fas fa-lock mr-2"></i>Password Required
                    </label>
                    <input type="password" name="password" required
                        class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-white/50 outline-none"
                        placeholder="Enter password to download">
                </div>
                
                <button type="submit" class="btn-gradient w-full py-3 rounded-xl text-white font-semibold">
                    <i class="fas fa-download mr-2"></i> Download File
                </button>
            </form>
            @else
            <!-- Direct Download -->
            <form action="{{ route('share.download', $share->share_token) }}" method="POST">
                @csrf
                <button type="submit" class="btn-gradient w-full py-4 rounded-xl text-white font-semibold text-lg">
                    <i class="fas fa-download mr-2"></i> Download File
                </button>
            </form>
            @endif
        </div>
        
        <!-- Footer -->
        <p class="text-center text-white/40 text-sm mt-8">
            &copy; {{ date('Y') }} Dekorasi.me - Secure File Sharing
        </p>
    </div>
</body>
</html>
