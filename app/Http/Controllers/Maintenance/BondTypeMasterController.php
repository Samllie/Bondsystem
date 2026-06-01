<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Maintenance\BondTypeMaster;

class BondTypeMasterController extends MaintenanceController
{
    protected function modelClass(): string { return BondTypeMaster::class; }

    protected function page(): string { return 'Maintenance/BondTypes'; }

    protected function routePrefix(): string { return 'maintenance.bond-types'; }

    protected function label(): string { return 'Bond Type'; }

    protected function rules(bool $isUpdate = false, $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
