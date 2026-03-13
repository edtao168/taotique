<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            // 1. 增加 store_id
            if (!Schema::hasColumn('inventory_movements', 'store_id')) {
                $table->unsignedBigInteger('store_id')->default(1)->after('id')->index();
            }

            // 2. 增加 cost_snapshot (命名建議: cost_snapshot)
            if (!Schema::hasColumn('inventory_movements', 'cost_snapshot')) {
                $table->decimal('cost_snapshot', 16, 4)->after('quantity')->default(0);
            }

            // 3. 修改數量為 Decimal
            // 注意：某些資料庫驅動在使用 change() 前需要確保 doctrine/dbal 已安裝
            $table->decimal('quantity', 16, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            // 回滾時的操作
            $table->integer('quantity')->change();
            $table->dropColumn(['store_id', 'cost_snapshot']);
        });
    }
};