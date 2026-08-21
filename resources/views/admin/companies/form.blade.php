@extends('layouts.app')

@php $baru = !$company->exists; @endphp

@section('title', ($baru ? 'Tambah' : 'Ubah') . ' Perusahaan - Dekorasi Drive')
@section('page-title', ($baru ? 'Tambah' : 'Ubah') . ' Perusahaan')

@section('content')
<div class="max-w-2xl">
    <div class="panel overflow-hidden">
        <div class="bg-gradient-to-br from-navy-700 to-navy-900 p-6 border-b border-navy-600">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gold-500/15 flex items-center justify-center ring-1 ring-gold-500/30">
                    <i class="fas fa-building text-gold-500 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-white">
                        {{ $baru ? 'Perusahaan Baru' : $company->name }}
                    </h2>
                    <p class="text-sm text-slate-400 mt-0.5">
                        {{ $baru
                            ? 'Pengguna perusahaan ini tidak akan pernah melihat data perusahaan lain.'
                            : 'Alamat singkat: ' . $company->slug }}
                    </p>
                </div>
            </div>
        </div>

        <form action="{{ $baru ? route('admin.companies.store') : route('admin.companies.update', $company) }}"
              method="POST" class="p-6 space-y-5">
            @csrf
            @if(!$baru) @method('PUT') @endif

            <div>
                <label class="label" for="name">Nama Perusahaan</label>
                <input type="text" id="name" name="name" required maxlength="255"
                    value="{{ old('name', $company->name) }}"
                    class="field" placeholder="Contoh: PT Dekorasi Nusantara">
                @error('name')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="label" for="email">Email <span class="text-slate-500 font-normal">(opsional)</span></label>
                    <input type="email" id="email" name="email" maxlength="255"
                        value="{{ old('email', $company->email) }}"
                        class="field" placeholder="kontak@perusahaan.com">
                    @error('email')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="phone">Telepon <span class="text-slate-500 font-normal">(opsional)</span></label>
                    <input type="text" id="phone" name="phone" maxlength="40"
                        value="{{ old('phone', $company->phone) }}"
                        class="field" placeholder="021-1234567">
                </div>
            </div>

            <div>
                <label class="label" for="address">Alamat <span class="text-slate-500 font-normal">(opsional)</span></label>
                <textarea id="address" name="address" rows="2" maxlength="1000"
                    class="field" placeholder="Alamat kantor">{{ old('address', $company->address) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="label" for="default_quota_gb">Kuota per Akun (GB)</label>
                    <input type="number" id="default_quota_gb" name="default_quota_gb" step="0.1" min="0.1" max="10240" required
                        value="{{ old('default_quota_gb', round($company->default_quota / 1073741824, 1)) }}"
                        class="field">
                    <p class="text-xs text-slate-500 mt-1.5">Kuota bawaan untuk setiap akun baru di perusahaan ini.</p>
                    @error('default_quota_gb')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label" for="max_users">Batas Jumlah Akun <span class="text-slate-500 font-normal">(opsional)</span></label>
                    <input type="number" id="max_users" name="max_users" min="1" max="100000"
                        value="{{ old('max_users', $company->max_users) }}"
                        class="field" placeholder="Kosongkan = tanpa batas">
                    @error('max_users')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
                </div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="is_active" value="1"
                    {{ old('is_active', $company->is_active ?? true) ? 'checked' : '' }}
                    class="w-5 h-5 mt-0.5 rounded accent-[#d4a843]">
                <span>
                    <span class="text-sm font-medium text-white">Perusahaan Aktif</span>
                    <span class="block text-xs text-slate-400 mt-0.5">
                        Jika dimatikan, seluruh pengguna perusahaan ini ikut kehilangan akses.
                    </span>
                </span>
            </label>

            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-navy-600">
                <a href="{{ route('admin.companies.index') }}" class="btn-ghost px-6 py-3 rounded-xl text-center">Batal</a>
                <button type="submit" class="flex-1 btn-primary px-6 py-3 rounded-xl">
                    <i class="fas fa-save mr-2"></i>{{ $baru ? 'Simpan Perusahaan' : 'Simpan Perubahan' }}
                </button>
            </div>
        </form>
    </div>

    @if(!$baru)
    <div class="panel p-5 mt-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-purple-500/15 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-user-shield text-purple-300"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white">Admin Perusahaan</p>
            <p class="text-xs text-slate-400 mt-0.5">
                Buat akun admin yang hanya mengelola pengguna {{ $company->name }}.
            </p>
        </div>
        <a href="{{ route('admin.companies.admin.create', $company) }}"
           class="btn-ghost px-4 py-2 rounded-xl text-sm whitespace-nowrap">
            <i class="fas fa-plus mr-1.5"></i>Tambah
        </a>
    </div>
    @endif
</div>
@endsection
