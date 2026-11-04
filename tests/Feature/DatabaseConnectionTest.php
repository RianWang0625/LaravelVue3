<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    // 確認自動化測試只連接獨立的 MySQL 測試資料庫
    public function test_uses_separate_test_database(): void
    {
        $this->assertSame(
            'mysql',
            DB::getDefaultConnection()
        );

        $this->assertSame(
            'saas_ecommerce_test',
            DB::connection()->getDatabaseName()
        );
    }
}
