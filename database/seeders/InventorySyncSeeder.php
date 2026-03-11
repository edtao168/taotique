<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;

class InventorySyncSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 取得新系統手動輸入的倉庫 [名稱 => ID] 對照表
        $warehouseMap = Warehouse::all()->pluck('id', 'name');

        if ($warehouseMap->isEmpty()) {
            $this->command->error("錯誤：尚未在庫別表 (warehouses) 中建立任何資料，請先手動輸入倉庫名稱。");
            return;
        }

        // 2. 取得所有現有產品的 SKU
        $products = Product::all();

        $this->command->info("正在同步 " . $products->count() . " 筆商品的庫存...");

        foreach ($products as $product) {
            // 3. 從舊資料庫抓取該 SKU 的庫存明細
            // 請將 'old_stock_table' 替換為你舊系統真正的庫存表名
            $oldStocks = DB::connection('old_db')->table('stock')
                ->where('ProductID', $product->sku) 
                ->get();

            foreach ($oldStocks as $stock) {
                // 4. 比對倉庫名稱是否一致
                // 請將 'OldWarehouseName' 替換為舊表紀錄倉庫名的欄位
                $wId = ($stock->StoreID === 'WH') ? 2 : 1;

                if ($wId) {
                    Inventory::updateOrCreate(
                        [
                            'product_id'   => $product->id,
                            'warehouse_id' => $wId,                        
                            'quantity' => $stock->qty, // 舊系統庫存數量欄位
                            'updated_at' => now()
                        ]
                    );
                }
            }
        }

        $this->command->info("庫存同步完成！");
    }
}