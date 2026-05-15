<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
	{
		DB::table('suppliers')->insert([
			['name' => '自有庫存', 'created_at' => now()],
			['name' => '七七水晶', 'created_at' => now()],
		]);
	}
}
