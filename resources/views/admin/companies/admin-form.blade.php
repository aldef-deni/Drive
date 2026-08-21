@extends('layouts.app')

@section('title', 'Tambah Admin - Dekorasi Drive')
@section('page-title', 'Tambah Admin Perusahaan')

@section('content')
<div class="max-w-xl">
    <div class="panel overflow-hidden">
        <div class="bg-gradient-to-br from-navy-700 to-navy-900 p-6 border-b border-navy-600">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-500/15 flex items-center justify-center ring-1 ring-purple-500/30">
                    <i class="fas fa-user-shield text-purple-300 text-xl"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-white">Admin untuk {{ $company->name }}</h2>
                    <p class="text-sm text-slate-400 mt-0.5">
                        Akun ini hanya melihat dan mengelola pengguna {{ $company->name }}.
                    </p>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.companies.admin.store', $company) }}" method="POST" class="p-6 space-y-5">
            @csrf

            <div>
                <label class="label" for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name" required maxlength="255"
                    value="{{ old('name') }}" class="field" placeholder="Nama admin">
                @error('name')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="255"
                    value="{{ old('email') }}" class="field" placeholder="admin@perusahaan.com">
                @error('email')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8"
                    class="field" placeholder="Minimal 8 karakter">
                @error('password')<p class="text-sm text-red-400 mt-1.5">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="password_confirmation">Ulangi Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="field" placeholder="Ketik ulang password">
            </div>

            <div class="p-4 bg-navy-700/60 border border-navy-600 rounded-xl flex gap-3">
                <i class="fas fa-circle-info text-gold-500 mt-0.5"></i>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Akun langsung aktif tanpa perlu verifikasi, dengan kuota
                    {{ $company->defaultQuotaGb() }} GB mengikuti
                    pengaturan perusahaan. Admin ini tidak bisa melihat perusahaan lain
                    maupun mengelola perusahaan.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-navy-600">
                <a href="{{ route('admin.companies.index') }}" class="btn-ghost px-6 py-3 rounded-xl text-center">Batal</a>
                <button type="submit" class="flex-1 btn-primary px-6 py-3 rounded-xl">
                    <i class="fas fa-user-plus mr-2"></i>Buat Admin
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
