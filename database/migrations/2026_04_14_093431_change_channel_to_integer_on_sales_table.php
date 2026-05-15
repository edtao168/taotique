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
        Schema::table('sales', function (Blueprint $table) {
            // 先移除外鍵約束（如果有）
            // $table->dropForeign(['channel']);
            
            // 修改 channel 為 unsignedBigInteger
            $table->unsignedBigInteger('channel')->change();
            
            // 加回外鍵約束（如果需要關聯 shops 表）
            // $table->foreign('channel')->references('id')->on('shops');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 還原為 string
            $table->string('channel')->change();
        });
    }
};