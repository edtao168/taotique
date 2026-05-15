<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
			$table->id();
			// 這裡建議存蝦皮的訂單編號，方便你以後人工搜尋
			$table->string('invoice_number')->unique()->comment('蝦皮訂單編號/自訂單號');
			
			// 關聯與基本資訊
			$table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			
			// 金額邏輯拆解
			$table->decimal('subtotal', 12, 2)->comment('訂單金額');
			$table->decimal('discount', 12, 2)->default(0)->comment('折扣金額');
			
			// 運費處理 (蝦皮關鍵)
			$table->decimal('shipping_fee_customer', 12, 2)->default(0)->comment('買家支付運費'); 
			$table->decimal('shipping_fee_platform', 12, 2)->default(0)->comment('平台代付運費');
			
			// 手續費
			$table->decimal('platform_fee', 12, 2)->default(0)->comment('平台手續費');
			
			// 自動計算結果 (存入資料庫方便拉報表)
			$table->decimal('customer_total', 12, 2)->comment('顧客付款合計(Subtotal + Shipping_Customer - Discount)');
			$table->decimal('final_net_amount', 12, 2)->comment('最終訂單進帳(Subtotal - Platform_Fee - Shipping_Platform - Discount)');
			
			$table->dateTime('sold_at')->useCurrent()->index();
			$table->timestamps();
		});
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
};