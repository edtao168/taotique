<?php

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
        Schema::table('sales', function (Blueprint $table) {
            // 檢查索引是否存在，不存在才添加
            $hasIndex = collect(DB::select("SHOW INDEX FROM sales"))
                ->pluck('Key_name')
                ->contains('sales_invoice_number_unique');
                
            if (!$hasIndex) {
                $table->unique('invoice_number');
            }
            
            // 其他欄位修改...
            // $table->renameColumn('note', 'notes'); // 如果有
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
        });
    }
};