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
		Schema::table('accounting_rule_lines', function (Blueprint $table) {
			// 加在 account_id 後面，允許為空，留空時代表由 DYNAMIC 解析
			$table->string('account_code', 20)->nullable()->after('account_id');
		});
	}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_rules_lines', function (Blueprint $table) {
            //
        });
    }
};
