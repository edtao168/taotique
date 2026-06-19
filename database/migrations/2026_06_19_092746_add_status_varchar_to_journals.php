<?php
// database/migrations/2026_06_19_000002_add_status_varchar_to_journals.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 新增 VARCHAR 欄位
        Schema::table('journals', function (Blueprint $table) {
            $table->string('status_varchar', 30)->nullable()->after('status');
        });

        // 複製資料
        DB::table('journals')->update([
            'status_varchar' => DB::raw("CASE 
                WHEN status = 'cancelled' THEN 'closed'
                ELSE status 
            END")
        ]);
    }

    public function down()
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropColumn('status_varchar');
        });
    }
};