<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
			Schema::create('channels', function (Blueprint $table) {
			$table->id();
			$table->string('name'); // e.g., 官方網站, 蝦皮, 臉書直播, 實體門市
			$table->string('type')->index(); // 用於區分邏輯：online, offline, social
			$table->decimal('platform_fee_rate', 5, 4)->default(0); // 預設抽成比例
			$table->boolean('is_active')->default(true);
			$table->timestamps();
		});

		// 初始化基礎資料
		DB::table('channels')->insert([
			[
				'name' => '實體店', 
				'type' => 'offline', 
				'platform_fee_rate' => 0,
				'created_at' => now(), 
				'updated_at' => now()
			],
			[
				'name' => '蝦皮', 
				'type' => 'shopee', 
				'platform_fee_rate' => 0,
				'created_at' => now(), 
				'updated_at' => now()
			],
			[
				'name' => 'Facebook', 
				'type' => 'facebook', 
				'platform_fee_rate' => 0, 
				'created_at' => now(), 
				'updated_at' => now()
			],
		]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
