<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'contact', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
