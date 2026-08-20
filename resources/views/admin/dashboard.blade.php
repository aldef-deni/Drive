@extends('layouts.app')

@section('title', 'Admin Dashboard - Dekorasi Drive')
@section('page-title', 'Admin Dashboard')

@section('content')
<!-- Stats Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-5 mb-6 md:mb-8">
    <div class="panel p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-gold-500/15 flex items-center justify-center">
                <i class="fas fa-users text-lg md:text-2xl text-gold-500"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">Total User</p>
                <p class="text-xl md:text-2xl font-bold text-white">{{ $stats['total_users'] }}</p>
            </div>
        </div>
    </div>
    
    <div class="panel p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-green-500/20 flex items-center justify-center">
                <i class="fas fa-user-check text-lg md:text-2xl text-green-400"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">Aktif</p>
                <p class="text-xl md:text-2xl font-bold text-white">{{ $stats['active_users'] }}</p>
            </div>
        </div>
    </div>
    
    <div class="panel p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-purple-500/20 flex items-center justify-center">
                <i class="fas fa-hard-drive text-lg md:text-2xl text-purple-400"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">Penyimpanan</p>
                <p class="text-xl md:text-2xl font-bold text-white">{{ \App\Models\User::formatStorageSize($stats['total_storage_used']) }}</p>
            </div>
        </div>
    </div>
    
    <div class="panel p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-gold-500/15 flex items-center justify-center">
                <i class="fas fa-file text-lg md:text-2xl text-gold-500"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">File</p>
                <p class="text-xl md:text-2xl font-bold text-white">{{ $stats['total_files'] }}</p>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.users', ['filter' => 'pending']) }}"
       class="panel p-4 md:p-6 hover-lift block {{ $stats['pending_users'] > 0 ? 'border-amber-500/50' : '' }}">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-amber-500/15 flex items-center justify-center">
                <i class="fas fa-user-clock text-lg md:text-2xl text-amber-400"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">Menunggu Verifikasi</p>
                <p class="text-xl md:text-2xl font-bold {{ $stats['pending_users'] > 0 ? 'text-amber-300' : 'text-white' }}">{{ $stats['pending_users'] }}</p>
            </div>
        </div>
    </a>
</div>

@if($pendingUsers->count() > 0)
<!-- Antrean verifikasi akun baru -->
<div class="panel overflow-hidden mb-6 md:mb-8 border-amber-500/40">
    <div class="p-4 md:p-6 border-b border-navy-600 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-user-clock text-amber-400"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h2 class="text-base font-semibold text-white">Menunggu Verifikasi</h2>
            <p class="text-xs text-slate-400">Akun baru belum bisa login sebelum Anda aktifkan.</p>
        </div>
    </div>

    <div class="divide-y divide-navy-600/60">
        @foreach($pendingUsers as $pending)
        <div class="p-4 flex items-center gap-3 hover:bg-navy-700/50 transition">
            <div class="w-10 h-10 rounded-full bg-amber-500/15 text-amber-300 font-bold flex items-center justify-center flex-shrink-0">
                {{ strtoupper(substr($pending->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-medium text-white text-sm truncate">{{ $pending->name }}</p>
                <p class="text-xs text-slate-400 truncate">{{ $pending->email }} &middot; daftar {{ $pending->created_at->diffForHumans() }}</p>
            </div>
            <form action="{{ route('admin.users.toggle-status', $pending) }}" method="POST" class="flex-shrink-0">
                @csrf
                <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-xs whitespace-nowrap">
                    <i class="fas fa-check mr-1.5"></i>Verifikasi
                </button>
            </form>
            <form action="{{ route('admin.users.delete', $pending) }}" method="POST" class="flex-shrink-0"
                  onsubmit="return confirm('Tolak dan hapus pendaftaran {{ $pending->name }}?')">
                @csrf
                @method('DELETE')
                <button type="submit" title="Tolak pendaftaran"
                    class="w-9 h-9 rounded-xl bg-red-500/15 hover:bg-red-500/25 text-red-400 flex items-center justify-center transition">
                    <i class="fas fa-xmark text-sm"></i>
                </button>
            </form>
        </div>
        @endforeach
    </div>
</div>
@endif

<!-- Users Table -->
<div class="panel overflow-hidden">
    <div class="p-4 md:p-6 border-b border-navy-600">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Manajemen User</h2>
            <a href="{{ route('admin.users') }}" class="text-gold-500 hover:text-gold-400 text-sm font-medium">
                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Mobile User List -->
    <div class="md:hidden divide-y divide-[#1d3566]">
        @foreach($users as $user)
        <div class="p-4 hover:bg-navy-700 transition">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-gold-500 to-gold-600 flex items-center justify-center text-navy-900 font-bold flex-shrink-0">
                    @if($avatarUrl = $user->avatarUrl())
                        <img src="{{ $avatarUrl }}" alt="" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-white text-sm truncate">{{ $user->name }}</p>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium {{ $user->isAdmin() ? 'bg-purple-500/20 text-purple-300' : 'bg-navy-600 text-slate-300' }}">{{ ucfirst($user->role) }}</span>
                    </div>
                    <p class="text-[10px] text-slate-400 truncate">{{ $user->email }}</p>
                </div>
                <div class="flex items-center gap-1">
                    <a href="{{ route('admin.users.edit', $user) }}" class="w-8 h-8 rounded-lg bg-navy-600 text-gold-500 flex items-center justify-center">
                        <i class="fas fa-edit text-xs"></i>
                    </a>
                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="w-8 h-8 rounded-lg {{ $user->is_active ? 'bg-yellow-500/20 text-yellow-400' : 'bg-green-500/20 text-green-400' }} flex items-center justify-center">
                            <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }} text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    <!-- Desktop Table -->
    <table class="w-full hidden md:table">
        <thead>
            <tr class="border-b border-navy-600">
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">User</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">Peran</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">Penyimpanan</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">File</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">Status</th>
                <th class="text-right px-6 py-4 text-sm font-semibold text-slate-300">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr class="border-b border-navy-600/50 hover:bg-navy-700 transition">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-gold-500 to-gold-600 flex items-center justify-center text-navy-900 font-bold">
                            @if($avatarUrl = $user->avatarUrl())
                                <img src="{{ $avatarUrl }}" alt="" class="w-full h-full object-cover">
                            @else
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            @endif
                        </div>
                        <div>
                            <p class="font-medium text-white">{{ $user->name }}</p>
                            <p class="text-xs text-slate-400">{{ $user->email }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $user->isAdmin() ? 'bg-purple-500/20 text-purple-300' : 'bg-navy-600 text-slate-300' }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <div class="flex-1">
                            <div class="w-full bg-navy-900 rounded-full h-2">
                                <div class="progress-bar h-2 rounded-full" style="width: {{ $user->getStoragePercentage() }}%"></div>
                            </div>
                        </div>
                        <span class="text-xs text-slate-300">{{ number_format($user->getStoragePercentage(), 1) }}%</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">{{ $user->formatStorage($user->storage_used) }} / {{ $user->formatStorage($user->storage_quota) }}</p>
                </td>
                <td class="px-6 py-4 text-sm text-slate-300">{{ $user->files_count }}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-500/20 text-green-300' : 'bg-amber-500/20 text-amber-300' }}">
                        {{ $user->is_active ? 'Aktif' : 'Menunggu' }}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.edit', $user) }}" 
                            class="w-9 h-9 rounded-lg bg-navy-600 hover:bg-navy-500 text-gold-500 flex items-center justify-center transition" title="Edit user">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" 
                                class="w-9 h-9 rounded-lg {{ $user->is_active ? 'bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400' : 'bg-green-500/20 hover:bg-green-500/30 text-green-400' }} flex items-center justify-center transition" title="{{ $user->is_active ? 'Nonaktifkan' : 'Verifikasi & aktifkan' }}">
                                <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="p-4 border-t border-navy-600">
        {{ $users->links() }}
    </div>
</div>
@endsection
