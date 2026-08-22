<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'path',
        'parent_path',
        'is_hidden',
        'is_starred',
        'lock_password',
    ];

    protected $hidden = [
        'lock_password',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'is_starred' => 'boolean',
    ];

    /**
     * Get the user that owns the folder.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if folder contains any locked files (recursive).
     */
    public function hasLockedFiles(): bool
    {
        return File::where('user_id', $this->user_id)
            ->where(function ($query) {
                $query->where('folder', $this->path)
                    ->orWhere('folder', 'like', rtrim($this->path, '/') . '/%');
            })
            ->whereNotNull('lock_password')
            ->exists();
    }
}
