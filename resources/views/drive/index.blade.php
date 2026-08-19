@extends('layouts.app')

@section('title', 'My Drive - Dekorasi Drive')
@section('page-title', 'My Drive')

@section('header-actions')
<!-- Search Bar -->
<div class="flex-1 max-w-md mx-4">
    <form action="{{ route('drive.index') }}" method="GET" class="relative">
        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder='Pencarian File'
            class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none text-sm">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        @if($search)
        <a href="{{ route('drive.index') }}" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
        </a>
        @endif
    </form>
</div>
<button onclick="openUploadModal()" class="btn-primary px-4 py-2 rounded-xl text-white font-medium flex items-center gap-2">
    <i class="fas fa-cloud-upload-alt"></i> Upload
</button>
<button onclick="openFolderModal()" class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-xl text-gray-700 font-medium flex items-center gap-2 transition">
    <i class="fas fa-folder-plus"></i> New Folder
</button>
@endsection

@section('content')
@if($showHidden)
<div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-3">
    <i class="fas fa-eye text-amber-600"></i>
    <span class="text-sm text-amber-700 font-medium">Hidden files are visible (secret mode)</span>
    <a href="{{ route('drive.index') }}" class="ml-auto text-amber-600 hover:text-amber-800 text-sm"><i class="fas fa-times"></i></a>
</div>
@endif

<!-- Breadcrumb -->
<nav class="mb-6 flex items-center gap-2 text-sm">
    @foreach($breadcrumbs as $index => $breadcrumb)
        @if($index > 0)
        <i class="fas fa-chevron-right text-gray-400"></i>
        @endif
        <a href="{{ route('drive.index', ['folder' => $breadcrumb['path']]) }}" class="text-gray-600 hover:text-indigo-600 transition {{ $index === count($breadcrumbs) - 1 ? 'font-semibold text-gray-800' : '' }}">
            @if($index === 0)<i class="fas fa-hard-drive mr-1"></i>@endif
            {{ $breadcrumb['name'] }}
        </a>
    @endforeach
</nav>

<!-- Drop Zone (external file upload) -->
<div id="dropZone" class="file-drop-zone rounded-2xl p-8 mb-6 text-center hidden">
    <i class="fas fa-cloud-upload-alt text-5xl mb-4 text-indigo-400"></i>
    <p class="text-lg font-medium text-gray-500">Drop files here to upload</p>
</div>

<!-- Move Zone (current folder drop target) -->
<div id="moveZone" class="hidden fixed inset-0 z-40 bg-indigo-500/10 border-4 border-dashed border-indigo-400 rounded-2xl flex items-center justify-center pointer-events-none">
    <div class="bg-white rounded-2xl shadow-xl px-8 py-6 text-center">
        <i class="fas fa-folder-open text-4xl text-indigo-500 mb-3"></i>
        <p class="text-lg font-semibold text-indigo-700">Drop here to move</p>
    </div>
</div>

<!-- Folders -->
@if($folders->count() > 0)
<div class="mb-8">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4"><i class="fas fa-folder mr-2"></i>Folders ({{ $folders->count() }})</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($folders as $folder)
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover-lift cursor-pointer group relative drag-item"
             draggable="true"
             data-type="folder" data-id="{{ $folder->id }}" data-name="{{ $folder->name }}" data-path="{{ $folder->path }}" data-hidden="{{ $folder->is_hidden ? '1' : '0' }}" data-locked="{{ $folder->lock_password ? '1' : '0' }}"
             onclick="window.location='{{ route('drive.index', ['folder' => $folder->path]) }}'"
             oncontextmenu="showContextMenu(event, 'folder', {{ $folder->id }}, '{{ $folder->name }}', {{ $folder->is_hidden ? 'true' : 'false' }}, {{ ($folder->lock_password || $folder->hasLockedFiles()) ? 'true' : 'false' }})">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                    <i class="fas fa-folder text-amber-500 text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-800 truncate">{{ $folder->name }}</p>
                    <p class="text-xs text-gray-500">{{ $folder->created_at->format('d M Y') }}</p>
                </div>
                @if($folder->lock_password)
                <i class="fas fa-lock text-red-400 text-sm"></i>
                @endif
            </div>
            @if($folder->is_hidden)
            <div class="absolute top-2 left-2"><span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full"><i class="fas fa-eye-slash mr-1"></i>Hidden</span></div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

<!-- Files -->
@if($files->count() > 0)
<div>
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4"><i class="fas fa-file mr-2"></i>Files ({{ $files->count() }})</h3>
    <div class="space-y-3">
        @foreach($files as $file)
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover-lift cursor-pointer drag-item"
             draggable="true"
             data-type="file" data-id="{{ $file->id }}" data-name="{{ $file->original_name }}" data-folder="{{ $file->folder }}" data-hidden="{{ $file->is_hidden ? '1' : '0' }}" data-locked="{{ $file->lock_password ? '1' : '0' }}" data-encrypted="{{ $file->is_encrypted ? '1' : '0' }}" data-shared="{{ $file->isShared() ? '1' : '0' }}" data-mime="{{ $file->mime_type }}"
             ondblclick="openFilePreview({{ $file->id }}, '{{ $file->original_name }}', '{{ $file->mime_type }}', {{ $file->is_encrypted ? 'true' : 'false' }}, {{ $file->lock_password ? 'true' : 'false' }})"
             oncontextmenu="showContextMenu(event, 'file', {{ $file->id }}, '{{ $file->original_name }}', {{ $file->is_hidden ? 'true' : 'false' }}, {{ $file->lock_password ? 'true' : 'false' }}, {{ $file->is_encrypted ? 'true' : 'false' }}, {{ $file->isShared() ? 'true' : 'false' }})">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas {{ $file->getIconClass() }} text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-medium text-gray-800">{{ $file->original_name }}</p>
                        @if($file->isShared())<span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full"><i class="fas fa-share-alt mr-1"></i>Shared</span>@endif
                        @if($file->lock_password)<span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full"><i class="fas fa-lock mr-1"></i>Locked</span>@endif
                        @if($file->is_hidden)<span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full"><i class="fas fa-eye-slash mr-1"></i>Hidden</span>@endif
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ $file->formatSize() }} &middot; {{ $file->mime_type }} &middot; {{ $file->updated_at->format('d M Y') }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

<!-- Empty State -->
@if($files->count() === 0 && $folders->count() === 0)
<div class="text-center py-16">
    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-indigo-100 flex items-center justify-center">
        <i class="fas fa-cloud-upload-alt text-4xl text-indigo-400"></i>
    </div>
    <h3 class="text-xl font-semibold text-gray-800 mb-2">{{ $search ? 'No results found' : 'No files yet' }}</h3>
    <p class="text-gray-500 mb-6">{{ $search ? 'Try a different search term' : 'Upload your first file to get started' }}</p>
    @if(!$search)
    <button onclick="openUploadModal()" class="btn-primary px-6 py-3 rounded-xl text-white font-medium"><i class="fas fa-cloud-upload-alt mr-2"></i> Upload File</button>
    @endif
</div>
@endif
@endsection

@section('modals')
<!-- Context Menu -->
<div id="contextMenu" class="hidden fixed z-50 bg-white rounded-xl shadow-2xl border border-gray-100 py-2 w-56">
    <button id="ctxDownload" onclick="ctxAction('download')" class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 flex items-center gap-3"><i class="fas fa-download text-indigo-500 w-5"></i>Download</button>
    <button id="ctxShare" onclick="ctxAction('share')" class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 flex items-center gap-3"><i class="fas fa-share-alt text-green-500 w-5"></i>Share</button>
    <button id="ctxUnshare" onclick="ctxAction('unshare')" class="hidden w-full px-4 py-2.5 text-left text-sm hover:bg-orange-50 text-orange-600 flex items-center gap-3"><i class="fas fa-ban w-5"></i>Unshare</button>
    <hr class="my-1">
    <button id="ctxHide" onclick="ctxAction('hide')" class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 flex items-center gap-3"><i class="fas fa-eye-slash text-yellow-500 w-5"></i><span id="ctxHideText">Hide</span></button>
    <button id="ctxLock" onclick="ctxAction('lock')" class="w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 flex items-center gap-3"><i class="fas fa-lock text-red-500 w-5"></i><span id="ctxLockText">Lock</span></button>
    <hr class="my-1">
    <button onclick="ctxAction('delete')" class="w-full px-4 py-2.5 text-left text-sm hover:bg-red-50 text-red-600 flex items-center gap-3"><i class="fas fa-trash w-5"></i>Delete</button>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Upload File</h3>
            <button onclick="closeUploadModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center"><i class="fas fa-times text-gray-500"></i></button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data" class="p-6">
            @csrf
            <input type="hidden" name="folder" value="{{ $currentFolder }}">
            <div class="mb-6">
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-400 transition cursor-pointer" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600 mb-2">Click to select file</p>
                    <p class="text-xs text-gray-500">Max 100MB</p>
                    <input type="file" id="fileInput" name="file" class="hidden" onchange="handleFileSelect(this)">
                </div>
                <div id="selectedFile" class="hidden mt-4 p-4 bg-gray-50 rounded-xl flex items-center gap-3">
                    <i class="fas fa-file text-indigo-500"></i>
                    <span id="fileName" class="text-sm text-gray-700 flex-1"></span>
                    <button type="button" onclick="clearFileSelection()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="mb-6">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_locked" value="1" id="lockToggle" onchange="togglePasswordField()" class="w-5 h-5 rounded text-indigo-600">
                    <div><span class="text-sm font-medium text-gray-700"><i class="fas fa-lock text-red-500 mr-1"></i>Lock File</span><p class="text-xs text-gray-500">Encrypt & lock — file cannot be deleted until unlocked</p></div>
                </label>
            </div>
            <div id="passwordField" class="hidden mb-6">
                <input type="password" name="lock_password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Lock password">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeUploadModal()" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" id="uploadBtn" class="flex-1 btn-primary px-4 py-3 rounded-xl text-white font-medium disabled:opacity-50" disabled><i class="fas fa-upload mr-2"></i>Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- Folder Modal -->
<div id="folderModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Create Folder</h3>
            <button onclick="closeFolderModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center"><i class="fas fa-times text-gray-500"></i></button>
        </div>
        <form id="folderForm" class="p-6">
            @csrf
            <input type="hidden" name="parent_path" value="{{ $currentFolder }}">
            <input type="text" name="name" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none mb-6" placeholder="Folder name">
            <div class="flex gap-3">
                <button type="button" onclick="closeFolderModal()" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl text-white font-medium"><i class="fas fa-folder-plus mr-2"></i>Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Lock/Unlock Modal -->
<div id="lockModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-lock text-red-500 mr-2"></i><span id="lockModalTitle">Lock File</span></h3>
            <button onclick="closeLockModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center"><i class="fas fa-times text-gray-500"></i></button>
        </div>
        <form id="lockForm" class="p-6">
            @csrf
            <input type="hidden" name="type" id="lockType">
            <input type="hidden" name="id" id="lockId">
            <p class="text-sm text-gray-600 mb-4">File: <span id="lockFileName" class="font-medium"></span></p>
            <input type="password" name="password" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none mb-6" placeholder="Enter lock password (different from login)">
            <div class="flex gap-3">
                <button type="button" onclick="closeLockModal()" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl text-white font-medium"><i class="fas fa-lock mr-2"></i><span id="lockBtnText">Lock</span></button>
            </div>
        </form>
    </div>
</div>

<!-- Decrypt Modal -->
<div id="decryptModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-lock text-green-500 mr-2"></i>Download Encrypted File</h3>
            <button onclick="closeDecryptModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center"><i class="fas fa-times text-gray-500"></i></button>
        </div>
        <form id="decryptForm" class="p-6">
            @csrf
            <p class="text-sm text-gray-600 mb-4">File: <span id="decryptFileName" class="font-medium"></span></p>
            <input type="password" name="password" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none mb-6" placeholder="Enter password to decrypt">
            <div class="flex gap-3">
                <button type="button" onclick="closeDecryptModal()" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl text-white font-medium"><i class="fas fa-download mr-2"></i>Download</button>
            </div>
        </form>
    </div>
</div>

<!-- Share Modal -->
<div id="shareModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800"><i class="fas fa-share-alt text-indigo-500 mr-2"></i>Share File</h3>
            <button onclick="closeShareModal()" class="w-8 h-8 rounded-lg hover:bg-gray-100 flex items-center justify-center"><i class="fas fa-times text-gray-500"></i></button>
        </div>
        <form id="shareForm" class="p-6">
            @csrf
            <p class="text-sm text-gray-600 mb-4">File: <span id="shareFileName" class="font-medium"></span></p>
            <label class="flex items-center gap-3 cursor-pointer mb-4">
                <input type="checkbox" name="has_password" id="sharePasswordToggle" onchange="toggleSharePasswordField()" class="w-5 h-5 rounded text-indigo-600">
                <div><span class="text-sm font-medium text-gray-700">Password Protection</span></div>
            </label>
            <div id="sharePasswordField" class="hidden mb-4">
                <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Share password">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Expiry (Optional)</label>
                <input type="datetime-local" name="expires_at" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div id="shareResult" class="hidden mb-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                <p class="text-sm text-green-700 mb-2"><i class="fas fa-check-circle mr-2"></i>Link created!</p>
                <div class="flex items-center gap-2">
                    <input type="text" id="shareUrl" readonly class="flex-1 px-3 py-2 bg-white border border-green-300 rounded-lg text-sm">
                    <button type="button" onclick="copyShareUrl()" class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600"><i class="fas fa-copy"></i></button>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeShareModal()" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50">Close</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl text-white font-medium"><i class="fas fa-share-alt mr-2"></i>Create Link</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
let ctxType = null, ctxId = null, ctxName = '', ctxHidden = false, ctxLocked = false, ctxEncrypted = false;
let currentDecryptFileId = null, currentShareFileId = null;

// Context Menu
let ctxShared = false;

function showContextMenu(e, type, id, name, hidden, locked, encrypted, shared) {
    e.preventDefault();
    ctxType = type; ctxId = id; ctxName = name;
    ctxHidden = hidden; ctxLocked = locked || false;
    ctxEncrypted = encrypted || false;
    ctxShared = shared || false;
    
    const menu = document.getElementById('contextMenu');
    document.getElementById('ctxHideText').textContent = hidden ? 'Unhide' : 'Hide';
    document.getElementById('ctxLockText').textContent = locked ? 'Unlock' : 'Lock';
    document.getElementById('ctxDownload').style.display = (type === 'folder') ? 'none' : '';
    document.getElementById('ctxShare').style.display = (type === 'folder' || locked || ctxShared) ? 'none' : '';
    document.getElementById('ctxUnshare').classList.toggle('hidden', !ctxShared || type === 'folder');
    
    // Hide delete option for locked items
    const deleteBtn = document.querySelector('#contextMenu button:last-child');
    if (locked) {
        deleteBtn.classList.add('hidden');
    } else {
        deleteBtn.classList.remove('hidden');
    }
    
    menu.style.left = e.pageX + 'px';
    menu.style.top = e.pageY + 'px';
    menu.classList.remove('hidden');
}

document.addEventListener('click', () => document.getElementById('contextMenu').classList.add('hidden'));

function ctxAction(action) {
    document.getElementById('contextMenu').classList.add('hidden');
    if (action === 'download') {
        if (ctxEncrypted) { openDecryptModal(ctxId, ctxName); }
        else { window.location = '/drive/file/' + ctxId + '/download'; }
    } else if (action === 'share') { openShareModal(ctxId, ctxName);
    } else if (action === 'unshare') { doUnshare(ctxId, ctxName);
    } else if (action === 'hide') { doHide(ctxType, ctxId);
    } else if (action === 'lock') { openLockModal(ctxType, ctxId, ctxName, ctxLocked);
    } else if (action === 'delete') { doDelete(ctxType, ctxId); }
}

async function doHide(type, id) {
    const url = type === 'folder' ? `/drive/folder/${id}/toggle-visibility` : `/drive/file/${id}/toggle-visibility`;
    const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
    const data = await res.json();
    if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
}

async function doDelete(type, id) {
    if (!confirm('Delete this ' + type + '?')) return;
    const url = type === 'folder' ? `/drive/folder/${id}` : `/drive/file/${id}`;
    const res = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' } });
    const data = await res.json();
    if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
    else { showToast(data.message, 'error'); }
}

async function doUnshare(id, name) {
    if (!confirm('Batalkan share untuk "' + name + '"? Link share akan tidak berlaku lagi.')) return;
    const res = await fetch(`/drive/file/${id}/unshare`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' }
    });
    const data = await res.json();
    if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
    else { showToast(data.message, 'error'); }
}

// Lock/Unlock
function openLockModal(type, id, name, isLocked) {
    document.getElementById('lockType').value = type;
    document.getElementById('lockId').value = id;
    document.getElementById('lockFileName').textContent = name;
    document.getElementById('lockModalTitle').textContent = isLocked ? 'Unlock ' + (type === 'folder' ? 'Folder' : 'File') : 'Lock ' + (type === 'folder' ? 'Folder' : 'File');
    document.getElementById('lockBtnText').textContent = isLocked ? 'Unlock' : 'Lock';
    document.getElementById('lockForm').action = isLocked
        ? (type === 'folder' ? `/drive/folder/${id}/unlock` : `/drive/file/${id}/unlock`)
        : (type === 'folder' ? `/drive/folder/${id}/lock` : `/drive/file/${id}/lock`);
    document.getElementById('lockModal').classList.remove('hidden');
}
function closeLockModal() { document.getElementById('lockModal').classList.add('hidden'); document.getElementById('lockForm').reset(); }

document.getElementById('lockForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const res = await fetch(this.action, { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
    const data = await res.json();
    if (data.success) { showToast(data.message); closeLockModal(); setTimeout(() => location.reload(), 500); }
    else { showToast(data.message, 'error'); }
});

// Upload
function openUploadModal() { document.getElementById('uploadModal').classList.remove('hidden'); }
function closeUploadModal() { document.getElementById('uploadModal').classList.add('hidden'); document.getElementById('uploadForm').reset(); document.getElementById('selectedFile').classList.add('hidden'); document.getElementById('passwordField').classList.add('hidden'); document.getElementById('uploadBtn').disabled = true; }
function handleFileSelect(input) { if (input.files.length > 0) { document.getElementById('fileName').textContent = input.files[0].name; document.getElementById('selectedFile').classList.remove('hidden'); document.getElementById('uploadBtn').disabled = false; } }
function clearFileSelection() { document.getElementById('fileInput').value = ''; document.getElementById('selectedFile').classList.add('hidden'); document.getElementById('uploadBtn').disabled = true; }
function togglePasswordField() { document.getElementById('passwordField').classList.toggle('hidden', !document.getElementById('lockToggle').checked); }

document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('uploadBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
    const res = await fetch('{{ route("drive.upload") }}', { method: 'POST', body: new FormData(this), headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } });
    const data = await res.json();
    if (data.success) { showToast('File uploaded!'); setTimeout(() => location.reload(), 500); } else { showToast(data.message, 'error'); }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
});

// Folder
function openFolderModal() { document.getElementById('folderModal').classList.remove('hidden'); }
function closeFolderModal() { document.getElementById('folderModal').classList.add('hidden'); document.getElementById('folderForm').reset(); }
document.getElementById('folderForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const res = await fetch('{{ route("drive.folder.create") }}', { method: 'POST', body: new FormData(this), headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
    const data = await res.json();
    if (data.success) { showToast('Folder created!'); setTimeout(() => location.reload(), 500); } else { showToast(data.message, 'error'); }
});

// Decrypt
function openDecryptModal(id, name) { currentDecryptFileId = id; document.getElementById('decryptFileName').textContent = name; document.getElementById('decryptModal').classList.remove('hidden'); }
function closeDecryptModal() { document.getElementById('decryptModal').classList.add('hidden'); document.getElementById('decryptForm').reset(); }
document.getElementById('decryptForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const res = await fetch(`/drive/file/${currentDecryptFileId}/download-encrypted`, { method: 'POST', body: new FormData(this), headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
    if (res.ok) { const blob = await res.blob(); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = document.getElementById('decryptFileName').textContent; a.click(); URL.revokeObjectURL(url); showToast('Downloaded!'); closeDecryptModal(); }
    else { const data = await res.json(); showToast(data.message || 'Failed', 'error'); }
});

// Share
function openShareModal(id, name) { currentShareFileId = id; document.getElementById('shareFileName').textContent = name; document.getElementById('shareResult').classList.add('hidden'); document.getElementById('shareModal').classList.remove('hidden'); }
function closeShareModal() { document.getElementById('shareModal').classList.add('hidden'); document.getElementById('shareForm').reset(); document.getElementById('shareResult').classList.add('hidden'); document.getElementById('sharePasswordField').classList.add('hidden'); }
function toggleSharePasswordField() { document.getElementById('sharePasswordField').classList.toggle('hidden', !document.getElementById('sharePasswordToggle').checked); }
document.getElementById('shareForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const res = await fetch(`/drive/file/${currentShareFileId}/share`, { method: 'POST', body: new FormData(this), headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
    const data = await res.json();
    if (data.success) { document.getElementById('shareUrl').value = data.share_url; document.getElementById('shareResult').classList.remove('hidden'); showToast('Link created!'); }
});
function copyShareUrl() { navigator.clipboard.writeText(document.getElementById('shareUrl').value); showToast('Copied!'); }

// ==========================================
// Drag & Drop — upload from desktop + move between folders
// ==========================================
const dropZone = document.getElementById('dropZone');
const moveZone = document.getElementById('moveZone');
let dragData = null; // {type:'file'|'folder', id, name, ...}

// --- Draggable items (files & folders in the list) ---
document.querySelectorAll('.drag-item').forEach(el => {
    el.addEventListener('dragstart', e => {
        dragData = {
            type: el.dataset.type,
            id: el.dataset.id,
            name: el.dataset.name,
        };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', ''); // needed for Firefox
        el.classList.add('opacity-40');
    });
    el.addEventListener('dragend', e => {
        el.classList.remove('opacity-40');
        hideMoveZone();
        clearFolderDropTargets();
    });
});

// --- Folder cards are drop targets ---
document.querySelectorAll('[data-type="folder"]').forEach(folder => {
    if (!folder.classList.contains('drag-item')) return;
    folder.addEventListener('dragover', e => {
        if (!dragData) return; // external file drag
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        folder.classList.add('ring-4', 'ring-indigo-400', 'bg-indigo-50');
    });
    folder.addEventListener('dragleave', e => {
        folder.classList.remove('ring-4', 'ring-indigo-400', 'bg-indigo-50');
    });
    folder.addEventListener('drop', async e => {
        e.preventDefault();
        e.stopPropagation();
        folder.classList.remove('ring-4', 'ring-indigo-400', 'bg-indigo-50');
        hideMoveZone();
        if (!dragData) return;

        const targetPath = folder.dataset.path;
        if (!targetPath) return;
        const csrf = '{{ csrf_token() }}';

        if (dragData.type === 'file') {
            const res = await fetch(`/drive/file/${dragData.id}/move`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ folder: targetPath })
            });
            const data = await res.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
            else { showToast(data.message, 'error'); }
        } else if (dragData.type === 'folder') {
            const res = await fetch(`/drive/folder/${dragData.id}/move`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ parent_path: targetPath })
            });
            const data = await res.json();
            if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
            else { showToast(data.message, 'error'); }
        }
        dragData = null;
    });
});

// --- Empty area / breadcrumb: drop target to move to current folder ---
const mainContent = document.querySelector('main');
const currentFolder = '{{ $currentFolder }}';

mainContent.addEventListener('dragover', e => {
    if (!dragData) {
        // External file drag → show upload drop zone
        if (e.dataTransfer.types.includes('Files')) {
            e.preventDefault();
            dropZone.classList.remove('hidden');
        }
        return;
    }
    // Internal drag → show move zone for current folder
    e.preventDefault();
    moveZone.classList.remove('hidden');
});

mainContent.addEventListener('dragleave', e => {
    if (!dragData) {
        dropZone.classList.add('hidden');
        return;
    }
    // Only hide if leaving mainContent entirely
    if (!mainContent.contains(e.relatedTarget)) {
        moveZone.classList.add('hidden');
    }
});

mainContent.addEventListener('drop', async e => {
    e.preventDefault();
    dropZone.classList.add('hidden');
    moveZone.classList.add('hidden');
    clearFolderDropTargets();

    // External file drop → upload
    if (e.dataTransfer.files.length > 0 && !dragData) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        handleFileSelect(document.getElementById('fileInput'));
        openUploadModal();
        return;
    }

    // Internal drag → move to current folder
    if (!dragData) return;
    const csrf = '{{ csrf_token() }}';

    if (dragData.type === 'file') {
        const res = await fetch(`/drive/file/${dragData.id}/move`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ folder: currentFolder })
        });
        const data = await res.json();
        if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
        else { showToast(data.message, 'error'); }
    } else if (dragData.type === 'folder') {
        const res = await fetch(`/drive/folder/${dragData.id}/move`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ parent_path: currentFolder })
        });
        const data = await res.json();
        if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 500); }
        else { showToast(data.message, 'error'); }
    }
    dragData = null;
});

// Prevent default on body for external file drops
['dragenter','dragover','dragleave','drop'].forEach(ev => {
    document.body.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); });
});

function hideMoveZone() { moveZone.classList.add('hidden'); }
function clearFolderDropTargets() {
    document.querySelectorAll('.ring-indigo-400').forEach(el => el.classList.remove('ring-4', 'ring-indigo-400', 'bg-indigo-50'));
}

// ==========================================
// Share Modal (when arriving from share link)
// ==========================================
@if(!empty($shareToken))
document.addEventListener('DOMContentLoaded', function() {
    const shareModal = document.getElementById('shareReceiveModal');
    if (shareModal) shareModal.classList.remove('hidden');
});

async function acceptShare() {
    const btn = document.getElementById('acceptShareBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...';

    const csrf = '{{ csrf_token() }}';
    const token = '{{ $shareToken }}';
    const passwordInput = document.getElementById('shareReceivePassword');
    const body = passwordInput ? { password: passwordInput.value } : {};

    const res = await fetch(`/share/${token}/download`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(body)
    });

    const data = await res.json();
    if (data.success) {
        showToast(data.message);
        document.getElementById('shareReceiveModal').classList.add('hidden');
        setTimeout(() => { window.location.href = data.redirect || '/drive'; }, 800);
    } else {
        showToast(data.message, 'error');
        // Shake animation
        const card = document.querySelector('#shareReceiveModal > div');
        card.classList.add('animate-shake');
        setTimeout(() => card.classList.remove('animate-shake'), 500);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Terima File';
    }
}
@endif
</script>

<style>
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-6px); }
    20%, 40%, 60%, 80% { transform: translateX(6px); }
}
.animate-shake { animation: shake 0.5s ease-in-out; }
</style>

<!-- Share Receive Modal -->
@if(!empty($shareToken))
<div id="shareReceiveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background: rgba(0,0,0,0.6); backdrop-filter: blur(6px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-6 text-center relative">
            <button onclick="document.getElementById('shareReceiveModal').classList.add('hidden')" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center text-white transition">
                <i class="fas fa-times"></i>
            </button>
            <div class="w-16 h-16 mx-auto mb-3 bg-white/20 rounded-full flex items-center justify-center">
                <i class="fas fa-share-alt text-white text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-white">File Dibagikan Kepada Anda</h3>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl mb-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-file text-indigo-500 text-xl"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800">{{ $shareFile->original_name }}</p>
                    <p class="text-xs text-gray-500">{{ $shareFile->formatSize() }} • {{ $shareFile->mime_type }}</p>
                </div>
            </div>

            @if($shareHasPassword)
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    <i class="fas fa-lock text-red-400 mr-1"></i> Password Diperlukan
                </label>
                <input type="password" id="shareReceivePassword"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none"
                    placeholder="Masukkan password share">
            </div>
            @endif

            <p class="text-xs text-gray-400 mb-4 text-center">File akan disimpan ke folder <strong>Shared</strong> di drive Anda.</p>

            <button id="acceptShareBtn" onclick="acceptShare()"
                class="w-full py-3 rounded-xl text-white font-semibold transition-all duration-300 hover:-translate-y-0.5"
                style="background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: 0 6px 20px rgba(99,102,241,0.35);">
                <i class="fas fa-check mr-2"></i>Terima File
            </button>
        </div>
    </div>
</div>
@endif

// ==========================================
// File Preview (image, video, office)
// ==========================================
function openFilePreview(id, name, mime, isEncrypted, isLocked) {
    const ext = name.split('.').pop().toLowerCase();
    const imageExts = ['jpg','jpeg','png','webp','gif'];
    const videoExts = ['mp4'];
    const officeExts = ['doc','docx','xls','xlsx','ppt','pptx','pdf'];

    if (imageExts.includes(ext)) {
        openImagePreview(id, name, isEncrypted);
    } else if (videoExts.includes(ext)) {
        openVideoPreview(id, name, isEncrypted);
    } else if (officeExts.includes(ext)) {
        openOfficePreview(id, name, ext, isEncrypted);
    } else if (isEncrypted) {
        openDecryptModal(id, name);
    } else {
        window.location = '/drive/file/' + id + '/download';
    }
}

function openImagePreview(id, name, isEncrypted) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('previewImage');
    const title = document.getElementById('imagePreviewTitle');
    title.textContent = name;
    if (isEncrypted) {
        img.src = '';
        title.textContent = name + ' (Encrypted — download to preview)';
    } else {
        img.src = '/drive/file/' + id + '/download';
    }
    modal.classList.remove('hidden');
}
function closeImagePreview() { document.getElementById('imagePreviewModal').classList.add('hidden'); }

function openVideoPreview(id, name, isEncrypted) {
    const modal = document.getElementById('videoPreviewModal');
    const player = document.getElementById('videoPlayer');
    const source = document.getElementById('videoSource');
    const title = document.getElementById('videoPreviewTitle');
    title.textContent = name;
    if (isEncrypted) {
        source.src = '';
        title.textContent = name + ' (Encrypted — download to play)';
    } else {
        source.src = '/drive/file/' + id + '/download';
    }
    player.load();
    modal.classList.remove('hidden');
}
function closeVideoPreview() {
    const modal = document.getElementById('videoPreviewModal');
    const player = document.getElementById('videoPlayer');
    player.pause();
    modal.classList.add('hidden');
}

function openOfficePreview(id, name, ext, isEncrypted) {
    if (isEncrypted) {
        openDecryptModal(id, name);
        return;
    }
    const modal = document.getElementById('officePreviewModal');
    const iframe = document.getElementById('officeFrame');
    const title = document.getElementById('officePreviewTitle');
    title.textContent = name;
    const fileUrl = encodeURIComponent(window.location.origin + '/drive/file/' + id + '/download');
    if (ext === 'pdf') {
        iframe.src = '/drive/file/' + id + '/download';
    } else {
        iframe.src = 'https://view.officeapps.live.com/op/embed.aspx?src=' + fileUrl;
    }
    modal.classList.remove('hidden');
}
function closeOfficePreview() {
    const modal = document.getElementById('officePreviewModal');
    document.getElementById('officeFrame').src = '';
    modal.classList.add('hidden');
}

// Click outside modal to close
document.getElementById('imagePreviewModal')?.addEventListener('click', function(e) { if (e.target === this) closeImagePreview(); });
document.getElementById('videoPreviewModal')?.addEventListener('click', function(e) { if (e.target === this) closeVideoPreview(); });
document.getElementById('officePreviewModal')?.addEventListener('click', function(e) { if (e.target === this) closeOfficePreview(); });
</script>

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.85);backdrop-filter:blur(4px)">
    <div class="relative max-w-4xl w-full mx-4">
        <button onclick="closeImagePreview()" class="absolute -top-12 right-0 text-white/70 hover:text-white text-2xl"><i class="fas fa-times"></i></button>
        <div class="bg-gray-900 rounded-2xl overflow-hidden shadow-2xl">
            <div class="p-3 border-b border-gray-700 flex items-center justify-between">
                <p id="imagePreviewTitle" class="text-white text-sm font-medium truncate"></p>
                <a id="imageDownloadBtn" class="text-indigo-400 hover:text-indigo-300 text-sm cursor-pointer" onclick="document.getElementById('imagePreviewModal').querySelector('img').src && window.location.assign(document.getElementById('imagePreviewModal').querySelector('img').src)"><i class="fas fa-download mr-1"></i>Download</a>
            </div>
            <div class="flex items-center justify-center p-4" style="max-height:75vh">
                <img id="previewImage" src="" class="max-w-full max-h-[70vh] object-contain rounded-lg">
            </div>
        </div>
    </div>
</div>

<!-- Video Preview Modal -->
<div id="videoPreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.85);backdrop-filter:blur(4px)">
    <div class="relative max-w-4xl w-full mx-4">
        <button onclick="closeVideoPreview()" class="absolute -top-12 right-0 text-white/70 hover:text-white text-2xl"><i class="fas fa-times"></i></button>
        <div class="bg-gray-900 rounded-2xl overflow-hidden shadow-2xl">
            <div class="p-3 border-b border-gray-700">
                <p id="videoPreviewTitle" class="text-white text-sm font-medium truncate"></p>
            </div>
            <div class="p-2">
                <video id="videoPlayer" controls class="w-full rounded-lg" style="max-height:70vh">
                    <source id="videoSource" src="" type="video/mp4">
                    Browser Anda tidak mendukung pemutar video.
                </video>
            </div>
        </div>
    </div>
</div>

<!-- Office Preview Modal -->
<div id="officePreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.85);backdrop-filter:blur(4px)">
    <div class="relative max-w-5xl w-full mx-4" style="height:85vh">
        <button onclick="closeOfficePreview()" class="absolute -top-10 right-0 text-white/70 hover:text-white text-2xl z-10"><i class="fas fa-times"></i></button>
        <div class="bg-white rounded-2xl overflow-hidden shadow-2xl h-full flex flex-col">
            <div class="p-3 border-b border-gray-200 flex items-center justify-between">
                <p id="officePreviewTitle" class="text-gray-800 text-sm font-medium truncate"></p>
                <button onclick="closeOfficePreview()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-external-link-alt"></i></button>
            </div>
            <iframe id="officeFrame" src="" class="flex-1 w-full border-0"></iframe>
        </div>
    </div>
</div>
@endpush
