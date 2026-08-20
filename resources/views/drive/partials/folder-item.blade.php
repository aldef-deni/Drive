{{--
    Kartu folder. $variant: small | large | list
    Semua data dipasang sebagai data-attribute; interaksi ditangani lewat event
    delegation di index.blade.php sehingga nama berisi kutip tidak merusak JS.
--}}
@php
    $isLockedTree = $folder->lock_password || $folder->hasLockedFiles();
@endphp

@if($variant === 'list')
<div class="drive-item panel px-4 py-3 hover-lift cursor-pointer flex items-center gap-3 md:gap-4"
     draggable="true"
     data-kind="folder"
     data-id="{{ $folder->id }}"
     data-name="{{ $folder->name }}"
     data-path="{{ $folder->path }}"
     data-url="{{ route('drive.index', ['folder' => $folder->path]) }}"
     data-hidden="{{ $folder->is_hidden ? '1' : '0' }}"
     data-locked="{{ $isLockedTree ? '1' : '0' }}">
    <div class="w-10 h-10 rounded-xl bg-amber-500/15 flex items-center justify-center flex-shrink-0">
        <i class="fas fa-folder text-amber-400 text-lg"></i>
    </div>
    <div class="flex-1 min-w-0">
        <p class="font-medium text-white text-sm truncate">{{ $folder->name }}</p>
        <p class="text-[11px] text-slate-500 md:hidden">{{ $folder->created_at->format('d M Y') }}</p>
    </div>
    <span class="text-xs text-slate-400 hidden md:block">{{ $folder->created_at->format('d M Y') }}</span>
    @if($folder->lock_password)
    <i class="fas fa-lock text-red-400 text-sm" title="Terkunci"></i>
    @endif
    @if($folder->is_hidden)
    <span class="px-2 py-0.5 bg-amber-500/15 text-amber-400 text-xs rounded-full"><i class="fas fa-eye-slash mr-1"></i>Tersembunyi</span>
    @endif
</div>

@elseif($variant === 'large')
<div class="drive-item panel p-5 hover-lift cursor-pointer relative"
     draggable="true"
     data-kind="folder"
     data-id="{{ $folder->id }}"
     data-name="{{ $folder->name }}"
     data-path="{{ $folder->path }}"
     data-url="{{ route('drive.index', ['folder' => $folder->path]) }}"
     data-hidden="{{ $folder->is_hidden ? '1' : '0' }}"
     data-locked="{{ $isLockedTree ? '1' : '0' }}">
    <div class="flex flex-col items-center text-center">
        <div class="w-20 h-20 rounded-2xl bg-amber-500/15 flex items-center justify-center mb-3">
            <i class="fas fa-folder text-amber-400 text-4xl"></i>
        </div>
        <p class="font-medium text-white text-sm truncate w-full">{{ $folder->name }}</p>
        <p class="text-xs text-slate-500 mt-0.5">{{ $folder->created_at->format('d M Y') }}</p>
        @if($folder->lock_password)
        <span class="mt-1.5 px-2 py-0.5 bg-red-500/15 text-red-400 text-[10px] rounded-full"><i class="fas fa-lock mr-1"></i>Terkunci</span>
        @endif
    </div>
    @if($folder->is_hidden)
    <span class="absolute top-2 left-2 px-2 py-1 bg-amber-500/15 text-amber-400 text-[10px] rounded-full"><i class="fas fa-eye-slash"></i></span>
    @endif
</div>

@else
<div class="drive-item panel p-3 hover-lift cursor-pointer relative"
     draggable="true"
     data-kind="folder"
     data-id="{{ $folder->id }}"
     data-name="{{ $folder->name }}"
     data-path="{{ $folder->path }}"
     data-url="{{ route('drive.index', ['folder' => $folder->path]) }}"
     data-hidden="{{ $folder->is_hidden ? '1' : '0' }}"
     data-locked="{{ $isLockedTree ? '1' : '0' }}">
    <div class="flex flex-col items-center text-center">
        <div class="w-11 h-11 rounded-xl bg-amber-500/15 flex items-center justify-center mb-2">
            <i class="fas fa-folder text-amber-400 text-xl"></i>
        </div>
        <p class="font-medium text-white text-xs truncate w-full">{{ $folder->name }}</p>
        @if($folder->lock_password)
        <i class="fas fa-lock text-red-400 text-[10px] mt-1"></i>
        @endif
    </div>
    @if($folder->is_hidden)
    <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 bg-amber-500/15 text-amber-400 text-[10px] rounded-full"><i class="fas fa-eye-slash"></i></span>
    @endif
</div>
@endif
