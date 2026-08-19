<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    protected $encryptionService;

    public function __construct(FileEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Store uploaded file.
     *
     * @param  bool  $isLocked  When true the file is encrypted AND lock-protected.
     * @param  string|null $password  Lock password (plain text).
     */
    public function storeFile(UploadedFile $file, User $user, string $folder = '/', bool $isLocked = false, ?string $password = null): File
    {
        // Check storage quota
        if ($user->storage_used + $file->getSize() > $user->storage_quota) {
            throw new \RuntimeException('Storage quota exceeded');
        }

        // Capture metadata BEFORE move() — after move the UploadedFile still
        // references the deleted temp path on Windows, causing getMimeType() to
        // throw "file does not exist or is not readable".
        $originalName = $file->getClientOriginalName();
        $mimeType     = $file->getMimeType();
        $size         = $file->getSize();

        // Generate unique filename
        $filename = $user->id . '_' . time() . '_' . $originalName;
        $relativePath = $user->id . '/' . ltrim($folder, '/') . '/' . $filename;
        $fullPath = storage_path('app/drive/' . $relativePath);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Store file to storage/app/drive/
        $file->move($directory, basename($relativePath));

        // Lock = encrypt + lock_password  (plain upload = no encryption)
        $finalPath   = $relativePath;
        $isEncrypted = false;
        $encPassword = null;
        $lockHash    = null;

        if ($isLocked && $password) {
            $encryptedPath = $this->encryptionService->encryptAndStore($fullPath, $password);
            $finalPath     = str_replace(storage_path('app/drive/'), '', $encryptedPath);
            $isEncrypted   = true;
            $encPassword   = $password;       // kept so download can decrypt
            $lockHash      = \Hash::make($password); // hashed — prevents deletion
        }

        // Create database record
        $fileRecord = File::create([
            'user_id'            => $user->id,
            'name'               => basename($finalPath),
            'original_name'      => $originalName,
            'mime_type'          => $mimeType,
            'size'               => $size,
            'path'               => $finalPath,
            'folder'             => $folder,
            'is_encrypted'       => $isEncrypted,
            'encryption_password'=> $encPassword,
            'lock_password'      => $lockHash,
        ]);

        // Update user storage
        $user->increment('storage_used', $size);

        return $fileRecord;
    }

    /**
     * Delete file.
     */
    public function deleteFile(File $file): bool
    {
        $path = $file->path;
        
        // Delete from storage
        $fullPath = storage_path('app/drive/' . $path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Also delete encrypted version if exists
        $encryptedPath = $fullPath . '.encrypted';
        if (file_exists($encryptedPath)) {
            unlink($encryptedPath);
        }

        // Update user storage
        $file->user->decrement('storage_used', $file->size);

        // Delete database record
        return $file->delete();
    }

    /**
     * Get file download path.
     */
    public function getDownloadPath(File $file): ?string
    {
        $fullPath = storage_path('app/drive/' . $file->path);
        if (file_exists($fullPath)) {
            return $fullPath;
        }
        return null;
    }

    /**
     * Get folder contents.
     */
    public function getFolderContents(User $user, string $folder = '/', bool $showHidden = false): array
    {
        $query = File::where('user_id', $user->id)->where('folder', $folder);
        if (!$showHidden) {
            $query->where('is_hidden', false);
        }
        $files = $query->orderBy('created_at', 'desc')->get();

        $folderQuery = \App\Models\FileFolder::where('user_id', $user->id)->where('parent_path', $folder);
        if (!$showHidden) {
            $folderQuery->where('is_hidden', false);
        }
        $folders = $folderQuery->orderBy('name')->get();

        return [
            'files' => $files,
            'folders' => $folders,
        ];
    }

    /**
     * Create folder.
     */
    public function createFolder(User $user, string $name, string $parentPath = '/'): \App\Models\FileFolder
    {
        $path = $parentPath === '/' ? '/' . $name : $parentPath . '/' . $name;
        
        return \App\Models\FileFolder::create([
            'user_id' => $user->id,
            'name' => $name,
            'path' => $path,
            'parent_path' => $parentPath,
        ]);
    }

    /**
     * Format bytes to human readable.
     */
    public function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Recalculate a user's storage_used from actual files in the database.
     */
    public function recalculateStorage(User $user): int
    {
        $totalSize = File::where('user_id', $user->id)->sum('size');
        $user->update(['storage_used' => $totalSize]);
        return $totalSize;
    }

    /**
     * Recalculate storage for all users.
     */
    public function recalculateAllStorage(): void
    {
        User::all()->each(function ($user) {
            $this->recalculateStorage($user);
        });
    }
}
