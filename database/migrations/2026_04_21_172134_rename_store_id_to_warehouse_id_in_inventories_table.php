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
		Schema::table('inventories', function (Blueprint $table) {
			// 使用 renameColumn 是最安全的方式，它會保留索引與資料內容
			$table->renameColumn('store_id', 'shop_id');
		});
	}

	public function down(): void
	{
		Schema::table('inventories', function (Blueprint $table) {
			$table->renameColumn('shop_id', 'store_id');
		});
	}
};
