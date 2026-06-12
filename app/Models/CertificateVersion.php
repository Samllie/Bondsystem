<?php

namespace App\Models;

use App\Enums\CertificateType;
use Database\Factories\CertificateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateVersion extends Model
{
    /** @use HasFactory<CertificateVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'bond_request_id',
        'version_number',
        'certificate_type',
        'template_id',
        'docx_path',
        'pdf_path',
        'generated_by',
        'generated_at',
        'is_current',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'certificate_type' => CertificateType::class,
            'generated_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    public function bondRequest(): BelongsTo
    {
        return $this->belongsTo(BondRequest::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    public function getCertificateTypeLabelAttribute(): string
    {
        return $this->certificate_type?->label() ?? '—';
    }

    public function currentPdfPath(): ?string
    {
        return $this->pdf_path ?? $this->docx_path;
    }
}
