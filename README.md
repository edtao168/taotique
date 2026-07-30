# OCI 災難復原與備份說明文件

本專案包含針對 OCI (Oracle Cloud Infrastructure) Ubuntu 生產環境開發的自動化營運工具。

## 📁 工具腳本說明

- `deploy.sh`: OCI 伺服器部署腳本 (包含拉取代碼、Composer、前端檢查、Migration、權限修正與重啟 Apache/PHP 8.2-FPM)。
- `backup.sh`: 全自動備份腳本 (自動匯出 MySQL、備份 `.env`、`storage/` 圖片、`deploy.sh` 與設定檔)。
- `rollback.sh`: 災難復原腳本 (自動解壓備份包並復原資料庫、環境變數與原始碼)。

## 🚀 OCI 伺服器備份使用方式

### 1. 手動執行備份（通常不需要，已設定自動執行）
```bash
sudo bash backup.sh

#任何時候想檢查時，你可以執行這兩行指令來確認自動備份的執行狀況：

## 查看進銷存備份日誌cat /var/log/taotique_backup.log
## 查看記帳系統備份日誌cat /var/log/tianfu_backup.log

如果日誌最後出現 Backup completed successfully 之類的成功提示，且備份目錄 /var/backups/taotique 與 /var/backups/tianfu 都有產生新的 .tar.gz 壓縮檔，就代表整個災難復原（DR）備份機制已經完全穩定運作了！