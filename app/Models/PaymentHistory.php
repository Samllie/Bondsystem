<?php

namespace App\Models;

use App\Services\PaymentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentHistory extends Model
{
    protected $fillable = [
        'user_id',
        'bond_request_id',
        'amount',
        'description',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentHistory $paymentHistory): void {
            if (empty($paymentHistory->payment_number)) {
                $paymentHistory->payment_number = PaymentNumberGenerator::generate();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bondRequest(): BelongsTo
    {
        return $this->belongsTo(BondRequest::class);
    }
}
