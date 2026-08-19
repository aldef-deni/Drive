@extends('layouts.app')

@section('title', 'Hidden System - Dekorasi Drive')
@section('page-title', 'Hidden System')

@section('content')
<!-- Password Gate -->
@if(!session('hidden_verified'))
<div class="max-w-md mx-auto mt-8">
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="bg-gradient-to-r from-gray-800 to-gray-900 p-6 text-center">
            <div class="w-16 h-16 mx-auto mb-3 bg-white/10 rounded-full flex items-center justify-center">
                <i class="fas fa-user-secret text-white text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-white">Hidden System</h3>
            <p class="text-white/60 text-sm mt-1">Masukkan password untuk mengakses file tersembunyi</p>
        </div>
        <div class="p-6">
            <form action="{{ route('drive.hidden.verify') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-lock text-red-400 mr-1"></i> Password Akses
                    </label>
                    <input type="password" name="password" required autofocus
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition"
                        placeholder="Masukkan password hidden system">
                </div>
                @if($errors->has('password'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
                    <i class="fas fa-exclamation-circle mr-1"></i> {{ $errors->first('password') }}
                </div>
                @endif
                <button type="submit" class="w-full btn-primary py-3 rounded-xl text-white font-semibold">
                    <i class="fas fa-unlock mr-2"></i> Buka Hidden System
                </button>
            </form>
        </div>
    </div>
</div>

@else
<!-- Hidden Files Content -->
<!-- Warning Banner -->
<div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
        <i class="fas fa-user-secret text-amber-600"></i>
    </div>
    <div>
        <p class="font-medium text-amber-800">Hidden System</p>
        <p class="text-sm text-amber-600">File dan folder tersembunyi hanya bisa dilihat di halaman ini. Ketik password di search untuk unhide.</p>
    </div>
</div>

<!-- Password Search Bar for Unhide -->
<div class="mb-6">
    <form action="{{ route('drive.hidden') }}" method="GET" class="flex gap-3">
        <div class="flex-1 relative">
            <input type="text" name="unhide_password" value="{{ $unhidePassword ?? '' }}"
                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition"
                placeholder="Ketik password di sini untuk unhide file/folder...">
            <i class="fas fa-key absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
        <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-white font-medium">
            <i class="fas fa-search mr-2"></i> Unhide
        </button>
    </form>
    @if(!empty($unhidePassword))
        @if($unhideSuccess)
        <p class="mt-2 text-sm text-green-600"><i class="fas fa-check-circle mr-1"></i> Password benar — file bisa di-unhide</p>
        @elseif($unhidePassword)
        <p class="mt-2 text-sm text-red-500"><i class="fas fa-times-circle mr-1"></i> Password salah</p>
        @endif
    @endif
</div>

<!-- Folders -->
@if($folders->count() > 0)
<div class="mb-8">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">
        <i class="fas fa-folder mr-2"></i>Hidden Folders ({{ $folders->count() }})
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($folders as $folder)
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover-lift relative">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                    <i class="fas fa-folder text-amber-500 text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-800 truncate">{{ $folder->name }}</p>
                    <p class="text-xs text-gray-500">{{ $folder->created_at->format('d M Y') }}</p>
                </div>
            </div>
            
            <div class="absolute top-2 right-2">
                @if(!empty($unhidePassword) && $unhideSuccess)
                <form action="{{ route('drive.folder.toggle-visibility', $folder) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-1 bg-green-100 text-green-700 text-xs rounded-full hover:bg-green-200 transition">
                        <i class="fas fa-eye mr-1"></i>Tampilkan
                    </button>
                </form>
                @else
                <span class="px-3 py-1 bg-gray-100 text-gray-400 text-xs rounded-full cursor-not-allowed">
                    <i class="fas fa-lock mr-1"></i>Locked
                </span>
                @endif
            </div>
            
            <div class="absolute top-2 left-2">
                <span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full">
                    <i class="fas fa-eye-slash mr-1"></i>Hidden
                </span>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

<!-- Files -->
@if($files->count() > 0)
<div>
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">
        <i class="fas fa-file mr-2"></i>Hidden Files ({{ $files->count() }})
    </h3>
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Name</th>
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Size</th>
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Modified</th>
                    <th class="text-right px-6 py-4 text-sm font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($files as $file)
                <tr class="border-b border-gray-50 hover:bg-gray-50 transition group">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                <i class="fas {{ $file->getIconClass() }}"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $file->original_name }}</p>
                                <p class="text-xs text-gray-500">{{ $file->mime_type }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $file->formatSize() }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $file->updated_at->format('d M Y H:i') }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if(!empty($unhidePassword) && $unhideSuccess)
                            <!-- Show -->
                            <form action="{{ route('drive.toggle-visibility', $file) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" 
                                    class="px-3 py-1 bg-green-100 text-green-700 text-xs rounded-full hover:bg-green-200 transition">
                                    <i class="fas fa-eye mr-1"></i>Tampilkan
                                </button>
                            </form>
                            
                            <!-- Delete -->
                            <form action="{{ route('drive.destroy', $file) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Are you sure?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                    class="px-3 py-1 bg-red-100 text-red-700 text-xs rounded-full hover:bg-red-200 transition">
                                    <i class="fas fa-trash mr-1"></i>Hapus
                                </button>
                            </form>
                            @else
                            <span class="px-3 py-1 bg-gray-100 text-gray-400 text-xs rounded-full cursor-not-allowed">
                                <i class="fas fa-lock mr-1"></i>Locked
                            </span>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Empty State -->
@if($files->count() === 0 && $folders->count() === 0)
<div class="text-center py-16">
    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gray-100 flex items-center justify-center">
        <i class="fas fa-user-secret text-4xl text-gray-400"></i>
    </div>
    <h3 class="text-xl font-semibold text-gray-800 mb-2">Tidak ada file tersembunyi</h3>
    <p class="text-gray-500 mb-6">File yang Anda sembunyikan akan muncul di sini</p>
    <a href="{{ route('drive.index') }}" class="btn-primary inline-flex px-6 py-3 rounded-xl text-white font-medium">
        <i class="fas fa-hard-drive mr-2"></i> Kembali ke Drive
    </a>
</div>
@endif

@endif
@endsection
