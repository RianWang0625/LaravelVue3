<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {

            // 商品關聯
            $table->foreignId('product_id')
                ->after('id')
                ->constrained('products')
                ->cascadeOnDelete();

            // 商品規格資料
            $table->string('sku', 100)->nullable();
            $table->json('options');

            // 規格價格與庫存
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);

            // 規格啟用狀態
            $table->boolean('is_active')->default(true);

            // 同一商品的 SKU 不可重複
            $table->unique(['product_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {

            // 移除索引與外鍵
            $table->dropUnique(['product_id', 'sku']);
            $table->dropForeign(['product_id']);

            // 移除規格欄位
            $table->dropColumn([
                'product_id',
                'sku',
                'options',
                'price',
                'stock',
                'is_active',
            ]);
        });
    }
};
