# OCI 災難復原與備份說明文件

本專案包含針對 OCI (Oracle Cloud Infrastructure) Ubuntu 生產環境開發的自動化營運工具。

## 📁 工具腳本說明

- `deploy.sh`: OCI 伺服器部署腳本 (包含拉取代碼、Composer、前端檢查、Migration、權限修正與重啟 Apache/PHP 8.2-FPM)。
- `backup.sh`: 全自動備份腳本 (自動匯出 MySQL、備份 `.env`、`storage/` 圖片、`deploy.sh` 與設定檔)。
- `rollback.sh`: 災難復原腳本 (自動解壓備份包並復原資料庫、環境變數與原始碼)。

## 🚀 OCI 伺服器備份使用方式

### 1. 手動執行備份
```bash
sudo bash backup.sh

# 編輯自動備份指令sudo crontab -e

#任何時候想檢查時，你可以執行這兩行指令來確認自動備份的執行狀況：
	## 查看進銷存備份日誌cat /var/log/taotique_backup.log
	## 查看記帳系統備份日誌cat /var/log/tianfu_backup.log
如果日誌最後出現 Backup completed successfully 之類的成功提示，且備份目錄 /var/backups/taotique 與 /var/backups/tianfu 都有產生新的 .tar.gz 壓縮檔，就代表整個災難復原（DR）備份機制已經完全穩定運作了！

# 備份檔將自動儲存於 /var/backups/tianfu/， /var/backups/taotique/
	##OCI： ls /var/backups/tianfu/
		本地：ssh -i "C:\laragon\www\keys\ssh-key-2026-03-07.key" ubuntu@158.101.10.167 "ls -l /var/backups/tianfu/"
	##OCI： ls  /var/backups/taotique/
		本地：ssh -i "C:\laragon\www\keys\ssh-key-2026-03-07.key" ubuntu@158.101.10.167 "ls -l /var/backups/taotique/"

# 備份複製到本地，
	## 先查找OCI備份文件目錄
		### ssh -i "C:\laragon\www\keys\ssh-key-2026-03-07.key" ubuntu@158.101.10.167
		### OCI cd /var/www/html
		ls /var/backups/taotique/ *.tar.gz
		exit
	## 下載到本地（本地命令）
		scp -i "C:\laragon\www\keys\ssh-key-2026-03-07.key" ubuntu@158.101.10.167:/var/backups/tianfu/*.tar.gz D:\Users\Administrator\Downloads
		scp -i "C:\laragon\www\keys\ssh-key-2026-03-07.key" ubuntu@158.101.10.167:/var/backups/taotique/*.tar.gz D:\Users\Administrator\Downloads
		（指定日期）scp -i "C:\laragon\www\keys\ssh-key-2026-03-07.key" ubuntu@158.101.10.167:/var/backups/tianfu/*0812*.tar.gz D:\Users\Administrator\Downloads
		（指定日期）scp -i "C:\laragon\www\keys\ssh-key-2026-03-07.key" ubuntu@158.101.10.167:/var/backups/taotique/*0812*.tar.gz D:\Users\Administrator\Downloads
