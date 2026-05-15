<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
	{
		Schema::table('category_definitions', function (Blueprint $table) {
			$table->softDeletes(); // 增加 deleted_at 欄位
		});
	}

	public function down(): void
	{
		Schema::table('category_definitions', function (Blueprint $table) {
			$table->dropSoftDeletes(); // 回復時刪除該欄位
		});
	}
};
