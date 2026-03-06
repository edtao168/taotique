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
        Schema::create('settings', function (Blueprint $table) {
			$table->string('key')->primary(); // 參數名稱作為主鍵
			$table->json('value')->nullable(); // 使用 JSON 型態儲存值，可支援字串、數字或陣列
			$table->string('group')->index();  // 分組：finance, inventory, system
			$table->string('description')->nullable(); // 備註說明
			$table->timestamps();
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
