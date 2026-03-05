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
        Schema::create('inventory_movements', function (Blueprint $table) {
		$table->id();
		$table->foreignId('product_id')->constrained();
		$table->foreignId('warehouse_id')->constrained();
		$table->integer('quantity'); // 異動數量，正數為入庫，負數為出庫
		$table->string('type');      // 類型：'sale', 'purchase', 'transfer', 'stocktake'
		$table->string('reference')->nullable(); // 參考單號 (如銷貨單號)
		$table->string('remark')->nullable();
		$table->foreignId('user_id')->constrained(); // 執行人
		$table->timestamps();
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
