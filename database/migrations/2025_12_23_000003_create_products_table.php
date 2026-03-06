<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
	{
		Schema::create('products', function (Blueprint $table) {
			$table->id();
			$table->string('sku', 8)->unique()->index()->comment('物料編碼');
			$table->string('name')->comment('品名；等級+重量');
			
			// 1. 分類關聯
			$table->char('category_code', 1);
			$table->foreign('category_code')->references('code')->on('category_definitions');

			// 2. 材質關聯：將原本的 foreignId 改為複合鍵關聯
			// 這裡對應 material_definitions 表的 bb_code (2位) 與 c_code (1位)
			$table->char('bb_code', 2)->comment('材質主碼 bb');
			$table->char('c_code', 1)->default('0')->comment('材質副碼 c');
			
			// 建立複合外鍵，指向材質定義表的 bb_code 和 c_code
			$table->foreign(['bb_code', 'c_code'])
				  ->references(['bb_code', 'c_code'])
				  ->on('material_definitions')
				  ->onUpdate('cascade'); 

			$table->string('unit', 4)->default('ea');
			$table->decimal('price', 12, 2);
			$table->string('remark')->nullable();
			$table->integer('min_stock')->default(0); 			
			$table->boolean('is_active')->default(true);
			$table->timestamps();
		});
	}

    public function down()
    {
        Schema::dropIfExists('products');
    }
};