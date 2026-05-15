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
        Schema::table('sale_items', function (Blueprint $table) {
            // 添加 warehouse_id 欄位，放在 product_id 之後
            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->after('product_id')
                  ->constrained('warehouses')
                  ->nullOnDelete();
            
            // 添加索引
            $table->index('warehouse_id');        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
