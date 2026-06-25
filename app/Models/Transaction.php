<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Maintenance\Branch;
use App\Services\TransactionNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'branch_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'subject_type',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'type' => TransactionType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction): void {
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = TransactionNumberGenerator::generate();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bondRequest(): BelongsTo
    {
        return $this->belongsTo(BondRequest::class, 'subject_id', 'id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
