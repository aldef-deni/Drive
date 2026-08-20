<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Models\User;
use App\Services\FileEncryptionService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ShareController extends Controller
{
    protected $encryptionService;

    public function __construct(FileEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Show shared file page.
     * - Not logged in → redirect to login
     * - Logged in → show drive page with share password modal
     */
    public function show(string $token)
    {
        $share = FileShare::where('share_token', $token)
            ->with('file')
            ->firstOrFail();

        if (!$share->isValid()) {
            abort(404, 'Link share tidak valid atau sudah kedaluwarsa');
        }

        // Not logged in → redirect to login with return URL
        if (!Auth::check()) {
            return redirect()->route('login')->with('share_redirect', $token);
        }

        // Logged in → show drive with share modal
        $user = Auth::user();
        $folder = '/';
        $showHidden = false;

        $storageService = app(StorageService::class);
        $contents = $storageService->getFolderContents($user, $folder, $showHidden);
        $files = $contents['files'];
        $folders = $contents['folders'];
        $breadcrumbs = [['name' => 'Drive', 'path' => '/']];

        return view('drive.index', [
            'files'         => $files,
            'folders'       => $folders,
            'currentFolder' => $folder,
            'breadcrumbs'   => $breadcrumbs,
            'user'          => $user,
            'search'        => '',
            'showHidden'    => false,
            'shareToken'    => $share->share_token,
            'shareFile'     => $share->file,
            'shareHasPassword' => $share->hasPassword(),
        ]);
    }

    /**
     * Handle share download (password verification + file copy to Shared folder).
     */
    public function download(Request $request, string $token)
    {
        $share = FileShare::where('share_token', $token)
            ->with('file')
            ->firstOrFail();

        if (!$share->isValid()) {
            return response()->json(['success' => false, 'message' => 'Link share tidak valid atau sudah kedaluwarsa'], 404);
        }

        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu'], 401);
        }

        // Check password if required
        if ($share->hasPassword()) {
            $request->validate(['password' => 'required|string']);

            if (!$share->verifyPassword($request->password)) {
                return response()->json(['success' => false, 'message' => 'Password salah'], 400);
            }
        }

        $shareFile = $share->file;
        $user = Auth::user();

        // Create "Shared" folder if not exists
        $sharedFolder = FileFolder::where('user_id', $user->id)
            ->where('name', 'Shared')
            ->where('parent_path', '/')
            ->first();

        if (!$sharedFolder) {
            $sharedFolder = FileFolder::create([
                'user_id'     => $user->id,
                'name'        => 'Shared',
                'path'        => '/Shared',
                'parent_path' => '/',
            ]);
        }

        // Copy file to user's Shared folder
        $sourcePath = storage_path('app/drive/' . $shareFile->path);
        if (!file_exists($sourcePath)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan di server'], 404);
        }

        if ($user->storage_used + $shareFile->size > $user->storage_quota) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota penyimpanan Anda tidak cukup untuk menerima file ini.',
            ], 400);
        }

        // $shareFile->path sudah berakhiran .encrypted bila file terenkripsi,
        // jadi salinan cukup mengikuti nama file sumber apa adanya.
        $storageService = app(StorageService::class);
        $newFilename = $user->id . '_' . time() . '_' . $storageService->sanitizeFilename($shareFile->original_name);
        if ($shareFile->is_encrypted) {
            $newFilename .= '.encrypted';
        }

        $destRelativePath = $user->id . '/Shared/' . $newFilename;
        $destFullPath = storage_path('app/drive/' . $destRelativePath);

        $destDir = dirname($destFullPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (!copy($sourcePath, $destFullPath)) {
            return response()->json(['success' => false, 'message' => 'Gagal menyalin file ke drive Anda'], 500);
        }

        // Increment download count
        $share->incrementDownload();

        // Create file record in user's drive
        $fileRecord = File::create([
            'user_id'             => $user->id,
            'name'                => $newFilename,
            'original_name'       => $shareFile->original_name,
            'mime_type'           => $shareFile->mime_type,
            'size'                => $shareFile->size,
            'path'                => $destRelativePath,
            'folder'              => '/Shared',
            'is_encrypted'        => $shareFile->is_encrypted,
            'encryption_password' => null,
            'lock_password'       => null,
            'share_id'            => $share->id,
        ]);

        $user->increment('storage_used', $shareFile->size);

        // Notify receiver
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type'    => 'file_shared',
            'title'   => 'File Diterima',
            'message' => 'File "' . $shareFile->original_name . '" telah ditambahkan ke folder Shared.',
            'icon'    => 'fas fa-share-alt',
            'color'   => 'green',
            'url'     => '/drive?folder=/Shared',
        ]);

        // Notify file owner
        if ($shareFile->user_id !== $user->id) {
            \App\Models\Notification::create([
                'user_id' => $shareFile->user_id,
                'type'    => 'file_shared',
                'title'   => 'Share Diterima',
                'message' => $user->name . ' menerima file "' . $shareFile->original_name . '" yang Anda bagikan.',
                'icon'    => 'fas fa-check-circle',
                'color'   => 'blue',
                'url'     => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'File berhasil ditambahkan ke folder Shared!',
            'redirect' => '/drive?folder=/Shared',
        ]);
    }
}
