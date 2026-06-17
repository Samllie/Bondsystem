<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::Pending->value, 'label' => 'Pending'],
            ['value' => self::Running->value, 'label' => 'Running'],
            ['value' => self::Completed->value, 'label' => 'Completed'],
            ['value' => self::Failed->value, 'label' => 'Failed'],
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
