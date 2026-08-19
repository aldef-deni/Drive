@extends('layouts.app')

@section('title', 'Notifikasi - Dekorasi Drive')
@section('page-title', 'Notifikasi')

@section('content')
<div class="max-w-3xl mx-auto">
    @if($notifications->count() > 0)
    <div class="flex justify-between items-center mb-6">
        <p class="text-sm text-gray-500">{{ $notifications->total() }} notifikasi</p>
        <form action="{{ route('notifications.read-all') }}" method="POST">
            @csrf
            <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                <i class="fas fa-check-double mr-1"></i>Tandai semua dibaca
            </button>
        </form>
    </div>

    <div class="space-y-3">
        @foreach($notifications as $notif)
        <a href="{{ $notif->url ? route('notifications.read', $notif) : '#' }}"
           class="block bg-white rounded-xl border p-4 transition hover:shadow-md {{ $notif->is_read ? 'border-gray-100' : 'border-indigo-200 bg-indigo-50/30' }}">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                    @if($notif->color === 'amber') bg-amber-100 text-amber-600
                    @elseif($notif->color === 'green') bg-green-100 text-green-600
                    @elseif($notif->color === 'blue') bg-blue-100 text-blue-600
                    @elseif($notif->color === 'red') bg-red-100 text-red-600
                    @elseif($notif->color === 'purple') bg-purple-100 text-purple-600
                    @else bg-gray-100 text-gray-600 @endif">
                    <i class="{{ $notif->icon ?? 'fas fa-bell' }}"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-gray-800 text-sm">{{ $notif->title }}</p>
                        @if(!$notif->is_read)
                            <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-600 mt-0.5">{{ $notif->message }}</p>
                    <p class="text-xs text-gray-400 mt-1.5">
                        <i class="far fa-clock mr-1"></i>{{ $notif->created_at->format('d M Y, H:i') }}
                    </p>
                </div>
                @if($notif->url)
                <i class="fas fa-chevron-right text-gray-300 mt-2"></i>
                @endif
            </div>
        </a>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
    @else
    <div class="text-center py-16">
        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gray-100 flex items-center justify-center">
            <i class="fas fa-bell-slash text-3xl text-gray-300"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-1">Belum Ada Notifikasi</h3>
        <p class="text-gray-500 text-sm">Notifikasi akan muncul di sini</p>
    </div>
    @endif
</div>
@endsection
