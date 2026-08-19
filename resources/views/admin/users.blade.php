@extends('layouts.app')

@section('title', 'User Management - Dekorasi Drive')
@section('page-title', 'User Management')

@section('content')
<div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] overflow-hidden">
    <div class="p-6 border-b border-[#1d3566]">
        <h2 class="text-lg font-semibold text-white">All Users</h2>
    </div>
    
    <div class="space-y-3 p-4">
        @foreach($users as $user)
        <div class="bg-[#162a52] rounded-xl border border-[#1d3566] p-4 hover-lift">
            <div class="flex items-center gap-4">
                <!-- Avatar -->
                <div class="w-12 h-12 rounded-full overflow-hidden bg-gradient-to-br from-[#d4a843] to-[#b8912e] flex items-center justify-center text-[#0a1628] font-bold flex-shrink-0">
                    @if($user->avatar)
                        <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-medium text-white">{{ $user->name }}</p>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $user->isAdmin() ? 'bg-purple-500/20 text-purple-300' : 'bg-[#1d3566] text-gray-300' }}">{{ ucfirst($user->role) }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <p class="text-xs text-slate-300 mt-1">{{ $user->email }} &middot; {{ $user->files_count }} files &middot; Joined {{ $user->created_at->format('d M Y') }}</p>
                    <!-- Storage Bar -->
                    <div class="flex items-center gap-2 mt-2">
                        <div class="flex-1 max-w-xs">
                            <div class="w-full bg-[#0a1628] rounded-full h-2">
                                <div class="progress-bar h-2 rounded-full" style="width: {{ $user->getStoragePercentage() }}%"></div>
                            </div>
                        </div>
                        <span class="text-xs text-slate-300">{{ $user->formatStorage($user->storage_used) }} / {{ $user->formatStorage($user->storage_quota) }} ({{ number_format($user->getStoragePercentage(), 1) }}%)</span>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('admin.users.edit', $user) }}" class="w-9 h-9 rounded-lg bg-[#1d3566] hover:bg-[#253f70] text-[#d4a843] flex items-center justify-center transition" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="w-9 h-9 rounded-lg {{ $user->is_active ? 'bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400' : 'bg-green-500/20 hover:bg-green-500/30 text-green-400' }} flex items-center justify-center transition" title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                            <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                        </button>
                    </form>
                    <form action="{{ route('admin.users.reset-storage', $user) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="w-9 h-9 rounded-lg bg-purple-500/20 hover:bg-purple-500/30 text-purple-400 flex items-center justify-center transition" title="Reset Storage">
                            <i class="fas fa-redo"></i>
                        </button>
                    </form>
                    @if($user->id !== auth()->id())
                    <form action="{{ route('admin.users.delete', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete this user?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-9 h-9 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-400 flex items-center justify-center transition" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    <div class="p-4 border-t border-[#1d3566]">
        {{ $users->links() }}
    </div>
</div>
@endsection
