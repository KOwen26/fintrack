<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringPresetRequest;
use App\Http\Requests\UpdateRecurringPresetRequest;
use App\Models\Account;
use App\Models\Category;
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
        $presets = TransactionRecurringPreset::query()
            ->where('created_by', $request->user()->id)
            ->with(['account', 'category'])
            ->orderBy('is_active', 'desc')
            ->oldest('next_run_date')
            ->get();

        $accounts = Account::query()
            ->visibleTo($request->user())
            ->whereNull('archived_at')
            ->get();

        $categories = Category::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return Inertia::render('recurring-presets/index', [
            'presets' => $presets,
            'accounts' => $accounts,
            'categories' => $categories,
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
