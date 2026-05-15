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
            // 角色權限：對應 config/acl.php 中的 key (如: owner, manager, staff)
            $table->string('role')->default('staff')->after('email');
            
            // 多店接口：允許為空（初期一人店可不填，或預設為 1）
            $table->unsignedBigInteger('store_id')->nullable()->after('role');
            
            // 狀態控制：合規系統建議不直接刪除帳號，而是停用
            $table->boolean('is_active')->default(true)->after('store_id');

            // 索引優化
            $table->index(['role', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'store_id', 'is_active']);
        });
    }
};
