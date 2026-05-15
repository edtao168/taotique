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
        Schema::create('material_definitions', function (Blueprint $table) {
            $table->id();
            $table->char('bb_code', 2); // bb
            $table->char('c_code', 1)->default('0'); // c
            $table->string('name'); // 品名 (如：金曜石)
            $table->string('market_names')->nullable(); // 市場俗名 (如：金運石)
            $table->timestamps();

            // 重要：確保 bb+c 的組合是唯一的，避免編碼重複
            $table->unique(['bb_code', 'c_code']);
            // 建立索引優化搜尋
            $table->index(['bb_code', 'c_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_definitions');
    }
};
