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
        // Berkas sempat gagal diterima PHP (ukuran melebihi batas, tmp dir hilang,
        // disk penuh). Tanpa pemeriksaan ini kegagalan muncul sebagai error samar
        // di langkah berikutnya.
        if (!$file->isValid()) {
            throw new \RuntimeException($this->uploadErrorMessage($file->getError()));
        }

        // Check storage quota
        if ($user->storage_used + $file->getSize() > $user->storage_quota) {
            $sisa = $this->formatBytes(max(0, $user->storage_quota - $user->storage_used));

            throw new \RuntimeException(
                'Kuota penyimpanan tidak cukup. Sisa kuota Anda ' . $sisa .
                ', sedangkan file ini berukuran ' . $this->formatBytes($file->getSize()) . '.'
            );
        }

        // Capture metadata BEFORE move() — after move the UploadedFile still
        // references the deleted temp path on Windows, causing getMimeType() to
        // throw "file does not exist or is not readable".
        $originalName = $file->getClientOriginalName();
        $mimeType     = $file->getMimeType();
        $size         = $file->getSize();

        // Generate unique filename (nama asli dibersihkan agar aman dipakai sebagai path)
        $filename = $user->id . '_' . time() . '_' . $this->sanitizeFilename($originalName);
        $relativePath = $user->id . '/' . ltrim($folder, '/') . '/' . $filename;
        $fullPath = storage_path('app/drive/' . $relativePath);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(
                'Server tidak bisa membuat folder penyimpanan. Pastikan folder ' .
                'storage/app/drive dapat ditulis (izin 755 dan pemiliknya benar).'
            );
        }

        if (!is_writable($directory)) {
            throw new \RuntimeException(
                'Folder penyimpanan di server tidak dapat ditulis. Perbaiki izin folder storage/app/drive.'
            );
        }

        // Store file to storage/app/drive/
        try {
            $file->move($directory, basename($relativePath));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gagal menyimpan file di server: ' . $e->getMessage());
        }

        // Lock = encrypt + lock_password  (plain upload = no encryption)
        $finalPath   = $relativePath;
        $isEncrypted = false;
        $encPassword = null;
        $lockHash    = null;

        if ($isLocked && $password) {
            $encryptedPath = $this->encryptionService->encryptAndStore($fullPath, $password);
            $finalPath     = str_replace(storage_path('app/drive/'), '', $encryptedPath);
            $isEncrypted   = true;
            $encPassword   = null;            // password tidak pernah disimpan polos
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
     * Terjemahkan kode error unggahan PHP menjadi kalimat yang bisa ditindaklanjuti.
     *
     * @see https://www.php.net/manual/en/features.file-upload.errors.php
     */
    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'Ukuran file melebihi batas yang diizinkan server (upload_max_filesize / post_max_size di PHP).',
            UPLOAD_ERR_PARTIAL =>
                'Pengiriman file terputus di tengah jalan. Coba ulangi dengan koneksi yang stabil.',
            UPLOAD_ERR_NO_FILE =>
                'Tidak ada file yang terkirim.',
            UPLOAD_ERR_NO_TMP_DIR =>
                'Folder sementara PHP tidak ditemukan di server. Periksa pengaturan upload_tmp_dir pada hosting.',
            UPLOAD_ERR_CANT_WRITE =>
                'Server gagal menulis file ke disk. Kemungkinan kuota hosting penuh atau izin folder salah.',
            UPLOAD_ERR_EXTENSION =>
                'Sebuah ekstensi PHP menolak unggahan ini.',
            default =>
                'File gagal diterima server (kode ' . $code . ').',
        };
    }

    /**
     * Bersihkan nama file agar aman dipakai sebagai nama fisik di disk.
     */
    public function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace(chr(92), '/', $name));
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        $base = preg_replace('/[^A-Za-z0-9._-]+/u', '-', $base) ?: 'file';
        $base = trim($base, '-.') ?: 'file';
        $base = mb_substr($base, 0, 80);

        $extension = preg_replace('/[^A-Za-z0-9]+/', '', (string) $extension);

        return $extension !== '' ? $base . '.' . $extension : $base;
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
