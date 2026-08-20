<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;

// Home redirect
Route::get('/', function () {
    return redirect(auth()->check() ? '/drive' : '/login');
});

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

// Drive routes
Route::prefix('drive')->middleware('auth')->group(function () {
    Route::get('/', [DriveController::class, 'index'])->name('drive.index');
    Route::post('/upload', [DriveController::class, 'upload'])->name('drive.upload');
    Route::delete('/file/{file}', [DriveController::class, 'destroy'])->name('drive.destroy');
    Route::get('/file/{file}/download', [DriveController::class, 'download'])->name('drive.download');
    Route::post('/file/{file}/download-encrypted', [DriveController::class, 'downloadEncrypted'])->name('drive.download.encrypted');
    Route::post('/file/{file}/toggle-visibility', [DriveController::class, 'toggleVisibility'])->name('drive.toggle-visibility');
    Route::post('/folder/create', [DriveController::class, 'createFolder'])->name('drive.folder.create');
    Route::post('/folder/{folder}/toggle-visibility', [DriveController::class, 'toggleFolderVisibility'])->name('drive.folder.toggle-visibility');
    Route::get('/hidden', [DriveController::class, 'showHidden'])->name('drive.hidden');
    Route::post('/hidden/verify', [DriveController::class, 'verifyHiddenPassword'])->name('drive.hidden.verify');
    Route::post('/hidden/lock', [DriveController::class, 'lockHidden'])->name('drive.hidden.lock');
    Route::post('/file/{file}/share', [DriveController::class, 'share'])->name('drive.share');
    Route::post('/file/{file}/unshare', [DriveController::class, 'unshare'])->name('drive.unshare');
    Route::get('/file/{file}/info', [DriveController::class, 'info'])->name('drive.info');
    Route::post('/file/{file}/lock', [DriveController::class, 'lockFile'])->name('drive.lock');
    Route::post('/file/{file}/unlock', [DriveController::class, 'unlockFile'])->name('drive.unlock');
    Route::post('/file/{file}/move', [DriveController::class, 'moveFile'])->name('drive.file.move');
    Route::post('/folder/{folder}/move', [DriveController::class, 'moveFolder'])->name('drive.folder.move');
    Route::delete('/folder/{folder}', [DriveController::class, 'destroyFolder'])->name('drive.folder.destroy');
    Route::post('/folder/{folder}/lock', [DriveController::class, 'lockFolder'])->name('drive.folder.lock');
    Route::post('/folder/{folder}/unlock', [DriveController::class, 'unlockFolder'])->name('drive.folder.unlock');
});

// Admin routes
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::post('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('admin.users.toggle-status');
    Route::post('/users/{user}/reset-storage', [AdminController::class, 'resetStorage'])->name('admin.users.reset-storage');
});

// Public share routes
Route::get('/share/{token}', [ShareController::class, 'show'])->name('share.show');
Route::post('/share/{token}/download', [ShareController::class, 'download'])->name('share.download');
