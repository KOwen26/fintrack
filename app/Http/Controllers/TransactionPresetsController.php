<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionPresetRequest;
use App\Http\Requests\UpdateTransactionPresetRequest;
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
        return Inertia::render('transaction-presets/index', [
            'presets' => $this->presetService->getUserPresets($request->user()),
            'accounts' => $this->presetService->getUserAccounts($request->user()),
            'categories' => $this->presetService->getUserCategories($request->user()),
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
