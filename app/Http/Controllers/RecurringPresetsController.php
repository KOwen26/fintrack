<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringPresetRequest;
use App\Http\Requests\UpdateRecurringPresetRequest;
use App\Models\TransactionRecurringPreset;
use App\Services\RecurringPresetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringPresetsController extends Controller
{
    public function __construct(private readonly RecurringPresetService $recurringPresetService) {}

    public function index(Request $request): Response
    {
        return Inertia::render('recurring-presets/index', [
            'presets' => $this->recurringPresetService->getUserPresets($request->user()),
            'accounts' => $this->recurringPresetService->getUserAccounts($request->user()),
            'categories' => $this->recurringPresetService->getUserCategories($request->user()),
        ]);
    }

    public function store(StoreRecurringPresetRequest $request): RedirectResponse
    {
        $this->recurringPresetService->create($request->user(), $request->validated());

        return back()->flash('Recurring rule created.');
    }

    public function update(UpdateRecurringPresetRequest $request, TransactionRecurringPreset $preset): RedirectResponse
    {
        $this->authorize('update', $preset);
        $this->recurringPresetService->update($preset, $request->validated());

        return back()->flash('Recurring rule updated.');
    }

    public function destroy(TransactionRecurringPreset $preset): RedirectResponse
    {
        $this->authorize('delete', $preset);
        $this->recurringPresetService->softDelete($preset);

        return back()->flash('Recurring rule deleted.');
    }

    public function toggle(Request $request, TransactionRecurringPreset $preset): RedirectResponse
    {
        $this->authorize('toggle', $preset);
        $this->recurringPresetService->toggle($preset, ! $preset->is_active);

        $message = $preset->fresh()->is_active ? 'Recurring rule activated.' : 'Recurring rule paused.';

        return back()->flash($message);
    }
}
