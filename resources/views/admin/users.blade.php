@extends('layouts.app')

@section('title', 'User Management - Dekorasi Drive')
@section('page-title', 'User Management')

@section('content')
<div class="panel overflow-hidden">
    <div class="p-4 md:p-6 border-b border-navy-600">
        <h2 class="text-lg font-semibold text-white">Semua User</h2>
    </div>
    
    <div class="space-y-2 md:space-y-3 p-3 md:p-4">
        @foreach($users as $user)
        <div class="panel !rounded-xl p-3 md:p-4 hover-lift">
            <div class="flex items-center gap-3 md:gap-4">
                <!-- Avatar -->
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full overflow-hidden bg-gradient-to-br from-gold-500 to-gold-600 flex items-center justify-center text-navy-900 font-bold flex-shrink-0">
                    @if($user->avatar)
                        <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 md:gap-2 flex-wrap">
                        <p class="font-medium text-white text-sm md:text-base truncate">{{ $user->name }}</p>
                        <span class="px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium {{ $user->isAdmin() ? 'bg-purple-500/20 text-purple-300' : 'bg-navy-600 text-slate-300' }}">{{ ucfirst($user->role) }}</span>
                        <span class="px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium {{ $user->is_active ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
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
                        <button type="submit" class="w-8 h-8 md:w-9 md:h-9 rounded-lg {{ $user->is_active ? 'bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400' : 'bg-green-500/20 hover:bg-green-500/30 text-green-400' }} flex items-center justify-center transition" title="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                            <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }} text-sm"></i>
                        </button>
                    </form>
                    <form action="{{ route('admin.users.reset-storage', $user) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="w-8 h-8 md:w-9 md:h-9 rounded-lg bg-purple-500/20 hover:bg-purple-500/30 text-purple-400 flex items-center justify-center transition" title="Hitung ulang penyimpanan">
                            <i class="fas fa-redo text-sm"></i>
                        </button>
                    </form>
                    @if($user->id !== auth()->id())
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
    
    <div class="p-4 border-t border-navy-600">
        {{ $users->links() }}
    </div>
</div>
@endsection
