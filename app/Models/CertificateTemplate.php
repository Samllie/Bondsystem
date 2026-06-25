<?php

namespace App\Models;

use App\Enums\CertificateTemplateType;
use Database\Factories\CertificateTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CertificateTemplate extends Model
{
    /** @use HasFactory<CertificateTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'template_type',
        'template_name',
        'version',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_by',
        'is_active',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'template_type' => CertificateTemplateType::class,
            'version' => 'integer',
            'file_size' => 'integer',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    public static function nextVersion(CertificateTemplateType $type): int
    {
        $maxVersion = static::query()
            ->where('template_type', $type)
            ->max('version');

        return ((int) $maxVersion) + 1;
    }

    public static function activeForType(CertificateTemplateType $type): ?self
    {
        return static::query()
            ->where('template_type', $type)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->first();
    }

    public static function fallbackFilename(CertificateTemplateType $type): string
    {
        return match ($type) {
            CertificateTemplateType::Car => 'Sterling_CAR_Template.docx',
            CertificateTemplateType::CarCertificateEndorsement => 'Sterling_CAR_Endorsement_Template.docx',
            CertificateTemplateType::Bond => 'Sterling_Bond_Template.docx',
        };
    }

    public static function fallbackPath(CertificateTemplateType $type): string
    {
        return resource_path('templates/'.self::fallbackFilename($type));
    }

    /**
     * @return array<string, mixed>
     */
    public static function inUseSummary(CertificateTemplateType $type): array
    {
        $uploaded = self::activeForType($type);

        if ($uploaded !== null) {
            $uploaded->loadMissing('uploader:id,name');
            $absolutePath = $uploaded->absolutePath();

            if (file_exists($absolutePath)) {
                return [
                    'source' => 'uploaded',
                    'id' => $uploaded->id,
                    'template_type' => $type->value,
                    'template_name' => $uploaded->template_name,
                    'version' => $uploaded->version,
                    'original_filename' => $uploaded->original_filename,
                    'file_size' => $uploaded->file_size,
                    'uploaded_by' => $uploaded->uploader?->name,
                    'created_at' => $uploaded->created_at?->toIso8601String(),
                    'is_active' => true,
                    'archived_at' => null,
                    'is_in_use' => true,
                ];
            }
        }

        return self::fallbackTableRowForType($type, isInUse: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function inUseSummaries(): array
    {
        return collect(CertificateTemplateType::cases())
            ->map(fn (CertificateTemplateType $type) => self::inUseSummary($type))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function fallbackTableRow(bool $isInUse = false): array
    {
        return [
            'source' => 'fallback',
            'id' => null,
            'template_type' => null,
            'template_name' => 'Built-in Fallback',
            'version' => null,
            'original_filename' => null,
            'file_size' => null,
            'uploaded_by' => 'System',
            'created_at' => null,
            'is_active' => $isInUse,
            'archived_at' => null,
            'is_in_use' => $isInUse,
            'is_previous' => ! $isInUse,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fallbackTableRowForType(CertificateTemplateType $type, bool $isInUse = false): array
    {
        $filename = self::fallbackFilename($type);
        $path = self::fallbackPath($type);

        return [
            ...self::fallbackTableRow($isInUse),
            'template_type' => $type->value,
            'original_filename' => $filename,
            'file_size' => file_exists($path) ? filesize($path) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function previousSummaries(): array
    {
        $rows = self::query()
            ->with('uploader:id,name')
            ->previous()
            ->latest()
            ->get()
            ->map(fn (CertificateTemplate $template) => $template->toTableRow())
            ->values();

        foreach (CertificateTemplateType::cases() as $type) {
            if (self::activeForType($type) !== null) {
                $rows->push(self::fallbackTableRowForType($type));
            }
        }

        return $rows
            ->sortBy([
                ['template_type', 'asc'],
                ['created_at', 'desc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toTableRow(bool $isInUse = false): array
    {
        $this->loadMissing('uploader:id,name');

        return [
            'source' => 'uploaded',
            'id' => $this->id,
            'template_type' => $this->template_type->value,
            'template_name' => $this->template_name,
            'version' => $this->version,
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'uploaded_by' => $this->uploader?->name,
            'created_at' => $this->created_at->toIso8601String(),
            'is_active' => $this->is_active,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'is_in_use' => $isInUse,
            'is_previous' => ! $this->is_active && $this->archived_at === null,
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePrevious(Builder $query): Builder
    {
        return $query
            ->where('is_active', false)
            ->whereNull('archived_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
