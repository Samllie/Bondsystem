<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Maintenance\Ctc;

class CtcController extends MaintenanceController
{
    protected function modelClass(): string { return Ctc::class; }

    protected function page(): string { return 'Maintenance/CTCs'; }

    protected function routePrefix(): string { return 'maintenance.ctcs'; }

    protected function label(): string { return 'CTC'; }

    protected function rules(bool $isUpdate = false, $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
