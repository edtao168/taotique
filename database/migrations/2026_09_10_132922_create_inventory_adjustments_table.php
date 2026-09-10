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
        // database/migrations/xxxx_xx_xx_create_inventory_adjustments_table.php

		Schema::create('inventory_adjustments', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('shop_id')->default(1);
			$table->unsignedBigInteger('warehouse_id');
			$table->unsignedBigInteger('user_id');
			$table->string('adjustment_no')->unique(); // 單號：ADJ-2026091001
			$table->string('type');                    // scrap, gift, sample, miscellaneous_out, etc.
			$table->string('status')->default('completed'); // 可預留 draft, approved, completed
			$table->string('remark')->nullable();
			$table->timestamp('adjusted_at');
			$table->timestamps();

			$table->foreign('warehouse_id')->references('id')->on('warehouses');
			$table->foreign('user_id')->references('id')->on('users');
		});

		Schema::create('inventory_adjustment_items', function (Blueprint $table) {
			$table->id();
			$table->foreignId('inventory_adjustment_id')->constrained()->cascadeOnDelete();
			$table->foreignId('product_id')->constrained();
			$table->decimal('quantity', 16, 4);   // 調整數量 (正數增加，負數減少)
			$table->decimal('unit_cost', 16, 4);  // 帶入快照成本
			$table->decimal('total_amount', 16, 4);// 總金額 (abs(qty) * unit_cost)
			$table->timestamps();
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
		Schema::dropIfExists('inventory_adjustment_items');
    }
};
