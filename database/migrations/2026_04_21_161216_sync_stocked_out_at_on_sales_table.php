<?php
// database/migrations/xxxx_xx_xx_sync_stocked_out_at_on_sales_table.php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 使用 RAW SQL 效能最高且最嚴謹
        DB::table('sales')
            ->whereNull('stocked_out_at')
            ->update([
                'stocked_out_at' => DB::raw('sold_at')
            ]);
    }

    public function down(): void
    {
        // 通常遷移回滾不需要清除此數據，除非業務邏輯有特殊需求
    }
};