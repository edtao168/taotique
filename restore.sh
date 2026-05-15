#!/bin/bash
# 1. 停止服務
php artisan down

# 2. 從備份目錄解壓縮並恢復資料庫
# 假設使用 spatie 備份
unzip /path/to/backup.zip -d ./temp_restore
mysql -u user -p db_name < ./temp_restore/db-dumps/mysql-db_name.sql

# 3. 恢復檔案權限 (之前撞過的權限問題務必重跑)
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 4. 重啟服務
php artisan up