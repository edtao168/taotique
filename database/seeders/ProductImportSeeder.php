<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\CategoryDefinition;
use App\Models\MaterialDefinition;

class ProductImportSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 從舊資料庫 (old_db) 抓取資料
        $oldProducts = DB::connection('old_db')->table('product')->get();

        $this->command->info("正在從舊資料庫匯入 " . $oldProducts->count() . " 筆商品...");

        foreach ($oldProducts as $old) {
            $sku = $old->ProductID;

            // 處理名稱規格
            $fullName = $old->Name . ($old->Specification ? '；' . $old->Specification : '');

            // 執行匯入
            Product::updateOrCreate(
                ['sku' => $sku], // 搜尋條件：唯一的 SKU
                [
                    'name'          => $fullName,
                    'category_code' => substr($sku, 0, 1), // "1"
                    'bb_code'       => substr($sku, 1, 2), // "01"
                    'c_code'        => substr($sku, 3, 1), // "0"
                    'price'         => $old->Price ?? 0,
                    'remark'        => $old->Remark ?? '',
                    'updated_at'    => now(),
                ]
            );
        }

        $this->command->info("匯入完成！");
    }
}