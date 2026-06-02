<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'contact', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return array<int, array{value: int, label: string, city: string|null}>
     */
    public static function activeOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'address'])
            ->map(fn (self $branch) => [
                'value' => $branch->id,
                'label' => $branch->name,
                'city' => $branch->address,
            ])
            ->values()
            ->all();
    }
}
