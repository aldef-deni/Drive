@extends('layouts.app')

@section('title', 'Berbintang - Dekorasi Drive')
@section('page-title', 'Berbintang')

@section('content')
@php $kosong = $files->count() === 0 && $folders->count() === 0; @endphp

<div class="mb-6 flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl bg-gold-500/15 flex items-center justify-center flex-shrink-0">
        <i class="fas fa-star text-gold-500"></i>
    </div>
    <div class="min-w-0">
        <h2 class="text-lg font-semibold text-white">Item Berbintang</h2>
        <p class="text-xs text-slate-400 mt-0.5">
            {{ $folders->count() }} folder &middot; {{ $files->count() }} file, dikumpulkan dari seluruh folder.
        </p>
    </div>
</div>

@if($showHidden)
<div class="mb-5 p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl flex items-center gap-3">
    <i class="fas fa-user-secret text-amber-400"></i>
    <p class="text-xs text-amber-200/90">Mode rahasia aktif — item tersembunyi ikut ditampilkan di sini.</p>
</div>
@endif

{{-- Kosong --}}
<div id="berbintangKosong" class="py-20 text-center {{ $kosong ? '' : 'hidden' }}">
    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gold-500/10 flex items-center justify-center">
        <i class="far fa-star text-3xl text-gold-500"></i>
    </div>
    <h3 class="text-lg font-semibold text-white mb-1">Belum ada yang ditandai</h3>
    <p class="text-slate-400 text-sm mb-6 max-w-sm mx-auto">
        Ketuk ikon bintang pada file atau folder di Drive untuk menyimpannya di sini,
        supaya cepat ditemukan tanpa menelusuri folder.
    </p>
    <a href="{{ route('drive.index') }}" class="btn-primary inline-flex items-center px-6 py-3 rounded-xl">
        <i class="fas fa-hard-drive mr-2"></i> Buka Drive
    </a>
</div>

@if($folders->count() > 0)
<div class="berbintang-grup mb-8">
    <h3 class="text-sm font-semibold text-slate-300 mb-3">
        <i class="fas fa-folder text-amber-400 mr-2"></i>Folder
    </h3>
    <div class="space-y-2 md:space-y-3">
        @foreach($folders as $folder)
            @include('drive.partials.folder-item', ['folder' => $folder, 'variant' => 'list'])
        @endforeach
    </div>
</div>
@endif

@if($files->count() > 0)
<div class="berbintang-grup">
    <h3 class="text-sm font-semibold text-slate-300 mb-3">
        <i class="fas fa-file text-gold-500 mr-2"></i>File
    </h3>
    <div class="space-y-2 md:space-y-3">
        @foreach($files as $file)
            @include('drive.partials.file-item', ['file' => $file, 'variant' => 'list'])
        @endforeach
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    // Menandai bintang di halaman ini hanya punya satu arah yang masuk akal:
    // melepasnya. Item yang dilepas langsung dikeluarkan dari daftar.
    document.addEventListener('click', async e => {
        const tombol = e.target.closest('.star-toggle');
        if (!tombol) return;

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

            if (!data.is_starred) {
                tombol.closest('.drive-item')?.remove();
                periksaKosong();
            }
        } catch (err) {
            alert(err.message || 'Gagal memperbarui bintang.');
        } finally {
            tombol.dataset.busy = '0';
        }
    });

    function periksaKosong() {
        if (document.querySelectorAll('.drive-item').length > 0) return;

        document.querySelectorAll('.berbintang-grup').forEach(g => g.classList.add('hidden'));
        document.getElementById('berbintangKosong').classList.remove('hidden');
    }

    // Membuka item: folder dijelajah, file diunduh. Pratinjau penuh tetap di
    // halaman Drive supaya halaman ini tidak menduplikasi seluruh mesinnya.
    document.querySelectorAll('.drive-item').forEach(el => {
        el.addEventListener('click', () => {
            if (el.dataset.kind === 'folder') {
                window.location = el.dataset.url;
            } else {
                window.location = `/drive?folder=${encodeURIComponent(el.dataset.folder || '/')}`;
            }
        });
    });
</script>
@endpush
