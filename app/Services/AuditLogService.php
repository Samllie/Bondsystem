<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public const ENTITY_BOND_REQUEST = 'BondRequest';

    public const ENTITY_CERTIFICATE_VERSION = 'CertificateVersion';

    public const ENTITY_CERTIFICATE_TEMPLATE = 'CertificateTemplate';

    public const ENTITY_RECEIPT = 'Receipt';

    public const ENTITY_SIGNATORY = 'Signatory';

    public const ENTITY_NOTARY = 'Notary';

    public const ENTITY_USER = 'User';

    public static function log(
        ?User $user,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
    ): void {
        try {
            AuditLog::query()->create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'route_name' => Request::route()?->getName(),
                'description' => $description,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Audit log write failed: '.$exception->getMessage(), [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
    }
}
