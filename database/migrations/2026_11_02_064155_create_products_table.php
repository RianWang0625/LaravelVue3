<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // 所屬網站
            $table->foreignId('site_id')
                ->constrained()
                ->cascadeOnDelete();

            // 商品基本資料
            $table->string('name');
            $table->text('description')->nullable();

            // 商品價格與庫存
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);

            // 商品圖片
            $table->string('image')->nullable();

            // 是否上架
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
