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
        Schema::create('accounting_rules', function (Blueprint $table) {
			$table->id();
			$table->string('event_type')->unique(); // e.g., 'sale_cash', 'sale_credit_card', 'purchase_inventory'			
			$table->unsignedBigInteger('shop_id')->default(1);
			$table->timestamps();
		});
		
		Schema::create('accounting_rule_lines', function (Blueprint $table) {
			$table->id();

			$table->foreignId('accounting_rule_id')
				  ->constrained()
				  ->cascadeOnDelete();

			$table->foreignId('account_id')->constrained();

			// 借 or 貸
			$table->enum('entry_type', ['debit', 'credit']);

			// 金額來源（超重要）
			$table->string('amount_source'); 
			// 例如：total_amount, fee, tax, discount

			// 比例（可選）
			$table->decimal('ratio', 8, 4)->default(1);

			$table->unsignedInteger('sort_order')->default(1);
			$table->timestamps();
		});
	}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_rule_lines');
		Schema::dropIfExists('accounting_rules');
    }
};
