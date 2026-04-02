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
            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->after('customer_id')
                  ->constrained('warehouses')
                  ->nullOnDelete();
            
            // 添加索引以提升查詢效能
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // 移除外鍵約束
            $table->dropForeign(['warehouse_id']);
            
            // 移除欄位
            $table->dropColumn('warehouse_id');
        });
    }
};
