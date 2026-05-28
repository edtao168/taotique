<?php

// database/migrations/2026_05_27_160000_restructure_sales_status_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 執行結構重構
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 1. 新增 status 欄位，預設為待付款 'pending'，加在 id 或 customer_id 後面
            $table->string('status', 30)->default('pending')->after('invoice_number');
            $table->index('status'); // 務必建立索引，未來報表查詢極快
        });

        // 2. 🛡️ 舊數據平滑過渡（Data Migration）
        // 如果原本 is_stocked_out = 1，代表已出庫，數據無縫對齊到 'completed'
        if (Schema::hasColumn('sales', 'is_stocked_out')) {
            DB::table('sales')->where('is_stocked_out', 1)->update(['status' => 'completed']);
            DB::table('sales')->where('is_stocked_out', 0)->update(['status' => 'processing']); // 已下單未出庫的視為處理中

            // 3. 安全刪除舊有 Boolean 欄位
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('is_stocked_out');
            });
        }
    }

    /**
     * 回滾邏輯
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->tinyInteger('is_stocked_out')->default(0)->after('status');
        });

        // 將狀態還原回布林值
        DB::table('sales')->where('status', 'completed')->update(['is_stocked_out' => 1]);

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};