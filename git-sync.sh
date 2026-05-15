#!/bin/bash
echo "=== Git 同步 ==="
git add .
git commit -m "更新: $(date '+%Y-%m-%d %H:%M')"
git pull --rebase origin main
git push origin main
echo "完成！"