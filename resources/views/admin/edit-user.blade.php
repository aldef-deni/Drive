@extends('layouts.app')

@section('title', 'Edit User - Dekorasi Drive')
@section('page-title', 'Edit User')

@section('content')
<div class="max-w-2xl">
    <div class="panel overflow-hidden">
        <div class="p-4 md:p-6 border-b border-navy-600">
            <div class="flex items-center gap-3 md:gap-4">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-full overflow-hidden bg-gradient-to-br from-gold-500 to-gold-600 flex items-center justify-center text-navy-900 text-xl md:text-2xl font-bold">
                    @if($user->avatar)
                        <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>
                <div>
                    <h2 class="text-lg md:text-xl font-semibold text-white">{{ $user->name }}</h2>
                    <p class="text-sm text-slate-300">{{ $user->email }}</p>
                </div>
            </div>
        </div>
        
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="p-4 md:p-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-5">
                <div>
                    <label class="label">Nama</label>
                    <input type="text" name="name" value="{{ $user->name }}" required
                        class="field">
                </div>
                
                <div>
                    <label class="label">Email</label>
                    <input type="email" name="email" value="{{ $user->email }}" required
                        class="field">
                </div>
                
                <div>
                    <label class="label">Peran</label>
                    <select name="role" 
                        class="field">
                        <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>User</option>
                        <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                </div>
                
                <div>
                    <label class="label">Kuota Penyimpanan (GB)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="storageQuotaGb" step="0.1" min="0.1"
                            value="{{ round($user->storage_quota / 1073741824, 1) }}"
                            class="field">
                        <span class="text-slate-300 font-medium whitespace-nowrap">GB</span>
                    </div>
                    <input type="hidden" name="storage_quota" id="storageQuotaBytes" value="{{ $user->storage_quota }}">
                    <p class="text-xs text-slate-400 mt-1">
                        Kuota saat ini: {{ $user->formatStorage($user->storage_quota) }} | 
                        Terpakai: {{ $user->formatStorage($user->storage_used) }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" onclick="setQuotaGb(0.1)" class="px-3 py-1 bg-navy-600 text-slate-300 text-xs rounded-lg hover:bg-navy-500">100 MB</button>
                        <button type="button" onclick="setQuotaGb(0.5)" class="px-3 py-1 bg-navy-600 text-slate-300 text-xs rounded-lg hover:bg-navy-500">500 MB</button>
                        <button type="button" onclick="setQuotaGb(1)" class="px-3 py-1 bg-navy-600 text-slate-300 text-xs rounded-lg hover:bg-navy-500">1 GB</button>
                        <button type="button" onclick="setQuotaGb(5)" class="px-3 py-1 bg-navy-600 text-slate-300 text-xs rounded-lg hover:bg-navy-500">5 GB</button>
                        <button type="button" onclick="setQuotaGb(10)" class="px-3 py-1 bg-navy-600 text-slate-300 text-xs rounded-lg hover:bg-navy-500">10 GB</button>
                    </div>
                </div>
                
                <div>
                    {{-- Hidden 0 wajib ada: checkbox yang tidak dicentang tidak ikut terkirim. --}}
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} class="w-5 h-5 mt-0.5 rounded accent-[#d4a843]">
                        <span>
                            <span class="text-sm font-medium text-white">Akun Aktif</span>
                            <span class="block text-xs text-slate-400 mt-0.5">Jika dimatikan, user tidak bisa login ke sistem.</span>
                        </span>
                    </label>
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4 mt-8 pt-6 border-t border-navy-600">
                <a href="{{ route('admin.users') }}" class="px-6 py-3 border border-navy-600 rounded-xl text-slate-300 hover:bg-navy-700 transition text-center">
                    Cancel
                </a>
                <button type="submit" class="flex-1 btn-primary px-6 py-3 rounded-xl font-medium">
                    <i class="fas fa-save mr-2"></i> Simpan Perubahan
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
