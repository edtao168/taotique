<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Step 1: 修改 quantity 為 decimal
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
            $table->decimal('price', 12, 4)->change();      // 提高精度
            $table->decimal('subtotal', 15, 4)->change();   // 提高精度
        });
        
        // Step 2: 新增成本欄位
        Schema::table('sale_items', function (Blueprint $table) {
            // 核心成本欄位
            $table->decimal('unit_cost', 12, 4)->default(0)->comment('功能幣別單位成本');
            $table->decimal('original_unit_cost', 12, 4)->nullable()->comment('原始幣別單位成本');
            $table->string('original_currency', 3)->nullable()->comment('原始幣別代碼');
            $table->decimal('exchange_rate', 12, 6)->nullable()->comment('當時匯率');
            
            // ✅ MySQL 8.0+ 支援的 VIRTUAL/STORED column
            $table->decimal('total_cost', 15, 4)->stored()->virtualAs('unit_cost * quantity')->comment('總成本（自動計算）');
            
            // 或者用 VIRTUAL（不佔空間，每次都算）
            // $table->decimal('total_cost', 15, 4)->virtualAs('unit_cost * quantity')->comment('總成本（自動計算）');
            
            // 索引
            $table->index('original_currency');
            $table->index('total_cost');  // 如果常用來查詢，加索引
        });
    }

    public function down()
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->decimal('price', 12, 2)->change();
            $table->decimal('subtotal', 15, 2)->change();
            
            $table->dropColumn([
                'unit_cost',
                'original_unit_cost', 
                'original_currency',
                'exchange_rate',
                'total_cost',  // 記得 drop
            ]);
        });
    }
};