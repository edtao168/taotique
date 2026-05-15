<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // 1. 關聯倉庫 (主表預設倉庫)
            $table->foreignId('warehouse_id')->after('supplier_id')->default(1)->constrained();
            
            // 2. 稅務與其他費用 (使用 DECIMAL 16,4)
            $table->decimal('tax', 16, 4)->after('discount')->default(0)->comment('稅金');
            $table->decimal('other_fees', 16, 4)->after('tax')->default(0)->comment('其他規費/雜費');
            
            // 3. 稅率快照 (選填，方便日後稽核)
            $table->decimal('tax_rate', 5, 2)->after('other_fees')->default(0)->comment('當下稅率 %');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['warehouse_id', 'tax', 'other_fees', 'tax_rate']);
        });
    }
};