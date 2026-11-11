<?php

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

// 商品管理路由
Route::middleware(['auth'])->group(function () {
    Route::get('/products', [ProductController::class, 'index'])
        ->name('products.index');

    Route::post('/products', [ProductController::class, 'store'])
        ->name('products.store');

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->name('products.update');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->name('products.destroy');

    
    // 商品規格管理路由
    Route::post('/products/{product}/variants', [
        ProductVariantController::class,
        'store'
    ])->name('products.variants.store');

    Route::put('/products/{product}/variants/{variant}', [
        ProductVariantController::class,
        'update'
    ])->name('products.variants.update');

    Route::delete('/products/{product}/variants/{variant}', [
        ProductVariantController::class,
        'destroy'
    ])->name('products.variants.destroy');


    // 商家訂單列表及明細
    Route::get('/seller/orders', [OrderController::class, 'index'])
        ->name('seller.orders.index');

    Route::get('/seller/orders/{order}', [OrderController::class, 'show'])
        ->name('seller.orders.show');

        // 本機測試付款路由
    if (app()->environment(['local', 'testing'])) {
        Route::patch('/seller/orders/{order}/simulate-payment', [
            OrderController::class,
            'simulatePayment'
        ])->name('seller.orders.simulate-payment');
    }
    // 商家訂單狀態與出貨管理
    Route::patch('/seller/orders/{order}/process', [OrderController::class, 'process'])
        ->name('seller.orders.process');

    Route::patch('/seller/orders/{order}/ship', [OrderController::class, 'ship'])
        ->name('seller.orders.ship');

    Route::patch('/seller/orders/{order}/complete', [OrderController::class, 'complete'])
        ->name('seller.orders.complete');

    Route::patch('/seller/orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->name('seller.orders.cancel');

});


 
// 買家購物網站首頁
Route::get('/shop/{site}', [ShopController::class, 'index'])
    ->name('shop.index');

// 買家商品詳細頁面
Route::get('/shop/{site}/products/{product}', [
    ShopController::class,
    'show'
])->name('shop.products.show');

// 買家購物車路由
Route::get('/shop/{site}/cart', [CartController::class, 'index'])
    ->name('shop.cart.index');

Route::post('/shop/{site}/cart', [CartController::class, 'store'])
    ->name('shop.cart.store');

Route::put('/shop/{site}/cart/{key}', [CartController::class, 'update'])
    ->name('shop.cart.update');

Route::delete('/shop/{site}/cart/{key}', [CartController::class, 'destroy'])
    ->name('shop.cart.destroy');


// 買家結帳與訂單成立路由
Route::get('/shop/{site}/checkout', [CheckoutController::class, 'index'])
    ->name('shop.checkout.index');

Route::post('/shop/{site}/checkout', [CheckoutController::class, 'store'])
    ->name('shop.checkout.store');

Route::get('/shop/{site}/orders/{order}/success', [CheckoutController::class, 'success'])
    ->name('shop.checkout.success');
