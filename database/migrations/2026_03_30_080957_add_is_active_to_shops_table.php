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
		Schema::table('shops', function (Blueprint $table) {
			// 增加 is_active 欄位，預設為 true
			$table->boolean('is_active')->default(true)->after('name'); 
		});
	}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            //
        });
    }
};
