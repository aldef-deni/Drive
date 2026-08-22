<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Models\Setting;
use App\Services\StorageService;
use App\Services\FileEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ApiDriveController extends Controller
{
    protected $storageService;
    protected $encryptionService;

    public function __construct(StorageService $storageService, FileEncryptionService $encryptionService)
    {
        $this->storageService = $storageService;
        $this->encryptionService = $encryptionService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $this->storageService->recalculateStorage($user);

        // Dipaksa jadi string: klien bisa mengirim folder kosong atau berbentuk
        // array, dan nilai null membuat StorageService melempar TypeError -
        // seluruh permintaan gagal dengan 500, bukan sekadar salah folder.
        $folder = $request->get('folder');
        $folder = is_string($folder) && $folder !== '' ? $folder : '/';
        $search = trim((string) $request->get('search', ''));

        // Mode ungkap dibuka dengan mengetik kata kunci rahasia di pencarian.
        // Klien menyimpan kata kunci itu dan mengirimnya kembali lewat
        // `reveal_keyword` selama masih ingin melihat item tersembunyi.
        $showHidden = false;

        if ($search !== '' && Setting::matchesHiddenKeyword($search)) {
            $showHidden = true;
            $search = '';
        } elseif (Setting::matchesHiddenKeyword((string) $request->get('reveal_keyword', ''))) {
            $showHidden = true;
        }

        if ($search !== '') {
            $files = File::where('user_id', $user->id)
                ->where('original_name', 'like', '%' . $search . '%')
                ->when(!$showHidden, fn ($q) => $q->where('is_hidden', false))
                ->orderBy('created_at', 'desc')
                ->get();
            $folders = FileFolder::where('user_id', $user->id)
                ->where('name', 'like', '%' . $search . '%')
                ->when(!$showHidden, fn ($q) => $q->where('is_hidden', false))
                ->orderBy('name')
                ->get();
            $breadcrumbs = [['name' => 'Drive', 'path' => '/'], ['name' => 'Pencarian: ' . $search, 'path' => '/']];
        } else {
            $contents = $this->storageService->getFolderContents($user, $folder, $showHidden);
            $files = $contents['files'];
            $folders = $contents['folders'];
            $breadcrumbs = $this->getBreadcrumbs($folder);
        }

        return response()->json([
            'success' => true,
            'files' => $files->map(function ($f) {
                return [
                    'id' => $f->id,
                    'name' => $f->original_name,
                    'mime_type' => $f->mime_type,
                    'size' => $f->size,
                    'size_formatted' => $f->formatSize(),
                    'folder' => $f->folder,
                    'is_hidden' => $f->is_hidden,
                    'is_starred' => $f->is_starred,
                    'is_encrypted' => $f->is_encrypted,
                    'is_locked' => !empty($f->lock_password),
                    'is_shared' => $f->isShared(),
                    'share_id' => $f->share_id,
                    'created_at' => $f->created_at->toISOString(),
                    'updated_at' => $f->updated_at->toISOString(),
                ];
            }),
            'folders' => $folders->map(function ($f) {
                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'path' => $f->path,
                    'parent_path' => $f->parent_path,
                    'is_hidden' => $f->is_hidden,
                    'is_starred' => $f->is_starred,
                    'is_locked' => !empty($f->lock_password),
                    'has_locked_files' => $f->hasLockedFiles(),
                    'created_at' => $f->created_at->toISOString(),
                ];
            }),
            'breadcrumbs' => $breadcrumbs,
            'current_folder' => $folder,
            'hidden_revealed' => $showHidden,
            'user' => [
                'storage_quota' => $user->storage_quota,
                'storage_used' => $user->storage_used,
                'storage_percentage' => $user->getStoragePercentage(),
            ],
        ]);
    }

    public function upload(Request $request)
    {
        // Bila ukuran kiriman melampaui post_max_size, PHP mengosongkan seluruh
        // request sehingga validasi hanya berkata "file wajib diisi" — pesan yang
        // menyesatkan. Deteksi kondisi itu lebih dulu.
        if ($this->melebihiBatasKiriman($request)) {
            $batas = ini_get('post_max_size');

            return response()->json([
                'success' => false,
                'message' => "File terlalu besar untuk dikirim. Batas server saat ini {$batas}.",
            ], 413);
        }

        $request->validate([
            'file' => 'required|file|max:102400',
            'folder' => 'nullable|string',
            'is_locked' => 'nullable|boolean',
            'lock_password' => 'nullable|required_if:is_locked,1|string|min:4',
        ], [
            'file.required' => 'Tidak ada file yang dipilih.',
            'file.max' => 'Ukuran file melebihi 100 MB.',
            'lock_password.required_if' => 'Password kunci wajib diisi.',
            'lock_password.min' => 'Password kunci minimal 4 karakter.',
        ]);

        try {
            $user = Auth::user();
            $folder = $request->get('folder', '/');
            $isLocked = $request->get('is_locked', false);
            $password = $request->get('lock_password');

            $file = $this->storageService->storeFile(
                $request->file('file'),
                $user,
                $folder,
                $isLocked,
                $password
            );

            \App\Models\Notification::createAndCheckQuota($user);

            return response()->json([
                'success' => true,
                'message' => $isLocked ? 'File berhasil diupload & terkunci' : 'File berhasil diupload',
                'file' => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                    'size_formatted' => $file->formatSize(),
                    'is_locked' => $isLocked,
                    'is_hidden' => $file->is_hidden,
                    'is_starred' => $file->is_starred,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($file->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'File terkunci. Unlock terlebih dahulu.',
            ], 403);
        }

        $this->storageService->deleteFile($file);

        \App\Models\Notification::create([
            'user_id' => Auth::id(),
            'type' => 'file_deleted',
            'title' => 'File Dihapus',
            'message' => 'File "' . $file->original_name . '" berhasil dihapus.',
            'icon' => 'fas fa-trash',
            'color' => 'red',
            'url' => null,
        ]);

        \App\Models\Notification::createAndCheckQuota(Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dihapus',
        ]);
    }

    public function download(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $path = $this->storageService->getDownloadPath($file);
        if (!$path || !file_exists($path)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan'], 404);
        }

        if ($file->is_encrypted) {
            return response()->json([
                'requires_password' => true,
                'file_id' => $file->id,
                'file_name' => $file->original_name,
            ]);
        }

        return response()->download($path, $file->original_name);
    }

    public function downloadEncrypted(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['password' => 'required|string']);

        try {
            $path = $this->storageService->getDownloadPath($file);
            if (!$path || !file_exists($path)) {
                return response()->json(['success' => false, 'message' => 'File tidak ditemukan'], 404);
            }

            $content = $this->encryptionService->decryptFile($path, $request->password);

            return response($content)
                ->header('Content-Type', $file->mime_type)
                ->header('Content-Disposition', 'attachment; filename="' . $file->original_name . '"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah atau file corrupt',
            ], 400);
        }
    }

    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_path' => 'nullable|string',
        ]);

        $user = Auth::user();
        $parentPath = $request->get('parent_path', '/');

        $folder = $this->storageService->createFolder($user, $request->name, $parentPath);

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dibuat',
            'folder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'path' => $folder->path,
                'parent_path' => $folder->parent_path,
                'is_hidden' => $folder->is_hidden,
                'is_starred' => $folder->is_starred,
                'created_at' => $folder->created_at->toISOString(),
            ],
        ]);
    }

    public function destroyFolder(FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($folder->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'Folder terkunci. Unlock terlebih dahulu.',
            ], 403);
        }

        $hasLockedFiles = File::where('user_id', Auth::id())
            ->where('folder', 'like', $folder->path . '%')
            ->whereNotNull('lock_password')
            ->exists();

        if ($hasLockedFiles) {
            return response()->json([
                'success' => false,
                'message' => 'Folder berisi file terkunci. Unlock semua file terlebih dahulu.',
            ], 403);
        }

        $files = File::where('user_id', Auth::id())
            ->where('folder', 'like', $folder->path . '%')
            ->get();

        foreach ($files as $file) {
            $this->storageService->deleteFile($file);
        }

        FileFolder::where('user_id', Auth::id())
            ->where('parent_path', 'like', $folder->path . '%')
            ->delete();

        $folder->delete();

        \App\Models\Notification::create([
            'user_id' => Auth::id(),
            'type' => 'folder_deleted',
            'title' => 'Folder Dihapus',
            'message' => 'Folder "' . $folder->name . '" berhasil dihapus.',
            'icon' => 'fas fa-folder-minus',
            'color' => 'red',
            'url' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dihapus',
        ]);
    }

    public function lockFile(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($file->lock_password) {
            return response()->json(['success' => false, 'message' => 'File sudah terkunci'], 400);
        }

        $request->validate(['password' => 'required|string|min:4']);

        $fullPath = storage_path('app/drive/' . $file->path);
        if (!$file->is_encrypted && file_exists($fullPath)) {
            $this->encryptionService->encryptAndStore($fullPath, $request->password);
            $file->is_encrypted = true;
            $file->encryption_password = null; // password tidak pernah disimpan polos
            $file->path = $file->path . '.encrypted';
        }

        $file->lock_password = Hash::make($request->password);
        $file->save();

        return response()->json([
            'success' => true,
            'message' => 'File berhasil di-lock & terenkripsi',
        ]);
    }

    public function unlockFile(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['password' => 'required|string']);

        if (!$file->lock_password || !Hash::check($request->password, $file->lock_password)) {
            return response()->json(['success' => false, 'message' => 'Password salah'], 400);
        }

        if ($file->is_encrypted) {
            $fullPath = storage_path('app/drive/' . $file->path);
            if (file_exists($fullPath)) {
                $decrypted = $this->encryptionService->decryptFile($fullPath, $request->password);
                $decryptedPath = str_replace('.encrypted', '', $fullPath);
                file_put_contents($decryptedPath, $decrypted);
                unlink($fullPath);
                $file->path = str_replace('.encrypted', '', $file->path);
            }
            $file->is_encrypted = false;
            $file->encryption_password = null;
        }

        $file->lock_password = null;
        $file->save();

        return response()->json([
            'success' => true,
            'message' => 'File berhasil di-unlock & didekripsi',
        ]);
    }

    public function lockFolder(Request $request, FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($folder->lock_password) {
            return response()->json(['success' => false, 'message' => 'Folder sudah terkunci'], 400);
        }

        $request->validate(['password' => 'required|string|min:4']);

        $folder->lock_password = Hash::make($request->password);
        $folder->save();

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil di-lock',
        ]);
    }

    public function unlockFolder(Request $request, FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['password' => 'required|string']);

        if (!$folder->lock_password || !Hash::check($request->password, $folder->lock_password)) {
            return response()->json(['success' => false, 'message' => 'Password salah'], 400);
        }

        $folder->lock_password = null;
        $folder->save();

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil di-unlock',
        ]);
    }

    public function share(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($file->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'File terkunci. Unlock terlebih dahulu sebelum share.',
            ], 403);
        }

        $request->validate([
            'password' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
            'download_limit' => 'nullable|integer|min:1',
        ]);

        $share = FileShare::create([
            'file_id' => $file->id,
            'user_id' => Auth::id(),
            'share_token' => FileShare::generateToken(),
            'password' => $request->password ? Hash::make($request->password) : null,
            'expires_at' => $request->expires_at,
            'download_limit' => $request->download_limit,
        ]);

        return response()->json([
            'success' => true,
            'share_url' => url('/share/' . $share->share_token),
            'share' => [
                'id' => $share->id,
                'token' => $share->share_token,
                'password_required' => !empty($share->password),
                'expires_at' => $share->expires_at,
                'download_limit' => $share->download_limit,
                'download_count' => $share->download_count,
            ],
        ]);
    }

    public function unshare(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $shareIds = FileShare::where('file_id', $file->id)
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->pluck('id');

        if ($shareIds->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'File tidak sedang di-share'], 400);
        }

        $receivedFiles = File::whereIn('share_id', $shareIds)->get();
        foreach ($receivedFiles as $rf) {
            $this->storageService->deleteFile($rf);
        }

        FileShare::whereIn('id', $shareIds)->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Share dibatalkan & file dihapus dari semua penerima',
        ]);
    }

    /**
     * File dan folder berbintang, lintas folder.
     *
     * Item tersembunyi tetap disembunyikan kecuali kata kunci rahasia dikirim -
     * kalau tidak, bintang menjadi celah untuk memunculkannya tanpa kata kunci.
     */
    public function starred(Request $request)
    {
        $user = Auth::user();
        $showHidden = Setting::matchesHiddenKeyword((string) $request->get('reveal_keyword', ''));

        $files = File::where('user_id', $user->id)
            ->where('is_starred', true)
            ->when(!$showHidden, fn ($q) => $q->where('is_hidden', false))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'folder' => $f->folder,
                'size' => $f->size,
                'size_formatted' => $f->formatSize(),
                'mime_type' => $f->mime_type,
                'is_locked' => (bool) $f->lock_password,
                'is_hidden' => $f->is_hidden,
                'is_starred' => $f->is_starred,
                'updated_at' => $f->updated_at->toISOString(),
            ]);

        $folders = FileFolder::where('user_id', $user->id)
            ->where('is_starred', true)
            ->when(!$showHidden, fn ($q) => $q->where('is_hidden', false))
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'path' => $f->path,
                'is_locked' => (bool) $f->lock_password,
                'is_hidden' => $f->is_hidden,
                'is_starred' => $f->is_starred,
            ]);

        return response()->json([
            'success' => true,
            'files' => $files,
            'folders' => $folders,
        ]);
    }

    public function toggleStar(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $file->is_starred = !$file->is_starred;
        $file->save();

        return response()->json([
            'success' => true,
            'message' => $file->is_starred ? 'File ditandai berbintang' : 'File dilepas dari berbintang',
            'is_starred' => $file->is_starred,
        ]);
    }

    public function toggleFolderStar(FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $folder->is_starred = !$folder->is_starred;
        $folder->save();

        return response()->json([
            'success' => true,
            'message' => $folder->is_starred ? 'Folder ditandai berbintang' : 'Folder dilepas dari berbintang',
            'is_starred' => $folder->is_starred,
        ]);
    }

    public function toggleVisibility(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $file->is_hidden = !$file->is_hidden;
        $file->save();

        return response()->json([
            'success' => true,
            'message' => $file->is_hidden ? 'File disembunyikan' : 'File ditampilkan',
            'is_hidden' => $file->is_hidden,
            'is_starred' => $file->is_starred,
        ]);
    }

    public function toggleFolderVisibility(FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $folder->is_hidden = !$folder->is_hidden;
        $folder->save();

        return response()->json([
            'success' => true,
            'message' => $folder->is_hidden ? 'Folder disembunyikan' : 'Folder ditampilkan',
            'is_hidden' => $folder->is_hidden,
            'is_starred' => $folder->is_starred,
        ]);
    }

    public function info(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'file' => [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'size_formatted' => $file->formatSize(),
                'folder' => $file->folder,
                'is_hidden' => $file->is_hidden,
                'is_starred' => $file->is_starred,
                'is_encrypted' => $file->is_encrypted,
                'is_locked' => !empty($file->lock_password),
                'is_shared' => $file->isShared(),
                'created_at' => $file->created_at->toISOString(),
                'updated_at' => $file->updated_at->toISOString(),
            ],
            'shares' => $file->shares->where('is_active', true)->map(function ($s) {
                return [
                    'id' => $s->id,
                    'token' => $s->share_token,
                    'url' => url('/share/' . $s->share_token),
                    'password_required' => !empty($s->password),
                    'expires_at' => $s->expires_at,
                    'download_limit' => $s->download_limit,
                    'download_count' => $s->download_count,
                ];
            }),
        ]);
    }

    public function moveFile(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($file->lock_password) {
            return response()->json(['success' => false, 'message' => 'File terkunci. Unlock terlebih dahulu.'], 403);
        }

        $request->validate(['folder' => 'required|string']);

        $newFolder = $request->folder;
        if ($newFolder === $file->folder) {
            return response()->json(['success' => false, 'message' => 'File sudah berada di folder tersebut'], 400);
        }

        $oldFullPath = storage_path('app/drive/' . $file->path);
        $newRelativePath = $file->user_id . '/' . ltrim($newFolder, '/') . '/' . basename($file->path);
        $newFullPath = storage_path('app/drive/' . $newRelativePath);

        $newDir = dirname($newFullPath);
        if (!is_dir($newDir)) {
            mkdir($newDir, 0755, true);
        }

        if (file_exists($oldFullPath)) {
            rename($oldFullPath, $newFullPath);
        }

        $file->path = $newRelativePath;
        $file->folder = $newFolder;
        $file->save();

        return response()->json(['success' => true, 'message' => 'File berhasil dipindah']);
    }

    public function moveFolder(Request $request, FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['parent_path' => 'required|string']);

        $newParent = $request->parent_path;
        if ($newParent === $folder->parent_path) {
            return response()->json(['success' => false, 'message' => 'Folder sudah berada di lokasi tersebut'], 400);
        }

        $oldPath = $folder->path;
        $newPath = $newParent === '/' ? '/' . $folder->name : $newParent . '/' . $folder->name;

        $folder->parent_path = $newParent;
        $folder->path = $newPath;
        $folder->save();

        $subfolders = FileFolder::where('user_id', Auth::id())
            ->where('parent_path', 'like', $oldPath . '%')
            ->get();

        foreach ($subfolders as $sub) {
            $sub->parent_path = str_replace($oldPath, $newPath, $sub->parent_path);
            $sub->path = str_replace($oldPath, $newPath, $sub->path);
            $sub->save();
        }

        $files = File::where('user_id', Auth::id())
            ->where('folder', 'like', $oldPath . '%')
            ->get();

        foreach ($files as $file) {
            $oldFullPath = storage_path('app/drive/' . $file->path);
            $newRelativePath = str_replace($oldPath, $newPath, $file->path);
            $newFullPath = storage_path('app/drive/' . $newRelativePath);

            $newDir = dirname($newFullPath);
            if (!is_dir($newDir)) {
                mkdir($newDir, 0755, true);
            }

            if (file_exists($oldFullPath)) {
                rename($oldFullPath, $newFullPath);
            }

            $file->path = $newRelativePath;
            $file->folder = str_replace($oldPath, $newPath, $file->folder);
            $file->save();
        }

        return response()->json(['success' => true, 'message' => 'Folder berhasil dipindah']);
    }

    /**
     * Apakah ukuran kiriman melebihi post_max_size milik PHP?
     */
    private function melebihiBatasKiriman(Request $request): bool
    {
        $batas = $this->keBytes((string) ini_get('post_max_size'));
        $dikirim = (int) $request->server('CONTENT_LENGTH', 0);

        return $batas > 0 && $dikirim > $batas;
    }

    /**
     * Ubah notasi ukuran PHP ("8M", "512K") menjadi bytes.
     */
    private function keBytes(string $nilai): int
    {
        $nilai = trim($nilai);
        if ($nilai === '') {
            return 0;
        }

        $angka = (int) $nilai;

        return match (strtolower(substr($nilai, -1))) {
            'g' => $angka * 1024 * 1024 * 1024,
            'm' => $angka * 1024 * 1024,
            'k' => $angka * 1024,
            default => $angka,
        };
    }

    private function getBreadcrumbs(string $folder): array
    {
        if ($folder === '/') {
            return [['name' => 'Drive', 'path' => '/']];
        }

        $parts = array_filter(explode('/', $folder));
        $breadcrumbs = [['name' => 'Drive', 'path' => '/']];
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            $breadcrumbs[] = ['name' => $part, 'path' => $currentPath];
        }

        return $breadcrumbs;
    }
}
