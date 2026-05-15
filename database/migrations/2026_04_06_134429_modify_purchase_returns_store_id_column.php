<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->renameColumn('store_id', 'shop_id');
        });
        
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->renameColumn('store_id', 'shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->renameColumn('shop_id', 'store_id');
        });
        
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->renameColumn('shop_id', 'store_id');
        });
    }
};