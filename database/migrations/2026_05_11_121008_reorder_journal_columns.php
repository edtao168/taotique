<?php

// database/migrations/2026_05_11_000003_reorder_journal_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [費曼註釋：OCI 環境下必須完全自動化，禁止任何手動步驟]

        // 步驟 1：檢查外鍵是否存在，存在則移除
        $foreignKeyExists = DB::selectOne("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'journals'
            AND CONSTRAINT_NAME = 'journals_corrects_journal_id_foreign'
        ");

        if ($foreignKeyExists) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropForeign('journals_corrects_journal_id_foreign');
            });
        }

        // 步驟 2：檢查欄位是否存在，存在則移除
        $columns = Schema::getColumnListing('journals');
        
        $columnsToDrop = array_intersect(['corrects_journal_id', 'correction_reason', 'updated_by'], $columns);
        
        if (!empty($columnsToDrop)) {
            Schema::table('journals', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        // 步驟 3：重建欄位（正確順序）
        Schema::table('journals', function (Blueprint $table) {
            if (!Schema::hasColumn('journals', 'corrects_journal_id')) {
                $table->foreignId('corrects_journal_id')
                    ->nullable()
                    ->after('reference_id');
            }
            
            if (!Schema::hasColumn('journals', 'correction_reason')) {
                $table->text('correction_reason')
                    ->nullable()
                    ->after('corrects_journal_id');
            }

            if (!Schema::hasColumn('journals', 'updated_by')) {
                $table->string('updated_by', 255)
                    ->nullable()
                    ->after('created_by');
            }
        });

        // 步驟 4：重建外鍵（如果不存在）
        $foreignKeyExistsAfter = DB::selectOne("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'journals'
            AND CONSTRAINT_NAME = 'journals_corrects_journal_id_foreign'
        ");

        if (!$foreignKeyExistsAfter) {
            Schema::table('journals', function (Blueprint $table) {
                $table->foreign('corrects_journal_id')
                    ->references('id')
                    ->on('journals');
            });
        }
    }

    public function down(): void
    {
        // [費曼註釋：down() 同樣必須自給自足，確保可回滾]

        $foreignKeyExists = DB::selectOne("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'journals'
            AND CONSTRAINT_NAME = 'journals_corrects_journal_id_foreign'
        ");

        if ($foreignKeyExists) {
            Schema::table('journals', function (Blueprint $table) {
                $table->dropForeign('journals_corrects_journal_id_foreign');
            });
        }

        $columns = Schema::getColumnListing('journals');
        $columnsToDrop = array_intersect(['corrects_journal_id', 'correction_reason', 'updated_by'], $columns);

        if (!empty($columnsToDrop)) {
            Schema::table('journals', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        // 還原為原來的順序
        Schema::table('journals', function (Blueprint $table) {
            if (!Schema::hasColumn('journals', 'corrects_journal_id')) {
                $table->foreignId('corrects_journal_id')
                    ->nullable()
                    ->after('updated_at');
            }
            
            if (!Schema::hasColumn('journals', 'correction_reason')) {
                $table->text('correction_reason')
                    ->nullable()
                    ->after('corrects_journal_id');
            }

            if (!Schema::hasColumn('journals', 'updated_by')) {
                $table->string('updated_by', 255)
                    ->nullable()
                    ->after('created_by');
            }
        });

        $foreignKeyExistsAfter = DB::selectOne("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'journals'
            AND CONSTRAINT_NAME = 'journals_corrects_journal_id_foreign'
        ");

        if (!$foreignKeyExistsAfter) {
            Schema::table('journals', function (Blueprint $table) {
                $table->foreign('corrects_journal_id')
                    ->references('id')
                    ->on('journals');
            });
        }
    }
};