<?php

return [
    'disk' => env('BACKUP_DISK', 'backups'),

    'directory' => trim(env('BACKUP_DIRECTORY', 'database-backups'), '/'),

    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),

    'dump_binary' => env('BACKUP_DUMP_BINARY', 'mysqldump'),

    'mysql_binary' => env('BACKUP_MYSQL_BINARY', 'mysql'),

    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 600),

    'retention' => [
        'daily' => (int) env('BACKUP_RETENTION_DAILY', 7),
        'weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 4),
        'monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 6),
    ],
];
