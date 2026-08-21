@extends('layouts.app')

@section('title', 'Perusahaan - Dekorasi Drive')
@section('page-title', 'Perusahaan')

@section('header-actions')
<a href="{{ route('admin.companies.create') }}" class="btn-primary px-4 py-2 rounded-xl flex items-center gap-2 text-sm">
    <i class="fas fa-plus"></i> <span class="hidden sm:inline">Tambah Perusahaan</span>
</a>
@endsection

@section('content')
{{-- Ringkasan --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5 mb-6 md:mb-8">
    @foreach([
        ['building', 'Total Perusahaan', $stats['total'], 'gold'],
        ['circle-check', 'Aktif', $stats['active'], 'green'],
        ['users', 'Total Pengguna', $stats['users'], 'blue'],
        ['hard-drive', 'Penyimpanan', \App\Models\User::formatStorageSize($stats['storage']), 'purple'],
    ] as [$icon, $label, $value, $tone])
    <div class="panel p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl flex items-center justify-center
                @if($tone === 'gold') bg-gold-500/15
                @elseif($tone === 'green') bg-green-500/15
                @elseif($tone === 'blue') bg-blue-500/15
                @else bg-purple-500/15 @endif">
                <i class="fas fa-{{ $icon }} text-lg md:text-2xl
                    @if($tone === 'gold') text-gold-500
                    @elseif($tone === 'green') text-green-400
                    @elseif($tone === 'blue') text-blue-400
                    @else text-purple-400 @endif"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs md:text-sm text-slate-400">{{ $label }}</p>
                <p class="text-lg md:text-2xl font-bold text-white truncate">{{ $value }}</p>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Daftar --}}
<div class="panel overflow-hidden">
    <div class="p-4 md:p-6 border-b border-navy-600 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1">
            <h2 class="text-lg font-semibold text-white">Daftar Perusahaan</h2>
            <p class="text-xs text-slate-400 mt-0.5">Setiap perusahaan memiliki pengguna dan data yang terpisah penuh.</p>
        </div>

        <form action="{{ route('admin.companies.index') }}" method="GET" class="relative w-full sm:w-64">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari perusahaan..."
                class="field !py-2 !pl-9 text-sm">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gold-500 text-xs"></i>
        </form>
    </div>

    @if($companies->count() === 0)
    <div class="py-16 text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gold-500/10 flex items-center justify-center">
            <i class="fas fa-building text-3xl text-gold-500"></i>
        </div>
        <h3 class="text-lg font-semibold text-white mb-1">
            {{ $search ? 'Tidak ada hasil' : 'Belum ada perusahaan' }}
        </h3>
        <p class="text-slate-400 text-sm mb-6">
            {{ $search ? 'Coba kata kunci lain.' : 'Tambahkan perusahaan pertama untuk mulai memisahkan data.' }}
        </p>
        @if(!$search)
        <a href="{{ route('admin.companies.create') }}" class="btn-primary inline-flex items-center px-6 py-3 rounded-xl">
            <i class="fas fa-plus mr-2"></i> Tambah Perusahaan
        </a>
        @endif
    </div>
    @else
    <div class="divide-y divide-navy-600/60">
        @foreach($companies as $company)
        @php
            $terpakai = $company->storageUsed();
            $kapasitas = $company->default_quota * max($company->users_count, 1);
            $persen = $kapasitas > 0 ? min(100, ($terpakai / $kapasitas) * 100) : 0;
        @endphp
        <div class="p-4 md:p-5 hover:bg-navy-700/40 transition">
            <div class="flex items-start gap-4">
                {{-- Inisial --}}
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-lg
                    {{ $company->is_active ? 'bg-gradient-to-br from-gold-500 to-gold-600 text-navy-900' : 'bg-navy-700 text-slate-500' }}">
                    {{ strtoupper(substr($company->name, 0, 1)) }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-semibold text-white truncate">{{ $company->name }}</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                            {{ $company->is_active ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300' }}">
                            {{ $company->is_active ? 'AKTIF' : 'NONAKTIF' }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-400 mt-1 truncate">
                        <span class="text-slate-500">{{ $company->slug }}</span>
                        @if($company->email) &middot; {{ $company->email }} @endif
                        @if($company->phone) &middot; {{ $company->phone }} @endif
                    </p>

                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-2 text-xs text-slate-400">
                        <span><i class="fas fa-users mr-1.5 text-gold-500/70"></i>{{ $company->users_count }}
                            @if($company->max_users)<span class="text-slate-600">/ {{ $company->max_users }}</span>@endif pengguna</span>
                        <span><i class="fas fa-hard-drive mr-1.5 text-gold-500/70"></i>{{ \App\Models\User::formatStorageSize($terpakai) }} terpakai</span>
                        <span><i class="fas fa-gauge mr-1.5 text-gold-500/70"></i>Kuota {{ number_format($company->default_quota / 1073741824, 1) }} GB / akun</span>
                    </div>

                    <div class="mt-3 max-w-md">
                        <div class="w-full bg-navy-900 rounded-full h-1.5 overflow-hidden">
                            <div class="progress-bar h-1.5 rounded-full" style="width: {{ max($persen, 1) }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Aksi --}}
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('admin.users', ['company' => $company->id]) }}"
                       title="Lihat pengguna"
                       class="w-9 h-9 rounded-lg bg-navy-600 hover:bg-navy-500 text-slate-300 flex items-center justify-center transition">
                        <i class="fas fa-users text-sm"></i>
                    </a>

                    <a href="{{ route('admin.companies.admin.create', $company) }}"
                       title="Tambah admin"
                       class="w-9 h-9 rounded-lg bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 flex items-center justify-center transition">
                        <i class="fas fa-user-shield text-sm"></i>
                    </a>

                    <a href="{{ route('admin.companies.edit', $company) }}"
                       title="Ubah"
                       class="w-9 h-9 rounded-lg bg-navy-600 hover:bg-navy-500 text-gold-500 flex items-center justify-center transition">
                        <i class="fas fa-pen text-sm"></i>
                    </a>

                    <form action="{{ route('admin.companies.toggle', $company) }}" method="POST"
                          onsubmit="return confirm('{{ $company->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $company->name }} beserta seluruh penggunanya?')">
                        @csrf
                        <button type="submit" title="{{ $company->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                            class="w-9 h-9 rounded-lg flex items-center justify-center transition
                                {{ $company->is_active ? 'bg-yellow-500/15 hover:bg-yellow-500/25 text-yellow-400' : 'bg-green-500/15 hover:bg-green-500/25 text-green-400' }}">
                            <i class="fas {{ $company->is_active ? 'fa-ban' : 'fa-check' }} text-sm"></i>
                        </button>
                    </form>

                    <form action="{{ route('admin.companies.destroy', $company) }}" method="POST"
                          onsubmit="return confirm('Hapus perusahaan {{ $company->name }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" title="Hapus"
                            class="w-9 h-9 rounded-lg bg-red-500/15 hover:bg-red-500/25 text-red-400 flex items-center justify-center transition">
                            <i class="fas fa-trash text-sm"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="p-4 border-t border-navy-600">
        {{ $companies->links() }}
    </div>
    @endif
</div>
@endsection
