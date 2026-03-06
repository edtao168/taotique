<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // 如果已經有軟刪除欄位，先檢查
            if (!Schema::hasColumn('partners', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};