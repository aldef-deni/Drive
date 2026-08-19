<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dekorasi Drive')</title>
    <link rel="icon" href="{{ asset('logo-dekorasi.png') }}" type="image/png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .premium-shadow {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .hover-lift {
            transition: all 0.3s ease;
        }
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .sidebar-link {
            transition: all 0.2s ease;
        }
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
        }
        .sidebar-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid #818cf8;
        }
        .file-drop-zone {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        .file-drop-zone.dragover {
            border-color: #818cf8;
            background: rgba(129, 140, 248, 0.1);
        }
        .progress-bar {
            background: linear-gradient(90deg, #818cf8, #6366f1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            transform: translateY(-1px);
        }
        .modal-overlay {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        @auth
        <aside class="w-64 gradient-bg text-white flex-shrink-0 flex flex-col">
            <!-- Logo -->
            <div class="p-6 border-b border-white/10">
                <a href="{{ route('drive.index') }}" class="flex items-center gap-3">
                    <img src="{{ asset('logo-dekorasi.png') }}" alt="Logo" class="w-10 h-10 rounded-lg">
                    <span class="font-bold text-lg">Dekorasi Drive</span>
                </a>
            </div>
            
            <!-- Navigation -->
            <nav class="flex-1 p-4 space-y-2">
                <a href="{{ route('drive.index') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('drive.index') ? 'active' : '' }}">
                    <i class="fas fa-hard-drive w-5"></i>
                    <span>My Drive</span>
                </a>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('drive.hidden') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('drive.hidden') ? 'active' : '' }}">
                    <i class="fas fa-eye-slash w-5"></i>
                    <span>Hidden Files</span>
                </a>
                @endif
                
                @if(auth()->user()->isAdmin())
                <div class="pt-4 mt-4 border-t border-white/10">
                    <p class="px-4 text-xs text-white/50 uppercase tracking-wider mb-2">Admin</p>
                    <a href="{{ route('admin.index') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('admin.index') ? 'active' : '' }}">
                        <i class="fas fa-cog w-5"></i>
                        <span>Admin Panel</span>
                    </a>
                    <a href="{{ route('admin.users') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                        <i class="fas fa-users w-5"></i>
                        <span>User Management</span>
                    </a>
                    <a href="{{ route('admin.lock-management') }}" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg {{ request()->routeIs('admin.lock-management') ? 'active' : '' }}">
                        <i class="fas fa-lock w-5"></i>
                        <span>Lock Management</span>
                    </a>
                </div>
                @endif
            </nav>
            
            <!-- Storage Info -->
            <div class="p-4 border-t border-white/10">
                <div class="glass-card rounded-lg p-4">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-white/70">Storage</span>
                        <span class="text-white">{{ auth()->user()->formatStorage(auth()->user()->storage_used) }} / {{ auth()->user()->formatStorage(auth()->user()->storage_quota) }}</span>
                    </div>
                    <div class="w-full bg-white/20 rounded-full h-2">
                        <div class="progress-bar h-2 rounded-full" style="width: {{ auth()->user()->getStoragePercentage() }}%"></div>
                    </div>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="p-4 border-t border-white/10">
                <a href="{{ route('profile.show') }}" class="flex items-center gap-3 p-2 rounded-lg hover:bg-white/10 transition group">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center font-bold flex-shrink-0">
                        @if(auth()->user()->avatar)
                            <img src="{{ asset('storage/avatars/' . auth()->user()->avatar) }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-white/50 truncate">{{ auth()->user()->email }}</p>
                    </div>
                    <i class="fas fa-user-edit text-white/30 group-hover:text-white/70 transition text-sm"></i>
                </a>
                <div class="mt-2 flex justify-end">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-white/50 hover:text-white transition text-xs flex items-center gap-1">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        @endauth
        
        <!-- Main Content -->
        <main class="flex-1 overflow-auto">
            <!-- Top Bar -->
            @auth
            <header class="bg-white border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl font-bold text-gray-800">@yield('page-title', 'Dashboard')</h1>
                    <div class="flex items-center gap-4">
                        @yield('header-actions')
                        <!-- Notification Bell -->
                        @php
                            $unreadCount = auth()->user()->unreadNotificationsCount();
                            $latestNotifs = \App\Models\Notification::where('user_id', auth()->id())
                                ->orderByDesc('created_at')->take(5)->get();
                        @endphp
                        <div class="relative">
                            <button onclick="toggleNotifDropdown()" class="relative flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 hover:bg-gray-200 transition">
                                <i class="fas fa-bell text-gray-600"></i>
                                @if($unreadCount > 0)
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center shadow">
                                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                </span>
                                @endif
                            </button>
                            <!-- Dropdown -->
                            <div id="notifPanel" class="hidden absolute right-0 top-12 w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
                                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                                    <h3 class="font-semibold text-gray-800 text-sm">Notifikasi</h3>
                                    @if($unreadCount > 0)
                                    <form action="{{ route('notifications.read-all') }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-700">Tandai semua dibaca</button>
                                    </form>
                                    @endif
                                </div>
                                <div class="max-h-80 overflow-y-auto">
                                    @if($latestNotifs->count() > 0)
                                        @foreach($latestNotifs as $notif)
                                        <a href="{{ $notif->url ? $notif->url : '#' }}" class="block px-4 py-3 hover:bg-gray-50 transition border-b border-gray-50 {{ !$notif->is_read ? 'bg-indigo-50/40' : '' }}">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5
                                                    @if($notif->color === 'amber') bg-amber-100 text-amber-600
                                                    @elseif($notif->color === 'green') bg-green-100 text-green-600
                                                    @elseif($notif->color === 'blue') bg-blue-100 text-blue-600
                                                    @elseif($notif->color === 'red') bg-red-100 text-red-600
                                                    @else bg-gray-100 text-gray-600 @endif">
                                                    <i class="{{ $notif->icon ?? 'fas fa-bell' }} text-xs"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $notif->title }}</p>
                                                    <p class="text-xs text-gray-500 truncate">{{ $notif->message }}</p>
                                                    <p class="text-xs text-gray-400 mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                                                </div>
                                                @if(!$notif->is_read)
                                                <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0 mt-2"></span>
                                                @endif
                                            </div>
                                        </a>
                                        @endforeach
                                    @else
                                        <div class="py-8 text-center">
                                            <i class="fas fa-bell-slash text-gray-300 text-2xl mb-2"></i>
                                            <p class="text-gray-400 text-sm">Belum ada notifikasi</p>
                                        </div>
                                    @endif
                                </div>
                                <a href="{{ route('notifications.index') }}" class="block text-center py-3 text-sm font-medium text-indigo-600 hover:bg-indigo-50 border-t border-gray-100 transition">
                                    Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            @endauth
            
            <!-- Page Content -->
            <div class="p-6">
                @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 flex items-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    {{ session('success') }}
                </div>
                @endif
                
                @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i>
                    {{ session('error') }}
                </div>
                @endif
                
                @yield('content')
            </div>
        </main>
    </div>
    
    <!-- Modals -->
    @yield('modals')
    
    <script>
        // CSRF token setup
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        // AJAX setup
        document.addEventListener('DOMContentLoaded', function() {
            // Toast notifications
            window.showToast = function(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg z-50 flex items-center gap-3 ${
                    type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
                }`;
                toast.innerHTML = `
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <span>${message}</span>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            };
        });

        function toggleNotifDropdown() {
            const panel = document.getElementById('notifPanel');
            panel.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
document.addEventListener('click', function(e) {
            const panel = document.getElementById('notifPanel');
            const btn = panel?.previousElementSibling;
            if (panel && !panel.contains(e.target) && !btn?.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
