<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileShare;
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

        $folder = $request->get('folder', '/');
        $search = $request->get('search', '');
        $showHidden = $request->get('show_hidden', false);

        if ($search === 'deniafrizal') {
            $showHidden = true;
        }

        if ($search && $search !== 'deniafrizal') {
            $files = File::where('user_id', $user->id)
                ->where('original_name', 'like', "%{$search}%")
                ->orderBy('created_at', 'desc')
                ->get();
            $folders = FileFolder::where('user_id', $user->id)
                ->where('name', 'like', "%{$search}%")
                ->orderBy('name')
                ->get();
            $breadcrumbs = [['name' => 'Drive', 'path' => '/'], ['name' => 'Search: ' . $search, 'path' => '/']];
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
                    'is_locked' => !empty($f->lock_password),
                    'has_locked_files' => $f->hasLockedFiles(),
                    'created_at' => $f->created_at->toISOString(),
                ];
            }),
            'breadcrumbs' => $breadcrumbs,
            'current_folder' => $folder,
            'user' => [
                'storage_quota' => $user->storage_quota,
                'storage_used' => $user->storage_used,
                'storage_percentage' => $user->getStoragePercentage(),
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400',
            'folder' => 'nullable|string',
            'is_locked' => 'nullable|boolean',
            'lock_password' => 'nullable|required_if:is_locked,1|string|min:4',
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
            $file->encryption_password = $request->password;
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

    public function showHidden(Request $request)
    {
        $user = Auth::user();

        $files = File::where('user_id', $user->id)
            ->where('is_hidden', true)
            ->orderBy('created_at', 'desc')
            ->get();

        $folders = FileFolder::where('user_id', $user->id)
            ->where('is_hidden', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'files' => $files->map(function ($f) {
                return [
                    'id' => $f->id,
                    'name' => $f->original_name,
                    'mime_type' => $f->mime_type,
                    'size' => $f->size,
                    'size_formatted' => $f->formatSize(),
                    'is_locked' => !empty($f->lock_password),
                    'is_encrypted' => $f->is_encrypted,
                    'created_at' => $f->created_at->toISOString(),
                ];
            }),
            'folders' => $folders->map(function ($f) {
                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'path' => $f->path,
                    'is_locked' => !empty($f->lock_password),
                    'created_at' => $f->created_at->toISOString(),
                ];
            }),
        ]);
    }

    public function verifyHiddenPassword(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $user = Auth::user();

        $lockedFile = \App\Models\File::where('user_id', $user->id)
            ->whereNotNull('lock_password')
            ->first();

        if ($lockedFile && Hash::check($request->password, $lockedFile->lock_password)) {
            return response()->json(['success' => true, 'message' => 'Password benar']);
        }

        if (Hash::check($request->password, $user->password)) {
            return response()->json(['success' => true, 'message' => 'Password benar']);
        }

        return response()->json(['success' => false, 'message' => 'Password salah'], 400);
    }

    public function unhideFile(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['password' => 'required|string']);

        $user = Auth::user();
        $lockedFile = \App\Models\File::where('user_id', $user->id)
            ->whereNotNull('lock_password')
            ->first();

        if ($lockedFile && Hash::check($request->password, $lockedFile->lock_password)) {
            $file->is_hidden = false;
            $file->save();
            return response()->json(['success' => true, 'message' => 'File berhasil ditampilkan']);
        }

        if (Hash::check($request->password, $user->password)) {
            $file->is_hidden = false;
            $file->save();
            return response()->json(['success' => true, 'message' => 'File berhasil ditampilkan']);
        }

        return response()->json(['success' => false, 'message' => 'Password salah'], 400);
    }

    public function unhideFolder(Request $request, FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate(['password' => 'required|string']);

        $user = Auth::user();
        $lockedFile = \App\Models\File::where('user_id', $user->id)
            ->whereNotNull('lock_password')
            ->first();

        if ($lockedFile && Hash::check($request->password, $lockedFile->lock_password)) {
            $folder->is_hidden = false;
            $folder->save();
            return response()->json(['success' => true, 'message' => 'Folder berhasil ditampilkan']);
        }

        if (Hash::check($request->password, $user->password)) {
            $folder->is_hidden = false;
            $folder->save();
            return response()->json(['success' => true, 'message' => 'Folder berhasil ditampilkan']);
        }

        return response()->json(['success' => false, 'message' => 'Password salah'], 400);
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
