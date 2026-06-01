<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Maintenance\Notary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class NotaryController extends MaintenanceController
{
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
            'tin' => ['required', 'string', 'max:50'],
            'signature' => [
                $isUpdate ? 'nullable' : 'required',
                File::types(['png'])->max(2048),
            ],
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

        $validated = $request->validate($this->rules());

        Notary::create([
            'name' => $validated['name'],
            'commission_number' => $validated['commission_number'],
            'tin' => $validated['tin'],
            'signature_path' => $request->file('signature')->store('notary-seals', 'public'),
            'is_active' => true,
        ]);

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
        $validated = $request->validate($this->rules(true, $notary));

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

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} updated successfully.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $notary = Notary::findOrFail($id);

        if ($notary->signature_path) {
            Storage::disk('public')->delete($notary->signature_path);
        }

        $notary->delete();

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} deleted.");
    }
}
