@extends('layouts.app')

@section('title', 'Edit User - Dekorasi Drive')
@section('page-title', 'Edit User')

@section('content')
<div class="max-w-2xl">
    <div class="bg-[#0f1f3d] rounded-2xl border border-[#1d3566] overflow-hidden">
        <div class="p-6 border-b border-[#1d3566]">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full overflow-hidden bg-gradient-to-br from-[#d4a843] to-[#b8912e] flex items-center justify-center text-[#0a1628] text-2xl font-bold">
                    @if($user->avatar)
                        <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-white">{{ $user->name }}</h2>
                    <p class="text-slate-300">{{ $user->email }}</p>
                </div>
            </div>
        </div>
        
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Name</label>
                    <input type="text" name="name" value="{{ $user->name }}" required
                        class="w-full px-4 py-3 border border-[#1d3566] bg-[#162a52] rounded-xl focus:ring-2 focus:ring-[#d4a843] focus:border-transparent outline-none text-white">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" name="email" value="{{ $user->email }}" required
                        class="w-full px-4 py-3 border border-[#1d3566] bg-[#162a52] rounded-xl focus:ring-2 focus:ring-[#d4a843] focus:border-transparent outline-none text-white">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Role</label>
                    <select name="role" 
                        class="w-full px-4 py-3 border border-[#1d3566] bg-[#162a52] rounded-xl focus:ring-2 focus:ring-[#d4a843] focus:border-transparent outline-none text-white">
                        <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>User</option>
                        <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Storage Quota (GB)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="storageQuotaGb" step="0.1" min="0.1"
                            value="{{ round($user->storage_quota / 1073741824, 1) }}"
                            class="w-full px-4 py-3 border border-[#1d3566] bg-[#162a52] rounded-xl focus:ring-2 focus:ring-[#d4a843] focus:border-transparent outline-none text-white">
                        <span class="text-slate-300 font-medium whitespace-nowrap">GB</span>
                    </div>
                    <input type="hidden" name="storage_quota" id="storageQuotaBytes" value="{{ $user->storage_quota }}">
                    <p class="text-xs text-slate-300 mt-1">
                        Current: {{ $user->formatStorage($user->storage_quota) }} | 
                        Used: {{ $user->formatStorage($user->storage_used) }}
                    </p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" onclick="setQuotaGb(0.1)" class="px-3 py-1 bg-[#1d3566] text-gray-300 text-xs rounded-lg hover:bg-[#253f70]">100 MB</button>
                        <button type="button" onclick="setQuotaGb(0.5)" class="px-3 py-1 bg-[#1d3566] text-gray-300 text-xs rounded-lg hover:bg-[#253f70]">500 MB</button>
                        <button type="button" onclick="setQuotaGb(1)" class="px-3 py-1 bg-[#1d3566] text-gray-300 text-xs rounded-lg hover:bg-[#253f70]">1 GB</button>
                        <button type="button" onclick="setQuotaGb(5)" class="px-3 py-1 bg-[#1d3566] text-gray-300 text-xs rounded-lg hover:bg-[#253f70]">5 GB</button>
                        <button type="button" onclick="setQuotaGb(10)" class="px-3 py-1 bg-[#1d3566] text-gray-300 text-xs rounded-lg hover:bg-[#253f70]">10 GB</button>
                    </div>
                </div>
                
                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ $user->is_active ? 'checked' : '' }} class="w-5 h-5 rounded accent-[#d4a843]">
                        <div>
                            <span class="text-sm font-medium text-white">Active Account</span>
                            <p class="text-xs text-slate-300">Allow user to access the system</p>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="flex items-center gap-4 mt-8 pt-6 border-t border-[#1d3566]">
                <a href="{{ route('admin.users') }}" class="px-6 py-3 border border-[#1d3566] rounded-xl text-slate-300 hover:bg-[#162a52] transition">
                    Cancel
                </a>
                <button type="submit" class="flex-1 btn-primary px-6 py-3 rounded-xl font-medium">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const GB = 1073741824;

function setQuotaGb(gb) {
    document.getElementById('storageQuotaGb').value = gb;
    document.getElementById('storageQuotaBytes').value = Math.round(gb * GB);
}

document.getElementById('storageQuotaGb').addEventListener('input', function() {
    const gb = parseFloat(this.value) || 0;
    document.getElementById('storageQuotaBytes').value = Math.round(gb * GB);
});

// Sync on page load
(function() {
    const bytes = parseInt(document.getElementById('storageQuotaBytes').value) || 0;
    document.getElementById('storageQuotaGb').value = (bytes / GB).toFixed(1);
})();
</script>
@endsection
