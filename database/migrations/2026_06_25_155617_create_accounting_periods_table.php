// database/migrations/2026_06_25_xxxxxx_create_accounting_periods_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
		Schema::create('accounting_periods', function (Blueprint $table) {
			$table->id();
			
			// 會計期間，格式：Y-m
			$table->string('period', 7)->unique();
			
			// 關帳紀錄
			$table->timestamp('closed_at')->nullable();
			$table->foreignId('closed_by')
				->nullable()
				->constrained('users')
				->nullOnDelete();
			
			// 重開紀錄（保留稽核軌跡）
			$table->timestamp('reopened_at')->nullable();
			$table->foreignId('reopened_by')
				->nullable()
				->constrained('users')
				->nullOnDelete();
			
			// 重開次數（方便追蹤）
			$table->unsignedInteger('reopen_count')->default(0);
			
			// 備註
			$table->string('note')->nullable();
			
			// 索引
			$table->index('period');
			$table->index('closed_at');
			
			$table->timestamps();
		});
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};