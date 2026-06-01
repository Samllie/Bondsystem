<?php

namespace App\Models\Maintenance;

use Database\Factories\SignatoryFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Signatory extends Model
{
    /** @use HasFactory<SignatoryFactory> */
    use HasFactory;

    protected static function newFactory(): SignatoryFactory
    {
        return SignatoryFactory::new();
    }

    protected $fillable = [
        'name',
        'position',
        'tin',
        'signature_path',
        'is_active',
    ];

    protected $appends = [
        'signature_url',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function signatureUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->signature_path
            ? Storage::disk('public')->url($this->signature_path)
            : null);
    }
}
