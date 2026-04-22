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
        Schema::table('purchases', function (Blueprint $table) {
            $table->timestamp('stocked_in_at')->nullable()->after('purchased_at');
			
			$table->boolean('is_stocked_in')
				  ->storedAs('CASE WHEN stocked_in_at IS NOT NULL THEN 1 ELSE 0 END');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('is_stocked_in');
            $table->dropColumn('stocked_in_at');
        });
    }
};
