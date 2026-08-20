<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ApiDriveController;
use App\Http\Controllers\Api\ApiProfileController;
use App\Http\Controllers\Api\ApiNotificationController;
use App\Http\Controllers\Api\ApiAdminController;
use App\Http\Controllers\Api\ApiShareController;

// Public API routes
Route::post('/login', [ApiAuthController::class, 'login']);
Route::post('/register', [ApiAuthController::class, 'register']);

// Share routes (public)
Route::get('/share/{token}', [ApiShareController::class, 'show']);
Route::post('/share/{token}/verify', [ApiShareController::class, 'verify']);

// Protected API routes
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/me', [ApiAuthController::class, 'me']);

    // Drive
    Route::get('/drive', [ApiDriveController::class, 'index']);
    Route::post('/drive/upload', [ApiDriveController::class, 'upload']);
    Route::delete('/drive/file/{file}', [ApiDriveController::class, 'destroy']);
    Route::get('/drive/file/{file}/download', [ApiDriveController::class, 'download']);
    Route::post('/drive/file/{file}/download-encrypted', [ApiDriveController::class, 'downloadEncrypted']);
    Route::post('/drive/folder/create', [ApiDriveController::class, 'createFolder']);
    Route::delete('/drive/folder/{folder}', [ApiDriveController::class, 'destroyFolder']);
    Route::post('/drive/file/{file}/lock', [ApiDriveController::class, 'lockFile']);
    Route::post('/drive/file/{file}/unlock', [ApiDriveController::class, 'unlockFile']);
    Route::post('/drive/folder/{folder}/lock', [ApiDriveController::class, 'lockFolder']);
    Route::post('/drive/folder/{folder}/unlock', [ApiDriveController::class, 'unlockFolder']);
    Route::post('/drive/file/{file}/share', [ApiDriveController::class, 'share']);
    Route::post('/drive/file/{file}/unshare', [ApiDriveController::class, 'unshare']);
    Route::post('/drive/file/{file}/toggle-visibility', [ApiDriveController::class, 'toggleVisibility']);
    Route::post('/drive/folder/{folder}/toggle-visibility', [ApiDriveController::class, 'toggleFolderVisibility']);
    Route::get('/drive/file/{file}/info', [ApiDriveController::class, 'info']);
    Route::post('/drive/file/{file}/move', [ApiDriveController::class, 'moveFile']);
    Route::post('/drive/folder/{folder}/move', [ApiDriveController::class, 'moveFolder']);

    // Profile
    Route::get('/profile', [ApiProfileController::class, 'show']);
    Route::put('/profile', [ApiProfileController::class, 'update']);
    Route::put('/profile/password', [ApiProfileController::class, 'updatePassword']);
    Route::post('/profile/avatar', [ApiProfileController::class, 'updateAvatar']);

    // Notifications
    Route::get('/notifications', [ApiNotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [ApiNotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [ApiNotificationController::class, 'markAllRead']);

    // Admin
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/dashboard', [ApiAdminController::class, 'dashboard']);
        Route::get('/users', [ApiAdminController::class, 'users']);
        Route::get('/users/{user}', [ApiAdminController::class, 'getUser']);
        Route::put('/users/{user}', [ApiAdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [ApiAdminController::class, 'deleteUser']);
        Route::post('/users/{user}/toggle-status', [ApiAdminController::class, 'toggleStatus']);
        Route::post('/users/{user}/reset-storage', [ApiAdminController::class, 'resetStorage']);

        // Hidden System — kata kunci rahasia untuk memunculkan file tersembunyi
        Route::get('/hidden-keyword', [ApiAdminController::class, 'hiddenKeyword']);
        Route::put('/hidden-keyword', [ApiAdminController::class, 'updateHiddenKeyword']);
    });

    // Share download
    Route::post('/share/{token}/download', [ApiShareController::class, 'download']);
});
