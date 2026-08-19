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

    /**
     * Create a notification and auto-check quota for the user.
     */
    public static function createAndCheckQuota(User $user): void
    {
        // Check if quota is below 1 GB
        $remaining = $user->storage_quota - $user->storage_used;
        if ($remaining < 1073741824) { // 1 GB
            $alreadyNotified = self::where('user_id', $user->id)
                ->where('type', 'quota_low')
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if (!$alreadyNotified) {
                self::create([
                    'user_id' => $user->id,
                    'type'    => 'quota_low',
                    'title'   => 'Kuota Hampir Habis',
                    'message' => 'Sisa kuota drive Anda tinggal ' . self::formatBytes($remaining) . '. Harap upgrade kuota.',
                    'icon'    => 'fas fa-exclamation-triangle',
                    'color'   => 'amber',
                    'url'     => null,
                ]);
            }
        }
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
