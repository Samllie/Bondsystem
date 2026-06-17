<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup retention
    |--------------------------------------------------------------------------
    |
    | Completed backups older than this many days may be removed by
    | php artisan backups:cleanup. Failed backups are never auto-deleted.
    |
    */

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Storage root (relative to storage/app/private)
    |--------------------------------------------------------------------------
    */

    'storage_root' => 'backups',

    /*
    |--------------------------------------------------------------------------
    | MySQL dump binary
    |--------------------------------------------------------------------------
    |
    | When available, mysqldump is preferred for database backups on MySQL.
    | Falls back to a PHP-based exporter when mysqldump is unavailable.
    |
    */

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    /*
    |--------------------------------------------------------------------------
    | Protected file paths
    |--------------------------------------------------------------------------
    |
    | Paths are relative to the Laravel base path unless noted.
    | The backups directory itself is always excluded from file archives.
    |
    */

    'file_paths' => [
        storage_path('app/private/certificates'),
        storage_path('app/private/generated-docx'),
        storage_path('app/private/qr-codes'),
        storage_path('app/private/certificate-templates'),
        storage_path('app/public'),
        resource_path('templates'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Manual restore instructions (shown in the UI)
    |--------------------------------------------------------------------------
    */

    'restore_instructions' => [
        'Place the application in maintenance mode before restoring.',
        'Restore the database from the SQL file using mysql or your DBA tooling.',
        'Extract file archives into the matching storage paths on the server.',
        'Verify file permissions and run php artisan storage:link if needed.',
        'Confirm a sample bond request, deposit receipt, and confirmation PDF open correctly.',
        'Disable maintenance mode only after Sterling IT validates the restore.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Example scheduler entries (not enabled automatically)
    |--------------------------------------------------------------------------
    */

    'schedule_examples' => [
        'daily_database' => '0 1 * * * php artisan backups:create database',
        'weekly_full' => '0 2 * * 0 php artisan backups:create full',
        'monthly_cleanup' => '0 3 1 * * php artisan backups:cleanup',
    ],

];
