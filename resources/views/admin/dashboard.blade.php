@extends('layouts.app')

@section('title', 'Admin Dashboard - Dekorasi Drive')
@section('page-title', 'Admin Dashboard')

@section('content')
<!-- Stats Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-6 md:mb-8">
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-[#d4a843]/20 flex items-center justify-center">
                <i class="fas fa-users text-lg md:text-2xl text-[#d4a843]"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">Total Users</p>
                <p class="text-xl md:text-2xl font-bold text-white">{{ $stats['total_users'] }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-green-500/20 flex items-center justify-center">
                <i class="fas fa-user-check text-lg md:text-2xl text-green-400"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">Active</p>
                <p class="text-xl md:text-2xl font-bold text-white">{{ $stats['active_users'] }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-purple-500/20 flex items-center justify-center">
                <i class="fas fa-hdd text-lg md:text-2xl text-purple-400"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">Storage</p>
                <p class="text-xl md:text-2xl font-bold text-white">{{ \App\Models\User::formatStorageSize($stats['total_storage_used']) }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-[#d4a843]/20 flex items-center justify-center">
                <i class="fas fa-file text-lg md:text-2xl text-[#d4a843]"></i>
            </div>
            <div>
                <p class="text-xs md:text-sm text-slate-400">Files</p>
                <p class="text-xl md:text-2xl font-bold text-white">{{ $stats['total_files'] }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] overflow-hidden">
    <div class="p-4 md:p-6 border-b border-[#1d3566]">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">User Management</h2>
            <a href="{{ route('admin.users') }}" class="text-[#d4a843] hover:text-[#e4be5a] text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Mobile User List -->
    <div class="md:hidden divide-y divide-[#1d3566]">
        @foreach($users as $user)
        <div class="p-4 hover:bg-[#162a52] transition">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-[#d4a843] to-[#b8912e] flex items-center justify-center text-[#0a1628] font-bold flex-shrink-0">
                    @if($user->avatar)
                        <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-white text-sm truncate">{{ $user->name }}</p>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-medium {{ $user->isAdmin() ? 'bg-purple-500/20 text-purple-300' : 'bg-[#1d3566] text-slate-300' }}">{{ ucfirst($user->role) }}</span>
                    </div>
                    <p class="text-[10px] text-slate-400 truncate">{{ $user->email }}</p>
                </div>
                <div class="flex items-center gap-1">
                    <a href="{{ route('admin.users.edit', $user) }}" class="w-8 h-8 rounded-lg bg-[#1d3566] text-[#d4a843] flex items-center justify-center">
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
            <tr class="border-b border-[#1d3566]">
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">User</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">Role</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">Storage</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">Files</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-slate-300">Status</th>
                <th class="text-right px-6 py-4 text-sm font-semibold text-slate-300">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr class="border-b border-[#1d3566]/50 hover:bg-[#162a52] transition">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-[#d4a843] to-[#b8912e] flex items-center justify-center text-[#0a1628] font-bold">
                            @if($user->avatar)
                                <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
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
                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $user->isAdmin() ? 'bg-purple-500/20 text-purple-300' : 'bg-[#1d3566] text-slate-300' }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <div class="flex-1">
                            <div class="w-full bg-[#0a1628] rounded-full h-2">
                                <div class="progress-bar h-2 rounded-full" style="width: {{ $user->getStoragePercentage() }}%"></div>
                            </div>
                        </div>
                        <span class="text-xs text-slate-300">{{ number_format($user->getStoragePercentage(), 1) }}%</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">{{ $user->formatStorage($user->storage_used) }} / {{ $user->formatStorage($user->storage_quota) }}</p>
                </td>
                <td class="px-6 py-4 text-sm text-slate-300">{{ $user->files_count }}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' }}">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.edit', $user) }}" 
                            class="w-9 h-9 rounded-lg bg-[#1d3566] hover:bg-[#253f70] text-[#d4a843] flex items-center justify-center transition" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" 
                                class="w-9 h-9 rounded-lg {{ $user->is_active ? 'bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400' : 'bg-green-500/20 hover:bg-green-500/30 text-green-400' }} flex items-center justify-center transition" title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="p-4 border-t border-[#1d3566]">
        {{ $users->links() }}
    </div>
</div>
@endsection
