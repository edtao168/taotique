<?php
// database/migrations/xxxx_xx_xx_xxxxxx_refactor_conversions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            // 1. 解除舊索引並更名 shop_id
            $table->dropIndex('conversions_store_id_index');
            $table->renameColumn('store_id', 'shop_id');
            
            // 2. 增加 header 層級的 warehouse_id (作為預設倉庫)
            $table->foreignId('warehouse_id')->after('shop_id')->constrained('warehouses');
        });

        Schema::table('conversions', function (Blueprint $table) {
            // 3. 建立新索引
            $table->index('shop_id', 'conversions_shop_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
            $table->dropIndex('conversions_shop_id_index');
            $table->renameColumn('shop_id', 'store_id');
            $table->index('store_id', 'conversions_store_id_index');
        });
    }
};