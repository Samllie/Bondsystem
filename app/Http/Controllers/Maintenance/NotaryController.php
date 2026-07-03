<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Maintenance\Notary;
use App\Services\AttorneyProfileSyncService;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class NotaryController extends MaintenanceController
{
    public function __construct(private readonly AttorneyProfileSyncService $attorneyProfileSyncService) {}

    protected function modelClass(): string
    {
        return Notary::class;
    }

    protected function page(): string
    {
        return 'Maintenance/Notaries/Form';
    }

    protected function routePrefix(): string
    {
        return 'maintenance.notaries';
    }

    protected function label(): string
    {
        return 'Notary';
    }

    protected function rules(bool $isUpdate = false, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'commission_number' => ['required', 'string', 'max:100'],
            'tin' => ['required', 'string', 'regex:/^\d{3}-\d{3}-\d{3}-\d{4}$/'],
            'signature' => [
                $isUpdate ? 'nullable' : 'required',
                File::types(['png'])->max(10 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'tin.regex' => 'Enter a valid TIN in the format 000-000-000-0000.',
            'signature.max' => 'The seal image may not be larger than 10 MB.',
        ];
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.view'), 403);

        $records = Notary::query()
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('commission_number', 'like', "%{$search}%")
                        ->orWhere('tin', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Maintenance/Notaries/Index', [
            'records' => $records,
            'filters' => $request->only('search'),
            'canManage' => $request->user()->hasPermission('maintenance.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        return Inertia::render($this->page(), [
            'notary' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $validated = $request->validate($this->rules(), $this->messages());

        $notary = Notary::create([
            'name' => $validated['name'],
            'commission_number' => $validated['commission_number'],
            'tin' => $validated['tin'],
            'signature_path' => $request->file('signature')->store('notary-seals', 'public'),
            'is_active' => true,
        ]);

        AuditLogService::log(
            user: $request->user(),
            action: 'notary_created',
            entityType: AuditLogService::ENTITY_NOTARY,
            entityId: $notary->id,
            newValues: [
                'name' => $notary->name,
                'commission_number' => $notary->commission_number,
            ],
            description: "Notary {$notary->name} created.",
        );

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} created successfully.");
    }

    public function edit(Request $request, int $id): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        return Inertia::render($this->page(), [
            'notary' => Notary::findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $notary = Notary::findOrFail($id);
        $validated = $request->validate($this->rules(true, $notary), $this->messages());

        $oldValues = $notary->only(['name', 'commission_number', 'tin']);

        $data = [
            'name' => $validated['name'],
            'commission_number' => $validated['commission_number'],
            'tin' => $validated['tin'],
        ];

        if ($request->hasFile('signature')) {
            if ($notary->signature_path) {
                Storage::disk('public')->delete($notary->signature_path);
            }

            $data['signature_path'] = $request->file('signature')->store('notary-seals', 'public');
        }

        $notary->update($data);

        $this->attorneyProfileSyncService->syncTinForLinkedAccount($notary, $validated['tin']);
        $this->attorneyProfileSyncService->syncNameForLinkedAccount($notary, $validated['name']);

        AuditLogService::log(
            user: $request->user(),
            action: 'notary_updated',
            entityType: AuditLogService::ENTITY_NOTARY,
            entityId: $notary->id,
            oldValues: $oldValues,
            newValues: $notary->only(['name', 'commission_number', 'tin']),
            description: "Notary {$notary->name} updated.",
        );

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} updated successfully.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $notary = Notary::findOrFail($id);
        $oldValues = $notary->only(['name', 'commission_number', 'tin']);

        if ($notary->signature_path) {
            Storage::disk('public')->delete($notary->signature_path);
        }

        $notary->delete();

        AuditLogService::log(
            user: $request->user(),
            action: 'notary_deleted',
            entityType: AuditLogService::ENTITY_NOTARY,
            entityId: $notary->id,
            oldValues: $oldValues,
            description: "Notary {$oldValues['name']} deleted.",
        );

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} deleted.");
    }
}
