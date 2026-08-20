<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /**
     * Kuota penyimpanan bawaan untuk setiap akun baru: 1 GB.
     * Ditaruh di satu tempat supaya web dan API tidak pernah berbeda lagi.
     */
    public const DEFAULT_STORAGE_QUOTA = 1073741824;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'role', 'storage_quota', 'storage_used', 'is_active', 'avatar', 'api_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'storage_quota' => 'integer',
            'storage_used' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the files for the user.
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Get the file shares for the user.
     */
    public function fileShares(): HasMany
    {
        return $this->hasMany(FileShare::class);
    }

    /**
     * Get the folders for the user.
     */
    public function folders(): HasMany
    {
        return $this->hasMany(FileFolder::class);
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get unread notification count.
     */
    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }

    /**
     * Lokasi fisik file avatar, atau null bila tidak ada.
     *
     * Nilai kolom `avatar` pernah disimpan dalam dua bentuk: nama file polos
     * (dari web) dan "avatars/nama.png" (dari API). basename() menyeragamkan
     * keduanya sehingga data lama tetap terbaca.
     */
    public function avatarPath(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        $filename = basename(str_replace(chr(92), '/', $this->avatar));
        $path = storage_path('app/public/avatars/' . $filename);

        return is_file($path) ? $path : null;
    }

    /**
     * URL avatar yang disajikan lewat route sendiri.
     *
     * Sengaja tidak memakai asset('storage/...') karena itu bergantung pada
     * symlink public/storage yang kerap tidak aktif di hosting cPanel.
     * Mengembalikan null bila file tidak ada, sehingga tampilan jatuh ke inisial
     * nama alih-alih gambar rusak.
     */
    public function avatarUrl(): ?string
    {
        if (!$this->avatarPath()) {
            return null;
        }

        return route('avatar.show', [
            'user' => $this->id,
            'v' => $this->updated_at?->timestamp ?? 0,
        ]);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get storage usage percentage.
     */
    public function getStoragePercentage(): float
    {
        if ($this->storage_quota <= 0) {
            return 0;
        }
        return min(100, ($this->storage_used / $this->storage_quota) * 100);
    }

    /**
     * Format storage size (static).
     */
    public static function formatStorageSize($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format storage size (instance).
     */
    public function formatStorage($bytes): string
    {
        return self::formatStorageSize($bytes);
    }
}
