<?php

namespace App\Models;

use App\Enums\BondRequestStatus;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BondRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bond_number',
        'bond_type_id',
        'bond_type',
        'principal_id',
        'obligee_id',
        'obligee_name',
        'address_1',
        'address_2',
        'address_3',
        'amount',
        'amount_in_words',
        'project_name',
        'description',
        'expiry_date',
        'request_date',
        'signatory_id',
        'signatory_position',
        'notary_id',
        'doc_no',
        'page_no',
        'book_no',
        'series_year',
        'status',
        'remarks',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expiry_date' => 'date',
            'request_date' => 'date',
            'approved_at' => 'datetime',
            'status' => BondRequestStatus::class,
        ];
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    public function bondTypeMaster(): BelongsTo
    {
        return $this->belongsTo(BondTypeMaster::class, 'bond_type_id');
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(Signatory::class);
    }

    public function notary(): BelongsTo
    {
        return $this->belongsTo(Notary::class);
    }

    /**
     * @return array{id: int, company_name: string}|null
     */
    public function obligeeSummary(): ?array
    {
        if (! $this->obligee_id) {
            return null;
        }

        return [
            'id' => $this->obligee_id,
            'company_name' => $this->obligee_name ?? 'Unknown Obligee',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paymentHistory(): HasOne
    {
        return $this->hasOne(PaymentHistory::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status->color();
    }

    public function getBondTypeLabelAttribute(): string
    {
        return $this->bondTypeMaster?->name ?? $this->bond_type ?? '—';
    }
}
