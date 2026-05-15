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
        Schema::create('conversion_items', function (Blueprint $table) {
            $table->id();
			$table->foreignId('conversion_id')->constrained()->onDelete('cascade');
			$table->foreignId('product_id')->constrained();
			$table->foreignId('warehouse_id')->constrained(); // 庫存異動核心對應倉庫
			$table->tinyInteger('type')->comment('1:領料/投入, 2:入庫/產出');
			$table->decimal('quantity', 16, 4); // 嚴謹數值
			$table->decimal('cost_snapshot', 16, 4)->default(0); // 歷史成本紀錄
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion_items');
    }
};
