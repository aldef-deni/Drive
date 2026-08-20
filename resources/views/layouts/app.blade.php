<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a1628">
    <meta name="description" content="Dekorasi Drive — penyimpanan file dan folder yang aman untuk tim Dekorasi.me">
    <title>@yield('title', 'Dekorasi Drive')</title>
    <link rel="icon" href="{{ asset('logo-dekorasi.png') }}" type="image/png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: {
                            950: '#060d18',
                            900: '#0a1628',
                            850: '#0e1a2e',
                            800: '#0f1f3d',
                            700: '#162a52',
                            600: '#1d3566',
                            500: '#253f70',
                        },
                        gold: {
                            300: '#f0d06a',
                            400: '#e4be5a',
                            500: '#d4a843',
                            600: '#b8912e',
                        },
                    },
                    fontFamily: {
                        sans: ['Figtree', 'Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        soft: '0 1px 2px rgba(0,0,0,.25), 0 12px 32px -18px rgba(0,0,0,.65)',
                        lifted: '0 18px 40px -22px rgba(0,0,0,.9)',
                    },
                },
            },
        };
    </script>

    <style>
        /* ============================================================
           Dekorasi Drive — tema Navy + Gold
           ============================================================ */
        :root {
            --navy-950: #060d18;
            --navy-900: #0a1628;
            --navy-850: #0e1a2e;
            --navy-800: #0f1f3d;
            --navy-700: #162a52;
            --navy-600: #1d3566;
            --gold-400: #e4be5a;
            --gold-500: #d4a843;
            --gold-600: #b8912e;
        }

        body {
            background-color: var(--navy-850);
            background-image:
                radial-gradient(900px 500px at 12% -10%, rgba(212, 168, 67, .07), transparent 60%),
                radial-gradient(700px 420px at 100% 0%, rgba(29, 53, 102, .45), transparent 60%);
            background-attachment: fixed;
            color: #e2e8f0;
        }

        .gradient-bg {
            background: linear-gradient(180deg, #0a1628 0%, #0f1f3d 55%, #162a52 100%);
        }

        .glass-card {
            background: rgba(255, 255, 255, .05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 168, 67, .15);
        }

        /* --- Kartu / panel --- */
        .panel {
            background: linear-gradient(160deg, rgba(15, 31, 61, .95), rgba(10, 22, 40, .95));
            border: 1px solid var(--navy-600);
            border-radius: 1rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .25), 0 12px 32px -18px rgba(0, 0, 0, .65);
        }

        .hover-lift {
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .hover-lift:hover {
            transform: translateY(-2px);
            border-color: rgba(212, 168, 67, .45);
            box-shadow: 0 18px 40px -22px rgba(0, 0, 0, .9);
        }

        /* --- Sidebar --- */
        .sidebar-link {
            transition: all .2s ease;
            border-left: 3px solid transparent;
        }
        .sidebar-link:hover {
            background: rgba(212, 168, 67, .1);
            transform: translateX(4px);
        }
        .sidebar-link.active {
            background: rgba(212, 168, 67, .15);
            border-left-color: var(--gold-500);
            color: var(--gold-400);
        }

        /* --- Form --- */
        .field {
            width: 100%;
            padding: .75rem 1rem;
            color: #fff;
            background: var(--navy-700);
            border: 1px solid var(--navy-600);
            border-radius: .75rem;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .field::placeholder { color: #7c8ba5; }
        .field:focus {
            border-color: rgba(212, 168, 67, .7);
            box-shadow: 0 0 0 3px rgba(212, 168, 67, .18);
            background: var(--navy-800);
        }
        .field option { background: var(--navy-700); color: #fff; }
        /* Ikon date/time picker bawaan browser agar terlihat di latar gelap */
        .field::-webkit-calendar-picker-indicator { filter: invert(.8) sepia(1) saturate(4) hue-rotate(2deg); cursor: pointer; }

        .label { display: block; font-size: .875rem; font-weight: 500; color: #cbd5e1; margin-bottom: .375rem; }

        /* --- Tombol --- */
        .btn-primary {
            background: linear-gradient(135deg, var(--gold-500), var(--gold-600));
            color: var(--navy-900);
            font-weight: 600;
            transition: all .25s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--gold-400), var(--gold-500));
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(212, 168, 67, .3);
        }
        .btn-primary:disabled { opacity: .5; transform: none; box-shadow: none; cursor: not-allowed; }

        .btn-ghost {
            background: var(--navy-700);
            color: #e2e8f0;
            border: 1px solid var(--navy-600);
            font-weight: 500;
            transition: all .2s ease;
        }
        .btn-ghost:hover { background: var(--navy-600); border-color: rgba(212, 168, 67, .35); }

        /* --- Drop zone & progress --- */
        .file-drop-zone {
            border: 2px dashed rgba(212, 168, 67, .4);
            transition: all .3s ease;
        }
        .file-drop-zone.dragover {
            border-color: var(--gold-500);
            background: rgba(212, 168, 67, .05);
        }
        .progress-bar { background: linear-gradient(90deg, var(--gold-500), var(--gold-600)); }

        .modal-overlay {
            background: rgba(6, 13, 24, .78);
            backdrop-filter: blur(6px);
        }

        /* --- Scrollbar --- */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--navy-900); }
        ::-webkit-scrollbar-thumb { background: rgba(212, 168, 67, .55); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-400); }

        /* --- Sidebar mobile --- */
        .sidebar-overlay {
            background: rgba(10, 22, 40, .7);
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
        }
        .sidebar-overlay.active { opacity: 1; pointer-events: auto; }

        .sidebar-mobile { transform: translateX(-100%); transition: transform .3s ease; }
        .sidebar-mobile.active { transform: translateX(0); }
        @media (min-width: 768px) { .sidebar-mobile { transform: none; } }

        /* --- Toast --- */
        .toast {
            animation: toast-in .28s cubic-bezier(.34, 1.56, .64, 1);
            box-shadow: 0 18px 40px -18px rgba(0, 0, 0, .9);
        }
        @keyframes toast-in {
            from { opacity: 0; transform: translateY(14px) scale(.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Target sentuh nyaman di mobile */
        @media (max-width: 768px) {
            .tap-target { min-height: 44px; min-width: 44px; }
        }

        @media print {
            aside, header, .no-print { display: none !important; }
        }
    </style>
    @stack('styles')
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex">
        <!-- Sidebar Overlay (Mobile) -->
        <div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 z-40 md:hidden" onclick="closeSidebar()"></div>

        <!-- Sidebar -->
        @auth
        <aside id="sidebar" class="sidebar-mobile fixed md:static inset-y-0 left-0 w-64 gradient-bg text-white flex-shrink-0 flex flex-col z-50 border-r border-navy-600/70">
            <!-- Logo -->
            <div class="p-4 md:p-6 border-b border-white/10 flex items-center justify-between">
                <a href="{{ route('drive.index') }}" class="flex items-center gap-3 min-w-0">
                    <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo Dekorasi" class="w-9 h-9 md:w-10 md:h-10 rounded-xl ring-1 ring-gold-500/40">
                    <span class="min-w-0">
                        <span class="block font-bold text-base leading-tight truncate">Dekorasi Drive</span>
                        <span class="block text-[11px] text-white/40 tracking-wide">Penyimpanan Aman</span>
                    </span>
                </a>
                <button onclick="closeSidebar()" aria-label="Tutup menu" class="md:hidden w-9 h-9 rounded-lg hover:bg-white/10 flex items-center justify-center">
                    <i class="fas fa-times text-white/70"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 p-4 space-y-1.5 overflow-y-auto">
                <p class="px-4 text-[11px] text-white/40 uppercase tracking-wider mb-2">Menu</p>
                <a href="{{ route('drive.index') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('drive.index') ? 'active' : '' }}">
                    <i class="fas fa-hard-drive w-5"></i>
                    <span>Drive Saya</span>
                </a>
                <a href="{{ route('notifications.index') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('notifications.index') ? 'active' : '' }}">
                    <i class="fas fa-bell w-5"></i>
                    <span>Notifikasi</span>
                    @if(auth()->user()->unreadNotificationsCount() > 0)
                    <span class="ml-auto px-2 py-0.5 rounded-full bg-gold-500 text-navy-900 text-[10px] font-bold">{{ auth()->user()->unreadNotificationsCount() }}</span>
                    @endif
                </a>
                <a href="{{ route('profile.show') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('profile.show') ? 'active' : '' }}">
                    <i class="fas fa-user w-5"></i>
                    <span>Profil Saya</span>
                </a>

                @if(auth()->user()->isAdmin())
                <div class="pt-4 mt-4 border-t border-white/10 space-y-1.5">
                    <p class="px-4 text-[11px] text-white/40 uppercase tracking-wider mb-2">Admin</p>
                    <a href="{{ route('admin.index') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('admin.index') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high w-5"></i>
                        <span>Dashboard Admin</span>
                    </a>
                    <a href="{{ route('admin.users') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                        <i class="fas fa-users w-5"></i>
                        <span>Manajemen User</span>
                    </a>
                    <a href="{{ route('admin.hidden') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('admin.hidden') ? 'active' : '' }}">
                        <i class="fas fa-user-secret w-5"></i>
                        <span>Hidden System</span>
                    </a>
                </div>
                @endif
            </nav>

            <!-- Storage Info -->
            @php
                $storagePercent = auth()->user()->getStoragePercentage();
            @endphp
            <div class="p-4 border-t border-white/10">
                <div class="glass-card rounded-xl p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs text-white/60 uppercase tracking-wider">Penyimpanan</span>
                        <span class="text-xs font-semibold {{ $storagePercent >= 90 ? 'text-red-400' : ($storagePercent >= 75 ? 'text-amber-400' : 'text-gold-400') }}">{{ number_format($storagePercent, 0) }}%</span>
                    </div>
                    <div class="w-full bg-white/10 rounded-full h-2 overflow-hidden">
                        <div class="progress-bar h-2 rounded-full transition-all duration-500" style="width: {{ max($storagePercent, 1.5) }}%"></div>
                    </div>
                    <p class="mt-2 text-[11px] text-white/50">
                        {{ auth()->user()->formatStorage(auth()->user()->storage_used) }} dari {{ auth()->user()->formatStorage(auth()->user()->storage_quota) }}
                    </p>
                </div>
            </div>

            <!-- User Menu -->
            <div class="p-4 border-t border-white/10">
                <a href="{{ route('profile.show') }}" class="flex items-center gap-3 p-2 rounded-xl hover:bg-white/10 transition group">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-gold-500 to-gold-600 flex items-center justify-center font-bold text-navy-900 flex-shrink-0">
                        @if($avatarUrl = auth()->user()->avatarUrl())
                            <img src="{{ $avatarUrl }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-white/50 truncate">{{ auth()->user()->email }}</p>
                    </div>
                    <i class="fas fa-chevron-right text-white/25 group-hover:text-white/60 transition text-xs"></i>
                </a>
                <form action="{{ route('logout') }}" method="POST" class="mt-2">
                    @csrf
                    <button type="submit" class="w-full py-2 rounded-lg text-white/50 hover:text-white hover:bg-white/5 transition text-xs flex items-center justify-center gap-2">
                        <i class="fas fa-arrow-right-from-bracket"></i> Keluar
                    </button>
                </form>
            </div>
        </aside>
        @endauth

        <!-- Main Content -->
        <main class="flex-1 overflow-auto min-w-0">
            <!-- Top Bar -->
            @auth
            <header class="sticky top-0 z-30 bg-navy-800/90 backdrop-blur border-b border-navy-600 px-4 md:px-6 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <button onclick="openSidebar()" aria-label="Buka menu" class="md:hidden tap-target w-10 h-10 rounded-xl bg-navy-700 hover:bg-navy-600 flex items-center justify-center transition flex-shrink-0">
                            <i class="fas fa-bars text-gold-500"></i>
                        </button>
                        <h1 class="text-base md:text-xl font-bold text-white truncate">@yield('page-title', 'Dashboard')</h1>
                    </div>

                    <div class="flex items-center gap-2 md:gap-3 min-w-0">
                        @yield('header-actions')

                        <!-- Notifikasi -->
                        @php
                            $unreadCount = auth()->user()->unreadNotificationsCount();
                            $latestNotifs = \App\Models\Notification::where('user_id', auth()->id())
                                ->orderByDesc('created_at')->take(5)->get();
                        @endphp
                        <div class="relative flex-shrink-0">
                            <button onclick="toggleNotifDropdown(event)" aria-label="Notifikasi" class="relative flex items-center justify-center w-10 h-10 rounded-xl bg-navy-700 hover:bg-navy-600 transition">
                                <i class="fas fa-bell text-gold-500"></i>
                                @if($unreadCount > 0)
                                <span class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow">
                                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                </span>
                                @endif
                            </button>

                            <div id="notifPanel" class="hidden absolute right-0 top-12 w-[min(20rem,calc(100vw-2rem))] panel z-50 overflow-hidden">
                                <div class="p-4 border-b border-navy-600 flex items-center justify-between gap-2">
                                    <h3 class="font-semibold text-white text-sm">Notifikasi</h3>
                                    @if($unreadCount > 0)
                                    <form action="{{ route('notifications.read-all') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-xs text-gold-500 hover:text-gold-400">Tandai semua dibaca</button>
                                    </form>
                                    @endif
                                </div>
                                <div class="max-h-80 overflow-y-auto">
                                    @forelse($latestNotifs as $notif)
                                        <a href="{{ route('notifications.read', $notif) }}" class="block px-4 py-3 hover:bg-navy-700 transition border-b border-navy-600/70 last:border-0 {{ !$notif->is_read ? 'bg-navy-700/50' : '' }}">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5
                                                    @if($notif->color === 'amber') bg-amber-500/20 text-amber-400
                                                    @elseif($notif->color === 'green') bg-green-500/20 text-green-400
                                                    @elseif($notif->color === 'blue') bg-blue-500/20 text-blue-400
                                                    @elseif($notif->color === 'red') bg-red-500/20 text-red-400
                                                    @else bg-slate-500/20 text-slate-400 @endif">
                                                    <i class="{{ $notif->icon ?? 'fas fa-bell' }} text-xs"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-white truncate">{{ $notif->title }}</p>
                                                    <p class="text-xs text-slate-400 truncate">{{ $notif->message }}</p>
                                                    <p class="text-[11px] text-slate-500 mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                                                </div>
                                                @if(!$notif->is_read)
                                                <span class="w-2 h-2 rounded-full bg-gold-500 flex-shrink-0 mt-2"></span>
                                                @endif
                                            </div>
                                        </a>
                                    @empty
                                        <div class="py-8 text-center">
                                            <i class="fas fa-bell-slash text-slate-500 text-2xl mb-2"></i>
                                            <p class="text-slate-400 text-sm">Belum ada notifikasi</p>
                                        </div>
                                    @endforelse
                                </div>
                                <a href="{{ route('notifications.index') }}" class="block text-center py-3 text-sm font-medium text-gold-500 hover:bg-navy-700 border-t border-navy-600 transition">
                                    Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            @endauth

            <!-- Page Content -->
            <div class="p-4 md:p-6 min-h-[calc(100vh-64px)]">
                @if(session('success'))
                <div class="mb-4 md:mb-6 p-4 bg-green-500/10 border border-green-500/40 rounded-xl text-green-300 flex items-center gap-3">
                    <i class="fas fa-circle-check text-green-400"></i>
                    <span class="text-sm">{{ session('success') }}</span>
                </div>
                @endif

                @if(session('error'))
                <div class="mb-4 md:mb-6 p-4 bg-red-500/10 border border-red-500/40 rounded-xl text-red-300 flex items-center gap-3">
                    <i class="fas fa-circle-exclamation text-red-400"></i>
                    <span class="text-sm">{{ session('error') }}</span>
                </div>
                @endif

                @if($errors->any() && !$errors->has('password'))
                <div class="mb-4 md:mb-6 p-4 bg-red-500/10 border border-red-500/40 rounded-xl text-red-300">
                    <p class="text-sm font-medium mb-1"><i class="fas fa-circle-exclamation mr-2"></i>Periksa kembali isian Anda</p>
                    <ul class="text-xs list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <!-- Modals -->
    @yield('modals')

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        function openSidebar() {
            document.getElementById('sidebar').classList.add('active');
            document.getElementById('sidebarOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            document.getElementById('sidebar')?.classList.remove('active');
            document.getElementById('sidebarOverlay')?.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Toast notification
        window.showToast = function (message, type = 'success') {
            document.querySelectorAll('.toast').forEach(t => t.remove());

            const toast = document.createElement('div');
            const tone = type === 'success'
                ? 'bg-green-600/95 border-green-400/50'
                : 'bg-red-600/95 border-red-400/50';

            toast.className = `toast fixed bottom-4 left-4 right-4 md:left-auto md:right-6 md:w-auto md:max-w-sm px-4 py-3 rounded-xl border text-white z-[60] flex items-start gap-3 ${tone}`;
            toast.setAttribute('role', 'status');
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'} mt-0.5"></i>
                <span class="text-sm leading-snug flex-1"></span>
            `;
            toast.querySelector('span').textContent = message ?? '';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3200);
        };

        function toggleNotifDropdown(event) {
            event?.stopPropagation();
            document.getElementById('notifPanel')?.classList.toggle('hidden');
        }

        // Tutup dropdown saat klik di luar
        document.addEventListener('click', function (e) {
            const panel = document.getElementById('notifPanel');
            if (panel && !panel.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });

        // Tutup dengan Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.getElementById('notifPanel')?.classList.add('hidden');
                closeSidebar();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768) closeSidebar();
        });
    </script>
    @stack('scripts')
</body>
</html>
