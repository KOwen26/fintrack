<?php

use App\Models\Account;
use App\Models\User;

/** A user with one owned account — the common feature-test arrangement pair. */
function userWithAccount(array $accountAttributes = []): array
{
    $user = User::factory()->create();

    return [$user, Account::factory()->for($user, 'owner')->create($accountAttributes)];
}

/** Row ids of a cursor-paged list result — for overlap, membership and order checks. */
function pageIds(array $page): array
{
    return array_map(fn (array $row): int => $row['id'], $page['transactions']);
}
