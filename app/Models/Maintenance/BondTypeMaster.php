<?php

namespace App\Models\Maintenance;

use Database\Factories\BondTypeMasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BondTypeMaster extends Model
{
    /** @use HasFactory<BondTypeMasterFactory> */
    use HasFactory;

    protected static function newFactory(): BondTypeMasterFactory
    {
        return BondTypeMasterFactory::new();
    }

    protected $table = 'bond_type_masters';

    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
