<?php
// database/migrations/2026_06_19_092758_switch_journal_status_to_varchar.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // ===== 1. 忽略索引錯誤，直接刪除欄位 =====
        // 先嘗試刪除可能的索引（忽略錯誤）
        try {
            DB::statement('ALTER TABLE journals DROP INDEX journals_status_index');
        } catch (\Exception $e) {
            // 索引不存在，繼續
        }
        
        try {
            DB::statement('ALTER TABLE journals DROP INDEX status');
        } catch (\Exception $e) {
            // 索引不存在，繼續
        }

        // ===== 2. 刪除舊 ENUM 欄位 =====
        Schema::table('journals', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // ===== 3. 重新命名新欄位 =====
        Schema::table('journals', function (Blueprint $table) {
            $table->renameColumn('status_varchar', 'status');
        });

        // ===== 4. 設定屬性 =====
        Schema::table('journals', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });

        // ===== 5. 建立索引 =====
        Schema::table('journals', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down()
    {
        // ===== 回滾 =====
        try {
            DB::statement('ALTER TABLE journals DROP INDEX journals_status_index');
        } catch (\Exception $e) {}
        
        try {
            DB::statement('ALTER TABLE journals DROP INDEX status');
        } catch (\Exception $e) {}

        // 新增暫存欄位
        Schema::table('journals', function (Blueprint $table) {
            $table->string('status_enum_temp', 30)->nullable()->after('status');
        });

        DB::table('journals')->update([
            'status_enum_temp' => DB::raw("CASE 
                WHEN status = 'closed' THEN 'cancelled'
                ELSE status 
            END")
        ]);

        Schema::table('journals', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->enum('status', ['draft', 'cancelled', 'posted', 'closed'])
                ->default('draft');
        });

        DB::table('journals')->update([
            'status' => DB::raw('status_enum_temp')
        ]);

        Schema::table('journals', function (Blueprint $table) {
            $table->dropColumn('status_enum_temp');
        });
    }
};