<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\HomeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/time', [HomeController::class, 'getSystemTime']);

// Users
Route::prefix('users')->group(function () {
    Route::get('/login', [AuthController::class, 'login']);
    Route::post('/register', [UserController::class, 'store']);
});

Route::get('settings/get-s3-api', [SettingController::class, 'getS3Setting']);
Route::get('settings/get-storage-type', [SettingController::class, 'getStorageTypeSetting']);
Route::get('settings/get-version', [SettingController::class, 'getPrivateServerVersion']); // 23.7.2024
Route::get('settings/get-setting', [SettingController::class, 'getAllSetting']); // 24.9.2024


Route::middleware(['auth:sanctum'])->group(function(){
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/update', [UserController::class, 'update']);
        Route::get('/current-user', [UserController::class, 'getCurrentUser']);
    });

    Route::prefix('groups')->group(function () {
        Route::get('/', [GroupController::class, 'index']);
        Route::get('/count', [GroupController::class, 'getTotal']);
        Route::post('/create', [GroupController::class, 'store']);
        Route::post('/update/{id}', [GroupController::class, 'update']);
        Route::get('/delete/{id}', [GroupController::class, 'destroy']);
        Route::get('/share/{id}', [GroupController::class, 'share']);
        Route::get('/roles/{id}', [GroupController::class, 'getGroupRoles']);
    });

    Route::prefix('profiles')->group(function () {
        Route::get('/', [ProfileController::class, 'index']);
        Route::get('/count', [ProfileController::class, 'getTotal']);
        Route::get('/{id}', [ProfileController::class, 'show']);
        Route::post('/create', [ProfileController::class, 'store']);
        Route::post('/update/{id}', [ProfileController::class, 'update']);
        Route::get('/update-status/{id}', [ProfileController::class, 'updateStatus']);
        Route::get('/delete/{id}', [ProfileController::class, 'destroy']);
        Route::get('/share/{id}', [ProfileController::class, 'share']);
        Route::get('/roles/{id}', [ProfileController::class, 'getProfileRoles']);
    });

    Route::prefix('settings')->group(function () {
        Route::get('/set-s3-api', [SettingController::class, 'setS3Setting']);
    });

    Route::post('file/upload', [UploadController::class, 'store']);
    Route::get('file/delete', [UploadController::class, 'delete']);
});

