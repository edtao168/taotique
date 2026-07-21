<?php
// database/migrations/2026_07_21_000001_modify_warehouses_unique_constraint.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            // 移除 name 的 unique 約束
            $table->dropUnique('warehouses_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->unique('name', 'warehouses_name_unique');
        });
    }
};