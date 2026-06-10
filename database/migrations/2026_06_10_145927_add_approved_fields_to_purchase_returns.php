<?php
// database/migrations/2026_06_10_000001_add_approved_fields_to_purchase_returns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->comment('審核者ID');
            $table->timestamp('approved_at')->nullable()->comment('審核時間');
            
            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'approved_at']);
        });
    }
};