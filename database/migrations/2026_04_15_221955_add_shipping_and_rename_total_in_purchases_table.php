<?php
/**
 * 檔案路徑: database/migrations/2026_04_15_000001_update_purchase_financial_columns.php
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 執行遷移
     * * 變更重點：
     * 1. 新增 shipping_fee (運費) 與 discount (折扣/抹零)
     * 2. 將 total_foreign 更名為 total_amount (原始幣別總額)
     * 3. 確保所有財務欄位符合 DECIMAL(16,4) 規範
     * 4. 調整 exchange_rate 為更精確的 DECIMAL(10,6)
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // 1. 新增業務欄位
            $table->decimal('shipping_fee', 16, 4)->default(0)
                ->after('exchange_rate')
                ->comment('運費 (原始幣別)');
                
            $table->decimal('discount', 16, 4)->default(0)
                ->after('shipping_fee')
                ->comment('折扣與抹零 (原始幣別)');

            // 2. 重新定義欄位名稱 (Laravel 10+ 原生支持，不需 dbal)
            // 將「外幣總計」改為更通用的「單據幣別總計」
            $table->renameColumn('total_foreign', 'total_amount');
        });

        // 3. 調整既有欄位的精度與註解
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('total_amount', 16, 4)->change(); // 確保精度為 (16,4)
            $table->decimal('total_twd', 16, 4)->change();
            $table->decimal('exchange_rate', 10, 6)->default(1.000000)->change();
        });
    }

    /**
     * 回復遷移
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // 還原名稱
            $table->renameColumn('total_amount', 'total_foreign');
            
            // 移除新增欄位
            $table->dropColumn(['shipping_fee', 'discount']);
        });
    }
};