<?php
// [本地操作] 檔案路徑：config/backup.php

return [
    'backup' => [
        'name' => 'taotique-backup',
        'source' => [
            'files' => [
                'include' => [
                    base_path(),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('storage/app/taotique-backup'), // 避免遞迴備份
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],
            'databases' => [
                'mysql',
            ],
        ],
        'database_dump_compressor' => Spatie\DbDumper\Compressors\GzipCompressor::class,
        'database_dump_file_extension' => 'sql',
        'destination' => [
            'filename_prefix' => '',
            'disks' => [
                'local', // 先存放在伺服器本地，再由你點擊下載
            ],
        ],
        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => null,
        'encryption' => 'default',
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];