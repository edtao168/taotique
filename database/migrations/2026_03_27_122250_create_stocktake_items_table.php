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
        Schema::create('stocktake_items', function (Blueprint $table) {
            $table->id();
			$table->foreignId('stocktake_id')->constrained('stocktakes')->cascadeOnDelete();
			$table->foreignId('product_id')->constrained();
			
			// 數值嚴謹性
			$table->decimal('system_quantity', 16, 4); // 盤點當下的帳面數
			$table->decimal('actual_quantity', 16, 4)->nullable(); // 實點數，NULL 代表漏盤（未點到）
			$table->decimal('cost_price', 16, 4); // 紀錄當下加權平均成本，用於計算盤盈虧金額
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocktake_items');
    }
};
