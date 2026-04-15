<?php
// database/migrations/2026_04_14_093431_change_channel_to_integer_on_sales_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
{
    // 再次確保萬一還有殘留字串，用字串比對
    DB::table('sales')->where('channel', 'shopee')->update(['channel' => 1]);
    DB::table('sales')->where('channel', 'facebook')->update(['channel' => 2]);
    DB::table('sales')->where('channel', 'line')->update(['channel' => 3]);

    Schema::table('sales', function (Blueprint $table) {
        // 修改欄位定義為無符號大整數
        $table->unsignedBigInteger('channel')->change();
    });
}

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('channel')->change();
        });
    }
};