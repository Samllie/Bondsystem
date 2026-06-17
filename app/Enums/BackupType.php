<?php

namespace App\Enums;

enum BackupType: string
{
    case Database = 'database';
    case Files = 'files';
    case Full = 'full';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::Database->value, 'label' => 'Database Only'],
            ['value' => self::Files->value, 'label' => 'Files Only'],
            ['value' => self::Full->value, 'label' => 'Full Backup'],
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Database => 'Database Only',
            self::Files => 'Files Only',
            self::Full => 'Full Backup',
        };
    }
}
