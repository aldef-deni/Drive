@extends('layouts.app')

@section('title', 'Lock Management - Admin Dashboard')
@section('page-title', 'Lock Management')

@section('content')
<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl border border-gray-200 p-6 hover-lift">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-red-100 flex items-center justify-center">
                <i class="fas fa-lock text-2xl text-red-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Total Locked</p>
                <p class="text-2xl font-bold text-gray-800">{{ $totalLocked }}</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-6 hover-lift">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-indigo-100 flex items-center justify-center">
                <i class="fas fa-file text-2xl text-indigo-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Locked Files</p>
                <p class="text-2xl font-bold text-gray-800">{{ $lockedFiles->count() }}</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-6 hover-lift">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-amber-100 flex items-center justify-center">
                <i class="fas fa-folder text-2xl text-amber-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Locked Folders</p>
                <p class="text-2xl font-bold text-gray-800">{{ $lockedFolders->count() }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search -->
<div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
    <form action="{{ route('admin.lock-management') }}" method="GET" class="flex items-center gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px]">
            <div class="relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search locked files/folders..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.lock-management', ['type' => 'all', 'search' => $search]) }}" 
                class="px-4 py-2.5 rounded-xl text-sm font-medium transition {{ $type === 'all' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                All
            </a>
            <a href="{{ route('admin.lock-management', ['type' => 'files', 'search' => $search]) }}" 
                class="px-4 py-2.5 rounded-xl text-sm font-medium transition {{ $type === 'files' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                <i class="fas fa-file mr-1"></i> Files
            </a>
            <a href="{{ route('admin.lock-management', ['type' => 'folders', 'search' => $search]) }}" 
                class="px-4 py-2.5 rounded-xl text-sm font-medium transition {{ $type === 'folders' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                <i class="fas fa-folder mr-1"></i> Folders
            </a>
        </div>
        <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-white text-sm font-medium">
            <i class="fas fa-search mr-1"></i> Search
        </button>
    </form>
</div>

<!-- Locked Files -->
@if($lockedFiles->count() > 0)
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
    <div class="p-6 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800"><i class="fas fa-file-lock text-red-500 mr-2"></i>Locked Files ({{ $lockedFiles->count() }})</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">File</th>
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Owner</th>
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Size</th>
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Folder</th>
                    <th class="text-right px-6 py-4 text-sm font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lockedFiles as $file)
                <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                                <i class="fas fa-lock text-red-500"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $file->original_name }}</p>
                                <p class="text-xs text-gray-500">{{ $file->mime_type }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full overflow-hidden bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold">
                                @if($file->user->avatar)
                                    <img src="{{ asset('storage/avatars/' . $file->user->avatar) }}" alt="" class="w-full h-full object-cover">
                                @else
                                    {{ strtoupper(substr($file->user->name, 0, 1)) }}
                                @endif
                            </div>
                            <span class="text-sm text-gray-600">{{ $file->user->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $file->formatSize() }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $file->folder }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <!-- Change Password -->
                            <button onclick="openChangePasswordModal('file', {{ $file->id }}, '{{ $file->original_name }}')" 
                                class="w-9 h-9 rounded-lg bg-indigo-100 hover:bg-indigo-200 text-indigo-600 flex items-center justify-center transition" title="Change Password">
                                <i class="fas fa-key"></i>
                            </button>
                            <!-- Remove Lock -->
                            <form action="{{ route('admin.file.remove-lock', $file) }}" method="POST" class="inline" 
                                onsubmit="return confirm('Remove lock from this file?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                    class="w-9 h-9 rounded-lg bg-green-100 hover:bg-green-200 text-green-600 flex items-center justify-center transition" title="Remove Lock">
                                    <i class="fas fa-unlock"></i>
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

<!-- Locked Folders -->
@if($lockedFolders->count() > 0)
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
    <div class="p-6 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800"><i class="fas fa-folder-lock text-amber-500 mr-2"></i>Locked Folders ({{ $lockedFolders->count() }})</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Folder</th>
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Owner</th>
                    <th class="text-left px-6 py-4 text-sm font-semibold text-gray-600">Path</th>
                    <th class="text-right px-6 py-4 text-sm font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lockedFolders as $folder)
                <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                                <i class="fas fa-lock text-amber-500"></i>
                            </div>
                            <p class="font-medium text-gray-800">{{ $folder->name }}</p>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full overflow-hidden bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold">
                                @if($folder->user->avatar)
                                    <img src="{{ asset('storage/avatars/' . $folder->user->avatar) }}" alt="" class="w-full h-full object-cover">
                                @else
                                    {{ strtoupper(substr($folder->user->name, 0, 1)) }}
                                @endif
                            </div>
                            <span class="text-sm text-gray-600">{{ $folder->user->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $folder->path }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <!-- Change Password -->
                            <button onclick="openChangePasswordModal('folder', {{ $folder->id }}, '{{ $folder->name }}')" 
                                class="w-9 h-9 rounded-lg bg-indigo-100 hover:bg-indigo-200 text-indigo-600 flex items-center justify-center transition" title="Change Password">
                                <i class="fas fa-key"></i>
                            </button>
                            <!-- Remove Lock -->
                            <form action="{{ route('admin.folder.remove-lock', $folder) }}" method="POST" class="inline" 
                                onsubmit="return confirm('Remove lock from this folder?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                    class="w-9 h-9 rounded-lg bg-green-100 hover:bg-green-200 text-green-600 flex items-center justify-center transition" title="Remove Lock">
                                    <i class="fas fa-unlock"></i>
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
@if($lockedFiles->count() === 0 && $lockedFolders->count() === 0)
<div class="text-center py-16">
    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-green-100 flex items-center justify-center">
        <i class="fas fa-unlock-alt text-4xl text-green-400"></i>
    </div>
    <h3 class="text-xl font-semibold text-gray-800 mb-2">No Locked Items</h3>
    <p class="text-gray-500">Tidak ada file atau folder yang terkunci saat ini.</p>
</div>
@endif

<!-- Change Password Modal -->
<div id="changePasswordModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-key text-indigo-500 mr-2"></i>Change Lock Password</h3>
            <button onclick="closeChangePasswordModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center"><i class="fas fa-times text-gray-500"></i></button>
        </div>
        <form id="changePasswordForm" method="POST" class="p-6">
            @csrf
            @method('PUT')
            <p class="text-sm text-gray-600 mb-4">
                <span class="font-medium" id="cpItemName"></span>
            </p>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                <input type="password" name="new_password" required minlength="4" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none" 
                    placeholder="Enter new lock password (min 4 characters)">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeChangePasswordModal()" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl text-white font-medium"><i class="fas fa-save mr-2"></i>Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openChangePasswordModal(type, id, name) {
    document.getElementById('cpItemName').textContent = name;
    document.getElementById('changePasswordForm').action = type === 'file' 
        ? '/admin/file/' + id + '/change-lock-password' 
        : '/admin/folder/' + id + '/change-lock-password';
    document.getElementById('changePasswordModal').classList.remove('hidden');
}

function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.add('hidden');
    document.getElementById('changePasswordForm').reset();
}
</script>
@endpush
