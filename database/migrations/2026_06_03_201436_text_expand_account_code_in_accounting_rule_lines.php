<?php
// database/migrations/2026_06_03_201436_text_expand_account_code_in_accounting_rule_lines.php
// [代碼開頭標註位置：database/migrations/2026_06_03_201436_text_expand_account_code_in_accounting_rule_lines.php]
// [費曼註釋：重構欄位變更遷移，先清洗歷史 NULL 資料並宣告為 nullable，徹底消滅 MySQL 1138 報錯]

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 🛡️ 步驟 1：防禦性資料清洗
        // 如果歷史資料中有任何 account_code 為 NULL 的記錄，先幫它塞入安全的預設動態策略字串，防止變更結構時崩潰
        DB::table('accounting_rule_lines')
            ->whereNull('account_code')
            ->update(['account_code' => 'DYNAMIC:sale:payment']);

        // 🛡️ 步驟 2：執行嚴謹的欄位長度擴充
        Schema::table('accounting_rule_lines', function (Blueprint $table) {
            // 加上 ->nullable() 允許未來彈性，並安全擴充至 50 碼，解決與 MySQL NOT NULL 規則的衝突
            $table->string('account_code', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_rule_lines', function (Blueprint $table) {
            $table->string('account_code', 20)->nullable()->change();
        });
    }
};