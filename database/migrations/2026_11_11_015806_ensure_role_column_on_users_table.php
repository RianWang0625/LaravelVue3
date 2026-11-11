<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // 此 Migration 已執行過，保留檔案以維持歷史紀錄
    // role 欄位統一由 add_role_to_users_table Migration 管理
    public function up(): void
    {
        // 不需要執行任何操作
    }

    // 避免回滾時誤刪第一個 Migration 建立的 role 欄位
    public function down(): void
    {
        // 不需要執行任何操作
    }
};
