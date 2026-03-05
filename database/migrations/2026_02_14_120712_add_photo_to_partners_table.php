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
		Schema::table('partners', function (Blueprint $table) {
			$table->string('photo_path')->nullable()->after('name'); // 存放照片路徑
		});
	}

	public function down(): void
	{
		Schema::table('partners', function (Blueprint $table) {
			$table->dropColumn('photo_path');
		});
	}
};
