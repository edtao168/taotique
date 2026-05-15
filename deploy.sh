#!/bin/bash

echo " 開始部署流程..."

# 拉取最新代碼
echo " 正在從 GitHub 拉取代碼..."
git pull origin main

# 安裝 PHP 依賴
if git diff HEAD@{1} HEAD --name-only | grep -q "composer.json"; then
    echo " 安裝 PHP 依賴..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 編譯前端資源
if git diff HEAD@{1} HEAD --name-only | grep -q -E "\.(css|js|vue|scss)$"; then
    echo " 編譯前端資源..."
    npm install --no-audit --no-fund
    npm run build
fi

# 執行資料庫遷移
echo " 執行資料庫遷移..."
sudo php artisan migrate --force

# 清除快取
echo " 清除快取..."
sudo php artisan optimize:clear

# 重啟服務
echo " 重啟服務..."
sudo systemctl restart php8.2-fpm
sudo systemctl restart apache2

echo " 部署完成！"
