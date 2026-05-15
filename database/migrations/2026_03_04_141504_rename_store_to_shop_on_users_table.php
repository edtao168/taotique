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
        Schema::table('users', function (Blueprint $table) {
            // 1. 安全更名：將現有的 store_id 數據轉移至 shop_id
            if (Schema::hasColumn('users', 'store_id') && !Schema::hasColumn('users', 'shop_id')) {
                $table->renameColumn('store_id', 'shop_id');
            }

            // 2. 補齊 warehouse_id (如果剛才沒加)
            if (!Schema::hasColumn('users', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('shop_id', 'store_id');
            $table->dropColumn(['warehouse_id', 'is_active']);
        });
    }
};
