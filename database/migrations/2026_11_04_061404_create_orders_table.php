<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 建立訂單主表
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->restrictOnDelete();

            $table->string('order_number', 40)->unique();

            $table->string('customer_name', 100);
            $table->string('customer_phone', 30);
            $table->string('customer_email', 150);

            $table->string('recipient_name', 100);
            $table->string('recipient_phone', 30);
            $table->text('shipping_address')->nullable();

            $table->string('shipping_method', 50)->default('home_delivery');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('payment_method', 50)->default('pending_selection');
            $table->string('payment_status', 30)->default('pending');
            $table->string('order_status', 30)->default('pending');

            $table->text('customer_note')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('shipped_at')->nullable();

            $table->string('access_token', 64)->unique();

            $table->timestamps();

            $table->index(['site_id', 'order_status']);
            $table->index(['site_id', 'created_at']);
        });
    }

    // 刪除訂單主表
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
