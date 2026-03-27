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
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
			$table->foreignId('store_id')->default(1)->index(); // 多店預留
			$table->foreignId('warehouse_id')->constrained();
			$table->foreignId('user_id')->constrained(); // 執行盤點的人
			$table->string('status')->default('pending'); // pending, completed
			$table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocktakes');
    }
};
