<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'icon', 'color', 'url', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Peringatan kuota muncul saat sisanya di bawah angka ini. */
    public const QUOTA_WARNING_THRESHOLD = 52428800; // 50 MB

    /**
     * Peringatkan pengguna bila sisa kuotanya menipis.
     *
     * Ambangnya pernah 1 GB, sama dengan kuota bawaan akun baru - akibatnya
     * peringatan muncul sejak unggahan pertama, saat kuota masih 96% kosong,
     * dan kehilangan artinya sebagai peringatan.
     */
    public static function createAndCheckQuota(User $user): void
    {
        $remaining = $user->storage_quota - $user->storage_used;

        if ($remaining >= self::quotaWarningThreshold($user)) {
            return;
        }

        // Sekali sehari sudah cukup; mengulanginya tiap unggahan hanya
        // menenggelamkan notifikasi lain.
        $alreadyNotified = self::where('user_id', $user->id)
            ->where('type', 'quota_low')
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $habis = $remaining <= 0;

        self::create([
            'user_id' => $user->id,
            'type'    => 'quota_low',
            'title'   => $habis ? 'Kuota Penyimpanan Habis' : 'Kuota Hampir Habis',
            'message' => $habis
                ? 'Kuota drive Anda sudah penuh. Hapus file lama atau hubungi admin untuk menambah kuota.'
                : 'Sisa kuota drive Anda tinggal ' . self::formatBytes($remaining)
                    . '. Hapus file lama atau hubungi admin untuk menambah kuota.',
            'icon'    => 'fas fa-exclamation-triangle',
            'color'   => $habis ? 'red' : 'amber',
            'url'     => null,
        ]);
    }

    /**
     * Batas sisa kuota yang memicu peringatan.
     *
     * Untuk kuota yang lebih kecil dari 50 MB, ambang tetap 50 MB akan selalu
     * terlampaui - bahkan saat drive-nya masih kosong. Karena itu ambangnya
     * ikut mengecil menjadi sepersepuluh kuota.
     */
    private static function quotaWarningThreshold(User $user): int
    {
        return (int) min(self::QUOTA_WARNING_THRESHOLD, floor($user->storage_quota * 0.1));
    }

    private static function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
