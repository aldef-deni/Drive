@extends('layouts.app')

@section('title', 'User Management - Dekorasi Drive')
@section('page-title', 'User Management')

@section('content')
<div class="panel overflow-hidden">
    <div class="p-4 md:p-6 border-b border-navy-600 flex flex-col sm:flex-row sm:items-center gap-3">
        <h2 class="text-lg font-semibold text-white flex-1">Manajemen User</h2>

        {{-- Filter perusahaan, khusus superadmin --}}
        @if($companies->count() > 0)
        <form action="{{ route('admin.users') }}" method="GET" class="self-start">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <select name="company" onchange="this.form.submit()" class="field !py-2 text-sm">
                <option value="">Semua perusahaan</option>
                @foreach($companies as $c)
                <option value="{{ $c->id }}" {{ (string) $companyId === (string) $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
                @endforeach
            </select>
        </form>
        @endif

        {{-- Filter status --}}
        <div class="flex items-center gap-1 p-1 bg-navy-900 rounded-xl self-start">
            @foreach([
                'all' => 'Semua',
                'pending' => 'Menunggu',
                'active' => 'Aktif',
            ] as $key => $label)
            <a href="{{ route('admin.users', $key === 'all' ? [] : ['filter' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $filter === $key ? 'bg-gold-500 text-navy-900' : 'text-slate-400 hover:text-white' }}">
                {{ $label }}
                @if($key === 'pending' && $pendingCount > 0)
                <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] {{ $filter === $key ? 'bg-navy-900/20' : 'bg-amber-500/20 text-amber-300' }}">{{ $pendingCount }}</span>
                @endif
            </a>
            @endforeach
        </div>
    </div>
    
    <div class="space-y-2 md:space-y-3 p-3 md:p-4">
        @foreach($users as $user)
        <div class="panel !rounded-xl p-3 md:p-4 hover-lift">
            <div class="flex items-center gap-3 md:gap-4">
                <!-- Avatar -->
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full overflow-hidden bg-gradient-to-br from-gold-500 to-gold-600 flex items-center justify-center text-navy-900 font-bold flex-shrink-0">
                    @if($avatarUrl = $user->avatarUrl())
                        <img src="{{ $avatarUrl }}" alt="" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 md:gap-2 flex-wrap">
                        <p class="font-medium text-white text-sm md:text-base truncate">{{ $user->name }}</p>
                        <span class="px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium
                            @if($user->isSuperAdmin()) bg-gold-500/20 text-gold-300
                            @elseif($user->isAdmin()) bg-purple-500/20 text-purple-300
                            @else bg-navy-600 text-slate-300 @endif">{{ $user->roleLabel() }}</span>
                        @if($user->company)
                        <span class="px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium bg-blue-500/15 text-blue-300">
                            <i class="fas fa-building mr-1"></i>{{ $user->company->name }}
                        </span>
                        @endif
                        <span class="px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium {{ $user->is_active ? 'bg-green-500/20 text-green-300' : 'bg-amber-500/20 text-amber-300' }}">{{ $user->is_active ? 'Aktif' : 'Menunggu verifikasi' }}</span>
                    </div>
                    <p class="text-[10px] md:text-xs text-slate-400 mt-1 truncate">{{ $user->email }} · {{ $user->files_count }} file · Bergabung {{ $user->created_at->format('d M Y') }}</p>
                    <!-- Storage Bar -->
                    <div class="flex items-center gap-2 mt-2">
                        <div class="flex-1 max-w-xs">
                            <div class="w-full bg-navy-900 rounded-full h-1.5 md:h-2">
                                <div class="progress-bar h-1.5 md:h-2 rounded-full" style="width: {{ $user->getStoragePercentage() }}%"></div>
                            </div>
                        </div>
                        <span class="text-[10px] md:text-xs text-slate-400">{{ $user->formatStorage($user->storage_used) }} / {{ $user->formatStorage($user->storage_quota) }}</span>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center gap-1 md:gap-2 flex-shrink-0">
                    <a href="{{ route('admin.users.edit', $user) }}" class="w-8 h-8 md:w-9 md:h-9 rounded-lg bg-navy-600 hover:bg-navy-500 text-gold-500 flex items-center justify-center transition" title="Edit user">
                        <i class="fas fa-edit text-sm"></i>
                    </a>
                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="w-8 h-8 md:w-9 md:h-9 rounded-lg {{ $user->is_active ? 'bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400' : 'bg-green-500/20 hover:bg-green-500/30 text-green-400' }} flex items-center justify-center transition" title="{{ $user->is_active ? 'Nonaktifkan' : 'Verifikasi & aktifkan' }}">
                            <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }} text-sm"></i>
                        </button>
                    </form>
                    <form action="{{ route('admin.users.reset-storage', $user) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="w-8 h-8 md:w-9 md:h-9 rounded-lg bg-purple-500/20 hover:bg-purple-500/30 text-purple-400 flex items-center justify-center transition" title="Hitung ulang penyimpanan">
                            <i class="fas fa-redo text-sm"></i>
                        </button>
                    </form>
                    @if($user->id !== auth()->id() && auth()->user()->canManage($user))
                    <form action="{{ route('admin.users.delete', $user) }}" method="POST" class="inline" onsubmit="return confirm('Hapus user ini beserta seluruh filenya?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-8 h-8 md:w-9 md:h-9 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-400 flex items-center justify-center transition" title="Hapus user">
                            <i class="fas fa-trash text-sm"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    @if($users->count() === 0)
    <div class="py-16 text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-navy-700 flex items-center justify-center">
            <i class="fas fa-users text-3xl text-slate-600"></i>
        </div>
        <p class="text-slate-400 text-sm">Tidak ada user pada filter ini.</p>
    </div>
    @endif

    <div class="p-4 border-t border-navy-600">
        {{ $users->links() }}
    </div>
</div>
@endsection
