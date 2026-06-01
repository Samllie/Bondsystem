<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Maintenance\Certification;

class CertificationController extends MaintenanceController
{
    protected function modelClass(): string { return Certification::class; }

    protected function page(): string { return 'Maintenance/Certifications'; }

    protected function routePrefix(): string { return 'maintenance.certifications'; }

    protected function label(): string { return 'Certification'; }

    protected function rules(bool $isUpdate = false, $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
