<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderController extends Controller
{
    // 查詢目前商家有權限操作的訂單
    private function ownedOrder(int $orderId): Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->whereHas('site', function ($query) {
                $query->where('owner_user_id', Auth::id());
            })
            ->firstOrFail();
    }

    // 顯示商家所屬網站的訂單列表
    public function index(Request $request)
    {
        $sites = Site::query()
            ->where('owner_user_id', Auth::id())
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        $siteIds = $sites->pluck('id');

        $search = trim((string) $request->query('search', ''));
        $selectedSite = $request->query('site_id');

        $query = Order::query()
            ->whereIn('site_id', $siteIds)
            ->with(['site:id,name'])
            ->withCount('items');

        // 依網站篩選訂單
        if ($selectedSite !== null && $selectedSite !== '') {
            $query->where('site_id', $selectedSite);
        }

        // 搜尋訂單編號、訂購人、收件人及電話
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Orders/Index', [
            'sites' => $sites,
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'site_id' => $selectedSite ?? '',
            ],
        ]);
    }

    // 顯示商家所屬網站的訂單明細
    public function show(Order $order)
    {
        $order = $this->ownedOrder($order->id);

        $order->load(['site:id,name', 'items']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }

    // 將待處理訂單更新為處理中
    public function process(Order $order)
    {
        $this->ownedOrder($order->id);

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->order_status !== 'pending') {
                throw ValidationException::withMessages([
                    'order_status' => '只有待處理訂單可以改為處理中。',
                ]);
            }

            $lockedOrder->update([
                'order_status' => 'processing',
            ]);
        });

        return back()->with('success', '訂單已改為處理中。');
    }


    // 本機模擬付款成功
    public function simulatePayment(Order $order)
    {
        // 正式環境禁止使用
        abort_unless(
            app()->environment(['local', 'testing']),
            404
        );

        // 確認訂單屬於目前登入的商家
        $this->ownedOrder($order->id);

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)
                ->whereHas('site', function ($query) {
                    $query->where('owner_user_id', Auth::id());
                })
                ->lockForUpdate()
                ->firstOrFail();

            // 只允許尚未付款且尚未出貨的訂單
            if (
                !in_array(
                    $lockedOrder->order_status,
                    ['pending', 'processing'],
                    true
                ) ||
                $lockedOrder->payment_status !== 'pending'
            ) {
                throw ValidationException::withMessages([
                    'payment_status' => '只有尚未付款且尚未出貨的訂單可以模擬付款。',
                ]);
            }

            $lockedOrder->update([
                'payment_status' => 'paid',
            ]);
        }, 3);

        return back()->with('success', '測試付款成功，訂單已標記為已付款。');
    }


    // 登記訂單出貨資訊
    public function ship(Request $request, Order $order)
    {
        $this->ownedOrder($order->id);

        $data = $request->validate([
            'shipping_company' => ['required', 'string', 'max:100'],
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($order, $data) {
            $lockedOrder = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->order_status !== 'processing') {
                throw ValidationException::withMessages([
                    'order_status' => '只有處理中的訂單可以登記出貨。',
                ]);
            }

            if ($lockedOrder->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment_status' => '此訂單尚未付款，不能登記出貨。',
                ]);
            }

            $lockedOrder->update([
                'order_status' => 'shipped',
                'shipping_company' => $data['shipping_company'],
                'tracking_number' => $data['tracking_number'],
                'shipped_at' => now(),
            ]);
        });

        return back()->with('success', '訂單已登記出貨。');
    }

    // 將已出貨訂單標記為完成
    public function complete(Order $order)
    {
        $this->ownedOrder($order->id);

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->order_status !== 'shipped' ||
                $lockedOrder->payment_status !== 'paid'
            ) {
                throw ValidationException::withMessages([
                    'order_status' => '只有已付款且已出貨的訂單可以完成。',
                ]);
            }

            $lockedOrder->update([
                'order_status' => 'completed',
            ]);
        });

        return back()->with('success', '訂單已完成。');
    }

    // 取消未付款訂單並恢復商品庫存
    public function cancel(Order $order)
    {
        $this->ownedOrder($order->id);

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array(
                $lockedOrder->order_status,
                ['pending', 'processing'],
                true
            )) {
                throw ValidationException::withMessages([
                    'order_status' => '只有尚未出貨且未取消的訂單可以取消。',
                ]);
            }

            if ($lockedOrder->payment_status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment_status' => '已付款或付款異常的訂單需進入退款流程，不能直接取消。',
                ]);
            }

            $items = $lockedOrder->items()
                ->orderBy('product_id')
                ->orderBy('product_variant_id')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_status' => '訂單沒有商品明細，無法安全恢復庫存。',
                ]);
            }

            // 逐筆鎖定商品並恢復對應庫存
            foreach ($items as $item) {
                $product = Product::whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || $product->site_id !== $lockedOrder->site_id) {
                    throw ValidationException::withMessages([
                        'order_status' => '訂單商品不存在或網站歸屬不正確，需人工處理庫存。',
                    ]);
                }

                if ($item->product_variant_id !== null) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->whereKey($item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$variant) {
                        throw ValidationException::withMessages([
                            'order_status' => '訂單規格已不存在，需人工處理庫存。',
                        ]);
                    }

                    $variant->increment('stock', $item->quantity);
                } else {
                    $product->increment('stock', $item->quantity);
                }
            }

            // 只有庫存恢復成功才將訂單標記為取消
            $lockedOrder->update([
                'order_status' => 'cancelled',
            ]);
        }, 3);

        return back()->with('success', '訂單已取消，庫存已恢復。');
    }
}
