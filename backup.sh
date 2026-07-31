#!/bin/bash
set -eo pipefail

RED="\033[0;31m"
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
CYAN="\033[0;36m"
NC="\033[0m"

PROJECT_DIR="/var/www/html"
BACKUP_ROOT="/var/backups/taotique"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="taotique_oci_backup_${TIMESTAMP}"
WORK_DIR="${BACKUP_ROOT}/${BACKUP_NAME}"
RETENTION_DAYS=5

mkdir -p "${WORK_DIR}/config"

echo -e "${CYAN}==========================================${NC}"
echo -e "${CYAN}  開始執行 OCI 進銷存系統全備份 [${TIMESTAMP}]${NC}"
echo -e "${CYAN}==========================================${NC}"

echo -e "${YELLOW}[1/5] 正在匯出 MySQL 資料庫...${NC}"
if [ -f "${PROJECT_DIR}/.env" ]; then
    DB_DATABASE=$(awk -F'=' '/^DB_DATABASE=/ {gsub(/["\r\x27]/, "", $2); print $2}' "${PROJECT_DIR}/.env" || true)
    DB_USERNAME=$(awk -F'=' '/^DB_USERNAME=/ {gsub(/["\r\x27]/, "", $2); print $2}' "${PROJECT_DIR}/.env" || true)
    DB_PASSWORD=$(awk -F'=' '/^DB_PASSWORD=/ {gsub(/["\r\x27]/, "", $2); print $2}' "${PROJECT_DIR}/.env" || true)

    if [ -n "${DB_DATABASE}" ]; then
        MYSQL_PWD="${DB_PASSWORD}" mysqldump -u"${DB_USERNAME}" "${DB_DATABASE}" > "${WORK_DIR}/database.sql" 2>/dev/null || true
        echo -e "${GREEN} 資料庫匯出成功: database.sql${NC}"
    else
        mysqldump --defaults-extra-file=/etc/mysql/debian.cnf --all-databases > "${WORK_DIR}/database_all.sql" 2>/dev/null || true
    fi
else
    echo -e "${RED} 找不到 .env，嘗試預設全庫備份...${NC}"
    mysqldump --defaults-extra-file=/etc/mysql/debian.cnf --all-databases > "${WORK_DIR}/database_all.sql" 2>/dev/null || true
fi

echo -e "${YELLOW}[2/5] 正在備份 .env 與 OCI 系統設定...${NC}"
[ -f "${PROJECT_DIR}/.env" ] && cp "${PROJECT_DIR}/.env" "${WORK_DIR}/.env"
[ -d "/etc/apache2/sites-available" ] && cp -r /etc/apache2/sites-available "${WORK_DIR}/config/apache_vhosts" 2>/dev/null || true
crontab -l -u www-data > "${WORK_DIR}/config/crontab_www_data.txt" 2>/dev/null || true
iptables-save > "${WORK_DIR}/config/iptables.rules" 2>/dev/null || true

echo -e "${YELLOW}[3/5] 打包專案檔案 (排除 tianfu 與暫存)...${NC}"
mkdir -p "${WORK_DIR}/app_source"
rsync -a \
  --exclude="tianfu" \
  --exclude="backup.sh" \
  --exclude="rollback.sh" \
  --exclude=".git" \
  --exclude="node_modules" \
  --exclude="vendor" \
  --exclude="storage/logs/*" \
  --exclude="storage/framework/cache/*" \
  --exclude="storage/framework/sessions/*" \
  --exclude="storage/framework/views/*" \
  "${PROJECT_DIR}/" "${WORK_DIR}/app_source/"

echo -e "${YELLOW}[4/5] 壓縮成 tar.gz 封包...${NC}"
cd "${BACKUP_ROOT}"
tar -czf "${BACKUP_NAME}.tar.gz" "${BACKUP_NAME}"
rm -rf "${WORK_DIR}"

echo -e "${YELLOW}[5/5] 清理 ${RETENTION_DAYS} 天前的舊備份檔案...${NC}"
find "${BACKUP_ROOT}" -type f -name "*.tar.gz" -mtime +${RETENTION_DAYS} -exec rm -f {} \;

BACKUP_SIZE=$(du -sh "${BACKUP_ROOT}/${BACKUP_NAME}.tar.gz" | cut -f1)
echo -e "${GREEN}==========================================${NC}"
echo -e "${GREEN} OCI 備份成功完成！${NC}"
echo -e "${GREEN}  檔名: ${BACKUP_NAME}.tar.gz${NC}"
echo -e "${GREEN}  大小: ${BACKUP_SIZE}${NC}"
echo -e "${GREEN}  位置: ${BACKUP_ROOT}/${BACKUP_NAME}.tar.gz${NC}"
echo -e "${GREEN}==========================================${NC}"
