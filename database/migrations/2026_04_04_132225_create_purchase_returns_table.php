<?php // database/migrations/2026_04_04_000001_create_purchase_returns_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->default(1)->index(); // 規範 5: 多店預留
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained(); // 採購退回需指定從哪個倉庫扣除
            $table->string('return_no')->unique();
            
            // 數值嚴謹性：規範 1
            $table->decimal('items_total_amount', 16, 4)->default(0);
            $table->decimal('fees_total_amount', 16, 4)->default(0);
            $table->decimal('total_return_amount', 16, 4)->default(0);
            
            // 匯率快照：規範 3
            $table->decimal('exchange_rate', 16, 6)->default(1.000000);
            
            $table->enum('status', ['pending', 'approved', 'completed', 'cancelled'])->default('pending');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->default(1)->index();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_price', 16, 4); // 原採購單價 (外幣)
            $table->decimal('subtotal', 16, 4);   // 小計 (外幣)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};