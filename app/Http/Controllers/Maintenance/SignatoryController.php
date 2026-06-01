<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Maintenance\Signatory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class SignatoryController extends MaintenanceController
{
    protected function modelClass(): string
    {
        return Signatory::class;
    }

    protected function page(): string
    {
        return 'Maintenance/Signatories/Form';
    }

    protected function routePrefix(): string
    {
        return 'maintenance.signatories';
    }

    protected function label(): string
    {
        return 'Signatory';
    }

    protected function rules(bool $isUpdate = false, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
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

        $records = Signatory::query()
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%")
                        ->orWhere('tin', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Maintenance/Signatories/Index', [
            'records' => $records,
            'filters' => $request->only('search'),
            'canManage' => $request->user()->hasPermission('maintenance.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        return Inertia::render($this->page(), [
            'signatory' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $validated = $request->validate($this->rules());

        Signatory::create([
            'name' => $validated['name'],
            'position' => $validated['position'],
            'tin' => $validated['tin'],
            'signature_path' => $request->file('signature')->store('signatures', 'public'),
            'is_active' => true,
        ]);

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} created successfully.");
    }

    public function edit(Request $request, int $id): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        return Inertia::render($this->page(), [
            'signatory' => Signatory::findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $signatory = Signatory::findOrFail($id);
        $validated = $request->validate($this->rules(true, $signatory));

        $data = [
            'name' => $validated['name'],
            'position' => $validated['position'],
            'tin' => $validated['tin'],
        ];

        if ($request->hasFile('signature')) {
            if ($signatory->signature_path) {
                Storage::disk('public')->delete($signatory->signature_path);
            }

            $data['signature_path'] = $request->file('signature')->store('signatures', 'public');
        }

        $signatory->update($data);

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} updated successfully.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $signatory = Signatory::findOrFail($id);

        if ($signatory->signature_path) {
            Storage::disk('public')->delete($signatory->signature_path);
        }

        $signatory->delete();

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} deleted.");
    }
}
