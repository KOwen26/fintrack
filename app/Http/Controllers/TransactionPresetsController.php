<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionPresetRequest;
use App\Http\Requests\UpdateTransactionPresetRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\TransactionPreset;
use App\Services\TransactionPresetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionPresetsController extends Controller
{
    public function __construct(private readonly TransactionPresetService $presetService) {}

    public function index(Request $request): Response
    {
        $presets = TransactionPreset::query()
            ->where('user_id', $request->user()->id)
            ->with(['defaultCategory', 'defaultSourceAccount', 'defaultDestinationAccount'])
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

        return Inertia::render('transaction-presets/index', [
            'presets' => $presets,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function store(StoreTransactionPresetRequest $request): RedirectResponse
    {
        $this->presetService->create($request->user(), $request->validated());

        return back()->flash('Template created.');
    }

    public function update(UpdateTransactionPresetRequest $request, TransactionPreset $preset): RedirectResponse
    {
        $this->authorize('update', $preset);
        $this->presetService->update($preset, $request->validated());

        return back()->flash('Template updated.');
    }

    public function destroy(TransactionPreset $preset): RedirectResponse
    {
        $this->authorize('delete', $preset);
        $this->presetService->softDelete($preset);

        return back()->flash('Template deleted.');
    }
}
