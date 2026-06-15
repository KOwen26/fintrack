<?php

namespace App\Http\Controllers;

use App\Models\HouseholdInvitation;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdInvitationsController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    public function show(string $token): Response | RedirectResponse
    {
        $invitation = HouseholdInvitation::where('token', $token)->firstOrFail();

        if (! $invitation->isPending()) {
            return to_route('household.settings')->with('message', 'Invitation is no longer valid.');
        }

        return Inertia::render('household/invitation', [
            'invitation' => [
                'token' => $token,
                'household_name' => $invitation->household->name,
                'invited_by' => $invitation->inviter->name,
                'expires_at' => $invitation->expires_at->toISOString(),
            ],
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = HouseholdInvitation::where('token', $token)->firstOrFail();
        abort_unless($invitation->isPending(), 422, 'Invitation is no longer valid.');
        $this->householdService->acceptInvitation($invitation, $request->user());

        return to_route('household.settings')->with('message', 'You have joined the household.');
    }

    public function decline(string $token): RedirectResponse
    {
        HouseholdInvitation::where('token', $token)->firstOrFail()->update(['accepted_at' => null]);

        return to_route('dashboard')->with('message', 'Invitation declined.');
    }
}
