<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->enum('type', [
                'asset',
                'liability',
                'common',
                'cost',
                'equity',
                'profit'
            ])->change();
        });
    }

    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->enum('type', [
                'asset',
                'liability',
                'equity',
                'revenue',
                'expense'
            ])->change();
        });
    }
};