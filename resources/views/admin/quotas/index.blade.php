@extends('layouts.app')

@section('title', 'Kuota Penyimpanan - Dekorasi Drive')
@section('page-title', 'Kuota Penyimpanan')

@section('header-actions')
<button onclick="openBulk()" class="btn-primary px-4 py-2 rounded-xl flex items-center gap-2 text-sm">
    <i class="fas fa-layer-group"></i> <span class="hidden sm:inline">Atur Massal</span>
</button>
@endsection

@section('content')
{{-- Ringkasan --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5 mb-6">
    <div class="panel p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-gold-500/15 flex items-center justify-center">
                <i class="fas fa-database text-lg md:text-2xl text-gold-500"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs md:text-sm text-slate-400">Total Dialokasikan</p>
                <p class="text-lg md:text-2xl font-bold text-white truncate">
                    {{ \App\Models\User::formatStorageSize($stats['allocated']) }}
                </p>
            </div>
        </div>
    </div>

    <div class="panel p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-blue-500/15 flex items-center justify-center">
                <i class="fas fa-hard-drive text-lg md:text-2xl text-blue-400"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs md:text-sm text-slate-400">Terpakai</p>
                <p class="text-lg md:text-2xl font-bold text-white truncate">
                    {{ \App\Models\User::formatStorageSize($stats['used']) }}
                </p>
            </div>
        </div>
    </div>

    <div class="panel p-4 md:p-6 hover-lift">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-purple-500/15 flex items-center justify-center">
                <i class="fas fa-percent text-lg md:text-2xl text-purple-400"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs md:text-sm text-slate-400">Pemanfaatan</p>
                <p class="text-lg md:text-2xl font-bold text-white">{{ number_format($stats['percent'], 1) }}%</p>
            </div>
        </div>
    </div>

    <div class="panel p-4 md:p-6 hover-lift {{ $stats['near_full'] > 0 ? 'border-amber-500/40' : '' }}">
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl bg-amber-500/15 flex items-center justify-center">
                <i class="fas fa-triangle-exclamation text-lg md:text-2xl text-amber-400"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs md:text-sm text-slate-400">Hampir Penuh</p>
                <p class="text-lg md:text-2xl font-bold {{ $stats['near_full'] > 0 ? 'text-amber-300' : 'text-white' }}">
                    {{ $stats['near_full'] }}
                </p>
            </div>
        </div>
    </div>
</div>

{{-- Kuota bawaan tiap perusahaan --}}
@if($companies->count() > 0)
<div class="panel overflow-hidden mb-6">
    <div class="p-4 md:p-5 border-b border-navy-600">
        <h2 class="text-base font-semibold text-white">Kuota Bawaan per Perusahaan</h2>
        <p class="text-xs text-slate-400 mt-0.5">
            Berlaku untuk akun baru. Tombol di kanan menyamakan seluruh pengguna lama ke nilai ini.
        </p>
    </div>

    <div class="divide-y divide-navy-600/60">
        @foreach($companies as $c)
        <div class="p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-gold-500/15 text-gold-500 flex items-center justify-center flex-shrink-0 text-sm font-bold">
                {{ strtoupper(substr($c->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate">{{ $c->name }}</p>
                <p class="text-xs text-slate-400">
                    Bawaan {{ number_format($c->default_quota / 1073741824, 1) }} GB per akun
                </p>
            </div>
            <form action="{{ route('admin.quotas.company-default', $c) }}" method="POST"
                  onsubmit="return confirm('Samakan kuota SEMUA pengguna {{ $c->name }} menjadi {{ number_format($c->default_quota / 1073741824, 1) }} GB?')">
                @csrf
                <button type="submit" class="btn-ghost px-3 py-2 rounded-lg text-xs whitespace-nowrap">
                    <i class="fas fa-wand-magic-sparkles mr-1.5"></i>Samakan
                </button>
            </form>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Daftar pengguna --}}
<div class="panel overflow-hidden">
    <div class="p-4 md:p-6 border-b border-navy-600 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
            <h2 class="text-lg font-semibold text-white">Kuota per Pengguna</h2>
            <p class="text-xs text-slate-400 mt-0.5">Ubah angka lalu tekan Enter untuk menyimpan.</p>
        </div>

        <form action="{{ route('admin.quotas.index') }}" method="GET" class="flex flex-col sm:flex-row gap-2">
            <div class="relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama atau email..."
                    class="field !py-2 !pl-9 text-sm sm:w-52">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gold-500 text-xs"></i>
            </div>

            <select name="company" onchange="this.form.submit()" class="field !py-2 text-sm">
                <option value="">Semua perusahaan</option>
                @foreach($companies as $c)
                <option value="{{ $c->id }}" {{ (string) $companyId === (string) $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
                @endforeach
            </select>

            <select name="sort" onchange="this.form.submit()" class="field !py-2 text-sm">
                <option value="usage" {{ $sort === 'usage' ? 'selected' : '' }}>Paling penuh</option>
                <option value="quota" {{ $sort === 'quota' ? 'selected' : '' }}>Kuota terbesar</option>
                <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Nama</option>
            </select>
        </form>
    </div>

    @if($users->count() === 0)
    <div class="py-16 text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-navy-700 flex items-center justify-center">
            <i class="fas fa-database text-3xl text-slate-600"></i>
        </div>
        <p class="text-slate-400 text-sm">Tidak ada pengguna yang cocok dengan penyaringan ini.</p>
    </div>
    @else
    <div class="divide-y divide-navy-600/60">
        @foreach($users as $user)
        @php
            $persen = $user->getStoragePercentage();
            $lebih = $user->storage_used > $user->storage_quota;
        @endphp
        <div class="p-4 md:p-5 hover:bg-navy-700/40 transition">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                {{-- Identitas --}}
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-gold-500 to-gold-600 flex items-center justify-center text-navy-900 font-bold flex-shrink-0">
                        @if($avatarUrl = $user->avatarUrl())
                            <img src="{{ $avatarUrl }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        @endif
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-medium text-white text-sm truncate">{{ $user->name }}</p>
                            @if($user->isSuperAdmin())
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gold-500/20 text-gold-300">SUPERADMIN</span>
                            @elseif($user->isAdmin())
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-300">ADMIN</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 truncate">
                            {{ $user->email }}
                            @if($user->company) <span class="text-slate-600">&middot;</span> {{ $user->company->name }} @endif
                        </p>
                    </div>
                </div>

                {{-- Pemakaian --}}
                <div class="md:w-64 flex-shrink-0">
                    <div class="flex items-center justify-between text-xs mb-1.5">
                        <span class="text-slate-400">
                            {{ \App\Models\User::formatStorageSize($user->storage_used) }}
                            / {{ \App\Models\User::formatStorageSize($user->storage_quota) }}
                        </span>
                        <span class="font-semibold {{ $persen >= 90 ? 'text-red-400' : ($persen >= 75 ? 'text-amber-400' : 'text-slate-300') }}">
                            {{ number_format($persen, 0) }}%
                        </span>
                    </div>
                    <div class="w-full bg-navy-900 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full {{ $persen >= 90 ? 'bg-red-500' : ($persen >= 75 ? 'bg-amber-500' : 'progress-bar') }}"
                             style="width: {{ max($persen, 1.5) }}%"></div>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">
                        {{ $user->files_count }} file
                        @if($lebih)
                        <span class="text-red-400">&middot; melebihi kuota</span>
                        @endif
                    </p>
                </div>

                {{-- Ubah kuota --}}
                <form action="{{ route('admin.quotas.update', $user) }}" method="POST"
                      class="flex items-center gap-2 flex-shrink-0">
                    @csrf
                    @method('PUT')
                    <div class="relative">
                        <input type="number" name="quota_gb" step="0.1" min="0.01" max="10240" required
                            value="{{ round($user->storage_quota / 1073741824, 2) }}"
                            class="field !py-2 !pr-10 text-sm w-28">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-500 pointer-events-none">GB</span>
                    </div>
                    <button type="submit" title="Simpan kuota"
                        class="w-9 h-9 rounded-lg bg-navy-600 hover:bg-navy-500 text-gold-500 flex items-center justify-center transition flex-shrink-0">
                        <i class="fas fa-check text-sm"></i>
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <div class="p-4 border-t border-navy-600">
        {{ $users->links() }}
    </div>
    @endif
</div>
@endsection

@section('modals')
{{-- Atur massal --}}
<div id="bulkModal" class="hidden fixed inset-0 z-50 flex items-end md:items-center justify-center modal-overlay p-0 md:p-4">
    <div class="panel !rounded-b-none md:!rounded-2xl w-full max-w-md">
        <div class="p-5 md:p-6 border-b border-navy-600 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">
                <i class="fas fa-layer-group text-gold-500 mr-2"></i>Atur Kuota Massal
            </h3>
            <button onclick="closeBulk()" aria-label="Tutup" class="w-9 h-9 rounded-lg hover:bg-navy-700 flex items-center justify-center">
                <i class="fas fa-times text-slate-400"></i>
            </button>
        </div>

        <form action="{{ route('admin.quotas.bulk') }}" method="POST" class="p-5 md:p-6"
              onsubmit="return confirm('Terapkan kuota ini ke semua pengguna pada lingkup terpilih?')">
            @csrf

            <label class="label" for="bulkTarget">Berlaku untuk</label>
            <select name="target" id="bulkTarget" class="field mb-4" onchange="toggleBulkCompany()">
                <option value="company">Satu perusahaan</option>
                <option value="all">Seluruh perusahaan</option>
            </select>

            <div id="bulkCompanyWrap" class="mb-4">
                <label class="label" for="bulkCompany">Perusahaan</label>
                <select name="company_id" id="bulkCompany" class="field">
                    @foreach($companies as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <label class="label" for="bulkQuota">Kuota per Akun (GB)</label>
            <input type="number" name="quota_gb" id="bulkQuota" step="0.1" min="0.01" max="10240" required
                value="1" class="field mb-4" placeholder="Contoh: 5">

            <div class="p-4 bg-navy-700/60 border border-navy-600 rounded-xl flex gap-3 mb-5">
                <i class="fas fa-circle-info text-gold-500 mt-0.5"></i>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Nilai ini menimpa kuota setiap akun pada lingkup terpilih, termasuk akun admin.
                    Pengguna menerima notifikasi saat kuotanya berubah.
                </p>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeBulk()" class="flex-1 btn-ghost px-4 py-3 rounded-xl">Batal</button>
                <button type="submit" class="flex-1 btn-primary px-4 py-3 rounded-xl">
                    <i class="fas fa-save mr-2"></i>Terapkan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openBulk() {
        document.getElementById('bulkModal').classList.remove('hidden');
    }

    function closeBulk() {
        document.getElementById('bulkModal').classList.add('hidden');
    }

    function toggleBulkCompany() {
        const perluPerusahaan = document.getElementById('bulkTarget').value === 'company';
        document.getElementById('bulkCompanyWrap').classList.toggle('hidden', !perluPerusahaan);
        document.getElementById('bulkCompany').disabled = !perluPerusahaan;
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeBulk();
    });
</script>
@endpush
