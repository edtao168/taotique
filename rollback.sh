#!/bin/bash
# ==============================================================================
# 檔案名稱: rollback.sh
# 說明: OCI Ubuntu 進銷存系統災難復原與回滾腳本 (Apache + PHP 8.2)
# 功能: 還原 deploy.sh、.env、storage/ 圖片與 MySQL 資料庫
# ==============================================================================
set -eo pipefail

PROJECT_DIR="/var/www/html"
BACKUP_DIR="/var/backups/taotique"
TEMP_RESTORE_DIR="/tmp/taotique_restore_temp"

if [ "$EUID" -ne 0 ]; then
  echo "錯誤: 請使用 sudo 執行此腳本！"
  exit 1
fi

LATEST_BACKUP="${1:-$(ls -t ${BACKUP_DIR}/taotique_oci_backup_*.tar.gz 2>/dev/null | head -1 || true)}"

if [ -z "$LATEST_BACKUP" ] || [ ! -f "$LATEST_BACKUP" ]; then
    echo "錯誤: 在 ${BACKUP_DIR} 找不到有效的備份檔案 (.tar.gz)！"
    exit 1
fi

echo "準備使用的備份檔: ${LATEST_BACKUP}"
read -p "確定要回滾覆蓋現有系統與資料庫嗎？(y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "操作已取消。"
    exit 0
fi

# 1. 解壓縮備份包
rm -rf "${TEMP_RESTORE_DIR}" && mkdir -p "${TEMP_RESTORE_DIR}"
tar -xzf "${LATEST_BACKUP}" -C "${TEMP_RESTORE_DIR}"
INNER_FOLDER=$(ls "${TEMP_RESTORE_DIR}")
RESTORE_SRC="${TEMP_RESTORE_DIR}/${INNER_FOLDER}"

# 進入維護模式
[ -f "${PROJECT_DIR}/artisan" ] && php "${PROJECT_DIR}/artisan" down || true

# 2. 還原原始碼與 deploy.sh
if [ -d "${RESTORE_SRC}/app_source" ]; then
    rsync -a "${RESTORE_SRC}/app_source/" "${PROJECT_DIR}/"
fi

# 3. 還原 .env 檔案
[ -f "${RESTORE_SRC}/.env" ] && cp "${RESTORE_SRC}/.env" "${PROJECT_DIR}/.env"

# 4. 還原 storage/ 圖片與檔案
if [ -d "${RESTORE_SRC}/storage" ]; then
    rsync -a "${RESTORE_SRC}/storage/" "${PROJECT_DIR}/storage/"
fi

# 5. 還原 MySQL 資料庫
if [ -f "${RESTORE_SRC}/database.sql" ] && [ -f "${PROJECT_DIR}/.env" ]; then
    DB_DATABASE=$(grep "^DB_DATABASE=" "${PROJECT_DIR}/.env" | cut -d'=' -f2 | tr -d '"\r\'' || true)
    DB_USERNAME=$(grep "^DB_USERNAME=" "${PROJECT_DIR}/.env" | cut -d'=' -f2 | tr -d '"\r\'' || true)
    DB_PASSWORD=$(grep "^DB_PASSWORD=" "${PROJECT_DIR}/.env" | cut -d'=' -f2 | tr -d '"\r\'' || true)
    
    if [ -n "$DB_DATABASE" ]; then
        echo "正在匯入資料庫 [${DB_DATABASE}]..."
        MYSQL_PWD="${DB_PASSWORD}" mysql -u"${DB_USERNAME}" "${DB_DATABASE}" < "${RESTORE_SRC}/database.sql"
    fi
fi

# 6. 重建狀態與權限修復
cd "${PROJECT_DIR}"
php artisan storage:link --force 2>/dev/null || true
php artisan optimize:clear 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true
php artisan up 2>/dev/null || true

sudo chown -R www-data:www-data "${PROJECT_DIR}"
sudo chmod -R 775 storage bootstrap/cache

# 7. 重啟 Apache + PHP 8.2
sudo systemctl restart php8.2-fpm
sudo systemctl restart apache2

rm -rf "${TEMP_RESTORE_DIR}"
echo "✓ 系統回滾與災難復原成功完成！"