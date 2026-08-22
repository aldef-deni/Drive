{{--
    Tombol bintang untuk file/folder.

    $kind    : 'file' | 'folder'
    $id      : id item
    $starred : bool
    $variant : small | large | list

    Klik ditangani lewat event delegation di index.blade.php. Tombolnya
    membawa stopPropagation sendiri supaya menandai bintang tidak ikut membuka
    item — dua aksi berbeda di area yang berdampingan.
--}}
@php
    $posisi = match ($variant) {
        'list', 'inline' => 'flex-shrink-0',
        'large' => 'absolute top-2 right-2',
        default => 'absolute top-1.5 right-1.5',
    };

    $ukuran = in_array($variant, ['small', 'inline'], true)
        ? 'w-6 h-6 text-[11px]'
        : 'w-7 h-7 text-xs';
@endphp

<button type="button"
        class="star-toggle {{ $posisi }} {{ $ukuran }} rounded-lg flex items-center justify-center transition
               {{ $starred ? 'text-gold-500' : 'text-slate-600 hover:text-gold-500' }}
               hover:bg-gold-500/10"
        data-star-kind="{{ $kind }}"
        data-star-id="{{ $id }}"
        data-starred="{{ $starred ? '1' : '0' }}"
        aria-pressed="{{ $starred ? 'true' : 'false' }}"
        title="{{ $starred ? 'Lepas dari berbintang' : 'Tandai berbintang' }}">
    <i class="{{ $starred ? 'fas' : 'far' }} fa-star"></i>
</button>
