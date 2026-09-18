<?php

$databaseDisks = array_values(array_filter(array_map(
    static fn (string $disk): string => trim($disk),
    explode(',', (string) env('BACKUP_DATABASE_DISKS', '')),
)));

return [
    'enabled' => (bool) env('BACKUP_ENABLED', false),

    'database' => [
        'connection' => env('BACKUP_DB_CONNECTION'),
        'disks' => $databaseDisks,
        'path' => 'database',
        'daily_at' => env('BACKUP_DAILY_AT', '02:00'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'temporary_directory' => env('BACKUP_TEMPORARY_DIRECTORY', storage_path('app/backup-tmp')),
        'process_timeout_seconds' => (int) env('BACKUP_PROCESS_TIMEOUT_SECONDS', 3600),
        'binaries' => [
            'mysql' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
            'pgsql' => env('BACKUP_PG_DUMP_BINARY', 'pg_dump'),
        ],
    ],
];
