<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UpdateController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [HomeController::class, 'index']);
Route::get('/setup', [HomeController::class, 'setup']);
Route::post('/setup', [HomeController::class, 'createDb']);
Route::get('/test', [HomeController::class, 'test']);
Route::get('/test', function(){
    return 'test';
});

Route::get('/admin/auth', function(){
    return view('login');
})->name('login');
Route::get('/admin/auth/logout', [AuthController::class, 'logout']);
Route::post('/admin/auth', [AuthController::class, 'login']);


Route::get('/admin', [AdminController::class, 'index']);
Route::get('/admin/active-user/{id}', [AdminController::class, 'toogleActiveUser']);
Route::get('/admin/reset-profile-status', [AdminController::class, 'resetProfileStatus']);
Route::get('/admin/save-setting', [AdminController::class, 'saveSetting']);
Route::get('/admin/migration', [AdminController::class, 'runMigrations']);

Route::middleware(['auth:sanctum'])->get('/phpinfo', function(){
    phpinfo();
});

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/auto-update', [UpdateController::class, 'updateFromRemoteZip']);
});
