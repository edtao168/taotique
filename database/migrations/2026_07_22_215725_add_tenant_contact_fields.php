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
        Schema::table('tenants', function (Blueprint $table) {
			$table->string('tax_id', 20)->nullable()->after('name');
			$table->string('phone', 50)->nullable()->after('tax_id');
			$table->string('address')->nullable()->after('phone');
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
