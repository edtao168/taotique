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
        Schema::create('inventories', function (Blueprint $table) {
			$table->id();
			// 連結到商品主表
			$table->foreignId('product_id')->constrained()->onDelete('cascade');			
			// 採購與財務資料
			$table->decimal('cost', 12, 2)->comment('進貨成本');		
						
			$table->foreignId('supplier_id')->nullable()->constrained()->onDelete('set null');
			$table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
			$table->string('location_code')->nullable()->comment('儲位編號');
			$table->decimal('quantity', 12, 2)->default(0);			
			
			// 狀態管理 (一對一孤品邏輯)
			$table->string('status')->default('in_stock')->comment('在庫, 售出, 預訂, 移庫');
			
			$table->timestamps();
		});
		
		
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
