<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

abstract class MaintenanceController extends Controller
{
    abstract protected function modelClass(): string;

    abstract protected function page(): string;

    abstract protected function routePrefix(): string;

    abstract protected function rules(bool $isUpdate = false, ?Model $record = null): array;

    protected function label(): string
    {
        return class_basename($this->modelClass());
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.view'), 403);

        $records = $this->modelClass()::query()
            ->when($request->string('search')->trim()->toString(), function ($q, $s) {
                $q->where('name', 'like', "%{$s}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Maintenance/Index', [
            'records' => $records,
            'filters' => $request->only('search'),
            'canManage' => $request->user()->hasPermission('maintenance.manage'),
            'routePrefix' => $this->routePrefix(),
            'label' => $this->label(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        return Inertia::render('Maintenance/Form', [
            'record' => null,
            'routePrefix' => $this->routePrefix(),
            'label' => $this->label(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $this->modelClass()::create($request->validate($this->rules()));

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} created successfully.");
    }

    public function edit(Request $request, int $id): Response
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        return Inertia::render('Maintenance/Form', [
            'record' => $this->modelClass()::findOrFail($id),
            'routePrefix' => $this->routePrefix(),
            'label' => $this->label(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $record = $this->modelClass()::findOrFail($id);
        $record->update($request->validate($this->rules(true, $record)));

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} updated successfully.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('maintenance.manage'), 403);

        $this->modelClass()::findOrFail($id)->delete();

        return redirect()->route("{$this->routePrefix()}.index")
            ->with('success', "{$this->label()} deleted.");
    }
}
