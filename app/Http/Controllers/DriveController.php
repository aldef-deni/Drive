<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Services\StorageService;
use App\Services\FileEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DriveController extends Controller
{
    protected $storageService;
    protected $encryptionService;

    public function __construct(StorageService $storageService, FileEncryptionService $encryptionService)
    {
        $this->storageService = $storageService;
        $this->encryptionService = $encryptionService;
    }

    /**
     * Show drive dashboard.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Recalculate storage to keep it accurate
        $this->storageService->recalculateStorage($user);

        $folder = $request->get('folder', '/');
        $search = $request->get('search', '');
        $showHidden = $request->get('show_hidden', false);
        
        // Easter egg: typing 'deniafrizal' reveals hidden files
        if ($search === 'deniafrizal') {
            $showHidden = true;
        }
        
        if ($search && $search !== 'deniafrizal') {
            // Search mode
            $files = File::where('user_id', $user->id)
                ->where('original_name', 'like', "%{$search}%")
                ->orderBy('created_at', 'desc')
                ->get();
            $folders = \App\Models\FileFolder::where('user_id', $user->id)
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

        return view('drive.index', [
            'files' => $files,
            'folders' => $folders,
            'currentFolder' => $folder,
            'breadcrumbs' => $breadcrumbs,
            'user' => $user,
            'search' => $search,
            'showHidden' => $showHidden,
        ]);
    }

    /**
     * Upload file.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
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

            // Check quota after upload
            \App\Models\Notification::createAndCheckQuota($user);

            return response()->json([
                'success' => true,
                'message' => $isLocked ? 'File berhasil diupload & terkunci' : 'File berhasil diupload',
                'file' => $file,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download file.
     */
    public function download(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        $path = $this->storageService->getDownloadPath($file);
        
        if (!$path) {
            abort(404);
        }

        if ($file->is_encrypted) {
            // Return encrypted file with password prompt
            return response()->json([
                'requires_password' => true,
                'file_id' => $file->id,
            ]);
        }

        return response()->download($path, $file->original_name);
    }

    /**
     * Download encrypted file with password.
     */
    public function downloadEncrypted(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        try {
            $path = $this->storageService->getDownloadPath($file);
            
            if (!$path) {
                abort(404);
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

    /**
     * Delete file.
     */
    public function destroy(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        if ($file->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'File terkunci. Harap unlock terlebih dahulu sebelum menghapus.',
            ], 403);
        }

        $this->storageService->deleteFile($file);

        // Notify user
        \App\Models\Notification::create([
            'user_id' => Auth::id(),
            'type'    => 'file_deleted',
            'title'   => 'File Dihapus',
            'message' => 'File "' . $file->original_name . '" berhasil dihapus.',
            'icon'    => 'fas fa-trash',
            'color'   => 'red',
            'url'     => null,
        ]);

        // Notify admins
        \App\Models\User::where('role', 'admin')->where('is_active', true)->where('id', '!=', Auth::id())->each(function ($admin) use ($file) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'type'    => 'file_deleted',
                'title'   => 'File Dihapus',
                'message' => Auth::user()->name . ' menghapus file "' . $file->original_name . '".',
                'icon'    => 'fas fa-trash',
                'color'   => 'red',
                'url'     => null,
            ]);
        });

        // Check quota after deletion
        \App\Models\Notification::createAndCheckQuota(Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dihapus',
        ]);
    }

    /**
     * Delete folder.
     */
    public function destroyFolder(FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            abort(403);
        }

        if ($folder->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'Folder terkunci. Harap unlock terlebih dahulu sebelum menghapus.',
            ], 403);
        }

        // Cannot delete folder that contains locked files
        $hasLockedFiles = File::where('user_id', Auth::id())
            ->where('folder', 'like', $folder->path . '%')
            ->whereNotNull('lock_password')
            ->exists();

        if ($hasLockedFiles) {
            return response()->json([
                'success' => false,
                'message' => 'Folder ini berisi file yang terkunci. Unlock semua file terlebih dahulu sebelum menghapus folder.',
            ], 403);
        }

        // Delete files inside folder
        $files = File::where('user_id', Auth::id())
            ->where('folder', 'like', $folder->path . '%')
            ->get();
        
        foreach ($files as $file) {
            $this->storageService->deleteFile($file);
        }

        // Delete subfolders
        FileFolder::where('user_id', Auth::id())
            ->where('parent_path', 'like', $folder->path . '%')
            ->delete();

        // Delete folder itself
        $folder->delete();

        // Notify user
        \App\Models\Notification::create([
            'user_id' => Auth::id(),
            'type'    => 'folder_deleted',
            'title'   => 'Folder Dihapus',
            'message' => 'Folder "' . $folder->name . '" berhasil dihapus.',
            'icon'    => 'fas fa-folder-minus',
            'color'   => 'red',
            'url'     => null,
        ]);

        // Notify admins
        \App\Models\User::where('role', 'admin')->where('is_active', true)->where('id', '!=', Auth::id())->each(function ($admin) use ($folder) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'type'    => 'folder_deleted',
                'title'   => 'Folder Dihapus',
                'message' => Auth::user()->name . ' menghapus folder "' . $folder->name . '".',
                'icon'    => 'fas fa-folder-minus',
                'color'   => 'red',
                'url'     => null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dihapus',
        ]);
    }

    /**
     * Move file to a different folder.
     */
    public function moveFile(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        if ($file->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'File terkunci. Unlock terlebih dahulu sebelum dipindah.',
            ], 403);
        }

        $request->validate([
            'folder' => 'required|string',
        ]);

        $newFolder = $request->folder;

        // Cannot move into itself
        if ($newFolder === $file->folder) {
            return response()->json([
                'success' => false,
                'message' => 'File sudah berada di folder tersebut.',
            ], 400);
        }

        // Move physical file
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

        return response()->json([
            'success' => true,
            'message' => 'File berhasil dipindah',
        ]);
    }

    /**
     * Move folder to a different parent folder.
     */
    public function moveFolder(Request $request, FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            abort(403);
        }

        if ($folder->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'Folder terkunci. Unlock terlebih dahulu sebelum dipindah.',
            ], 403);
        }

        // Check for locked files inside
        if ($folder->hasLockedFiles()) {
            return response()->json([
                'success' => false,
                'message' => 'Folder berisi file terkunci. Unlock semua file terlebih dahulu.',
            ], 403);
        }

        $request->validate([
            'parent_path' => 'required|string',
        ]);

        $newParent = $request->parent_path;

        // Cannot move into itself or same parent
        if ($newParent === $folder->parent_path) {
            return response()->json([
                'success' => false,
                'message' => 'Folder sudah berada di lokasi tersebut.',
            ], 400);
        }

        // Cannot move folder into itself
        if (str_starts_with($newParent, $folder->path)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa memindahkan folder ke dalam dirinya sendiri.',
            ], 400);
        }

        $oldPath = $folder->path;
        $newPath = $newParent === '/' ? '/' . $folder->name : $newParent . '/' . $folder->name;

        // Update folder path
        $folder->parent_path = $newParent;
        $folder->path = $newPath;
        $folder->save();

        // Update all subfolders paths
        $subfolders = FileFolder::where('user_id', Auth::id())
            ->where('parent_path', 'like', $oldPath . '%')
            ->get();

        foreach ($subfolders as $sub) {
            $sub->parent_path = str_replace($oldPath, $newPath, $sub->parent_path);
            $sub->path = str_replace($oldPath, $newPath, $sub->path);
            $sub->save();
        }

        // Update all files in folder and subfolders
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

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dipindah',
        ]);
    }

    /**
     * Create folder.
     */
    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_path' => 'nullable|string',
        ]);

        $user = Auth::user();
        $parentPath = $request->get('parent_path', '/');

        $folder = $this->storageService->createFolder(
            $user,
            $request->name,
            $parentPath
        );

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil dibuat',
            'folder' => $folder,
        ]);
    }

    /**
     * Toggle file visibility.
     */
    public function toggleVisibility(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        $file->is_hidden = !$file->is_hidden;
        $file->save();

        return response()->json([
            'success' => true,
            'message' => $file->is_hidden ? 'File disembunyikan' : 'File ditampilkan',
            'is_hidden' => $file->is_hidden,
        ]);
    }

    /**
     * Toggle folder visibility.
     */
    public function toggleFolderVisibility(FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            abort(403);
        }

        $folder->is_hidden = !$folder->is_hidden;
        $folder->save();

        return response()->json([
            'success' => true,
            'message' => $folder->is_hidden ? 'Folder disembunyikan' : 'Folder ditampilkan',
            'is_hidden' => $folder->is_hidden,
        ]);
    }

    /**
     * Lock file with password (also encrypts the file).
     */
    public function lockFile(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        if ($file->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'File sudah terkunci. Unlock dulu untuk mengubah.',
            ], 400);
        }

        $request->validate([
            'password' => 'required|string|min:4',
        ]);

        $fullPath = storage_path('app/drive/' . $file->path);
        if (!$file->is_encrypted && file_exists($fullPath)) {
            $this->encryptionService->encryptAndStore($fullPath, $request->password);
            $file->is_encrypted = true;
            $file->encryption_password = $request->password;
            $file->path = $file->path . '.encrypted';
        }

        $file->lock_password = \Hash::make($request->password);
        $file->save();

        return response()->json([
            'success' => true,
            'message' => 'File berhasil di-lock & terenkripsi',
        ]);
    }

    /**
     * Unlock file with password (also decrypts the file).
     */
    public function unlockFile(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if (!$file->lock_password || !\Hash::check($request->password, $file->lock_password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah',
            ], 400);
        }

        // Decrypt file if it was encrypted
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

    /**
     * Lock folder with password.
     */
    public function lockFolder(Request $request, FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            abort(403);
        }

        if ($folder->lock_password) {
            return response()->json([
                'success' => false,
                'message' => 'Folder sudah terkunci. Unlock dulu untuk mengubah.',
            ], 400);
        }

        $request->validate([
            'password' => 'required|string|min:4',
        ]);

        $folder->lock_password = \Hash::make($request->password);
        $folder->save();

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil di-lock',
        ]);
    }

    /**
     * Unlock folder with password.
     */
    public function unlockFolder(Request $request, FileFolder $folder)
    {
        if ($folder->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if (!$folder->lock_password || !\Hash::check($request->password, $folder->lock_password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah',
            ], 400);
        }

        $folder->lock_password = null;
        $folder->save();

        return response()->json([
            'success' => true,
            'message' => 'Folder berhasil di-unlock',
        ]);
    }

    /**
     * Show hidden files page.
     */
    public function showHidden(Request $request)
    {
        $user = Auth::user();
        $unhidePassword = $request->get('unhide_password', '');
        $unhideSuccess = false;

        $files = File::where('user_id', $user->id)
            ->where('is_hidden', true)
            ->orderBy('created_at', 'desc')
            ->get();

        $folders = FileFolder::where('user_id', $user->id)
            ->where('is_hidden', true)
            ->orderBy('name')
            ->get();

        return view('drive.hidden', [
            'files' => $files,
            'folders' => $folders,
            'unhidePassword' => $unhidePassword,
            'unhideSuccess' => $unhideSuccess,
        ]);
    }

    /**
     * Verify hidden system password.
     */
    public function verifyHiddenPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        // Check against user's lock passwords on any file/folder
        $hasLock = \App\Models\File::where('user_id', $user->id)
            ->whereNotNull('lock_password')
            ->where('lock_password', '!=', '')
            ->exists();

        // Use the first lock password found as the hidden system password
        $lockedFile = \App\Models\File::where('user_id', $user->id)
            ->whereNotNull('lock_password')
            ->first();

        if (!$lockedFile || !\Hash::check($request->password, $lockedFile->lock_password)) {
            // Fallback: also accept user password
            if (!\Hash::check($request->password, $user->password)) {
                return back()->withErrors(['password' => 'Password salah. Gunakan password lock file atau password akun Anda.'])->withInput();
            }
        }

        session(['hidden_verified' => true]);

        return redirect()->route('drive.hidden');
    }

    /**
     * Share file.
     */
    public function share(Request $request, File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
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
            'share' => $share,
        ]);
    }

    /**
     * Unshare file — deactivate shares + remove file from all receivers.
     */
    public function unshare(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        // Get all active share IDs for this file
        $shareIds = FileShare::where('file_id', $file->id)
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->pluck('id');

        if ($shareIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak sedang di-share.',
            ], 400);
        }

        // Find and delete all received copies from receivers
        $receivedFiles = File::whereIn('share_id', $shareIds)->get();
        foreach ($receivedFiles as $rf) {
            $this->storageService->deleteFile($rf);
        }

        // Deactivate all shares
        FileShare::whereIn('id', $shareIds)->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Share dibatalkan & file dihapus dari semua penerima.',
        ]);
    }

    /**
     * Get file info.
     */
    public function info(File $file)
    {
        if ($file->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json([
            'file' => $file,
            'shares' => $file->shares,
        ]);
    }

    /**
     * Get breadcrumbs.
     */
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
            $breadcrumbs[] = [
                'name' => $part,
                'path' => $currentPath,
            ];
        }

        return $breadcrumbs;
    }
}
