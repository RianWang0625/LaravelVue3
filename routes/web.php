<?php

use App\Http\Controllers\Admin\SiteController as AdminSiteController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Middleware\EnsureSitePublished;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;

// 首頁
Route::get('/', function () {
    return Inertia::render('Home');
});

// 註冊路由
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store']);
});

// 登入路由
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// 登出路由
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

// SaaS 網站管理路由（需要登入）
Route::middleware('auth')->group(function () {

    // 顯示我的網站清單
    Route::get('/sites', [SiteController::class, 'index'])
        ->name('sites.index');

    // 商家發布自己擁有的網站
    Route::patch('/sites/{site}/publish', [SiteController::class, 'publish'])
        ->name('sites.publish');

    // 顯示新增網站頁面
    Route::get('/sites/create', [SiteController::class, 'create'])
        ->name('sites.create');

    // 儲存新網站
    Route::post('/sites', [SiteController::class, 'store'])
        ->name('sites.store');
});

// 商品管理與商家訂單管理路由
Route::middleware(['auth'])->group(function () {

    // 商品列表
    Route::get('/products', [ProductController::class, 'index'])
        ->name('products.index');

    // 新增商品
    Route::post('/products', [ProductController::class, 'store'])
        ->name('products.store');

    // 修改商品
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->name('products.update');

    // 刪除商品
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->name('products.destroy');

    // 新增商品規格
    Route::post('/products/{product}/variants', [
        ProductVariantController::class,
        'store'
    ])->name('products.variants.store');

    // 修改商品規格
    Route::put('/products/{product}/variants/{variant}', [
        ProductVariantController::class,
        'update'
    ])->name('products.variants.update');

    // 刪除商品規格
    Route::delete('/products/{product}/variants/{variant}', [
        ProductVariantController::class,
        'destroy'
    ])->name('products.variants.destroy');

    // 商家訂單列表
    Route::get('/seller/orders', [OrderController::class, 'index'])
        ->name('seller.orders.index');

    // 商家訂單明細
    Route::get('/seller/orders/{order}', [OrderController::class, 'show'])
        ->name('seller.orders.show');

    // 本機及測試環境模擬付款
    if (app()->environment(['local', 'testing'])) {
        Route::patch('/seller/orders/{order}/simulate-payment', [
            OrderController::class,
            'simulatePayment'
        ])->name('seller.orders.simulate-payment');
    }

    // 商家處理訂單
    Route::patch('/seller/orders/{order}/process', [
        OrderController::class,
        'process'
    ])->name('seller.orders.process');

    // 商家出貨
    Route::patch('/seller/orders/{order}/ship', [
        OrderController::class,
        'ship'
    ])->name('seller.orders.ship');

    // 商家完成訂單
    Route::patch('/seller/orders/{order}/complete', [
        OrderController::class,
        'complete'
    ])->name('seller.orders.complete');

    // 商家取消訂單
    Route::patch('/seller/orders/{order}/cancel', [
        OrderController::class,
        'cancel'
    ])->name('seller.orders.cancel');
});

// ==================================================
// 買家商店瀏覽路由
// 草稿網站由 ShopController 控制，僅擁有者可預覽
// ==================================================

// 買家購物網站首頁
Route::get('/shop/{site}', [ShopController::class, 'index'])
    ->name('shop.index');

// 買家商品詳細頁面
Route::get('/shop/{site}/products/{product}', [
    ShopController::class,
    'show'
])->name('shop.products.show');

// ==================================================
// 買家購物車、結帳及訂單成功頁
// 只有已正式發布的網站可以使用
// ==================================================

Route::middleware(EnsureSitePublished::class)->group(function () {

    // 查看購物車
    Route::get('/shop/{site}/cart', [CartController::class, 'index'])
        ->name('shop.cart.index');

    // 加入購物車
    Route::post('/shop/{site}/cart', [CartController::class, 'store'])
        ->name('shop.cart.store');

    // 修改購物車商品數量
    Route::put('/shop/{site}/cart/{key}', [CartController::class, 'update'])
        ->name('shop.cart.update');

    // 刪除購物車商品
    Route::delete('/shop/{site}/cart/{key}', [CartController::class, 'destroy'])
        ->name('shop.cart.destroy');

    // 顯示結帳頁面
    Route::get('/shop/{site}/checkout', [CheckoutController::class, 'index'])
        ->name('shop.checkout.index');

    // 送出訂單
    Route::post('/shop/{site}/checkout', [CheckoutController::class, 'store'])
        ->name('shop.checkout.store');

    // 顯示訂單成功頁
    Route::get('/shop/{site}/orders/{order}/success', [
        CheckoutController::class,
        'success'
    ])->name('shop.checkout.success');

});

/*
|--------------------------------------------------------------------------
| 平台管理員後台
|--------------------------------------------------------------------------
| 只有登入且具有 Admin 角色的使用者可以進入
| 具體角色授權由 DashboardController 負責
*/


Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Admin Dashboard
        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');

        // Admin 查看平台所有商店
        Route::get('/sites', [AdminSiteController::class, 'index'])
            ->name('sites.index');

    });


