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
        Schema::table('accounting_rules', function (Blueprint $table) {
            // 費曼註釋：增加啟用開關，確保規則停用後歷史帳目仍可追溯
            $table->boolean('is_active')->default(true)->after('shop_id')->index();
        });

        Schema::table('accounting_rule_lines', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_rules', function (Blueprint $table) { $table->dropColumn('is_active'); });
        Schema::table('accounting_rule_lines', function (Blueprint $table) { $table->dropColumn('is_active'); });
    }
};
