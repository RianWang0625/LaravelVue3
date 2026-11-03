<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    // 驗證商品是否屬於登入客戶
    private function checkOwnership(Product $product): void
    {
        Site::where('owner_user_id', Auth::id())
            ->findOrFail($product->site_id);
    }

    // 驗證規格組合
    private function validateOptions(array $options): array
    {
        $cleaned = [];

        foreach ($options as $key => $value) {
            if (
                !is_string($key) ||
                trim($key) === '' ||
                mb_strlen($key) > 50 ||
                !is_string($value) ||
                trim($value) === '' ||
                mb_strlen($value) > 100
            ) {
                throw ValidationException::withMessages([
                    'options' => '規格名稱或內容格式不正確',
                ]);
            }

            $cleaned[trim($key)] = trim($value);
        }

        if (count($cleaned) !== count($options)) {
            throw ValidationException::withMessages([
                'options' => '規格名稱不可重複',
            ]);
        }

        ksort($cleaned);

        return $cleaned;
    }

    // 檢查相同商品是否已有相同規格
    private function checkDuplicate(
        Product $product,
        array $options,
        ?int $exceptId = null
    ): void {
        $query = $product->variants();

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        foreach ($query->get() as $variant) {
            $existing = $variant->options ?? [];
            ksort($existing);

            if ($existing === $options) {
                throw ValidationException::withMessages([
                    'options' => '此商品已存在相同的規格組合',
                ]);
            }
        }
    }

    // 新增商品規格
    public function store(Request $request, Product $product)
    {
        $this->checkOwnership($product);

        $validated = $request->validate([
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')
                    ->where('product_id', $product->id),
            ],
            'options' => 'required|array|min:1',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0|max:4294967295',
            'is_active' => 'required|boolean',
        ]);

        $options = $this->validateOptions(
            $validated['options']
        );

        $this->checkDuplicate($product, $options);

        $product->variants()->create([
            'sku' => $validated['sku'] ?: null,
            'options' => $options,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->route('products.index')
            ->with('success', '商品規格新增成功');
    }

    // 修改商品規格
    public function update(
        Request $request,
        Product $product,
        ProductVariant $variant
    ) {
        $this->checkOwnership($product);

        abort_unless(
            $variant->product_id === $product->id,
            404
        );

        $validated = $request->validate([
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')
                    ->where('product_id', $product->id)
                    ->ignore($variant->id),
            ],
            'options' => 'required|array|min:1',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0|max:4294967295',
            'is_active' => 'required|boolean',
        ]);

        $options = $this->validateOptions(
            $validated['options']
        );

        $this->checkDuplicate(
            $product,
            $options,
            $variant->id
        );

        $variant->update([
            'sku' => $validated['sku'] ?: null,
            'options' => $options,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->route('products.index')
            ->with('success', '商品規格修改成功');
    }

    // 刪除商品規格
    public function destroy(
        Product $product,
        ProductVariant $variant
    ) {
        $this->checkOwnership($product);

        abort_unless(
            $variant->product_id === $product->id,
            404
        );

        $variant->delete();

        return redirect()->route('products.index')
            ->with('success', '商品規格刪除成功');
    }
}
