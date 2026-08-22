<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * List all notifications for the current user.
     */
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Notification $notification)
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        if ($notification->url) {
            return redirect($notification->url);
        }

        return back();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back();
    }

    /**
     * Hapus seluruh notifikasi milik pengguna.
     *
     * Dibatasi pada user_id pemiliknya, bukan truncate: notifikasi milik orang
     * lain tidak boleh ikut hilang hanya karena satu orang membersihkan
     * kotaknya sendiri.
     */
    public function destroyAll()
    {
        $jumlah = Notification::where('user_id', Auth::id())->delete();

        return back()->with(
            'success',
            $jumlah > 0
                ? $jumlah . ' notifikasi berhasil dihapus'
                : 'Tidak ada notifikasi untuk dihapus'
        );
    }
}
