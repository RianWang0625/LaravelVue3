<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SiteController;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store']);
});


Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

// SaaS 網站管理路由（需要登入）
Route::middleware('auth')->group(function () {

    // 顯示我的網站清單
    Route::get('/sites', [SiteController::class, 'index'])
        ->name('sites.index');

    // 顯示新增網站頁面
    Route::get('/sites/create', [SiteController::class, 'create'])
        ->name('sites.create');

    // 儲存新網站
    Route::post('/sites', [SiteController::class, 'store'])
        ->name('sites.store');

});

