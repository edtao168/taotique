<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
	{
		Schema::table('products', function (Blueprint $table) {
			// 放在 SKU 後面，定義本質
			$table->boolean('is_unique')->default(true)->after('sku')->comment('孤品標記');
			
			// 放在單位後面，方便對比售價
			$table->decimal('cost', 16, 4)->default(0)->after('unit')->comment('成本單價');
		});
	}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
