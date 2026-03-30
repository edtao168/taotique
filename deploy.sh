#!/bin/bash

echo "🚀 開始部署流程..."

# 1. 進入維護模式 (選配，高頻交易建議開啟)
# php artisan down --render="errors::503"

# 2. 拉取最新代碼
echo "📥 正在從 GitHub 拉取代碼..."
git pull origin main

# 3. 安裝 PHP 依賴 (如果有 composer.json 變動)
# composer install --no-interaction --prefer-dist --optimize-autoloader

# 4. 編譯前端資源 (處理您剛修改的 CSS/JS)
echo "📦 正在編譯前端資源 (Vite)..."
npm install
npm run build

# 5. 執行資料庫遷移 (確保 DECIMAL(16,4) 等結構同步)
php artisan migrate --force

# 6. 清除並優化快取
echo "🧹 清除快取..."
php artisan view:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear

# 7. 確保權限正確 (針對 storage 與 cache)
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 8. 結束維護模式
# php artisan up

echo "✅ 部署完成！請使用手機測試。"