<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'original_name',
        'mime_type',
        'size',
        'path',
        'folder',
        'is_hidden',
        'is_encrypted',
        'encryption_password',
        'lock_password',
        'share_id',
        'description',
    ];

    protected $hidden = [
        'encryption_password',
        'lock_password',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_hidden' => 'boolean',
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get the user that owns the file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the shares for the file.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(FileShare::class);
    }

    /**
     * Check if file has any active shares.
     */
    public function isShared(): bool
    {
        return $this->shares()->where('is_active', true)->exists();
    }

    /**
     * Format file size.
     */
    public function formatSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($this->size, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get file extension icon class.
     */
    public function getIconClass(): string
    {
        $ext = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
        
        $icons = [
            'pdf' => 'fa-file-pdf text-red-500',
            'doc' => 'fa-file-word text-blue-500',
            'docx' => 'fa-file-word text-blue-500',
            'xls' => 'fa-file-excel text-green-500',
            'xlsx' => 'fa-file-excel text-green-500',
            'ppt' => 'fa-file-powerpoint text-orange-500',
            'pptx' => 'fa-file-powerpoint text-orange-500',
            'jpg' => 'fa-file-image text-purple-500',
            'jpeg' => 'fa-file-image text-purple-500',
            'png' => 'fa-file-image text-purple-500',
            'gif' => 'fa-file-image text-purple-500',
            'svg' => 'fa-file-image text-purple-500',
            'mp4' => 'fa-file-video text-pink-500',
            'avi' => 'fa-file-video text-pink-500',
            'mov' => 'fa-file-video text-pink-500',
            'mp3' => 'fa-file-audio text-indigo-500',
            'wav' => 'fa-file-audio text-indigo-500',
            'zip' => 'fa-file-archive text-yellow-500',
            'rar' => 'fa-file-archive text-yellow-500',
            '7z' => 'fa-file-archive text-yellow-500',
            'txt' => 'fa-file text-gray-500',
            'php' => 'fa-file-code text-indigo-500',
            'js' => 'fa-file-code text-yellow-500',
            'css' => 'fa-file-code text-blue-500',
            'html' => 'fa-file-code text-orange-500',
        ];

        return $icons[$ext] ?? 'fa-file text-gray-400';
    }
}
