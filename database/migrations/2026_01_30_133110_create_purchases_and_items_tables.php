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
		// 1. 採購單主表
		Schema::create('purchases', function (Blueprint $table) {
			$table->id();
			$table->string('purchase_number')->unique()->comment('採購單號');
			$table->foreignId('supplier_id')->constrained();
			$table->foreignId('user_id')->constrained(); // 採購人員
			
			// 匯率資訊 (整張單據共用)
			$table->string('currency', 3)->default('CNY');
			$table->decimal('exchange_rate', 10, 6)->default(1.000000);
			
			$table->decimal('total_foreign', 16, 4)->default(0)->comment('外幣總計');
			$table->decimal('total_twd', 16, 4)->default(0)->comment('本幣總計');
			
			$table->date('purchased_at');
			$table->text('remark')->nullable();
			$table->timestamps();
		});

		// 2. 採購明細表
		Schema::create('purchase_items', function (Blueprint $table) {
			$table->id();
			$table->foreignId('purchase_id')->constrained()->onDelete('cascade');
			$table->foreignId('product_id')->constrained();
			$table->foreignId('warehouse_id')->constrained(); // 決定入庫到哪個倉庫
			
			$table->decimal('quantity', 12, 2);
			$table->decimal('foreign_price', 16, 4)->comment('外幣單價');
			$table->decimal('cost_twd', 16, 4)->comment('換算後本幣成本單價');
			$table->decimal('subtotal_twd', 16, 4)->comment('小計本幣');
			$table->timestamps();
		});
	}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases_and_items_tables');
    }
};
