<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('sales', function (Blueprint $table) {
        // 使用 decimal 確保資料庫精確度，15 位數，2 位小數
        $table->decimal('order_adjustment', 15, 2)->default(0.00)->comment('訂單帳款調整');
        
        if (!Schema::hasColumn('sales', 'platform_coupon')) {
            $table->decimal('platform_coupon', 15, 2)->default(0.00)->comment('平台優惠券');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['order_adjustment', 'platform_coupon']);
        });
    }
};
