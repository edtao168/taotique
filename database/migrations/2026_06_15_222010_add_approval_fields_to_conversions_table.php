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
        Schema::table('conversions', function (Blueprint $table) {
            // 新增 status 欄位（在 remark 之後）
            $table->enum('status', ['draft', 'pending', 'approved', 'completed', 'cancelled'])
                  ->default('draft')
                  ->comment('狀態：草稿/待審/已審/已完成/已取消')
                  ->after('conversion_no');
            
            // 新增核准者 ID（在 user_id 之後）
            $table->unsignedBigInteger('approved_by')->nullable()->after('updated_at');
            
            // 新增核准時間
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            
            // 索引
            $table->index('status', 'conversions_status_index');
            $table->index('approved_by', 'conversions_approved_by_index');
            
            // 外鍵約束
            $table->foreign('approved_by', 'conversions_approved_by_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            // 先刪除外鍵
            $table->dropForeign('conversions_approved_by_foreign');
            
            // 刪除索引
            $table->dropIndex('conversions_status_index');
            $table->dropIndex('conversions_approved_by_index');
            
            // 刪除欄位
            $table->dropColumn(['status', 'approved_by', 'approved_at']);
        });
    }
};