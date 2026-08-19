@extends('layouts.app')

@section('title', 'Admin Dashboard - Dekorasi Drive')
@section('page-title', 'Admin Dashboard')

@section('content')
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] p-6 hover-lift">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-indigo-100 flex items-center justify-center">
                <i class="fas fa-users text-2xl text-indigo-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-400">Total Users</p>
                <p class="text-2xl font-bold text-white">{{ $stats['total_users'] }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] p-6 hover-lift">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-green-100 flex items-center justify-center">
                <i class="fas fa-user-check text-2xl text-green-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Active Users</p>
                <p class="text-2xl font-bold text-white">{{ $stats['active_users'] }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] p-6 hover-lift">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-purple-100 flex items-center justify-center">
                <i class="fas fa-hdd text-2xl text-purple-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-400">Total Storage</p>
                <p class="text-2xl font-bold text-white">{{ \App\Models\User::formatStorageSize($stats['total_storage_used']) }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] p-6 hover-lift">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-amber-100 flex items-center justify-center">
                <i class="fas fa-file text-2xl text-amber-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-400">Total Files</p>
                <p class="text-2xl font-bold text-white">{{ $stats['total_files'] }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] overflow-hidden">
    <div class="p-6 border-b border-gray-100">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">User Management</h2>
            <a href="{{ route('admin.users') }}" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <table class="w-full">
        <thead>
            <tr class="border-b border-gray-100">
                <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">User</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Role</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Storage</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Files</th>
                <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Status</th>
                <th class="text-right px-6 py-4 text-sm font-semibold text-gray-600">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white font-bold">
                            @if($user->avatar)
                                <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                            @else
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            @endif
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500">{{ $user->email }}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $user->isAdmin() ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <div class="flex-1">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="progress-bar h-2 rounded-full" style="width: {{ $user->getStoragePercentage() }}%"></div>
                            </div>
                        </div>
                        <span class="text-xs text-gray-500">{{ number_format($user->getStoragePercentage(), 1) }}%</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ $user->formatStorage($user->storage_used) }} / {{ $user->formatStorage($user->storage_quota) }}</p>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">{{ $user->files_count }}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.edit', $user) }}" 
                            class="w-9 h-9 rounded-lg bg-indigo-100 hover:bg-indigo-200 text-indigo-600 flex items-center justify-center transition" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" 
                                class="w-9 h-9 rounded-lg {{ $user->is_active ? 'bg-yellow-100 hover:bg-yellow-200 text-yellow-600' : 'bg-green-100 hover:bg-green-200 text-green-600' }} flex items-center justify-center transition" title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="p-4 border-t border-gray-100">
        {{ $users->links() }}
    </div>
</div>
@endsection
