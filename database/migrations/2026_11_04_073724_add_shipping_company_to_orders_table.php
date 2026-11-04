<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 新增物流公司欄位
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_company', 100)
                ->nullable()
                ->after('shipping_method');
        });
    }

    // 移除物流公司欄位
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_company');
        });
    }
};
