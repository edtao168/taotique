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
        Schema::create('journals', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('shop_id')->default(1)->index();
			$table->date('entry_date');       // 記帳日期
			$table->string('description');    // 摘要（例如：採購單 PO20240428 入庫）
			
			// 多型關聯，連結到 Sale 或 Purchase
			$table->nullableMorphs('reference'); 
			
			$table->string('created_by')->nullable();
			$table->timestamps();
		});

		Schema::create('journal_items', function (Blueprint $table) {
			$table->id();
			$table->foreignId('journal_id')->constrained()->onDelete('cascade');
			$table->foreignId('account_id')->constrained();
			
			// --- 幣別擴充 ---
			// 原始幣別金额 (例如：1000.0000 CNY)
			$table->string('currency', 3)->default('TWD'); // CNY, TWD
			$table->decimal('debit_currency', 16, 4)->default(0);
			$table->decimal('credit_currency', 16, 4)->default(0);
			
			// 換算為本位幣後的金額 (例如：4500.0000 TWD)
			// 報表與試算表平衡以此欄位為準
			$table->decimal('debit', 16, 4)->default(0);
			$table->decimal('credit', 16, 4)->default(0);
			
			// 記錄當下的匯率快照
			$table->decimal('exchange_rate', 16, 6)->default(1.000000);
			
			$table->unsignedBigInteger('shop_id')->default(1)->index();
			$table->timestamps();
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_items');
		Schema::dropIfExists('journals');
    }
};
