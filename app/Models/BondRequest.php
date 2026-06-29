<?php

namespace App\Models;

use App\Enums\BondRequestStatus;
use App\Enums\CertificateTemplateType;
use App\Enums\CertificateType;
use App\Enums\PartyType;
use App\Models\Maintenance\BondTypeMaster;
use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Support\BondFormat;
use App\Support\BondNumberGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'principal_name',
        'obligee_id',
        'obligee_name',
        'address_1',
        'address_2',
        'address_3',
        'amount',
        'amount_in_words',
        'project_name',
        'date_issued',
        'extension_period_start',
        'validity_extension',
        'inception_date',
        'attention',
        'supporting_document_paths',
        'docx_path',
        'certificate_path',
        'certificate_type',
        'party_type',
        'car',
        'authorized_representative',
        'tin',
        'endorsement_number',
        'include_endorsement_number',
        'description',
        'expiry_date',
        'request_date',
        'signatory_id',
        'signatory_position',
        'include_signatory_signature',
        'require_notary',
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
            'request_date' => 'date',
            'date_issued' => 'date',
            'extension_period_start' => 'date',
            'inception_date' => 'date',
            'approved_at' => 'datetime',
            'status' => BondRequestStatus::class,
            'certificate_type' => CertificateType::class,
            'party_type' => PartyType::class,
            'include_endorsement_number' => 'boolean',
            'include_signatory_signature' => 'boolean',
            'require_notary' => 'boolean',
            'supporting_document_paths' => 'array',
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
        if (! filled($this->obligee_name) && ! $this->obligee_id) {
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

    public function certificateTemplateType(): CertificateTemplateType
    {
        return CertificateTemplateType::fromBondRequest($this);
    }

    public function paymentHistory(): HasOne
    {
        return $this->hasOne(PaymentHistory::class);
    }

    public function certificateVersions(): HasMany
    {
        return $this->hasMany(CertificateVersion::class)->orderByDesc('version_number');
    }

    public function currentCertificateVersion(): HasOne
    {
        return $this->hasOne(CertificateVersion::class)->where('is_current', true);
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

    public function getCertificateTypeLabelAttribute(): string
    {
        return $this->certificate_type?->label() ?? '—';
    }

    public function getBondLabelAttribute(): string
    {
        if ($this->certificate_type === CertificateType::CarCertificate) {
            return $this->car ?? '';
        }

        if (filled($this->bond_number) && str_contains((string) $this->bond_number, ' NO. ')) {
            return (string) $this->bond_number;
        }

        $creator = $this->creator;
        if ($creator && ! $creator->relationLoaded('branch')) {
            $creator->load('branch');
        }

        return BondFormat::buildValue(
            $this->bond_type_label,
            $creator ? BondNumberGenerator::branchCodeFor($creator) : null,
            $this->bondTypeMaster?->code ?? $this->bond_number,
        );
    }
}
