<?php

use App\Enums\WorkflowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ============================================
        // 1. purchases 表：新增 status 欄位
        // ============================================
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('status', 30)
                ->default(WorkflowStatus::DRAFT->value)
                ->comment('狀態：draft/approved/completed/cancelled')
                ->after('purchase_number');
            
            $table->index('status', 'purchases_status_index');
            $table->index('currency', 'purchases_currency_index');
        });

        // 根據 stocked_in_at 回填 status
        // ✅ 所有 stocked_in_at 有日期的設為 completed
        DB::statement("
            UPDATE purchases 
            SET status = CASE 
                WHEN stocked_in_at IS NOT NULL THEN '" . WorkflowStatus::COMPLETED->value . "'
                ELSE '" . WorkflowStatus::DRAFT->value . "'
            END
        ");

        // ============================================
        // 2. purchases 表：重新命名 total_twd 為 total_base
        // ============================================
        Schema::table('purchases', function (Blueprint $table) {
            $table->renameColumn('total_twd', 'total_base');
        });

        DB::statement("ALTER TABLE purchases MODIFY total_base DECIMAL(16,4) NOT NULL COMMENT '功能幣別總額'");

        // ============================================
        // 3. purchase_items 表：重新命名 twd 欄位為 base
        // ============================================
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->renameColumn('cost_twd', 'cost_base');
            $table->renameColumn('subtotal_twd', 'subtotal_base');
        });

        DB::statement("ALTER TABLE purchase_items MODIFY cost_base DECIMAL(16,4) NOT NULL COMMENT '換算後功能幣別成本單價'");
        DB::statement("ALTER TABLE purchase_items MODIFY subtotal_base DECIMAL(16,4) NOT NULL COMMENT '小計功能幣別'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 復原 purchase_items
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->renameColumn('cost_base', 'cost_twd');
            $table->renameColumn('subtotal_base', 'subtotal_twd');
        });

        // 復原 purchases
        Schema::table('purchases', function (Blueprint $table) {
            $table->renameColumn('total_base', 'total_twd');
            $table->dropIndex('purchases_status_index');
            $table->dropIndex('purchases_currency_index');
            $table->dropColumn('status');
        });
    }
};