<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 移除與 sale_fees 重複的欄位
            $table->dropColumn([
                'discount',
                'shipping_fee_customer',
                'shipping_fee_platform',
                'platform_fee',
                'platform_coupon',
                'order_adjustment',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('discount', 12, 2)->default(0)->comment('折扣金額');
            $table->decimal('shipping_fee_customer', 12, 2)->default(0)->comment('買家支付運費');
            $table->decimal('shipping_fee_platform', 12, 2)->default(0)->comment('平台代付運費');
            $table->decimal('platform_fee', 12, 2)->default(0)->comment('平台手續費');
            $table->string('platform_coupon')->default('0')->comment('平台優惠券');
            $table->string('order_adjustment')->default('0')->comment('訂單帳款調整');
        });
    }
};