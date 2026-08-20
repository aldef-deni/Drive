<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileShare;
use App\Models\FileFolder;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ApiShareController extends Controller
{
    public function show(string $token)
    {
        $share = FileShare::where('share_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$share || !$share->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Link share tidak valid atau sudah expired',
            ], 404);
        }

        $file = $share->file;

        return response()->json([
            'success' => true,
            'share' => [
                'token' => $share->share_token,
                'file_name' => $file->original_name,
                'file_size' => $file->formatSize(),
                'file_mime' => $file->mime_type,
                'password_required' => $share->hasPassword(),
                'expires_at' => $share->expires_at,
                'download_limit' => $share->download_limit,
                'download_count' => $share->download_count,
                'shared_by' => $share->user->name,
            ],
        ]);
    }

    public function verify(Request $request, string $token)
    {
        $share = FileShare::where('share_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$share || !$share->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Link share tidak valid',
            ], 404);
        }

        if ($share->hasPassword()) {
            $request->validate(['password' => 'required|string']);

            if (!$share->verifyPassword($request->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password salah',
                ], 400);
            }
        }

        // Check if user is logged in
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'requires_login' => true,
                'message' => 'Silakan login terlebih dahulu',
            ], 401);
        }

        $user = Auth::user();
        $file = $share->file;

        // Check quota
        if ($user->storage_used + $file->size > $user->storage_quota) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota storage tidak cukup',
            ], 400);
        }

        // Create Shared folder if not exists
        $sharedFolder = FileFolder::where('user_id', $user->id)
            ->where('name', 'Shared')
            ->where('parent_path', '/')
            ->first();

        if (!$sharedFolder) {
            $sharedFolder = FileFolder::create([
                'user_id' => $user->id,
                'name' => 'Shared',
                'path' => '/Shared',
                'parent_path' => '/',
            ]);
        }

        // Create file record for receiver
        $receivedFile = File::create([
            'user_id' => $user->id,
            'name' => $file->name,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'path' => $file->path,
            'folder' => '/Shared',
            'share_id' => $share->id,
        ]);

        // Update user storage
        $user->storage_used += $file->size;
        $user->save();

        // Increment download count
        $share->incrementDownload();

        // Notify receiver
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'share_received',
            'title' => 'File Diterima',
            'message' => 'File "' . $file->original_name . '" telah ditambahkan ke folder Shared.',
            'icon' => 'fas fa-share-alt',
            'color' => 'green',
            'url' => null,
        ]);

        // Notify owner
        \App\Models\Notification::create([
            'user_id' => $share->user_id,
            'type' => 'share_accepted',
            'title' => 'Share Diterima',
            'message' => $user->name . ' menerima file "' . $file->original_name . '" yang Anda bagikan.',
            'icon' => 'fas fa-check-circle',
            'color' => 'blue',
            'url' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File berhasil diterima dan disimpan di folder Shared',
        ]);
    }

    public function download(Request $request, string $token)
    {
        $share = FileShare::where('share_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$share || !$share->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Link share tidak valid',
            ], 404);
        }

        $file = $share->file;
        $path = storage_path('app/drive/' . $file->path);

        if (!file_exists($path)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan'], 404);
        }

        return response()->download($path, $file->original_name);
    }
}
