@extends('layouts.app')

@section('title', 'Hidden System - Dekorasi Drive')
@section('page-title', 'Hidden System')

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Penjelasan konsep --}}
    <div class="panel overflow-hidden">
        <div class="bg-gradient-to-br from-navy-700 to-navy-900 p-6 border-b border-navy-600">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gold-500/15 flex items-center justify-center flex-shrink-0 ring-1 ring-gold-500/30">
                    <i class="fas fa-user-secret text-gold-500 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-white">Kata Kunci Rahasia</h2>
                    <p class="text-sm text-slate-400 mt-1">
                        Kata kunci yang diketik di kolom pencarian untuk memunculkan file dan folder yang disembunyikan.
                    </p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <ol class="space-y-3 text-sm text-slate-300">
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-navy-600 text-gold-500 text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                    <span>Klik kanan file atau folder di Drive, lalu pilih <strong class="text-white">Sembunyikan</strong>. Item itu langsung hilang dari daftar maupun hasil pencarian biasa.</span>
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-navy-600 text-gold-500 text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                    <span>Untuk memunculkannya kembali, ketik <strong class="text-white">kata kunci rahasia</strong> di kolom pencarian Drive lalu tekan Enter.</span>
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-navy-600 text-gold-500 text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                    <span>Mode rahasia menyala sampai Anda menutupnya lewat tombol silang pada banner, atau keluar dari akun. Selama menyala, klik kanan &rarr; <strong class="text-white">Tampilkan</strong> untuk mengembalikan item ke Drive.</span>
                </li>
            </ol>
        </div>
    </div>

    {{-- Status kata kunci --}}
    <div class="panel p-5 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 {{ $isDefault ? 'bg-amber-500/15' : 'bg-green-500/15' }}">
            <i class="fas {{ $isDefault ? 'fa-triangle-exclamation text-amber-400' : 'fa-shield-halved text-green-400' }}"></i>
        </div>
        <div class="flex-1 min-w-0">
            @if($isDefault)
            <p class="text-sm font-medium text-amber-300">Masih memakai kata kunci bawaan</p>
            <p class="text-xs text-slate-400 mt-0.5">Kata kunci bawaan diketahui banyak orang. Sebaiknya segera diganti.</p>
            @else
            <p class="text-sm font-medium text-green-300">Kata kunci sudah diganti</p>
            <p class="text-xs text-slate-400 mt-0.5">
                Terakhir diperbarui {{ $updatedAt?->translatedFormat('d F Y, H:i') ?? '-' }}
            </p>
            @endif
        </div>
        <span class="text-xs text-slate-500 flex items-center gap-1.5">
            <i class="fas fa-lock"></i>
            {{ $canReveal ? 'Disimpan terenkripsi' : 'Disimpan terenkripsi, hanya superadmin yang bisa melihat' }}
        </span>
    </div>

    {{-- Kata kunci yang sedang berlaku, khusus superadministrator --}}
    @if($canReveal)
    <div class="panel overflow-hidden">
        <div class="p-6 border-b border-navy-600 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gold-500/15 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-key text-gold-500"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-lg font-semibold text-white">Kata Kunci Aktif</h2>
                <p class="text-xs text-slate-400 mt-0.5">Hanya superadministrator yang bisa melihat bagian ini.</p>
            </div>
        </div>

        <div class="p-6">
            @if($keyword !== null)
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <input type="password" id="activeKeyword" value="{{ $keyword }}" readonly
                        class="field !pr-11 font-mono tracking-wider">
                    <button type="button" onclick="toggleActiveKeyword(this)" aria-label="Tampilkan kata kunci"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-gold-500">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="button" onclick="salinKeyword()" class="btn-ghost px-4 py-3 rounded-xl whitespace-nowrap">
                    <i class="fas fa-copy mr-2"></i><span id="salinLabel">Salin</span>
                </button>
            </div>

            <p class="text-xs text-slate-500 mt-3">
                Ketik kata kunci ini di kolom pencarian Drive untuk memunculkan file tersembunyi.
                @if($isDefault)
                <span class="text-amber-400">Ini masih kata kunci bawaan.</span>
                @endif
            </p>
            @else
            <div class="flex gap-3 p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl">
                <i class="fas fa-circle-info text-amber-400 mt-0.5"></i>
                <div class="text-sm text-amber-200/90 leading-relaxed">
                    <p class="font-medium text-amber-300 mb-1">Kata kunci lama tidak bisa ditampilkan</p>
                    <p class="text-xs">
                        Kata kunci yang tersimpan sekarang dibuat oleh versi sebelumnya dan disimpan
                        satu arah, sehingga tidak mungkin dibaca kembali. Ganti sekali lewat formulir
                        di bawah, setelah itu nilainya akan terlihat di sini.
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Form ganti kata kunci --}}
    <div class="panel overflow-hidden">
        <div class="p-6 border-b border-navy-600">
            <h2 class="text-lg font-semibold text-white"><i class="fas fa-key text-gold-500 mr-2"></i>Ganti Kata Kunci</h2>
        </div>

        <form action="{{ route('admin.hidden.update') }}" method="POST" class="p-6 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="label" for="currentPassword">Password Admin</label>
                <input type="password" id="currentPassword" name="current_password" required autocomplete="current-password"
                    class="field" placeholder="Password login Anda">
                <p class="text-xs text-slate-500 mt-1.5">Diminta sebagai konfirmasi bahwa Anda yang mengganti.</p>
                @error('current_password')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="keyword">Kata Kunci Baru</label>
                <div class="relative">
                    <input type="password" id="keyword" name="keyword" required minlength="4" maxlength="64"
                        class="field !pr-11" placeholder="Minimal 4 karakter, tanpa spasi di awal/akhir">
                    <button type="button" onclick="toggleKeyword('keyword', this)" aria-label="Tampilkan kata kunci"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-gold-500">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                @error('keyword')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="keywordConfirm">Ulangi Kata Kunci Baru</label>
                <div class="relative">
                    <input type="password" id="keywordConfirm" name="keyword_confirmation" required
                        class="field !pr-11" placeholder="Ketik ulang kata kunci baru">
                    <button type="button" onclick="toggleKeyword('keywordConfirm', this)" aria-label="Tampilkan kata kunci"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-gold-500">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="p-4 bg-navy-700/60 border border-navy-600 rounded-xl flex gap-3">
                <i class="fas fa-circle-info text-gold-500 mt-0.5"></i>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Kata kunci berlaku untuk seluruh pengguna dan hanya membuka file tersembunyi
                    milik masing-masing akun. Setelah diganti, kata kunci lama langsung tidak berlaku
                    dan tidak bisa dipulihkan &mdash; catat di tempat aman.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-1">
                <a href="{{ route('admin.index') }}" class="btn-ghost px-6 py-3 rounded-xl text-center">Kembali</a>
                <button type="submit" class="flex-1 btn-primary px-6 py-3 rounded-xl">
                    <i class="fas fa-save mr-2"></i>Simpan Kata Kunci
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleActiveKeyword(button) {
        const input = document.getElementById('activeKeyword');
        const icon = button.querySelector('i');
        const tampil = input.type === 'password';

        input.type = tampil ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !tampil);
        icon.classList.toggle('fa-eye-slash', tampil);
    }

    function salinKeyword() {
        const input = document.getElementById('activeKeyword');
        const label = document.getElementById('salinLabel');

        const tandai = () => {
            label.textContent = 'Tersalin';
            setTimeout(() => { label.textContent = 'Salin'; }, 1800);
        };

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(input.value).then(tandai).catch(() => cadangan(input, tandai));
            return;
        }

        cadangan(input, tandai);
    }

    // Sebagian browser menolak clipboard API di luar HTTPS.
    function cadangan(input, tandai) {
        const semula = input.type;
        input.type = 'text';
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand('copy');
        input.type = semula;
        tandai();
    }

    function toggleKeyword(id, button) {
        const input = document.getElementById(id);
        const icon = button.querySelector('i');
        const show = input.type === 'password';

        input.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);
    }
</script>
@endpush
