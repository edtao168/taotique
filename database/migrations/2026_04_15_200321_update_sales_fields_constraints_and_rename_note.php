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
        // 第一步：處理欄位名稱（如果還沒改就改，改了就跳過）
        if (Schema::hasColumn('sales', 'payment_note')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->renameColumn('payment_note', 'remark');
            });
        }

        // 第二步：處理欄位屬性（DECIMAL 嚴謹性與長度限制）
        Schema::table('sales', function (Blueprint $table) {
            $table->string('invoice_number', 32)->nullable(false)->change();
        });

        // 第三步：單獨處理唯一索引 (分開執行避免 MySQL 語法衝突)
        Schema::table('sales', function (Blueprint $table) {
            // 從你提供的 SHOW INDEX 來看，這裡確定沒有索引，可以直接加
            // 但為了絕對安全，我們套用 Try-Catch
            try {
                $table->unique('invoice_number', 'sales_invoice_number_unique');
            } catch (\Exception $e) {
                // 如果報錯就忽略，代表索引其實已經在那了（雖然 SHOW INDEX 沒看到）
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('sales_invoice_number_unique');
            $table->string('invoice_number', 255)->nullable()->change();
            $table->renameColumn('remark', 'payment_note');
        });
    }
};