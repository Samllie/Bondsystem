<?php

namespace App\Models\Maintenance;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'branch_code', 'branch_city', 'address', 'contact', 'notary_price', 'balance', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notary_price' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return array<int, array{value: int, label: string, city: string|null, branch_code: string|null}>
     */
    public static function activeOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'branch_city', 'branch_code'])
            ->map(fn (self $branch) => [
                'value' => $branch->id,
                'label' => $branch->name,
                'city' => $branch->branch_city ?? $branch->address,
                'branch_code' => $branch->branch_code ? strtoupper($branch->branch_code) : null,
            ])
            ->values()
            ->all();
    }
}
