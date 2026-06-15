<?php

namespace App\Http\Controllers;

use App\Data\HouseholdData;
use App\Data\HouseholdMemberData;
use App\Http\Requests\InviteHouseholdMemberRequest;
use App\Http\Requests\StoreHouseholdRequest;
use App\Models\HouseholdMember;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdsController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    public function show(Request $request): Response
    {
        $membership = $request->user()
            ->householdMemberships()
            ->whereNotNull('joined_at')
            ->with('household.members.user')
            ->first();

        $household = $membership?->household;

        return Inertia::render('household/settings', [
            'household' => $household ? HouseholdData::from([
                'id' => $household->id,
                'name' => $household->name,
                'members' => $household->members->map(fn (HouseholdMember $m) => new HouseholdMemberData(
                    id: $m->id,
                    user_id: $m->user_id,
                    name: $m->user->name,
                    role: $m->role,
                    joined_at: $m->joined_at?->toISOString(),
                ))->toArray(),
            ]) : null,
        ]);
    }

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        $this->householdService->create($request->user(), $request->validated()['name']);

        return to_route('household.settings')->with('message', 'Household created.');
    }

    public function invite(InviteHouseholdMemberRequest $request): RedirectResponse
    {
        $membership = $request->user()
            ->householdMemberships()
            ->whereNotNull('joined_at')
            ->first();

        abort_unless($membership !== null, 403);

        $household = $membership->household;
        $this->authorize('invite', $household);
        $this->householdService->invite($household, $request->validated()['email'], $request->user());

        return back()->with('message', 'Invitation sent.');
    }

    public function removeMember(Request $request, HouseholdMember $member): RedirectResponse
    {
        $this->authorize('removeMember', $member->household);
        $this->householdService->removeMember($member);

        return back()->with('message', 'Member removed.');
    }
}
