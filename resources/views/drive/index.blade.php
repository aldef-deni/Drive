@extends('layouts.app')

@section('title', 'Drive Saya - Dekorasi Drive')
@section('page-title', 'Drive Saya')

@section('header-actions')
<!-- Pencarian (desktop) -->
<form action="{{ route('drive.index') }}" method="GET" class="hidden md:block relative w-64 lg:w-80">
    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Cari file atau folder..."
        class="field !py-2 !pl-10 !pr-9 text-sm">
    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gold-500 text-sm"></i>
    @if($search)
    <a href="{{ route('drive.index') }}" aria-label="Bersihkan pencarian" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white">
        <i class="fas fa-times"></i>
    </a>
    @endif
</form>

<!-- Tombol mobile -->
<button onclick="openSearchMobile()" aria-label="Cari" class="md:hidden tap-target w-10 h-10 rounded-xl bg-navy-700 hover:bg-navy-600 flex items-center justify-center transition">
    <i class="fas fa-search text-gold-500"></i>
</button>
<button onclick="openUploadModal()" aria-label="Unggah file" class="md:hidden btn-primary tap-target w-10 h-10 rounded-xl flex items-center justify-center">
    <i class="fas fa-cloud-arrow-up"></i>
</button>

<!-- Tombol desktop -->
<div class="hidden md:flex items-center gap-2">
    <button onclick="openFolderModal()" class="btn-ghost px-4 py-2 rounded-xl flex items-center gap-2 text-sm">
        <i class="fas fa-folder-plus"></i> Folder Baru
    </button>
    <button onclick="openUploadModal()" class="btn-primary px-4 py-2 rounded-xl flex items-center gap-2 text-sm">
        <i class="fas fa-cloud-arrow-up"></i> Unggah
    </button>
</div>
@endsection

@section('content')
<!-- Pencarian (mobile) -->
<form action="{{ route('drive.index') }}" method="GET" id="searchMobile" class="{{ $search ? '' : 'hidden' }} md:hidden relative mb-4">
    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Cari file atau folder..."
        class="field !pl-10 text-sm">
    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gold-500 text-sm"></i>
</form>

@if($showHidden)
<div class="mb-4 p-3 bg-gold-500/10 border border-gold-500/30 rounded-xl flex items-center gap-3">
    <i class="fas fa-eye text-gold-500 flex-shrink-0"></i>
    <div class="flex-1 min-w-0">
        <p class="text-sm text-gold-400 font-medium">Mode rahasia aktif</p>
        <p class="text-xs text-gold-400/60">File dan folder tersembunyi ikut ditampilkan. Klik kanan &rarr; Tampilkan untuk mengembalikannya ke Drive.</p>
    </div>
    <form action="{{ route('drive.reveal.off') }}" method="POST" class="flex-shrink-0">
        @csrf
        <input type="hidden" name="folder" value="{{ $currentFolder }}">
        <button type="submit" aria-label="Keluar mode rahasia" title="Sembunyikan lagi"
            class="w-8 h-8 rounded-lg text-gold-500 hover:bg-gold-500/15 flex items-center justify-center transition">
            <i class="fas fa-times"></i>
        </button>
    </form>
</div>
@endif

<!-- Breadcrumb + pengaturan tampilan -->
<div class="flex items-center justify-between gap-3 mb-5">
    <nav aria-label="Lokasi folder" class="flex items-center gap-2 text-sm flex-1 min-w-0 overflow-x-auto">
        @foreach($breadcrumbs as $index => $breadcrumb)
            @if($index > 0)
            <i class="fas fa-chevron-right text-slate-600 text-[10px] flex-shrink-0"></i>
            @endif
            <a href="{{ route('drive.index', ['folder' => $breadcrumb['path']]) }}"
               class="flex-shrink-0 transition {{ $index === count($breadcrumbs) - 1 ? 'font-semibold text-white' : 'text-slate-400 hover:text-gold-500' }}">
                @if($index === 0)<i class="fas fa-hard-drive mr-1.5"></i>@endif{{ $breadcrumb['name'] }}
            </a>
        @endforeach
    </nav>

    <div class="relative flex-shrink-0">
        <button onclick="toggleViewDropdown(event)" class="btn-ghost flex items-center gap-2 px-3 py-2 rounded-xl text-sm">
            <i class="fas fa-sliders text-gold-500"></i>
            <span class="hidden sm:inline">Tampilan</span>
            <i class="fas fa-chevron-down text-slate-500 text-[10px]"></i>
        </button>
        <div id="viewDropdown" class="hidden absolute right-0 top-12 w-48 panel z-40 overflow-hidden">
            <button onclick="setView('list')" data-view-option="list" class="w-full px-4 py-3 text-left text-sm flex items-center gap-3 hover:bg-navy-700 transition">
                <i class="fas fa-list text-gold-500 w-5"></i><span class="text-white">Daftar</span>
                <i class="fas fa-check text-gold-500 ml-auto text-xs opacity-0"></i>
            </button>
            <button onclick="setView('small')" data-view-option="small" class="w-full px-4 py-3 text-left text-sm flex items-center gap-3 hover:bg-navy-700 transition">
                <i class="fas fa-table-cells text-gold-500 w-5"></i><span class="text-white">Ikon Kecil</span>
                <i class="fas fa-check text-gold-500 ml-auto text-xs opacity-0"></i>
            </button>
            <button onclick="setView('large')" data-view-option="large" class="w-full px-4 py-3 text-left text-sm flex items-center gap-3 hover:bg-navy-700 transition">
                <i class="fas fa-table-cells-large text-gold-500 w-5"></i><span class="text-white">Ikon Besar</span>
                <i class="fas fa-check text-gold-500 ml-auto text-xs opacity-0"></i>
            </button>
        </div>
    </div>
</div>

<!-- Area lepas file dari komputer -->
<div id="dropZone" class="file-drop-zone rounded-2xl p-8 mb-6 text-center hidden">
    <i class="fas fa-cloud-arrow-up text-4xl mb-3 text-gold-500"></i>
    <p class="text-base font-medium text-slate-300">Lepaskan file di sini untuk mengunggah</p>
</div>

<!-- Overlay pindah ke folder saat ini -->
<div id="moveZone" class="hidden fixed inset-4 z-40 rounded-2xl border-4 border-dashed border-gold-500/70 bg-navy-900/40 backdrop-blur-sm flex items-center justify-center pointer-events-none">
    <div class="panel px-8 py-6 text-center">
        <i class="fas fa-folder-open text-4xl text-gold-500 mb-3"></i>
        <p class="text-base font-semibold text-white">Lepaskan untuk memindahkan ke sini</p>
    </div>
</div>

<!-- Folder -->
@if($folders->count() > 0)
<section class="mb-8">
    <h2 class="text-xs font-semibold text-gold-500 uppercase tracking-wider mb-3">
        <i class="fas fa-folder mr-2"></i>Folder <span class="text-slate-500 font-normal">({{ $folders->count() }})</span>
    </h2>

    <div data-group="folders" data-variant="small" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 md:gap-3">
        @foreach($folders as $folder)
            @include('drive.partials.folder-item', ['folder' => $folder, 'variant' => 'small'])
        @endforeach
    </div>

    <div data-group="folders" data-variant="large" class="hidden grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
        @foreach($folders as $folder)
            @include('drive.partials.folder-item', ['folder' => $folder, 'variant' => 'large'])
        @endforeach
    </div>

    <div data-group="folders" data-variant="list" class="hidden space-y-2">
        @foreach($folders as $folder)
            @include('drive.partials.folder-item', ['folder' => $folder, 'variant' => 'list'])
        @endforeach
    </div>
</section>
@endif

<!-- File -->
@if($files->count() > 0)
<section>
    <h2 class="text-xs font-semibold text-gold-500 uppercase tracking-wider mb-3">
        <i class="fas fa-file mr-2"></i>File <span class="text-slate-500 font-normal">({{ $files->count() }})</span>
    </h2>

    <div data-group="files" data-variant="small" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 md:gap-3">
        @foreach($files as $file)
            @include('drive.partials.file-item', ['file' => $file, 'variant' => 'small'])
        @endforeach
    </div>

    <div data-group="files" data-variant="large" class="hidden grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
        @foreach($files as $file)
            @include('drive.partials.file-item', ['file' => $file, 'variant' => 'large'])
        @endforeach
    </div>

    <div data-group="files" data-variant="list" class="hidden space-y-2">
        @foreach($files as $file)
            @include('drive.partials.file-item', ['file' => $file, 'variant' => 'list'])
        @endforeach
    </div>
</section>
@endif

<!-- Kondisi kosong -->
@if($files->count() === 0 && $folders->count() === 0)
<div class="text-center py-16">
    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gold-500/10 flex items-center justify-center">
        <i class="fas {{ $search ? 'fa-magnifying-glass' : 'fa-cloud-arrow-up' }} text-4xl text-gold-500"></i>
    </div>
    <h3 class="text-xl font-semibold text-white mb-2">{{ $search ? 'Tidak ada hasil' : 'Belum ada file' }}</h3>
    <p class="text-slate-400 mb-6 text-sm">{{ $search ? 'Coba kata kunci lain.' : 'Unggah file pertama Anda untuk memulai.' }}</p>
    @if(!$search)
    <button onclick="openUploadModal()" class="btn-primary px-6 py-3 rounded-xl"><i class="fas fa-cloud-arrow-up mr-2"></i>Unggah File</button>
    @endif
</div>
@endif

<p class="mt-10 text-center text-[11px] text-slate-600">
    Klik untuk membuka pratinjau &middot; klik kanan untuk menu aksi &middot; seret untuk memindahkan
</p>
@endsection

@section('modals')
<!-- Menu klik kanan -->
<div id="contextMenu" class="hidden fixed z-50 panel py-2 w-56">
    <p id="ctxTitle" class="px-4 pb-2 mb-1 text-xs text-slate-500 truncate border-b border-navy-600"></p>
    <button id="ctxOpen" onclick="ctxAction('open')" class="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-navy-700 flex items-center gap-3"><i class="fas fa-up-right-from-square text-gold-500 w-5"></i>Buka</button>
    <button id="ctxDownload" onclick="ctxAction('download')" class="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-navy-700 flex items-center gap-3"><i class="fas fa-download text-gold-500 w-5"></i>Unduh</button>
    <button id="ctxShare" onclick="ctxAction('share')" class="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-navy-700 flex items-center gap-3"><i class="fas fa-share-alt text-green-400 w-5"></i>Bagikan</button>
    <button id="ctxUnshare" onclick="ctxAction('unshare')" class="hidden w-full px-4 py-2.5 text-left text-sm text-orange-400 hover:bg-navy-700 flex items-center gap-3"><i class="fas fa-ban w-5"></i>Batalkan Berbagi</button>
    <hr class="my-1 border-navy-600">
    <button id="ctxHide" onclick="ctxAction('hide')" class="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-navy-700 flex items-center gap-3"><i class="fas fa-eye-slash text-amber-400 w-5"></i><span id="ctxHideText">Sembunyikan</span></button>
    <button id="ctxLock" onclick="ctxAction('lock')" class="w-full px-4 py-2.5 text-left text-sm text-white hover:bg-navy-700 flex items-center gap-3"><i class="fas fa-lock text-red-400 w-5"></i><span id="ctxLockText">Kunci</span></button>
    <hr class="my-1 border-navy-600">
    <button id="ctxDelete" onclick="ctxAction('delete')" class="w-full px-4 py-2.5 text-left text-sm text-red-400 hover:bg-red-900/30 flex items-center gap-3"><i class="fas fa-trash w-5"></i>Hapus</button>
</div>

<!-- Modal unggah -->
<div id="uploadModal" class="hidden fixed inset-0 z-50 flex items-end md:items-center justify-center modal-overlay p-0 md:p-4">
    <div class="panel !rounded-b-none md:!rounded-2xl w-full max-w-lg max-h-[92vh] overflow-y-auto">
        <div class="p-5 md:p-6 border-b border-navy-600 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Unggah File</h3>
            <button onclick="closeUploadModal()" aria-label="Tutup" class="w-9 h-9 rounded-lg hover:bg-navy-700 flex items-center justify-center"><i class="fas fa-times text-slate-400"></i></button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data" class="p-5 md:p-6">
            @csrf
            <input type="hidden" name="folder" value="{{ $currentFolder }}">

            <div class="mb-5">
                <button type="button" class="w-full border-2 border-dashed border-navy-600 rounded-xl p-8 text-center hover:border-gold-500 transition" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-arrow-up text-4xl text-gold-500 mb-3"></i>
                    <p class="text-slate-300 mb-1 text-sm">Klik untuk memilih file</p>
                    <p class="text-xs text-slate-500">Maksimal 100 MB per file</p>
                </button>
                <input type="file" id="fileInput" name="file" class="hidden" onchange="handleFileSelect(this)">

                <div id="selectedFile" class="hidden mt-4 p-3 bg-navy-700 rounded-xl flex items-center gap-3">
                    <i class="fas fa-file text-gold-500"></i>
                    <span id="fileName" class="text-sm text-slate-200 flex-1 truncate"></span>
                    <span id="fileSize" class="text-xs text-slate-500"></span>
                    <button type="button" onclick="clearFileSelection()" aria-label="Batal pilih" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
                </div>
            </div>

            <label class="flex items-start gap-3 cursor-pointer mb-4">
                <input type="checkbox" name="is_locked" value="1" id="lockToggle" onchange="togglePasswordField()" class="w-5 h-5 mt-0.5 rounded accent-[#d4a843]">
                <span>
                    <span class="text-sm font-medium text-white"><i class="fas fa-lock text-red-400 mr-1"></i>Kunci file</span>
                    <span class="block text-xs text-slate-400 mt-0.5">File dienkripsi dan tidak bisa dihapus sebelum dibuka kuncinya.</span>
                </span>
            </label>

            <div id="passwordField" class="hidden mb-5">
                <label class="label" for="uploadLockPassword">Password kunci</label>
                <input type="password" id="uploadLockPassword" name="lock_password" class="field" placeholder="Minimal 4 karakter">
            </div>

            <div id="uploadProgressWrap" class="hidden mb-5">
                <div class="w-full bg-navy-700 rounded-full h-2 overflow-hidden">
                    <div id="uploadProgress" class="progress-bar h-2 rounded-full transition-all duration-200" style="width: 0%"></div>
                </div>
                <p id="uploadProgressText" class="text-xs text-slate-400 mt-1.5 text-center">0%</p>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeUploadModal()" class="flex-1 btn-ghost px-4 py-3 rounded-xl">Batal</button>
                <button type="submit" id="uploadBtn" class="flex-1 btn-primary px-4 py-3 rounded-xl" disabled><i class="fas fa-upload mr-2"></i>Unggah</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal folder baru -->
<div id="folderModal" class="hidden fixed inset-0 z-50 flex items-end md:items-center justify-center modal-overlay p-0 md:p-4">
    <div class="panel !rounded-b-none md:!rounded-2xl w-full max-w-md">
        <div class="p-5 md:p-6 border-b border-navy-600 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Folder Baru</h3>
            <button onclick="closeFolderModal()" aria-label="Tutup" class="w-9 h-9 rounded-lg hover:bg-navy-700 flex items-center justify-center"><i class="fas fa-times text-slate-400"></i></button>
        </div>
        <form id="folderForm" class="p-5 md:p-6">
            @csrf
            <input type="hidden" name="parent_path" value="{{ $currentFolder }}">
            <label class="label" for="folderName">Nama folder</label>
            <input type="text" id="folderName" name="name" required maxlength="255" class="field mb-5" placeholder="Contoh: Dokumen Proyek">
            <div class="flex gap-3">
                <button type="button" onclick="closeFolderModal()" class="flex-1 btn-ghost px-4 py-3 rounded-xl">Batal</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl"><i class="fas fa-folder-plus mr-2"></i>Buat</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal kunci / buka kunci -->
<div id="lockModal" class="hidden fixed inset-0 z-50 flex items-end md:items-center justify-center modal-overlay p-0 md:p-4">
    <div class="panel !rounded-b-none md:!rounded-2xl w-full max-w-md">
        <div class="p-5 md:p-6 border-b border-navy-600 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white"><i class="fas fa-lock text-red-400 mr-2"></i><span id="lockModalTitle">Kunci File</span></h3>
            <button onclick="closeLockModal()" aria-label="Tutup" class="w-9 h-9 rounded-lg hover:bg-navy-700 flex items-center justify-center"><i class="fas fa-times text-slate-400"></i></button>
        </div>
        <form id="lockForm" class="p-5 md:p-6">
            @csrf
            <p class="text-sm text-slate-400 mb-4">Item: <span id="lockFileName" class="font-medium text-white"></span></p>
            <label class="label" for="lockPassword">Password</label>
            <input type="password" id="lockPassword" name="password" required class="field mb-5" placeholder="Password kunci (berbeda dari password login)">
            <div class="flex gap-3">
                <button type="button" onclick="closeLockModal()" class="flex-1 btn-ghost px-4 py-3 rounded-xl">Batal</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl"><i class="fas fa-lock mr-2"></i><span id="lockBtnText">Kunci</span></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal unduh file terenkripsi -->
<div id="decryptModal" class="hidden fixed inset-0 z-50 flex items-end md:items-center justify-center modal-overlay p-0 md:p-4">
    <div class="panel !rounded-b-none md:!rounded-2xl w-full max-w-md">
        <div class="p-5 md:p-6 border-b border-navy-600 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white"><i class="fas fa-shield-halved text-green-400 mr-2"></i>Unduh File Terenkripsi</h3>
            <button onclick="closeDecryptModal()" aria-label="Tutup" class="w-9 h-9 rounded-lg hover:bg-navy-700 flex items-center justify-center"><i class="fas fa-times text-slate-400"></i></button>
        </div>
        <form id="decryptForm" class="p-5 md:p-6">
            @csrf
            <p class="text-sm text-slate-400 mb-4">File: <span id="decryptFileName" class="font-medium text-white"></span></p>
            <label class="label" for="decryptPassword">Password</label>
            <input type="password" id="decryptPassword" name="password" required class="field mb-5" placeholder="Password untuk dekripsi">
            <div class="flex gap-3">
                <button type="button" onclick="closeDecryptModal()" class="flex-1 btn-ghost px-4 py-3 rounded-xl">Batal</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl"><i class="fas fa-download mr-2"></i>Unduh</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal berbagi -->
<div id="shareModal" class="hidden fixed inset-0 z-50 flex items-end md:items-center justify-center modal-overlay p-0 md:p-4">
    <div class="panel !rounded-b-none md:!rounded-2xl w-full max-w-md max-h-[92vh] overflow-y-auto">
        <div class="p-5 md:p-6 border-b border-navy-600 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white"><i class="fas fa-share-alt text-gold-500 mr-2"></i>Bagikan File</h3>
            <button onclick="closeShareModal()" aria-label="Tutup" class="w-9 h-9 rounded-lg hover:bg-navy-700 flex items-center justify-center"><i class="fas fa-times text-slate-400"></i></button>
        </div>
        <form id="shareForm" class="p-5 md:p-6">
            @csrf
            <p class="text-sm text-slate-400 mb-4">File: <span id="shareFileName" class="font-medium text-white"></span></p>

            <label class="flex items-center gap-3 cursor-pointer mb-4">
                <input type="checkbox" id="sharePasswordToggle" onchange="toggleSharePasswordField()" class="w-5 h-5 rounded accent-[#d4a843]">
                <span class="text-sm font-medium text-white">Lindungi dengan password</span>
            </label>

            <div id="sharePasswordField" class="hidden mb-4">
                <label class="label" for="sharePassword">Password link</label>
                <input type="password" id="sharePassword" name="password" class="field" placeholder="Password untuk penerima">
            </div>

            <div class="mb-4">
                <label class="label" for="shareExpires">Berlaku sampai <span class="text-slate-500 font-normal">(opsional)</span></label>
                <input type="datetime-local" id="shareExpires" name="expires_at" class="field">
            </div>

            <div class="mb-5">
                <label class="label" for="shareLimit">Batas unduhan <span class="text-slate-500 font-normal">(opsional)</span></label>
                <input type="number" id="shareLimit" name="download_limit" min="1" class="field" placeholder="Contoh: 5">
            </div>

            <div id="shareResult" class="hidden mb-5 p-4 bg-green-500/10 border border-green-500/40 rounded-xl">
                <p class="text-sm text-green-300 mb-2"><i class="fas fa-circle-check mr-2"></i>Link berhasil dibuat</p>
                <div class="flex items-center gap-2">
                    <input type="text" id="shareUrl" readonly class="field !py-2 text-xs">
                    <button type="button" onclick="copyShareUrl()" aria-label="Salin link" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg text-sm flex-shrink-0"><i class="fas fa-copy"></i></button>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeShareModal()" class="flex-1 btn-ghost px-4 py-3 rounded-xl">Tutup</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl"><i class="fas fa-link mr-2"></i>Buat Link</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal konfirmasi hapus -->
<div id="confirmModal" class="hidden fixed inset-0 z-[55] flex items-center justify-center modal-overlay p-4">
    <div class="panel w-full max-w-sm p-6 text-center">
        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-500/15 flex items-center justify-center">
            <i class="fas fa-triangle-exclamation text-red-400 text-xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-white mb-2" id="confirmTitle">Konfirmasi</h3>
        <p class="text-sm text-slate-400 mb-6" id="confirmMessage"></p>
        <div class="flex gap-3">
            <button type="button" onclick="closeConfirm()" class="flex-1 btn-ghost px-4 py-3 rounded-xl">Batal</button>
            <button type="button" id="confirmOkBtn" class="flex-1 px-4 py-3 rounded-xl bg-red-600 hover:bg-red-500 text-white font-semibold transition">Hapus</button>
        </div>
    </div>
</div>

<!-- Pratinjau gambar -->
<div id="imagePreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay p-4">
    <div class="relative w-full max-w-4xl">
        <button onclick="closeImagePreview()" aria-label="Tutup" class="absolute -top-11 right-0 text-white/70 hover:text-white text-2xl"><i class="fas fa-times"></i></button>
        <div class="panel overflow-hidden">
            <div class="p-3 border-b border-navy-600 flex items-center justify-between gap-3">
                <p id="imagePreviewTitle" class="text-white text-sm font-medium truncate"></p>
                <a id="imageDownloadBtn" href="#" class="text-gold-500 hover:text-gold-400 text-sm flex-shrink-0"><i class="fas fa-download mr-1"></i>Unduh</a>
            </div>
            <div class="relative flex items-center justify-center p-4 bg-navy-950/60 min-h-[220px]">
                <p id="imagePreviewStatus" class="hidden absolute text-sm text-slate-400"></p>
                <img id="previewImage" src="" alt="" class="max-w-full max-h-[70vh] object-contain rounded-lg transition-opacity duration-200">
            </div>
        </div>
    </div>
</div>

<!-- Pratinjau video -->
<div id="videoPreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay p-4">
    <div class="relative w-full max-w-4xl">
        <button onclick="closeVideoPreview()" aria-label="Tutup" class="absolute -top-11 right-0 text-white/70 hover:text-white text-2xl"><i class="fas fa-times"></i></button>
        <div class="panel overflow-hidden">
            <div class="p-3 border-b border-navy-600 flex items-center justify-between gap-3">
                <p id="videoPreviewTitle" class="text-white text-sm font-medium truncate"></p>
                <a id="videoDownloadBtn" href="#" class="text-gold-500 hover:text-gold-400 text-sm flex-shrink-0"><i class="fas fa-download mr-1"></i>Unduh</a>
            </div>
            <div class="p-2 bg-navy-950/60">
                <video id="videoPlayer" controls class="w-full rounded-lg" style="max-height:70vh">
                    <source id="videoSource" src="" type="video/mp4">
                    Browser Anda tidak mendukung pemutar video.
                </video>
            </div>
        </div>
    </div>
</div>

<!-- Pratinjau dokumen -->
<div id="officePreviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-overlay p-4">
    <div class="relative w-full max-w-5xl" style="height:85vh">
        <button onclick="closeOfficePreview()" aria-label="Tutup" class="absolute -top-11 right-0 text-white/70 hover:text-white text-2xl z-10"><i class="fas fa-times"></i></button>
        <div class="panel overflow-hidden h-full flex flex-col">
            <div class="p-3 border-b border-navy-600 flex items-center justify-between gap-3">
                <p id="officePreviewTitle" class="text-white text-sm font-medium truncate"></p>
                <a id="officeDownloadBtn" href="#" class="text-gold-500 hover:text-gold-400 text-sm flex-shrink-0"><i class="fas fa-download mr-1"></i>Unduh</a>
            </div>
            <iframe id="officeFrame" title="Pratinjau dokumen" src="" class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    </div>
</div>

@if(!empty($shareToken))
<!-- Modal terima file yang dibagikan -->
<div id="shareReceiveModal" class="hidden fixed inset-0 z-[55] flex items-center justify-center modal-overlay p-4">
    <div class="panel w-full max-w-md overflow-hidden" id="shareReceiveCard">
        <div class="bg-gradient-to-r from-gold-500 to-gold-600 p-6 text-center relative">
            <button onclick="document.getElementById('shareReceiveModal').classList.add('hidden')" aria-label="Tutup"
                class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center text-navy-900 transition">
                <i class="fas fa-times"></i>
            </button>
            <div class="w-16 h-16 mx-auto mb-3 bg-white/25 rounded-full flex items-center justify-center">
                <i class="fas fa-share-alt text-navy-900 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold text-navy-900">File Dibagikan Kepada Anda</h3>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4 p-4 bg-navy-700 rounded-xl mb-4">
                <div class="w-12 h-12 rounded-xl bg-navy-800 flex items-center justify-center flex-shrink-0">
                    <i class="fas {{ $shareFile->getIconClass() }} text-xl"></i>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-white truncate">{{ $shareFile->original_name }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ $shareFile->formatSize() }} &middot; {{ $shareFile->mime_type }}</p>
                </div>
            </div>

            @if($shareHasPassword)
            <div class="mb-4">
                <label class="label" for="shareReceivePassword"><i class="fas fa-lock text-red-400 mr-1"></i> Password diperlukan</label>
                <input type="password" id="shareReceivePassword" class="field" placeholder="Masukkan password share">
            </div>
            @endif

            <p class="text-xs text-slate-400 mb-4 text-center">File akan disimpan ke folder <strong class="text-slate-300">Shared</strong> di drive Anda.</p>

            <button id="acceptShareBtn" onclick="acceptShare()" class="btn-primary w-full py-3 rounded-xl">
                <i class="fas fa-check mr-2"></i>Terima File
            </button>
        </div>
    </div>
</div>
@endif
@endsection

@push('styles')
<style>
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-6px); }
        20%, 40%, 60%, 80% { transform: translateX(6px); }
    }
    .animate-shake { animation: shake .5s ease-in-out; }
    .drive-item.dragging { opacity: .4; }
    .drive-item.drop-target {
        border-color: var(--gold-500) !important;
        box-shadow: 0 0 0 3px rgba(212, 168, 67, .35);
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const CURRENT_FOLDER = @json($currentFolder);

    // =====================================================================
    // Pengaturan tampilan (daftar / ikon kecil / ikon besar)
    // =====================================================================
    let currentView = localStorage.getItem('driveView') || 'small';

    function applyView(view) {
        document.querySelectorAll('[data-group]').forEach(group => {
            const active = group.dataset.variant === view;
            group.classList.toggle('hidden', !active);
            // Grid perlu display:grid saat aktif; kelas `hidden` menang atas `grid`.
            if (group.dataset.variant !== 'list') {
                group.classList.toggle('grid', active);
            }
        });

        document.querySelectorAll('[data-view-option]').forEach(btn => {
            const check = btn.querySelector('.fa-check');
            if (check) check.classList.toggle('opacity-0', btn.dataset.viewOption !== view);
        });
    }

    window.setView = function (view) {
        currentView = view;
        localStorage.setItem('driveView', view);
        applyView(view);
        document.getElementById('viewDropdown').classList.add('hidden');
    };

    window.toggleViewDropdown = function (event) {
        event?.stopPropagation();
        document.getElementById('viewDropdown').classList.toggle('hidden');
    };

    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('viewDropdown');
        if (dropdown && !dropdown.contains(e.target)) dropdown.classList.add('hidden');
    });

    window.openSearchMobile = function () {
        const form = document.getElementById('searchMobile');
        form.classList.toggle('hidden');
        form.querySelector('input')?.focus();
    };

    // =====================================================================
    // Utilitas
    // =====================================================================
    async function postJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload || {}),
        });

        return { ok: res.ok, data: await res.json().catch(() => ({})) };
    }

    function handleResult({ ok, data }) {
        const message = data.message || (ok ? 'Berhasil' : 'Terjadi kesalahan');
        showToast(message, ok && data.success !== false ? 'success' : 'error');

        if (ok && data.success !== false) {
            setTimeout(() => location.reload(), 600);
            return true;
        }
        return false;
    }

    function formatBytes(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return bytes.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
    }

    // Konfirmasi berbasis modal (menggantikan confirm() bawaan browser)
    let confirmCallback = null;

    function askConfirm(message, onConfirm, title) {
        document.getElementById('confirmTitle').textContent = title || 'Konfirmasi';
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmModal').classList.remove('hidden');
        confirmCallback = onConfirm;
    }

    window.closeConfirm = function () {
        document.getElementById('confirmModal').classList.add('hidden');
        confirmCallback = null;
    };

    document.getElementById('confirmOkBtn').addEventListener('click', function () {
        const cb = confirmCallback;
        window.closeConfirm();
        cb?.();
    });

    // =====================================================================
    // Menu klik kanan — data diambil dari data-attribute (aman untuk nama
    // file yang mengandung kutip, tanda kurung, dsb.)
    // =====================================================================
    let ctx = null;

    function readItem(el) {
        return {
            kind: el.dataset.kind,
            id: el.dataset.id,
            name: el.dataset.name,
            path: el.dataset.path || '',
            url: el.dataset.url || '',
            folder: el.dataset.folder || '',
            hidden: el.dataset.hidden === '1',
            locked: el.dataset.locked === '1',
            encrypted: el.dataset.encrypted === '1',
            shared: el.dataset.shared === '1',
        };
    }

    function showContextMenu(event, item) {
        event.preventDefault();
        ctx = item;

        const isFolder = item.kind === 'folder';
        const menu = document.getElementById('contextMenu');

        document.getElementById('ctxTitle').textContent = item.name;
        document.getElementById('ctxHideText').textContent = item.hidden ? 'Tampilkan' : 'Sembunyikan';
        document.getElementById('ctxLockText').textContent = item.locked ? 'Buka Kunci' : 'Kunci';

        document.getElementById('ctxOpen').classList.toggle('hidden', !isFolder);
        document.getElementById('ctxDownload').classList.toggle('hidden', isFolder);
        document.getElementById('ctxShare').classList.toggle('hidden', isFolder || item.locked || item.shared);
        document.getElementById('ctxUnshare').classList.toggle('hidden', isFolder || !item.shared);
        document.getElementById('ctxDelete').classList.toggle('hidden', item.locked);

        menu.classList.remove('hidden');

        // Jaga agar menu tidak keluar dari layar
        const rect = menu.getBoundingClientRect();
        const x = Math.min(event.clientX, window.innerWidth - rect.width - 8);
        const y = Math.min(event.clientY, window.innerHeight - rect.height - 8);
        menu.style.left = Math.max(8, x) + 'px';
        menu.style.top = Math.max(8, y) + 'px';
    }

    document.addEventListener('click', () => document.getElementById('contextMenu').classList.add('hidden'));
    document.addEventListener('scroll', () => document.getElementById('contextMenu').classList.add('hidden'), true);

    window.ctxAction = function (action) {
        document.getElementById('contextMenu').classList.add('hidden');
        if (!ctx) return;

        switch (action) {
            case 'open':
                if (ctx.url) window.location = ctx.url;
                break;
            case 'download':
                if (ctx.encrypted) openDecryptModal(ctx.id, ctx.name);
                else window.location = '/drive/file/' + ctx.id + '/download';
                break;
            case 'share':
                openShareModal(ctx.id, ctx.name);
                break;
            case 'unshare':
                doUnshare(ctx.id, ctx.name);
                break;
            case 'hide':
                doHide(ctx.kind, ctx.id);
                break;
            case 'lock':
                openLockModal(ctx.kind, ctx.id, ctx.name, ctx.locked);
                break;
            case 'delete':
                doDelete(ctx.kind, ctx.id, ctx.name);
                break;
        }
    };

    async function doHide(kind, id) {
        const url = kind === 'folder'
            ? `/drive/folder/${id}/toggle-visibility`
            : `/drive/file/${id}/toggle-visibility`;
        handleResult(await postJson(url));
    }

    function doDelete(kind, id, name) {
        const label = kind === 'folder' ? 'Folder' : 'File';
        askConfirm(
            `${label} "${name}" akan dihapus permanen${kind === 'folder' ? ' beserta seluruh isinya' : ''}. Lanjutkan?`,
            async () => {
                const url = kind === 'folder' ? `/drive/folder/${id}` : `/drive/file/${id}`;
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                });
                handleResult({ ok: res.ok, data: await res.json().catch(() => ({})) });
            },
            `Hapus ${label}`
        );
    }

    function doUnshare(id, name) {
        askConfirm(
            `Batalkan berbagi "${name}"? Link yang sudah dibagikan tidak akan berlaku lagi dan salinan pada penerima ikut dihapus.`,
            async () => handleResult(await postJson(`/drive/file/${id}/unshare`)),
            'Batalkan Berbagi'
        );
    }

    // =====================================================================
    // Modal kunci / buka kunci
    // =====================================================================
    function openLockModal(kind, id, name, isLocked) {
        const label = kind === 'folder' ? 'Folder' : 'File';
        document.getElementById('lockFileName').textContent = name;
        document.getElementById('lockModalTitle').textContent = (isLocked ? 'Buka Kunci ' : 'Kunci ') + label;
        document.getElementById('lockBtnText').textContent = isLocked ? 'Buka Kunci' : 'Kunci';
        document.getElementById('lockForm').dataset.action = isLocked
            ? (kind === 'folder' ? `/drive/folder/${id}/unlock` : `/drive/file/${id}/unlock`)
            : (kind === 'folder' ? `/drive/folder/${id}/lock` : `/drive/file/${id}/lock`);
        document.getElementById('lockModal').classList.remove('hidden');
        document.getElementById('lockPassword').focus();
    }

    window.closeLockModal = function () {
        document.getElementById('lockModal').classList.add('hidden');
        document.getElementById('lockForm').reset();
    };

    document.getElementById('lockForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const password = document.getElementById('lockPassword').value;
        const result = await postJson(this.dataset.action, { password });
        if (result.ok && result.data.success !== false) window.closeLockModal();
        handleResult(result);
    });

    // =====================================================================
    // Unggah
    // =====================================================================
    window.openUploadModal = function () {
        document.getElementById('uploadModal').classList.remove('hidden');
    };

    window.closeUploadModal = function () {
        document.getElementById('uploadModal').classList.add('hidden');
        document.getElementById('uploadForm').reset();
        document.getElementById('selectedFile').classList.add('hidden');
        document.getElementById('passwordField').classList.add('hidden');
        document.getElementById('uploadProgressWrap').classList.add('hidden');
        document.getElementById('uploadBtn').disabled = true;
    };

    window.handleFileSelect = function (input) {
        if (!input.files.length) return;
        document.getElementById('fileName').textContent = input.files[0].name;
        document.getElementById('fileSize').textContent = formatBytes(input.files[0].size);
        document.getElementById('selectedFile').classList.remove('hidden');
        document.getElementById('uploadBtn').disabled = false;
    };

    window.clearFileSelection = function () {
        document.getElementById('fileInput').value = '';
        document.getElementById('selectedFile').classList.add('hidden');
        document.getElementById('uploadBtn').disabled = true;
    };

    window.togglePasswordField = function () {
        const checked = document.getElementById('lockToggle').checked;
        document.getElementById('passwordField').classList.toggle('hidden', !checked);
        document.getElementById('uploadLockPassword').required = checked;
    };

    document.getElementById('uploadForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const btn = document.getElementById('uploadBtn');
        const wrap = document.getElementById('uploadProgressWrap');
        const bar = document.getElementById('uploadProgress');
        const text = document.getElementById('uploadProgressText');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Mengunggah...';
        wrap.classList.remove('hidden');

        // XHR dipakai agar progres unggah bisa ditampilkan.
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '{{ route('drive.upload') }}');
        xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.addEventListener('progress', function (evt) {
            if (!evt.lengthComputable) return;
            const percent = Math.round((evt.loaded / evt.total) * 100);
            bar.style.width = percent + '%';
            text.textContent = percent + '%';
        });

        xhr.addEventListener('load', function () {
            let data = {};
            try { data = JSON.parse(xhr.responseText); } catch (err) { /* abaikan */ }

            if (xhr.status >= 200 && xhr.status < 300 && data.success) {
                showToast(data.message || 'File berhasil diunggah');
                setTimeout(() => location.reload(), 600);
                return;
            }

            const message = data.message
                || (data.errors ? Object.values(data.errors).flat()[0] : null)
                || 'Gagal mengunggah file';
            showToast(message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload mr-2"></i>Unggah';
            wrap.classList.add('hidden');
        });

        xhr.addEventListener('error', function () {
            showToast('Koneksi terputus saat mengunggah', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload mr-2"></i>Unggah';
            wrap.classList.add('hidden');
        });

        xhr.send(new FormData(this));
    });

    // =====================================================================
    // Folder baru
    // =====================================================================
    window.openFolderModal = function () {
        document.getElementById('folderModal').classList.remove('hidden');
        document.getElementById('folderName').focus();
    };

    window.closeFolderModal = function () {
        document.getElementById('folderModal').classList.add('hidden');
        document.getElementById('folderForm').reset();
    };

    document.getElementById('folderForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const result = await postJson('{{ route('drive.folder.create') }}', {
            name: document.getElementById('folderName').value,
            parent_path: CURRENT_FOLDER,
        });
        if (result.ok && result.data.success !== false) window.closeFolderModal();
        handleResult(result);
    });

    // =====================================================================
    // Unduh file terenkripsi
    // =====================================================================
    let decryptFileId = null;

    function openDecryptModal(id, name) {
        decryptFileId = id;
        document.getElementById('decryptFileName').textContent = name;
        document.getElementById('decryptModal').classList.remove('hidden');
        document.getElementById('decryptPassword').focus();
    }
    window.openDecryptModal = openDecryptModal;

    window.closeDecryptModal = function () {
        document.getElementById('decryptModal').classList.add('hidden');
        document.getElementById('decryptForm').reset();
    };

    document.getElementById('decryptForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const res = await fetch(`/drive/file/${decryptFileId}/download-encrypted`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF },
            body: new FormData(this),
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            showToast(data.message || 'Password salah', 'error');
            return;
        }

        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = document.getElementById('decryptFileName').textContent;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        showToast('File berhasil diunduh');
        window.closeDecryptModal();
    });

    // =====================================================================
    // Berbagi
    // =====================================================================
    let shareFileId = null;

    function openShareModal(id, name) {
        shareFileId = id;
        document.getElementById('shareFileName').textContent = name;
        document.getElementById('shareResult').classList.add('hidden');
        document.getElementById('shareModal').classList.remove('hidden');
    }

    window.closeShareModal = function () {
        document.getElementById('shareModal').classList.add('hidden');
        document.getElementById('shareForm').reset();
        document.getElementById('shareResult').classList.add('hidden');
        document.getElementById('sharePasswordField').classList.add('hidden');
    };

    window.toggleSharePasswordField = function () {
        document.getElementById('sharePasswordField')
            .classList.toggle('hidden', !document.getElementById('sharePasswordToggle').checked);
    };

    document.getElementById('shareForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const usePassword = document.getElementById('sharePasswordToggle').checked;
        const payload = {
            password: usePassword ? document.getElementById('sharePassword').value : null,
            expires_at: document.getElementById('shareExpires').value || null,
            download_limit: document.getElementById('shareLimit').value || null,
        };

        const { ok, data } = await postJson(`/drive/file/${shareFileId}/share`, payload);

        if (ok && data.success) {
            document.getElementById('shareUrl').value = data.share_url;
            document.getElementById('shareResult').classList.remove('hidden');
            showToast('Link berhasil dibuat');
            return;
        }

        const message = data.message
            || (data.errors ? Object.values(data.errors).flat()[0] : null)
            || 'Gagal membuat link';
        showToast(message, 'error');
    });

    window.copyShareUrl = function () {
        const input = document.getElementById('shareUrl');

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(input.value)
                .then(() => showToast('Link disalin'))
                .catch(() => fallbackCopy(input));
            return;
        }
        fallbackCopy(input);
    };

    function fallbackCopy(input) {
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand('copy');
        showToast('Link disalin');
    }

    // =====================================================================
    // Pratinjau file
    // =====================================================================
    const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
    const VIDEO_EXT = ['mp4', 'webm', 'ogg'];
    const OFFICE_EXT = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf'];

    function openFilePreview(item) {
        const ext = item.name.split('.').pop().toLowerCase();
        const downloadUrl = '/drive/file/' + item.id + '/download';

        if (item.encrypted) {
            openDecryptModal(item.id, item.name);
            return;
        }

        if (IMAGE_EXT.includes(ext)) {
            const img = document.getElementById('previewImage');
            const status = document.getElementById('imagePreviewStatus');

            document.getElementById('imagePreviewTitle').textContent = item.name;
            document.getElementById('imageDownloadBtn').href = downloadUrl;

            img.classList.add('opacity-0');
            status.textContent = 'Memuat gambar...';
            status.classList.remove('hidden');

            img.onload = () => {
                img.classList.remove('opacity-0');
                status.classList.add('hidden');
            };
            img.onerror = () => {
                status.textContent = 'Gambar gagal dimuat.';
            };

            img.src = downloadUrl;
            document.getElementById('imagePreviewModal').classList.remove('hidden');
        } else if (VIDEO_EXT.includes(ext)) {
            const source = document.getElementById('videoSource');

            document.getElementById('videoPreviewTitle').textContent = item.name;
            document.getElementById('videoDownloadBtn').href = downloadUrl;

            // Tipe MIME harus sesuai ekstensi; dipatok video/mp4 membuat berkas
            // webm dan ogg ditolak browser.
            source.type = ext === 'webm' ? 'video/webm' : ext === 'ogg' ? 'video/ogg' : 'video/mp4';
            source.src = downloadUrl;

            document.getElementById('videoPlayer').load();
            document.getElementById('videoPreviewModal').classList.remove('hidden');
        } else if (OFFICE_EXT.includes(ext)) {
            document.getElementById('officePreviewTitle').textContent = item.name;
            document.getElementById('officeDownloadBtn').href = downloadUrl;
            document.getElementById('officeFrame').src = ext === 'pdf'
                ? downloadUrl
                : 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(window.location.origin + downloadUrl);
            document.getElementById('officePreviewModal').classList.remove('hidden');
        } else {
            window.location = downloadUrl;
        }
    }

    window.closeImagePreview = function () {
        document.getElementById('imagePreviewModal').classList.add('hidden');
        document.getElementById('previewImage').src = '';
    };

    window.closeVideoPreview = function () {
        document.getElementById('videoPlayer').pause();
        document.getElementById('videoSource').src = '';
        document.getElementById('videoPreviewModal').classList.add('hidden');
    };

    window.closeOfficePreview = function () {
        document.getElementById('officeFrame').src = '';
        document.getElementById('officePreviewModal').classList.add('hidden');
    };

    ['imagePreviewModal', 'videoPreviewModal', 'officePreviewModal'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', function (e) {
            if (e.target !== this) return;
            if (id === 'imagePreviewModal') window.closeImagePreview();
            if (id === 'videoPreviewModal') window.closeVideoPreview();
            if (id === 'officePreviewModal') window.closeOfficePreview();
        });
    });

    // Tutup modal apa pun dengan Escape
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(m => m.classList.add('hidden'));
        document.getElementById('contextMenu').classList.add('hidden');
        document.getElementById('videoPlayer')?.pause();
    });

    // =====================================================================
    // Interaksi item (klik, dobel klik, klik kanan, seret)
    // =====================================================================
    let dragData = null;

    /**
     * Tandai / lepas bintang.
     *
     * Dipasang sekali di tingkat dokumen: kartu bisa digambar ulang, dan
     * mengikat ulang tiap tombol setiap kali hanya menambah pekerjaan.
     */
    document.addEventListener('click', async e => {
        const tombol = e.target.closest('.star-toggle');
        if (!tombol) return;

        // Kartunya sendiri membuka file/folder saat diklik; bintang tidak boleh
        // ikut memicunya.
        e.preventDefault();
        e.stopPropagation();

        if (tombol.dataset.busy === '1') return;
        tombol.dataset.busy = '1';

        const kind = tombol.dataset.starKind;
        const id = tombol.dataset.starId;
        const url = kind === 'folder' ? `/drive/folder/${id}/star` : `/drive/file/${id}/star`;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Gagal');

            terapkanBintang(tombol, data.is_starred);

            // Di halaman Berbintang, item yang dilepas tidak lagi termasuk —
            // membiarkannya di layar membuat daftarnya berbohong.
            if (!data.is_starred && document.body.dataset.page === 'starred') {
                const kartu = tombol.closest('.drive-item');
                if (kartu) kartu.remove();
                perbaruiKosongBerbintang();
            }
        } catch (err) {
            alert(err.message || 'Gagal memperbarui bintang.');
        } finally {
            tombol.dataset.busy = '0';
        }
    });

    function terapkanBintang(tombol, berbintang) {
        const ikon = tombol.querySelector('i');

        tombol.dataset.starred = berbintang ? '1' : '0';
        tombol.setAttribute('aria-pressed', berbintang ? 'true' : 'false');
        tombol.title = berbintang ? 'Lepas dari berbintang' : 'Tandai berbintang';
        tombol.classList.toggle('text-gold-500', berbintang);
        tombol.classList.toggle('text-slate-600', !berbintang);
        ikon.classList.toggle('fas', berbintang);
        ikon.classList.toggle('far', !berbintang);

        const kartu = tombol.closest('.drive-item');
        if (kartu) kartu.dataset.starred = berbintang ? '1' : '0';
    }

    function perbaruiKosongBerbintang() {
        const kosong = document.getElementById('berbintangKosong');
        if (kosong && document.querySelectorAll('.drive-item').length === 0) {
            kosong.classList.remove('hidden');
            document.querySelectorAll('.berbintang-grup').forEach(g => g.classList.add('hidden'));
        }
    }

    function bindItems() {
        document.querySelectorAll('.drive-item').forEach(el => {
            if (el.dataset.bound === '1') return;
            el.dataset.bound = '1';

            const item = () => readItem(el);

            el.addEventListener('contextmenu', e => showContextMenu(e, item()));

            // Satu klik: folder dibuka, file langsung dipratinjau bila jenisnya
            // didukung (gambar, video, dokumen). Selain itu file diunduh.
            el.addEventListener('click', () => {
                const data = item();
                if (data.kind === 'folder') window.location = data.url;
                else openFilePreview(data);
            });

            el.addEventListener('dragstart', e => {
                dragData = item();
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', dragData.name);
                el.classList.add('dragging');
            });

            el.addEventListener('dragend', () => {
                el.classList.remove('dragging');
                document.getElementById('moveZone').classList.add('hidden');
                clearDropTargets();
                dragData = null;
            });

            // Folder juga menjadi target lepas
            if (el.dataset.kind === 'folder') {
                el.addEventListener('dragover', e => {
                    if (!dragData || dragData.id === el.dataset.id) return;
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    el.classList.add('drop-target');
                });

                el.addEventListener('dragleave', () => el.classList.remove('drop-target'));

                el.addEventListener('drop', async e => {
                    e.preventDefault();
                    e.stopPropagation();
                    el.classList.remove('drop-target');
                    document.getElementById('moveZone').classList.add('hidden');

                    if (!dragData || dragData.id === el.dataset.id) return;
                    await moveItem(dragData, el.dataset.path);
                    dragData = null;
                });
            }
        });
    }

    async function moveItem(item, targetPath) {
        const url = item.kind === 'folder'
            ? `/drive/folder/${item.id}/move`
            : `/drive/file/${item.id}/move`;
        const payload = item.kind === 'folder'
            ? { parent_path: targetPath }
            : { folder: targetPath };

        handleResult(await postJson(url, payload));
    }

    function clearDropTargets() {
        document.querySelectorAll('.drop-target').forEach(el => el.classList.remove('drop-target'));
    }

    // --- Area utama: unggah dari komputer & pindah ke folder saat ini ---
    const main = document.querySelector('main');
    const dropZone = document.getElementById('dropZone');
    const moveZone = document.getElementById('moveZone');

    main.addEventListener('dragover', e => {
        if (dragData) {
            e.preventDefault();
            moveZone.classList.remove('hidden');
            return;
        }
        if (e.dataTransfer.types.includes('Files')) {
            e.preventDefault();
            dropZone.classList.remove('hidden');
            dropZone.classList.add('dragover');
        }
    });

    main.addEventListener('dragleave', e => {
        if (main.contains(e.relatedTarget)) return;
        dropZone.classList.add('hidden');
        dropZone.classList.remove('dragover');
        moveZone.classList.add('hidden');
    });

    main.addEventListener('drop', async e => {
        e.preventDefault();
        dropZone.classList.add('hidden');
        dropZone.classList.remove('dragover');
        moveZone.classList.add('hidden');
        clearDropTargets();

        // File dari komputer → buka modal unggah
        if (!dragData && e.dataTransfer.files.length > 0) {
            const input = document.getElementById('fileInput');
            input.files = e.dataTransfer.files;
            window.handleFileSelect(input);
            window.openUploadModal();
            return;
        }

        if (!dragData) return;

        const alreadyHere = dragData.kind === 'folder'
            ? false
            : dragData.folder === CURRENT_FOLDER;

        if (!alreadyHere) await moveItem(dragData, CURRENT_FOLDER);
        dragData = null;
    });

    // Cegah browser membuka file saat dilepas di luar area
    ['dragover', 'drop'].forEach(ev => {
        document.body.addEventListener(ev, e => {
            if (e.target === document.body) e.preventDefault();
        });
    });

    // =====================================================================
    // Terima file yang dibagikan
    // =====================================================================
    @if(!empty($shareToken))
    window.acceptShare = async function () {
        const btn = document.getElementById('acceptShareBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...';

        const passwordInput = document.getElementById('shareReceivePassword');
        const { ok, data } = await postJson(
            '/share/' + @json($shareToken) + '/download',
            passwordInput ? { password: passwordInput.value } : {}
        );

        if (ok && data.success) {
            showToast(data.message);
            document.getElementById('shareReceiveModal').classList.add('hidden');
            setTimeout(() => { window.location.href = data.redirect || '/drive'; }, 800);
            return;
        }

        showToast(data.message || 'Gagal menerima file', 'error');
        const card = document.getElementById('shareReceiveCard');
        card.classList.add('animate-shake');
        setTimeout(() => card.classList.remove('animate-shake'), 500);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check mr-2"></i>Terima File';
    };
    @endif

    // =====================================================================
    // Inisialisasi
    // =====================================================================
    document.addEventListener('DOMContentLoaded', function () {
        applyView(currentView);
        bindItems();

        @if(!empty($shareToken))
        document.getElementById('shareReceiveModal')?.classList.remove('hidden');
        @endif
    });
})();
</script>
@endpush
