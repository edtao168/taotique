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
        Schema::create('sales_returns', function (Blueprint $table) {
			$table->id();
			$table->foreignId('shop_id')->default(1)->constrained()->onDelete('restrict');
			$table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
			$table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
			
			$table->string('return_no')->unique();
			
			// 匯總欄位：建議保留，用於快速查詢與報表，不需每次 JOIN SUM
			$table->decimal('items_total_amount', 16, 4)->default(0); // 商品退款小計
			$table->decimal('fees_total_amount', 16, 4)->default(0);  // 總費用小計 (來自費用表)
			$table->decimal('total_refund_amount', 16, 4)->default(0); // 最終退款額 (items - fees)
			
			$table->decimal('exchange_rate', 16, 6)->default(1.000000);
			$table->enum('status', ['pending', 'approved', 'completed', 'cancelled'])->default('pending');
			$table->string('return_reason')->nullable();
			
			$table->foreignId('created_by')->constrained('users');
			$table->foreignId('approved_by')->nullable()->constrained('users');
			$table->timestamp('approved_at')->nullable();
			$table->timestamps();
			
			$table->index(['shop_id', 'status']);
		});

		Schema::create('sales_return_fees', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('shop_id')->default(1)->index();
			$table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
			
			// 費用類型：例如 'shipping', 'platform_fee', 'amazon_admin_fee', 'restocking_fee'
			$table->string('fee_type')->index(); 
			$table->decimal('amount', 16, 4);
			$table->string('note')->nullable(); // 備註
			$table->timestamps();
		});

		Schema::create('sales_return_items', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('shop_id')->default(1)->index();
			$table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
			$table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->cascadeOnDelete();
			$table->foreignId('product_id')->constrained();
			$table->decimal('quantity', 16, 4);
			$table->decimal('unit_price', 16, 4); // 退貨時的單價
			$table->decimal('subtotal', 16, 4);
			$table->boolean('is_restock')->default(true); // 是否回補庫存
			$table->timestamps();
			
			$table->index(['product_id', 'is_restock']);
			$table->index('sale_item_id');
		});
	}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
		Schema::dropIfExists('sales_returns');
    }
};
