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
		Schema::table('product_images', function (Blueprint $table) {
			// 增加首圖標記欄位，預設為 false
			$table->boolean('is_primary')->default(false)->after('path');
		});
	}

	public function down(): void
	{
		Schema::table('product_images', function (Blueprint $table) {
			$table->dropColumn('is_primary');
		});
	}
};
