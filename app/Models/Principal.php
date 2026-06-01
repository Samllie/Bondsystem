<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Principal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name',
        'address',
        'contact_person',
        'email',
        'phone_number',
    ];

    public function bondRequests(): HasMany
    {
        return $this->hasMany(BondRequest::class);
    }
}
