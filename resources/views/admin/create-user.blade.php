@extends('layouts.app')

@section('title', 'Tambah User - Dekorasi Drive')
@section('page-title', 'Tambah User')

@section('content')
<div class="max-w-2xl">
    <div class="panel overflow-hidden">
        <div class="bg-gradient-to-br from-navy-700 to-navy-900 p-6 border-b border-navy-600">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gold-500/15 flex items-center justify-center ring-1 ring-gold-500/30">
                    <i class="fas fa-user-plus text-gold-500 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-white">Akun Baru</h2>
                    <p class="text-sm text-slate-400 mt-0.5">
                        Dibuat oleh Anda, jadi langsung aktif tanpa perlu diverifikasi lagi.
                    </p>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.users.store') }}" method="POST" class="p-6 space-y-5">
            @csrf

            <div>
                <label class="label" for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name" required maxlength="255"
                    value="{{ old('name') }}" class="field" placeholder="Nama pengguna">
                @error('name')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="255"
                    value="{{ old('email') }}" class="field" placeholder="nama@perusahaan.com">
                <p class="text-xs text-slate-500 mt-1.5">Dipakai untuk login, jadi harus belum terdaftar.</p>
                @error('email')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="label" for="company_id">Perusahaan</label>
                    @if($kunciPerusahaan)
                    {{-- Admin perusahaan tidak memilih: akunnya selalu masuk ke
                         perusahaannya sendiri, dan server pun mengabaikan isian
                         ini kalau sampai diubah. --}}
                    <div class="field flex items-center gap-2 !bg-navy-800 text-slate-300">
                        <i class="fas fa-building text-gold-500"></i>
                        <span class="truncate">{{ $companies->first()->name }}</span>
                        <i class="fas fa-lock text-xs text-slate-500 ml-auto"></i>
                    </div>
                    <input type="hidden" name="company_id" value="{{ $companies->first()->id }}">
                    <p class="text-xs text-slate-500 mt-1.5">
                        Akun baru selalu masuk ke perusahaan Anda.
                    </p>
                    @else
                    <select id="company_id" name="company_id" required class="field"
                            onchange="perbaruiKuota(this)">
                        <option value="" disabled hidden {{ old('company_id') ? '' : 'selected' }}>
                            Pilih perusahaan
                        </option>
                        @foreach($companies as $c)
                        <option value="{{ $c->id }}" data-kuota="{{ $c->defaultQuotaGb() }}"
                            {{ (string) old('company_id') === (string) $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                        @endforeach
                    </select>
                    @endif
                    @error('company_id')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="role">Peran</label>
                    <select id="role" name="role" required class="field">
                        <option value="{{ \App\Models\User::ROLE_USER }}"
                            {{ old('role') === \App\Models\User::ROLE_ADMIN ? '' : 'selected' }}>User</option>
                        <option value="{{ \App\Models\User::ROLE_ADMIN }}"
                            {{ old('role') === \App\Models\User::ROLE_ADMIN ? 'selected' : '' }}>Admin</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1.5">
                        Admin hanya mengelola pengguna di perusahaannya sendiri.
                    </p>
                    @error('role')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="label" for="password">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required minlength="8"
                            class="field !pr-11" placeholder="Minimal 8 karakter">
                        <button type="button" onclick="toggleField('password', this)" aria-label="Tampilkan password"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-gold-500">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    @error('password')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="password_confirmation">Ulangi Password</label>
                    <div class="relative">
                        <input type="password" id="password_confirmation" name="password_confirmation" required
                            class="field !pr-11" placeholder="Ketik ulang password">
                        <button type="button" onclick="toggleField('password_confirmation', this)" aria-label="Tampilkan password"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-gold-500">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-navy-700/60 border border-navy-600 rounded-xl flex gap-3">
                <i class="fas fa-circle-info text-gold-500 mt-0.5"></i>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Akun langsung aktif dan bisa dipakai login saat itu juga.
                    Kuotanya <strong class="text-slate-300" id="ringkasanKuota">{{ $kunciPerusahaan ? $companies->first()->defaultQuotaGb() . ' GB' : 'mengikuti pengaturan perusahaan' }}</strong>,
                    dan bisa diubah kapan saja lewat
                    {{ auth()->user()->isSuperAdmin() ? 'menu Kuota Penyimpanan' : 'Manajemen User' }}.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-navy-600">
                <a href="{{ route('admin.users') }}" class="btn-ghost px-6 py-3 rounded-xl text-center">Batal</a>
                <button type="submit" class="flex-1 btn-primary px-6 py-3 rounded-xl">
                    <i class="fas fa-user-plus mr-2"></i>Buat Akun
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleField(id, button) {
        const input = document.getElementById(id);
        const icon = button.querySelector('i');
        const show = input.type === 'password';

        input.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);
    }

    // Kuota mengikuti perusahaan, jadi tunjukkan angkanya begitu dipilih -
    // supaya tidak perlu menebak berapa yang didapat akun baru ini.
    function perbaruiKuota(select) {
        const kuota = select.selectedOptions[0]?.dataset.kuota;
        const label = document.getElementById('ringkasanKuota');

        label.textContent = kuota ? kuota + ' GB' : 'mengikuti pengaturan perusahaan';
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Admin perusahaan tidak punya select-nya; kuotanya sudah tercetak.
        const select = document.getElementById('company_id');
        if (select && select.value) perbaruiKuota(select);
    });
</script>
@endpush
