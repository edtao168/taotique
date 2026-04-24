<?php
// 檔案路徑：database/migrations/xxxx_xx_xx_xxxxxx_rename_store_id_to_shop_id_in_inventory_movements.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            // 先解除原本的 index
            $table->dropIndex('inventory_movements_store_id_index');
            
            // 更名欄位
            $table->renameColumn('store_id', 'shop_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            // 重新建立索引，名稱改為 shop_id_index
            $table->index('shop_id', 'inventory_movements_shop_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('inventory_movements_shop_id_index');
            $table->renameColumn('shop_id', 'store_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index('store_id', 'inventory_movements_store_id_index');
        });
    }
};