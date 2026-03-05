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
			// 1. 銷售通路：例如 'shopee', 'store', 'facebook', 'line'
			$table->string('channel')->default('store')->after('user_id');
			
			// 2. 付款方式：例如 'cash', 'transfer', 'shopee_pay', 'line_pay'
			$table->string('payment_method')->default('cash')->after('channel');
			
			// 3. 讓訂單編號可為空 (針對實體店)
			$table->string('invoice_number')->nullable()->change();
			
			// 4. 備註 (紀錄轉帳末五碼或實體店特殊要求)
			$table->text('payment_note')->nullable()->after('payment_method');
			
		});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            //
        });
    }
};
