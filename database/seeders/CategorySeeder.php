<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
	{
		$categories = [
			['code' => '1', 'name' => '吊墜', 'remark' => '包含掛墜、項鍊、毛衣鍊、銀墜'],
			['code' => '2', 'name' => '手鏈', 'remark' => '包含手鍊、手鐲、手排'],
			['code' => '3', 'name' => '百貨', 'remark' => '包含擺飾、把玩件、車掛等'],			
			['code' => '5', 'name' => '耳飾', 'remark' => '包含耳環、耳釘'],
			['code' => '6', 'name' => '戒子', 'remark' => null],
			['code' => '7', 'name' => '足飾', 'remark' => '	
包含腳鏈'],			
			['code' => '9', 'name' => '其它', 'remark' => null],
		];

		DB::table('category_definitions')->insert($categories);
	}
}
