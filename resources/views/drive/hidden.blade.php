@extends('layouts.app')

@section('title', 'Hidden System - Dekorasi Drive')
@section('page-title', 'Hidden System')

@section('content')
@if(!session('hidden_verified'))
{{-- Gerbang password --}}
<div class="max-w-md mx-auto mt-6 md:mt-12">
    <div class="panel overflow-hidden">
        <div class="bg-gradient-to-br from-navy-700 to-navy-900 p-8 text-center border-b border-navy-600">
            <div class="w-16 h-16 mx-auto mb-4 bg-gold-500/15 rounded-full flex items-center justify-center ring-1 ring-gold-500/30">
                <i class="fas fa-user-secret text-gold-500 text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold text-white">Hidden System</h2>
            <p class="text-slate-400 text-sm mt-1.5">Masukkan password untuk mengakses file tersembunyi</p>
        </div>
        <div class="p-6">
            <form action="{{ route('drive.hidden.verify') }}" method="POST">
                @csrf
                <label class="label" for="hiddenPassword">
                    <i class="fas fa-lock text-gold-500 mr-1"></i> Password akses
                </label>
                <input type="password" id="hiddenPassword" name="password" required autofocus
                    class="field mb-4" placeholder="Password akun atau password kunci file">

                @if($errors->has('password'))
                <div class="mb-4 p-3 bg-red-500/10 border border-red-500/40 rounded-xl text-red-300 text-sm">
                    <i class="fas fa-circle-exclamation mr-1"></i> {{ $errors->first('password') }}
                </div>
                @endif

                <button type="submit" class="btn-primary w-full py-3 rounded-xl">
                    <i class="fas fa-unlock mr-2"></i> Buka Hidden System
                </button>
            </form>

            <p class="mt-4 text-center text-xs text-slate-500">
                Gunakan password akun Anda atau password kunci salah satu file/folder.
            </p>
        </div>
    </div>
</div>

@else
{{-- Konten setelah terverifikasi --}}
<div class="mb-6 p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl flex items-start gap-3">
    <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center flex-shrink-0">
        <i class="fas fa-user-secret text-amber-400"></i>
    </div>
    <div class="flex-1 min-w-0">
        <p class="font-medium text-amber-300">Mode tersembunyi aktif</p>
        <p class="text-sm text-amber-200/80">File dan folder tersembunyi hanya terlihat di halaman ini dan akan tetap terbuka sampai Anda menguncinya kembali.</p>
    </div>
    <form action="{{ route('drive.hidden.lock') }}" method="POST" class="flex-shrink-0">
        @csrf
        <button type="submit" class="btn-ghost px-4 py-2 rounded-xl text-xs whitespace-nowrap">
            <i class="fas fa-lock mr-1.5"></i>Kunci Kembali
        </button>
    </form>
</div>

{{-- Folder tersembunyi --}}
@if($folders->count() > 0)
<section class="mb-8">
    <h2 class="text-xs font-semibold text-gold-500 uppercase tracking-wider mb-3">
        <i class="fas fa-folder mr-2"></i>Folder Tersembunyi <span class="text-slate-500 font-normal">({{ $folders->count() }})</span>
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($folders as $folder)
        <div class="panel p-4 hover-lift">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 rounded-xl bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-folder text-amber-400 text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-white truncate">{{ $folder->name }}</p>
                    <p class="text-xs text-slate-500">{{ $folder->created_at->format('d M Y') }}</p>
                </div>
            </div>
            <form action="{{ route('drive.folder.toggle-visibility', $folder) }}" method="POST">
                @csrf
                <button type="submit" class="w-full py-2 bg-green-500/15 text-green-400 text-xs font-medium rounded-lg hover:bg-green-500/25 transition">
                    <i class="fas fa-eye mr-1"></i>Tampilkan di Drive
                </button>
            </form>
        </div>
        @endforeach
    </div>
</section>
@endif

{{-- File tersembunyi --}}
@if($files->count() > 0)
<section>
    <h2 class="text-xs font-semibold text-gold-500 uppercase tracking-wider mb-3">
        <i class="fas fa-file mr-2"></i>File Tersembunyi <span class="text-slate-500 font-normal">({{ $files->count() }})</span>
    </h2>
    <div class="panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead>
                    <tr class="border-b border-navy-600">
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-400 uppercase tracking-wider">Nama</th>
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-400 uppercase tracking-wider">Ukuran</th>
                        <th class="text-left px-5 py-3.5 text-xs font-semibold text-slate-400 uppercase tracking-wider">Diubah</th>
                        <th class="text-right px-5 py-3.5 text-xs font-semibold text-slate-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($files as $file)
                    <tr class="border-b border-navy-600/50 last:border-0 hover:bg-navy-700/60 transition">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-navy-700 flex items-center justify-center flex-shrink-0">
                                    <i class="fas {{ $file->getIconClass() }}"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-white truncate">{{ $file->original_name }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $file->mime_type }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-slate-300 whitespace-nowrap">{{ $file->formatSize() }}</td>
                        <td class="px-5 py-3.5 text-sm text-slate-300 whitespace-nowrap">{{ $file->updated_at->format('d M Y H:i') }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <form action="{{ route('drive.toggle-visibility', $file) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-green-500/15 text-green-400 text-xs font-medium rounded-lg hover:bg-green-500/25 transition whitespace-nowrap">
                                        <i class="fas fa-eye mr-1"></i>Tampilkan
                                    </button>
                                </form>
                                @if(!$file->lock_password)
                                <form action="{{ route('drive.destroy', $file) }}" method="POST"
                                      onsubmit="return confirm('Hapus file &quot;{{ $file->original_name }}&quot; secara permanen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 bg-red-500/15 text-red-400 text-xs font-medium rounded-lg hover:bg-red-500/25 transition whitespace-nowrap">
                                        <i class="fas fa-trash mr-1"></i>Hapus
                                    </button>
                                </form>
                                @else
                                <span class="px-3 py-1.5 bg-navy-700 text-slate-500 text-xs rounded-lg whitespace-nowrap">
                                    <i class="fas fa-lock mr-1"></i>Terkunci
                                </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endif

{{-- Kondisi kosong --}}
@if($files->count() === 0 && $folders->count() === 0)
<div class="text-center py-16">
    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-navy-700 flex items-center justify-center">
        <i class="fas fa-user-secret text-4xl text-slate-600"></i>
    </div>
    <h3 class="text-xl font-semibold text-white mb-2">Tidak ada file tersembunyi</h3>
    <p class="text-slate-400 mb-6 text-sm">File yang Anda sembunyikan akan muncul di sini.</p>
    <a href="{{ route('drive.index') }}" class="btn-primary inline-flex items-center px-6 py-3 rounded-xl">
        <i class="fas fa-hard-drive mr-2"></i> Kembali ke Drive
    </a>
</div>
@endif
@endif
@endsection
