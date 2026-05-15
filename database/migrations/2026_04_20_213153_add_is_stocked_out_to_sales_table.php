<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
			// 1. 先建立時間戳欄位
			$table->timestamp('stocked_out_at')->nullable()->after('sold_at');

			// 2. 建立生成欄位 (Stored 為物理存儲，查詢效能較佳)
			// 邏輯：只要時間戳不是 NULL，這裡就是 1 (true)
			$table->boolean('is_stocked_out')
				  ->storedAs('CASE WHEN stocked_out_at IS NOT NULL THEN 1 ELSE 0 END');
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('is_stocked_out');
            $table->dropColumn('stocked_out_at');
        });
    }
};