@extends('layouts.app')

@section('title', 'Profil Saya - Dekorasi Drive')
@section('page-title', 'Profil Saya')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">

    <!-- Avatar Section -->
    <div class="panel overflow-hidden">
        <div class="p-6 border-b border-navy-600">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-camera text-gold-500 mr-2"></i>Foto Profil</h2>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-6">
                <!-- Current Avatar -->
                <div class="relative group">
                    <div class="w-24 h-24 rounded-full overflow-hidden bg-gradient-to-br from-gold-500 to-gold-600 flex items-center justify-center text-navy-900 text-3xl font-bold">
                        @if($user->avatar)
                            <img src="{{ asset('storage/avatars/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        @endif
                    </div>
                    <label for="avatarInput" class="absolute inset-0 bg-black/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer">
                        <i class="fas fa-camera text-white text-xl"></i>
                    </label>
                </div>

                <div class="flex-1">
                    <form action="{{ route('profile.avatar') }}" method="POST" enctype="multipart/form-data" id="avatarForm">
                        @csrf
                        <input type="file" id="avatarInput" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(this)">
                        <p class="text-sm text-slate-300 mb-2">Klik foto untuk mengganti avatar</p>
                        <p class="text-xs text-slate-400">Format: JPG, PNG, GIF, WebP. Maks 2MB.</p>
                        <div id="avatarPreview" class="hidden mt-3">
                            <div class="flex items-center gap-3">
                                <img id="previewImg" class="w-12 h-12 rounded-full object-cover border-2 border-gold-500">
                                <div>
                                    <p id="previewName" class="text-sm font-medium text-white"></p>
                                    <button type="submit" class="text-sm text-gold-500 hover:text-gold-400 font-medium mt-1">
                                        <i class="fas fa-save mr-1"></i>Simpan Avatar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    @if($user->avatar)
                    <form action="{{ route('profile.avatar.destroy') }}" method="POST" class="mt-3"
                          onsubmit="return confirm('Hapus foto profil Anda?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-400 hover:text-red-300">
                            <i class="fas fa-trash mr-1"></i>Hapus Avatar
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Info Section -->
    <div class="panel overflow-hidden">
        <div class="p-6 border-b border-navy-600">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-user text-gold-500 mr-2"></i>Informasi Profil</h2>
        </div>
        <form action="{{ route('profile.update') }}" method="POST" class="p-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <label class="label">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                    class="field">
                @error('name')<p class="text-sm text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                    class="field">
                @error('email')<p class="text-sm text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl">
                    <i class="fas fa-save mr-2"></i>Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <!-- Password Section -->
    <div class="panel overflow-hidden">
        <div class="p-6 border-b border-navy-600">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-lock text-gold-500 mr-2"></i>Ubah Password</h2>
        </div>
        <form action="{{ route('profile.password') }}" method="POST" class="p-6 space-y-5">
            @csrf
            @method('PUT')
            <div>
                <label class="label">Password Saat Ini</label>
                <input type="password" name="current_password" required autocomplete="current-password"
                    class="field" placeholder="Masukkan password saat ini">
                @error('current_password')<p class="text-sm text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Password Baru</label>
                <input type="password" name="password" required autocomplete="new-password"
                    class="field" placeholder="Min 8 karakter">
                @error('password')<p class="text-sm text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Konfirmasi Password Baru</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                    class="field" placeholder="Ulangi password baru">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl">
                    <i class="fas fa-key mr-2"></i>Ubah Password
                </button>
            </div>
        </form>
    </div>

    <!-- Account Info -->
    <div class="panel overflow-hidden">
        <div class="p-6 border-b border-navy-600">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-info-circle text-gold-500 mr-2"></i>Informasi Akun</h2>
        </div>
        <div class="p-6 space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Role</span>
                <span class="font-medium {{ $user->isAdmin() ? 'text-purple-300' : 'text-white' }}">{{ ucfirst($user->role) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Storage</span>
                <span class="font-medium text-white">{{ $user->formatStorage($user->storage_used) }} / {{ $user->formatStorage($user->storage_quota) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Bergabung</span>
                <span class="font-medium text-white">{{ $user->created_at->format('d M Y') }}</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('previewName').textContent = input.files[0].name;
            document.getElementById('avatarPreview').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
