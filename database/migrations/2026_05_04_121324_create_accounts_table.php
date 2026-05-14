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
        Schema::create('accounts', function (Blueprint $table) {
			$table->id();

			$table->string('code')->unique();
			$table->string('name');

			$table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);

			$table->foreignId('parent_id')
				->nullable()
				->constrained('accounts')
				->onDelete('restrict');

			$table->unsignedTinyInteger('level')->default(1);

			$table->boolean('is_monetary')->default(false)->index();
			$table->string('currency', 3)->default('TWD');
			$table->string('account_number')->nullable();

			$table->unsignedBigInteger('shop_id')->default(1)->index();

			$table->boolean('is_active')->default(true);

			$table->timestamps();
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
