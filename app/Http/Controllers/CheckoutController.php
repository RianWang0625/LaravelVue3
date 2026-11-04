<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    // 取得商店獨立的購物車 Key
    private function cartKey(Site $site): string
    {
        return 'shop_cart_' . $site->id;
    }

    // 取得商店獨立的結帳 Token Key
    private function tokenKey(Site $site): string
    {
        return 'shop_checkout_token_' . $site->id;
    }

    // 取得商店最近成功建立的訂單 Key
    private function lastOrderKey(Site $site): string
    {
        return 'shop_last_order_' . $site->id;
    }

    // 驗證購物車商品、規格、價格及庫存
    private function getCartItems(Site $site, array $cart, bool $lock = false): array
    {
        if (empty($cart)) {
            throw ValidationException::withMessages([
                'cart' => '購物車沒有商品，請先選購。',
            ]);
        }

        $items = [];
        $subtotalCents = 0;

        foreach ($cart as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $variantId = isset($row['variant_id'])
                ? (int) $row['variant_id']
                : null;
            $quantity = (int) ($row['quantity'] ?? 0);

            $productQuery = Product::where('site_id', $site->id)
                ->where('is_active', true);

            if ($lock) {
                $productQuery->lockForUpdate();
            }

            $product = $productQuery->find($productId);

            if (!$product || $quantity < 1) {
                throw ValidationException::withMessages([
                    'cart' => '購物車包含無法購買的商品，請返回購物車檢查。',
                ]);
            }

            $hasVariants = $product->variants()->exists();
            $variant = null;
            $price = $product->price;
            $stock = (int) $product->stock;

            if ($hasVariants) {
                if ($variantId === null) {
                    throw ValidationException::withMessages([
                        'cart' => '有商品尚未選擇規格。',
                    ]);
                }

                $variantQuery = ProductVariant::where('product_id', $product->id)
                    ->where('is_active', true);

                if ($lock) {
                    $variantQuery->lockForUpdate();
                }

                $variant = $variantQuery->find($variantId);

                if (!$variant) {
                    throw ValidationException::withMessages([
                        'cart' => '商品規格已停用或不存在。',
                    ]);
                }

                $price = $variant->price;
                $stock = (int) $variant->stock;
            } elseif ($variantId !== null) {
                throw ValidationException::withMessages([
                    'cart' => '購物車商品規格不正確。',
                ]);
            }

            if ($quantity > $stock) {
                throw ValidationException::withMessages([
                    'cart' => "{$product->name} 的庫存不足，請返回購物車調整數量。",
                ]);
            }

            $priceCents = (int) round((float) $price * 100);
            $lineCents = $priceCents * $quantity;
            $subtotalCents += $lineCents;

            $items[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_options' => $variant?->options,
                'unit_price' => number_format($priceCents / 100, 2, '.', ''),
                'quantity' => $quantity,
                'subtotal' => number_format($lineCents / 100, 2, '.', ''),
                'stock_model' => $variant ?? $product,
            ];
        }

        return [
            'items' => $items,
            'subtotal_cents' => $subtotalCents,
        ];
    }

    // 顯示結帳頁並產生本次結帳專用 Token
    public function index(Request $request, Site $site)
    {
        $cart = $request->session()->get($this->cartKey($site), []);

        if (empty($cart)) {
            return redirect()->route('shop.cart.index', $site->id);
        }

        $result = $this->getCartItems($site, $cart);

        $checkoutToken = Str::random(64);

        $request->session()->put(
            $this->tokenKey($site),
            $checkoutToken
        );

        return Inertia::render('Shop/Checkout', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
            ],
            'items' => array_map(function ($item) {
                unset($item['stock_model']);

                return $item;
            }, $result['items']),
            'subtotal' => $result['subtotal_cents'] / 100,
            'shippingFee' => 60,
            'total' => ($result['subtotal_cents'] + 6000) / 100,
            'checkoutToken' => $checkoutToken,
        ]);
    }

    // 建立訂單並防止同一 Token 重複下單
    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'checkout_token' => ['required', 'string', 'size:64'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['required', 'email', 'max:150'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $token = $data['checkout_token'];
        $sessionToken = $request->session()->get($this->tokenKey($site));

        if (!$sessionToken || !hash_equals($sessionToken, $token)) {
            throw ValidationException::withMessages([
                'checkout_token' => '結帳頁面已過期，請重新進入結帳頁。',
            ]);
        }

        // 相同 Token 已建立訂單時直接返回原訂單
        $existingOrder = Order::where('checkout_token', $token)
            ->where('site_id', $site->id)
            ->first();

        if ($existingOrder) {
            $request->session()->put(
                $this->lastOrderKey($site),
                $existingOrder->id
            );

            return redirect()->route('shop.checkout.success', [
                'site' => $site->id,
                'order' => $existingOrder->id,
            ]);
        }

        $cartKey = $this->cartKey($site);
        $cart = $request->session()->get($cartKey, []);

        try {
            // 資料庫交易內重新驗證並鎖定庫存
            $order = DB::transaction(function () use ($site, $cart, $data, $token) {
                $result = $this->getCartItems($site, $cart, true);

                $subtotalCents = $result['subtotal_cents'];
                $shippingCents = 6000;

                $order = Order::create([
                    'site_id' => $site->id,
                    'order_number' => 'ORD-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(8)),
                    'checkout_token' => $token,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'],
                    'recipient_name' => $data['recipient_name'],
                    'recipient_phone' => $data['recipient_phone'],
                    'shipping_address' => $data['shipping_address'],
                    'shipping_method' => 'home_delivery',
                    'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
                    'shipping_fee' => '60.00',
                    'discount_amount' => '0.00',
                    'total' => number_format(
                        ($subtotalCents + $shippingCents) / 100,
                        2,
                        '.',
                        ''
                    ),
                    'payment_method' => 'pending_selection',
                    'payment_status' => 'pending',
                    'order_status' => 'pending',
                    'customer_note' => $data['customer_note'] ?? null,
                    'access_token' => Str::random(64),
                ]);

                foreach ($result['items'] as $item) {
                    $stockModel = $item['stock_model'];
                    unset($item['stock_model']);

                    $order->items()->create($item);

                    $stockModel->stock -= $item['quantity'];
                    $stockModel->save();
                }

                return $order;
            }, 3);
        } catch (QueryException $e) {
            // 同一 Token 的並發請求由資料庫唯一限制擋下
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }

            $order = Order::where('checkout_token', $token)
                ->where('site_id', $site->id)
                ->first();

            if (!$order) {
                throw $e;
            }
        }

        // 交易成功後才清空購物車並記錄訂單
        $request->session()->forget($cartKey);

        $request->session()->put(
            $this->lastOrderKey($site),
            $order->id
        );

        return redirect()->route('shop.checkout.success', [
            'site' => $site->id,
            'order' => $order->id,
        ]);
    }

    // 確認同一商店及目前 Session 才能查看訂單
    public function success(Request $request, Site $site, Order $order)
    {
        if (
            $order->site_id !== $site->id ||
            (int) $request->session()->get($this->lastOrderKey($site)) !== $order->id
        ) {
            abort(404);
        }

        $order->load('items');

        return Inertia::render('Shop/OrderSuccess', [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
            ],
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'subtotal' => $order->subtotal,
                'shipping_fee' => $order->shipping_fee,
                'total' => $order->total,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'variant_options' => $item->variant_options,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ]),
            ],
        ]);
    }
}
