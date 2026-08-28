@extends('layouts.app')

@section('title', 'Notifikasi - Aldef Tech Drive')
@section('page-title', 'Notifikasi')

@section('content')
<div class="max-w-3xl mx-auto">
    @if($notifications->count() > 0)
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
        <p class="text-sm text-slate-400">{{ $notifications->total() }} notifikasi</p>

        <div class="flex items-center gap-2">
            <form action="{{ route('notifications.read-all') }}" method="POST">
                @csrf
                <button type="submit" class="text-sm text-gold-500 hover:text-gold-400 font-medium px-3 py-2">
                    <i class="fas fa-check-double mr-1"></i>Tandai semua dibaca
                </button>
            </form>

            {{-- Menghapus seluruh notifikasi tidak bisa dibatalkan, jadi selalu
                 lewat konfirmasi lebih dulu. --}}
            <button type="button" onclick="bukaHapusSemua()" title="Hapus semua notifikasi"
                class="w-9 h-9 rounded-lg bg-red-500/15 hover:bg-red-500/25 text-red-400 flex items-center justify-center transition">
                <i class="fas fa-trash text-sm"></i>
            </button>
        </div>
    </div>

    <div class="space-y-2 md:space-y-3">
        @foreach($notifications as $notif)
        <a href="{{ $notif->url ? route('notifications.read', $notif) : '#' }}"
           class="block bg-[#0f1f3d] rounded-xl border border-navy-600 p-3 md:p-4 transition hover:shadow-md {{ $notif->is_read ? '' : 'border-[#d4a843]/40 bg-navy-700/50' }}">
            <div class="flex items-start gap-3 md:gap-4">
                <div class="w-9 h-9 md:w-10 md:h-10 rounded-full flex items-center justify-center flex-shrink-0
                    @if($notif->color === 'amber') bg-amber-500/20 text-amber-400
                    @elseif($notif->color === 'green') bg-green-500/20 text-green-400
                    @elseif($notif->color === 'blue') bg-blue-500/20 text-blue-400
                    @elseif($notif->color === 'red') bg-red-500/20 text-red-400
                    @elseif($notif->color === 'purple') bg-purple-500/20 text-purple-400
                    @else bg-slate-500/20 text-slate-400 @endif">
                    <i class="{{ $notif->icon ?? 'fas fa-bell' }} text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-white text-sm truncate">{{ $notif->title }}</p>
                        @if(!$notif->is_read)
                            <span class="w-2 h-2 rounded-full bg-[#d4a843] flex-shrink-0"></span>
                        @endif
                    </div>
                    <p class="text-xs md:text-sm text-slate-300 mt-0.5 line-clamp-2">{{ $notif->message }}</p>
                    <p class="text-[10px] md:text-xs text-slate-400 mt-1.5">
                        <i class="far fa-clock mr-1"></i>{{ $notif->created_at->format('d M Y, H:i') }}
                    </p>
                </div>
                @if($notif->url)
                <i class="fas fa-chevron-right text-slate-500 mt-2 text-sm hidden md:block"></i>
                @endif
            </div>
        </a>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
    @else
    <div class="text-center py-12 md:py-16">
        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-navy-700 flex items-center justify-center">
            <i class="fas fa-bell-slash text-3xl text-slate-500"></i>
        </div>
        <h3 class="text-lg font-semibold text-white mb-1">Belum Ada Notifikasi</h3>
        <p class="text-slate-400 text-sm">Notifikasi akan muncul di sini</p>
    </div>
    @endif
</div>
{{-- Konfirmasi hapus semua --}}
<div id="modalHapusSemua" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-navy-900/80 backdrop-blur-sm">
    <div class="panel w-full max-w-md p-6 text-center">
        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-500/15 flex items-center justify-center">
            <i class="fas fa-trash text-xl text-red-400"></i>
        </div>

        <h3 class="text-lg font-semibold text-white">Hapus Semua Notifikasi</h3>
        <p class="text-sm text-slate-400 mt-2 leading-relaxed">
            Apakah Anda yakin untuk menghapus semua notifikasi?
            Notifikasi yang dihapus tidak bisa dikembalikan.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 mt-6">
            <button type="button" onclick="tutupHapusSemua()"
                class="btn-ghost flex-1 px-6 py-3 rounded-xl">Batal</button>

            <form action="{{ route('notifications.destroy-all') }}" method="POST" class="flex-1">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="w-full px-6 py-3 rounded-xl bg-red-500 hover:bg-red-600 text-white font-semibold transition">
                    <i class="fas fa-trash mr-2"></i>Iya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const modalHapus = document.getElementById('modalHapusSemua');

    function bukaHapusSemua() {
        modalHapus.classList.remove('hidden');
        modalHapus.classList.add('flex');
    }

    function tutupHapusSemua() {
        modalHapus.classList.add('hidden');
        modalHapus.classList.remove('flex');
    }

    // Klik di luar kotak dan tombol Esc sama-sama menutup - dialog yang hanya
    // bisa ditutup lewat satu tombol terasa seperti jebakan.
    modalHapus.addEventListener('click', e => {
        if (e.target === modalHapus) tutupHapusSemua();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') tutupHapusSemua();
    });
</script>
@endpush
