<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\ProxyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\HomeController;

/*
|--------------------------------------------------------------------------
| API V1 Routes (Default)
|--------------------------------------------------------------------------
|
| These routes are available at both:
| - /api/* (for backward compatibility)
| - /api/v1/* (versioned endpoint)
|
| When adding new features, consider adding them to api_v2.php instead
| to maintain backward compatibility with existing applications.
|
*/

Route::get('/time', [HomeController::class, 'getSystemTime']);

// Users
Route::prefix('users')->group(function () {
    Route::get('/login', [AuthController::class, 'login']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [UserController::class, 'store']);
});

Route::prefix('settings')->group(function () {
    Route::get('get-version', [SettingController::class, 'getPrivateServerVersion']); // 23.7.2024
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('get-s3-api', [SettingController::class, 'getS3Setting']);
        Route::get('get-storage-type', [SettingController::class, 'getStorageTypeSetting']);
        Route::get('get-setting', [SettingController::class, 'getAllSetting']); // 24.9.2024
    });
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/update', [UserController::class, 'update']);
        Route::get('/current-user', [UserController::class, 'getCurrentUser']);
        Route::get('/logout', [AuthController::class, 'logout']);
    });

    Route::prefix('groups')->group(function () {
        Route::get('/', [GroupController::class, 'index']);
        Route::get('/count', [GroupController::class, 'getTotal']);
        Route::post('/create', [GroupController::class, 'store']);
        Route::post('/update/{id}', [GroupController::class, 'update']);
        Route::post('/delete/{id}', [GroupController::class, 'destroy']);
        Route::post('/share/{id}', [GroupController::class, 'share']);
        Route::post('/remove-share/{id}', [GroupController::class, 'removeShare']);
        Route::get('/{id}', [GroupController::class, 'show']);
        Route::get('/get-share-users/{id}', [GroupController::class, 'getGroupShareUsers']);
    });

    Route::prefix('profiles')->group(function () {
        Route::get('/', [ProfileController::class, 'index']);
        Route::get('/count', [ProfileController::class, 'getTotal']);
        Route::get('/{id}', [ProfileController::class, 'show']);
        Route::post('/create', [ProfileController::class, 'store']);
        Route::post('/update/{id}', [ProfileController::class, 'update']);
        Route::post('/bulk-edit-property', [ProfileController::class, 'bulkEditProperty']);
        Route::post('/delete/{id}', [ProfileController::class, 'destroy']);
        Route::post('/bulk-delete', [ProfileController::class, 'bulkDelete']);
        Route::post('/share/{id}', [ProfileController::class, 'share']);
        Route::post('/remove-share/{id}', [ProfileController::class, 'removeShare']);
        Route::post('/bulk-share', [ProfileController::class, 'bulkShare']);
        Route::post('/bulk-remove-share', [ProfileController::class, 'bulkRemoveShare']);
        Route::post('/start-using/{id}', [ProfileController::class, 'startUsing']);
        Route::post('/stop-using/{id}', [ProfileController::class, 'stopUsing']);
        Route::post('/update-status/{id}', [ProfileController::class, 'updateStatus']);
        Route::post('/add-tags/{id}', [ProfileController::class, 'addTags']);
        Route::post('/remove-tags/{id}', [ProfileController::class, 'removeTags']);
        Route::post('/remove-all-tags/{id}', [ProfileController::class, 'removeAllTags']);
        Route::post('/restore/{id}', [ProfileController::class, 'restore']);
        Route::post('/bulk-restore', [ProfileController::class, 'bulkRestore']);
        Route::get('/get-share-users/{id}', [ProfileController::class, 'getProfileShareUsers']);
    });

    Route::prefix('file')->group(function () {
        Route::post('local-upload', [UploadController::class, 'store']);
        Route::put('local-upload', [UploadController::class, 'store']);
        Route::post('delete', [UploadController::class, 'delete']);
        Route::post('create-upload-url', [UploadController::class, 'createUploadUrl']);
        Route::post('create-download-url', [UploadController::class, 'createDownloadUrl']);
        Route::post('check-file-exists', [UploadController::class, 'checkFileExists']);
    });

    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index']);
        Route::get('/get-by-name', [TagController::class, 'getByName']);
        Route::get('/{id}', [TagController::class, 'show']);
        Route::post('/create', [TagController::class, 'store']);
        Route::post('/update/{id}', [TagController::class, 'update']);
        Route::get('/delete/{id}', [TagController::class, 'destroy']);
    });

    Route::prefix('proxies')->group(function () {
        Route::get('/', [ProxyController::class, 'index']);
        Route::get('/{id}', [ProxyController::class, 'show']);
        Route::post('/bulk-create', [ProxyController::class, 'bulkStore']);
        Route::post('/update/{id}', [ProxyController::class, 'update']);
        Route::post('/delete/{id}', [ProxyController::class, 'destroy']);
        Route::post('/bulk-delete', [ProxyController::class, 'bulkDelete']);
        Route::post('/add-tags/{id}', [ProxyController::class, 'addTags']);
        Route::post('/remove-tags/{id}', [ProxyController::class, 'removeTags']);
        Route::post('/remove-share/{id}', [ProxyController::class, 'removeShare']);
        Route::post('/remove-all-tags/{id}', [ProxyController::class, 'removeAllTags']);
        Route::post('/bulk-share', [ProxyController::class, 'bulkShare']);
        Route::post('/bulk-remove-share', [ProxyController::class, 'bulkRemoveShare']);
        Route::get('/get-share-users/{id}', [ProxyController::class, 'getProxyShareUsers']);
    });
});
