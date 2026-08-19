@extends('layouts.app')

@section('title', 'User Management - Dekorasi Drive')
@section('page-title', 'User Management')

@section('content')
<div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] overflow-hidden">
    <div class="p-6 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">All Users</h2>
    </div>
    
    <div class="space-y-3">
        @foreach($users as $user)
        <div class="bg-[#0f1f3d] rounded-xl border border-[#1d3566] p-4 hover-lift">
            <div class="flex items-center gap-4">
                <!-- Avatar -->
                <div class="w-12 h-12 rounded-full overflow-hidden bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white font-bold flex-shrink-0">
                    @if($user->avatar)
                        <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-medium text-gray-800">{{ $user->name }}</p>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $user->isAdmin() ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' }}">{{ ucfirst($user->role) }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ $user->email }} &middot; {{ $user->files_count }} files &middot; Joined {{ $user->created_at->format('d M Y') }}</p>
                    <!-- Storage Bar -->
                    <div class="flex items-center gap-2 mt-2">
                        <div class="flex-1 max-w-xs">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="progress-bar h-2 rounded-full" style="width: {{ $user->getStoragePercentage() }}%"></div>
                            </div>
                        </div>
                        <span class="text-xs text-gray-500">{{ $user->formatStorage($user->storage_used) }} / {{ $user->formatStorage($user->storage_quota) }} ({{ number_format($user->getStoragePercentage(), 1) }}%)</span>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('admin.users.edit', $user) }}" class="w-9 h-9 rounded-lg bg-indigo-100 hover:bg-indigo-200 text-indigo-600 flex items-center justify-center transition" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="w-9 h-9 rounded-lg {{ $user->is_active ? 'bg-yellow-100 hover:bg-yellow-200 text-yellow-600' : 'bg-green-100 hover:bg-green-200 text-green-600' }} flex items-center justify-center transition" title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                            <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                        </button>
                    </form>
                    <form action="{{ route('admin.users.reset-storage', $user) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="w-9 h-9 rounded-lg bg-purple-100 hover:bg-purple-200 text-purple-600 flex items-center justify-center transition" title="Reset Storage">
                            <i class="fas fa-redo"></i>
                        </button>
                    </form>
                    @if($user->id !== auth()->id())
                    <form action="{{ route('admin.users.delete', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete this user?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-9 h-9 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    <div class="p-4 border-t border-gray-100">
        {{ $users->links() }}
    </div>
</div>
@endsection
