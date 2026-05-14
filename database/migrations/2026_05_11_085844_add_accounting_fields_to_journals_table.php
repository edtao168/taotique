<?php

// database/migrations/2026_05_11_000001_add_clean_accounting_fields_to_journals.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            
            // 幣別與匯率（系統核心原則 #3）
            $table->string('currency', 3)->default('TWD')->after('shop_id');
            $table->decimal('exchange_rate', 16, 4)->default(1.0000)->after('currency');

            // 狀態（會計法規：draft → posted → closed）
            // [費曼註釋：draft=草稿可刪除；posted=已過帳不可刪除僅可更正；closed=結帳期間鎖定]
            $table->enum('status', ['draft', 'cancelled', 'posted', 'closed'])
                ->default('draft')
                ->after('description');

            // 更正機制（會計法必備）
            $table->foreignId('corrects_journal_id')
                ->nullable()
                ->constrained('journals')
                ->after('reference_id');
            
            $table->text('correction_reason')->nullable()->after('corrects_journal_id');

            // 索引
            $table->index(['shop_id', 'status', 'period'], 'idx_journals_shop_status_period');
            $table->index('corrects_journal_id', 'idx_journals_corrects');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropForeign(['corrects_journal_id']);
            $table->dropIndex('idx_journals_shop_status_period');
            $table->dropIndex('idx_journals_corrects');
            
            $table->dropColumn([
                'currency',
                'exchange_rate',
                'status',
                'corrects_journal_id',
                'correction_reason',
            ]);
        });
    }
};