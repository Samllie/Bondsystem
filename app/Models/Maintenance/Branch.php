<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'branch_code', 'address', 'contact', 'notary_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notary_price' => 'decimal:2',
        ];
    }

    /**
     * @return array<int, array{value: int, label: string, city: string|null, branch_code: string|null}>
     */
    public static function activeOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'branch_code'])
            ->map(fn (self $branch) => [
                'value' => $branch->id,
                'label' => $branch->name,
                'city' => $branch->address,
                'branch_code' => $branch->branch_code ? strtoupper($branch->branch_code) : null,
            ])
            ->values()
            ->all();
    }
}
