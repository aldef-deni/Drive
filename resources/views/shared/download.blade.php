<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unduh - {{ $file->original_name }}</title>
    <link rel="icon" href="{{ asset('logo-dekorasi.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Figtree, Inter, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background:
                radial-gradient(ellipse at 20% 20%, rgba(212, 168, 67, .08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(212, 168, 67, .06) 0%, transparent 50%),
                linear-gradient(135deg, #060d18 0%, #0a1628 30%, #0f1f3d 65%, #162a52 100%);
            background-attachment: fixed;
        }

        .card {
            background: linear-gradient(145deg, rgba(15, 31, 61, .98), rgba(10, 22, 40, .99));
            border: 1px solid rgba(212, 168, 67, .3);
            border-radius: 24px;
            box-shadow:
                0 0 90px rgba(212, 168, 67, .12),
                0 25px 50px -12px rgba(0, 0, 0, .6);
        }

        .field {
            width: 100%;
            padding: .875rem 1rem;
            color: #fff;
            background: #0a1628;
            border: 1px solid #1d3566;
            border-radius: .875rem;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .field::placeholder { color: #7c8ba5; }
        .field:focus {
            border-color: rgba(212, 168, 67, .7);
            box-shadow: 0 0 0 3px rgba(212, 168, 67, .18);
        }

        .btn-gold {
            background: linear-gradient(135deg, #d4a843, #b8912e);
            color: #0a1628;
            font-weight: 700;
            transition: all .25s ease;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #e4be5a, #d4a843);
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(212, 168, 67, .35);
        }
    </style>
</head>
<body>
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-7">
            <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo Dekorasi" class="w-16 h-16 mx-auto rounded-2xl shadow-2xl mb-3">
            <h1 class="text-2xl font-bold text-white">Dekorasi Drive</h1>
            <p class="text-white/40 text-sm mt-1">Berbagi File Aman</p>
        </div>

        <!-- Kartu unduh -->
        <div class="card p-7 md:p-8">
            <div class="text-center mb-7">
                <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-[#162a52] flex items-center justify-center">
                    <i class="fas {{ $file->getIconClass() }} text-4xl"></i>
                </div>
                <h2 class="text-lg font-semibold text-white mb-1 break-words">{{ $file->original_name }}</h2>
                <p class="text-white/50 text-sm">{{ $file->formatSize() }} &middot; {{ $file->mime_type }}</p>
            </div>

            <!-- Informasi share -->
            <div class="mb-6 p-4 bg-white/5 rounded-xl space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-white/50">Jumlah unduhan</span>
                    <span class="text-white">{{ $share->download_count }}{{ $share->download_limit ? ' / ' . $share->download_limit : '' }}</span>
                </div>
                @if($share->expires_at)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-white/50">Berlaku sampai</span>
                    <span class="text-white">{{ $share->expires_at->format('d M Y H:i') }}</span>
                </div>
                @endif
            </div>

            @if(session('error') || $errors->any())
            <div class="mb-6 p-4 bg-red-500/15 border border-red-500/40 rounded-xl text-red-200 text-sm">
                <i class="fas fa-circle-exclamation mr-2"></i>
                {{ session('error') ?: $errors->first() }}
            </div>
            @endif

            <form action="{{ route('share.download', $share->share_token) }}" method="POST">
                @csrf

                @if($share->hasPassword())
                <div class="mb-5">
                    <label for="sharePassword" class="block text-white/70 text-sm mb-2">
                        <i class="fas fa-lock mr-2"></i>Password diperlukan
                    </label>
                    <input type="password" id="sharePassword" name="password" required class="field" placeholder="Masukkan password untuk mengunduh">
                </div>
                @endif

                <button type="submit" class="btn-gold w-full py-3.5 rounded-xl">
                    <i class="fas fa-download mr-2"></i> Unduh File
                </button>
            </form>
        </div>

        <p class="text-center text-white/25 text-xs mt-7">
            &copy; {{ date('Y') }} Dekorasi.me &middot; Berbagi File Aman
        </p>
    </div>
</body>
</html>
