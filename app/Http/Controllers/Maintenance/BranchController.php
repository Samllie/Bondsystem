<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Maintenance\Branch;

class BranchController extends MaintenanceController
{
    protected function modelClass(): string { return Branch::class; }

    protected function page(): string { return 'Maintenance/Branches'; }

    protected function routePrefix(): string { return 'maintenance.branches'; }

    protected function label(): string { return 'Branch'; }

    protected function rules(bool $isUpdate = false, $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'contact' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
