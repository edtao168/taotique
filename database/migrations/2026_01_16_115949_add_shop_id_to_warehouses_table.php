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
		Schema::table('warehouses', function (Blueprint $table) {
			// 增加 shop_id，通常關聯到 shops 表
			// 使用 constrained() 會自動建立外鍵約束，或是單純用 unsignedBigInteger
			$table->unsignedBigInteger('shop_id')->default(1)->after('id');
			
			// 如果你有 shops 表，建議加上這行：
			// $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
		});
	}

	public function down(): void
	{
		Schema::table('warehouses', function (Blueprint $table) {
			$table->dropColumn('shop_id');
		});
	}
};
