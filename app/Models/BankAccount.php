<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'bank_name',
        'account_number',
        'account_name',
        'branch',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->bank_name} — {$this->account_number}";
    }
}
