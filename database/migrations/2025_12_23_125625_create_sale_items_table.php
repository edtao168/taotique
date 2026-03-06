<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();            
            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_items');
    }
};