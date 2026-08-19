<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FileShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'user_id',
        'share_token',
        'password',
        'expires_at',
        'download_limit',
        'download_count',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'download_limit' => 'integer',
        'download_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the file that is shared.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get the user that shared the file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if share is valid.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->download_limit && $this->download_count >= $this->download_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if password is required.
     */
    public function hasPassword(): bool
    {
        return !empty($this->password);
    }

    /**
     * Verify password.
     */
    public function verifyPassword(string $password): bool
    {
        return \Hash::check($password, $this->password);
    }

    /**
     * Generate share token.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Increment download count.
     */
    public function incrementDownload(): void
    {
        $this->increment('download_count');
    }
}
