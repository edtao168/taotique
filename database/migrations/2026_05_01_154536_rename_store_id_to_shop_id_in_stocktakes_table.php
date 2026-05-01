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
        Schema::table('stocktakes', function (Blueprint $table) {
            // 1. 更名索引（若資料庫支援）或先刪除索引再重新建立
            $table->dropIndex('stocktakes_store_id_index');
            
            // 2. 更名欄位
            $table->renameColumn('store_id', 'shop_id');
            
            // 3. 重新建立索引
            $table->index('shop_id', 'stocktakes_shop_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('stocktakes', function (Blueprint $table) {
            $table->dropIndex('stocktakes_shop_id_index');
            $table->renameColumn('shop_id', 'store_id');
            $table->index('store_id', 'stocktakes_store_id_index');
        });
    }
};
