<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */    protected $fillable = [
        'name', 'email', 'password', 'role', 'storage_quota', 'storage_used', 'is_active', 'avatar',
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
