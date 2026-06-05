# 記錄開始時間
START_TIME=$(date +%s)

# 進入專案目錄（確保在正確的路徑）
cd /var/www/html || exit 1

# 1. 拉取最新代碼
echo -e "\n${YELLOW}[1/7] 正在從 GitHub 拉取代碼...${NC}"
git pull origin main

if [ $? -ne 0 ]; then
    echo -e "${RED}錯誤：Git pull 失敗${NC}"
    exit 1
fi
echo -e "${GREEN} 代碼更新完成${NC}"

# 2. 安裝 PHP 依賴（正式環境：排除開發套件）
echo -e "\n${YELLOW}[2/7] 安裝/更新 PHP 依賴...${NC}"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

if [ $? -ne 0 ]; then
    echo -e "${RED}錯誤：Composer 安裝失敗${NC}"
    exit 1
fi
echo -e "${GREEN} PHP 依賴處理完成${NC}"

# 3. 編譯前端資源（如果需要）
echo -e "\n${YELLOW}[3/7] 檢查前端資源...${NC}"
if [ -f "package.json" ]; then
    # 檢查 package.json 是否有變更
    if git diff HEAD@{1} HEAD --name-only | grep -q "package.json"; then
        echo -e "${YELLOW}偵測到 package.json 變更，更新前端依賴...${NC}"
        npm ci --no-audit --no-fund --production 2>/dev/null || npm install --no-audit --no-fund --production
    fi
    
    # 檢查是否需要編譯
    if git diff HEAD@{1} HEAD --name-only | grep -q -E "\.(css|js|vue|scss|sass)$|vite\.config|webpack\.mix"; then
        echo -e "${YELLOW}偵測到前端檔案變更，編譯中...${NC}"
        npm run build
        echo -e "${GREEN} 前端編譯完成${NC}"
    else
        echo -e "${GREEN} 前端無變更，跳過編譯${NC}"
    fi
else
    echo -e "${GREEN} 無前端依賴，跳過${NC}"
fi

# 4. 執行資料庫遷移
echo -e "\n${YELLOW}[4/7] 執行資料庫遷移...${NC}"
php artisan migrate --force

if [ $? -ne 0 ]; then
    echo -e "${RED}錯誤：資料庫遷移失敗${NC}"
    exit 1
fi
echo -e "${GREEN} 資料庫遷移完成${NC}"

# 5. 清除快取
echo -e "\n${YELLOW}[5/7] 清除系統快取...${NC}"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo -e "${GREEN} 快取清除/重建完成${NC}"

# 6. 設定權限
echo -e "\n${YELLOW}[6/7] 設定目錄權限...${NC}"
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
echo -e "${GREEN} 權限設定完成${NC}"

# 7. 重啟服務
echo -e "\n${YELLOW}[7/7] 重啟服務...${NC}"
sudo systemctl restart php8.2-fpm
sudo systemctl restart apache2
echo -e "${GREEN} 服務重啟完成${NC}"

# 計算部署時間
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo -e "\n${BLUE}${NC}"
echo -e "${GREEN} 部署成功完成！${NC}"
echo -e "${GREEN} 耗時：${DURATION} 秒${NC}"
echo -e "${BLUE}${NC}"
