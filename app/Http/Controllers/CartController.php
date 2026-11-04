<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CartController extends Controller
{
    // 取得每個商店獨立的購物車 Session Key
    private function cartKey(Site $site): string
    {
        return 'shop_cart_' . $site->id;
    }

    // 取得商品可購買的規格與庫存
    private function resolveProduct(Site $site, int $productId, ?int $variantId): array
    {
        $product = Product::with('variants')
            ->where('site_id', $site->id)
            ->where('is_active', true)
            ->findOrFail($productId);

        $activeVariants = $product->variants
            ->where('is_active', true);

        if ($activeVariants->isNotEmpty()) {
            if ($variantId === null) {
                throw ValidationException::withMessages([
                    'variant_id' => '請先選擇商品規格。',
                ]);
            }

            $variant = $activeVariants->firstWhere('id', $variantId);

            if (!$variant) {
                throw ValidationException::withMessages([
                    'variant_id' => '商品規格不存在或已停用。',
                ]);
            }

            return [
                'product' => $product,
                'variant' => $variant,
                'price' => (float) $variant->price,
                'stock' => (int) $variant->stock,
            ];
        }

        if ($product->variants->isNotEmpty()) {
            throw ValidationException::withMessages([
                'product_id' => '這項商品目前沒有可購買的規格。',
            ]);
        }

        if ($variantId !== null) {
            throw ValidationException::withMessages([
                'variant_id' => '這項商品不需要選擇規格。',
            ]);
        }

        return [
            'product' => $product,
            'variant' => null,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
        ];
    }

    // 建立購物車項目識別碼
    private function itemKey(int $productId, ?int $variantId): string
    {
        return 'p' . $productId . '_v' . ($variantId ?? 0);
    }

    // 顯示購物車並重新檢查價格與庫存
    public function index(Request $request, Site $site)
    {
        $cart = $request->session()->get($this->cartKey($site), []);
        $items = [];
        $total = 0;
        $count = 0;

        foreach ($cart as $key => $row) {
            try {
                $result = $this->resolveProduct(
                    $site,
                    (int) $row['product_id'],
                    isset($row['variant_id']) ? (int) $row['variant_id'] : null
                );
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException | ValidationException $e) {
                continue;
            }

            $product = $result['product'];
            $variant = $result['variant'];
            $quantity = min((int) $row['quantity'], $result['stock']);

            if ($quantity <= 0) {
                continue;
            }

            $price = $result['price'];
            $subtotal = round($price * $quantity, 2);

            $image = $product->image;
            // 取得商品圖片網址
            $imageUrl = $image
                ? (
                    str_starts_with($image, 'http://') ||
                    str_starts_with($image, 'https://') ||
                    str_starts_with($image, '/')
                        ? $image
                        : asset('storage/' . ltrim($image, '/'))
                )
                : null;
            $items[] = [
                'key' => $key,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $product->name,
                'options' => $variant?->options ?? [],
                'image_url' => $imageUrl,
                'price' => $price,
                'quantity' => $quantity,
                'stock' => $result['stock'],
                'subtotal' => $subtotal,
            ];

            $total += $subtotal;
            $count += $quantity;
        }

        return Inertia::render('Shop/Cart', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
            ],
            'items' => $items,
            'total' => round($total, 2),
            'count' => $count,
        ]);
    }

    // 加入購物車
    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $productId = (int) $data['product_id'];
        $variantId = isset($data['variant_id']) ? (int) $data['variant_id'] : null;
        $quantity = (int) $data['quantity'];

        $result = $this->resolveProduct($site, $productId, $variantId);

        $key = $this->itemKey($productId, $variantId);
        $sessionKey = $this->cartKey($site);
        $cart = $request->session()->get($sessionKey, []);

        $existing = (int) ($cart[$key]['quantity'] ?? 0);
        $newQuantity = $existing + $quantity;

        if ($newQuantity > $result['stock']) {
            throw ValidationException::withMessages([
                'quantity' => '加入數量超過可購買庫存。',
            ]);
        }

        $cart[$key] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $newQuantity,
        ];

        $request->session()->put($sessionKey, $cart);

        return redirect()->route('shop.cart.index', $site->id);
    }

    // 修改購物車數量
    public function update(Request $request, Site $site, string $key)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $sessionKey = $this->cartKey($site);
        $cart = $request->session()->get($sessionKey, []);

        if (!isset($cart[$key])) {
            abort(404);
        }

        $row = $cart[$key];

        $result = $this->resolveProduct(
            $site,
            (int) $row['product_id'],
            isset($row['variant_id']) ? (int) $row['variant_id'] : null
        );

        if ((int) $data['quantity'] > $result['stock']) {
            throw ValidationException::withMessages([
                'quantity' => '購買數量超過可購買庫存。',
            ]);
        }

        $cart[$key]['quantity'] = (int) $data['quantity'];
        $request->session()->put($sessionKey, $cart);

        return back();
    }

    // 移除購物車商品
    public function destroy(Request $request, Site $site, string $key)
    {
        $sessionKey = $this->cartKey($site);
        $cart = $request->session()->get($sessionKey, []);

        unset($cart[$key]);

        $request->session()->put($sessionKey, $cart);

        return back();
    }
}
