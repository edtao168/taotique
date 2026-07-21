<?php
// database/migrations/2026_07_20_000003_modify_accounts_unique_constraint.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // 1. 刪除舊的 unique 約束
            $table->dropUnique('accounts_code_unique');
            
            // 2. 建立新的複合唯一約束 (tenant_id, code)
            $table->unique(['tenant_id', 'code'], 'accounts_tenant_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique('accounts_tenant_code_unique');
            $table->unique('code', 'accounts_code_unique');
        });
    }
};