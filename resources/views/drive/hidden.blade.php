@extends('layouts.app')

@section('title', 'Hidden Files - Dekorasi Drive')
@section('page-title', 'Hidden Files')

@section('content')
<!-- Warning Banner -->
<div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
        <i class="fas fa-eye-slash text-amber-600"></i>
    </div>
    <div>
        <p class="font-medium text-amber-800">Hidden Files</p>
        <p class="text-sm text-amber-600">Files and folders listed here are hidden from the main view. Only you can see them.</p>
    </div>
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
                <form action="{{ route('drive.folder.toggle-visibility', $folder) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1 bg-green-100 text-green-700 text-xs rounded-full hover:bg-green-200 transition">
                        <i class="fas fa-eye mr-1"></i>Tampilkan
                    </button>
                </form>
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
        <i class="fas fa-eye-slash text-4xl text-gray-400"></i>
    </div>
    <h3 class="text-xl font-semibold text-gray-800 mb-2">No hidden files</h3>
    <p class="text-gray-500 mb-6">Files you hide will appear here</p>
    <a href="{{ route('drive.index') }}" class="btn-primary inline-flex px-6 py-3 rounded-xl text-white font-medium">
        <i class="fas fa-hard-drive mr-2"></i> Go to Drive
    </a>
</div>
@endif
@endsection
