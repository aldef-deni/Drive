{{--
    Kartu file. $variant: small | large | list
    Interaksi (klik kanan / dobel klik) ditangani lewat event delegation.
--}}
@php
    $isShared = $file->isShared();
@endphp

@if($variant === 'list')
<div class="drive-item panel px-4 py-3 hover-lift cursor-pointer flex items-center gap-3 md:gap-4"
     draggable="true"
     data-kind="file"
     data-id="{{ $file->id }}"
     data-name="{{ $file->original_name }}"
     data-folder="{{ $file->folder }}"
     data-mime="{{ $file->mime_type }}"
     data-hidden="{{ $file->is_hidden ? '1' : '0' }}"
     data-locked="{{ $file->lock_password ? '1' : '0' }}"
     data-encrypted="{{ $file->is_encrypted ? '1' : '0' }}"
     data-shared="{{ $isShared ? '1' : '0' }}">
    <div class="w-10 h-10 rounded-xl bg-navy-700 flex items-center justify-center flex-shrink-0">
        <i class="fas {{ $file->getIconClass() }} text-lg"></i>
    </div>
    <div class="flex-1 min-w-0">
        <p class="font-medium text-white text-sm truncate">{{ $file->original_name }}</p>
        <p class="text-[11px] text-slate-500 md:hidden">{{ $file->formatSize() }} &middot; {{ $file->updated_at->format('d M Y') }}</p>
    </div>
    <span class="text-xs text-slate-400 hidden md:block w-20 text-right">{{ $file->formatSize() }}</span>
    <span class="text-xs text-slate-500 hidden lg:block w-40 truncate">{{ $file->mime_type }}</span>
    <span class="text-xs text-slate-400 hidden md:block w-24 text-right">{{ $file->updated_at->format('d M Y') }}</span>
    @if($isShared)<span class="px-2 py-0.5 bg-blue-500/15 text-blue-400 text-xs rounded-full hidden md:inline-flex"><i class="fas fa-share-alt mr-1"></i>Dibagikan</span>@endif
    @if($file->lock_password)<span class="px-2 py-0.5 bg-red-500/15 text-red-400 text-xs rounded-full hidden md:inline-flex"><i class="fas fa-lock mr-1"></i>Terkunci</span>@endif
</div>

@elseif($variant === 'large')
<div class="drive-item panel p-5 hover-lift cursor-pointer"
     draggable="true"
     data-kind="file"
     data-id="{{ $file->id }}"
     data-name="{{ $file->original_name }}"
     data-folder="{{ $file->folder }}"
     data-mime="{{ $file->mime_type }}"
     data-hidden="{{ $file->is_hidden ? '1' : '0' }}"
     data-locked="{{ $file->lock_password ? '1' : '0' }}"
     data-encrypted="{{ $file->is_encrypted ? '1' : '0' }}"
     data-shared="{{ $isShared ? '1' : '0' }}">
    <div class="flex flex-col items-center text-center">
        <div class="w-20 h-20 rounded-2xl bg-navy-700 flex items-center justify-center mb-3">
            <i class="fas {{ $file->getIconClass() }} text-4xl"></i>
        </div>
        <p class="font-medium text-white text-sm truncate w-full">{{ $file->original_name }}</p>
        <p class="text-xs text-slate-500 mt-0.5">{{ $file->formatSize() }}</p>
        <div class="flex items-center gap-1 mt-1.5 min-h-[18px]">
            @if($isShared)<span class="px-1.5 py-0.5 bg-blue-500/15 text-blue-400 text-[10px] rounded-full"><i class="fas fa-share-alt"></i></span>@endif
            @if($file->lock_password)<span class="px-1.5 py-0.5 bg-red-500/15 text-red-400 text-[10px] rounded-full"><i class="fas fa-lock"></i></span>@endif
            @if($file->is_hidden)<span class="px-1.5 py-0.5 bg-amber-500/15 text-amber-400 text-[10px] rounded-full"><i class="fas fa-eye-slash"></i></span>@endif
        </div>
    </div>
</div>

@else
<div class="drive-item panel p-3 hover-lift cursor-pointer relative"
     draggable="true"
     data-kind="file"
     data-id="{{ $file->id }}"
     data-name="{{ $file->original_name }}"
     data-folder="{{ $file->folder }}"
     data-mime="{{ $file->mime_type }}"
     data-hidden="{{ $file->is_hidden ? '1' : '0' }}"
     data-locked="{{ $file->lock_password ? '1' : '0' }}"
     data-encrypted="{{ $file->is_encrypted ? '1' : '0' }}"
     data-shared="{{ $isShared ? '1' : '0' }}">
    <div class="flex flex-col items-center text-center">
        <div class="w-11 h-11 rounded-xl bg-navy-700 flex items-center justify-center mb-2">
            <i class="fas {{ $file->getIconClass() }} text-xl"></i>
        </div>
        <p class="font-medium text-white text-xs truncate w-full">{{ $file->original_name }}</p>
        <p class="text-[10px] text-slate-500 mt-0.5">{{ $file->formatSize() }}</p>
    </div>
    <div class="absolute top-1.5 right-1.5 flex gap-1">
        @if($file->lock_password)<i class="fas fa-lock text-red-400 text-[10px]"></i>@endif
        @if($isShared)<i class="fas fa-share-alt text-blue-400 text-[10px]"></i>@endif
    </div>
</div>
@endif
