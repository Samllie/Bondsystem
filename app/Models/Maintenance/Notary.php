<?php

namespace App\Models\Maintenance;

use App\Models\User;
use Database\Factories\NotaryFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Notary extends Model
{
    /** @use HasFactory<NotaryFactory> */
    use HasFactory;

    protected static function newFactory(): NotaryFactory
    {
        return NotaryFactory::new();
    }

    protected $fillable = [
        'user_id',
        'name',
        'commission_number',
        'expiry_date',
        'tin',
        'signature_path',
        'is_active',
    ];

    protected $appends = [
        'signature_url',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected function signatureUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->signature_path
            ? Storage::disk('public')->url($this->signature_path)
            : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
