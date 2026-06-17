<?php

namespace App\Http\Controllers;

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
        $invitation = $this->householdService->findInvitationByToken($token);

        if (! $invitation->isPending()) {
            return to_route('household.settings')->flash('Invitation is no longer valid.');
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
        $invitation = $this->householdService->findInvitationByToken($token);
        abort_unless($invitation->isPending(), 422, 'Invitation is no longer valid.');
        $this->householdService->acceptInvitation($invitation, $request->user());

        return to_route('household.settings')->flash('You have joined the household.');
    }

    public function decline(string $token): RedirectResponse
    {
        $this->householdService->declineInvitation($token);

        return to_route('dashboard')->flash('Invitation declined.');
    }
}
