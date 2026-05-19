<?php
// 檔案路徑：database/migrations/2026_05_18_000000_refactor_purchases_table_structure.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 一元化重構：補齊多店架構、新增採購金流核心，並徹底移除布林冗餘狀態。
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // 1. 補齊 shop_id：多店架構預留維度，初期預設值為 1，置於 id 之後
            // 強制建立 index()，避免高頻交易併發鎖定 (lockForUpdate) 時引發全表鎖定 (Table Lock)
            if (!Schema::hasColumn('purchases', 'shop_id')) {
                $table->unsignedBigInteger('shop_id')->default(1)->after('id')->index();
            }

            // 2. 補齊 payment_method：採購專用付款方式（長度50）
            // 預設為 'china_ap' (大陸廠商應付/月結賒欠)，置於 supplier_id 之後
            // 後續會計日記帳結轉（Journal Entry）將依據此欄位動態定錨貸方科目（如：微信支付、現金、應付帳款）
            if (!Schema::hasColumn('purchases', 'payment_method')) {
                $table->string('payment_method', 50)->default('china_ap')->after('supplier_id');
            }

            // 3. 乾淨移除 is_stocked_in：
            // 嚴格落實以 stocked_in_at（Datetime）是否為 null 作為全系統唯一真理源（Single Source of Truth）
            if (Schema::hasColumn('purchases', 'is_stocked_in')) {
                $table->dropColumn('is_stocked_in');
            }
        });
    }

    /**
     * Reverse the migrations.
     * 完美逆向回滾：還原結構相容性。
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // 1. 回滾補回布林欄位，防退版時前端報錯（置於 stocked_in_at 之後）
            if (!Schema::hasColumn('purchases', 'is_stocked_in')) {
                $table->boolean('is_stocked_in')->default(false)->after('stocked_in_at');
            }

            // 2. 移除採購付款方式與多店欄位
            if (Schema::hasColumn('purchases', 'payment_method')) {
                $table->dropColumn('payment_method');
            }

            if (Schema::hasColumn('purchases', 'shop_id')) {
                $table->dropColumn('shop_id');
            }
        });
    }
};