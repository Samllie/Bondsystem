<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->integer('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->string('action')->trim()->toString(), fn ($query, $action) => $query->where('action', $action))
            ->when($request->string('entity_type')->trim()->toString(), fn ($query, $entityType) => $query->where('entity_type', $entityType))
            ->when($request->date('date_from'), fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($request->date('date_to'), fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['user_id', 'action', 'entity_type', 'date_from', 'date_to']),
            'userOptions' => User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])
                ->values()
                ->all(),
            'actionOptions' => $this->actionOptions(),
            'entityTypeOptions' => $this->entityTypeOptions(),
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function actionOptions(): array
    {
        $actions = [
            'certificate_generated',
            'certificate_viewed',
            'certificate_downloaded',
            'certificate_version_created',
            'certificate_version_made_current',
            'receipt_uploaded',
            'receipt_viewed',
            'receipt_downloaded',
            'receipt_approved',
            'receipt_rejected',
            'template_uploaded',
            'template_downloaded',
            'template_activated',
            'template_archived',
            'bond_request_created',
            'bond_request_updated',
            'bond_request_submitted',
            'bond_request_approved',
            'bond_request_rejected',
            'signatory_created',
            'signatory_updated',
            'signatory_deleted',
            'notary_created',
            'notary_updated',
            'notary_deleted',
            'user_login',
            'user_logout',
        ];

        return collect($actions)
            ->map(fn (string $action) => [
                'value' => $action,
                'label' => str($action)->replace('_', ' ')->title()->toString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function entityTypeOptions(): array
    {
        $types = [
            AuditLogService::ENTITY_BOND_REQUEST,
            AuditLogService::ENTITY_CERTIFICATE_VERSION,
            AuditLogService::ENTITY_CERTIFICATE_TEMPLATE,
            AuditLogService::ENTITY_RECEIPT,
            AuditLogService::ENTITY_SIGNATORY,
            AuditLogService::ENTITY_NOTARY,
            AuditLogService::ENTITY_USER,
        ];

        return collect($types)
            ->map(fn (string $type) => ['value' => $type, 'label' => $type])
            ->values()
            ->all();
    }
}
