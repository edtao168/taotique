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
        Schema::create('sale_fees', function (Blueprint $table) {
            $table->id();
			$table->foreignId('shop_id')->default(1)->constrained()->restrictOnDelete()->index();
			$table->foreignId('sale_id')->constrained()->restrictOnDelete();
			$table->string('fee_type')->index();			
			$table->decimal('amount', 16, 4);
			$table->string('note')->nullable();			
            $table->timestamps();			
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_fees');
    }
};
