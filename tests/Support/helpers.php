<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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

/** Raw contents of a statement import fixture, e.g. `import_fixture('mandiri-basic.xlsx')`. */
function import_fixture(string $name): string
{
    return file_get_contents(__DIR__ . '/../Fixtures/Imports/' . $name);
}

/** A fake statement upload built from an import fixture — `statementFile('mandiri-dirty.xlsx')`. */
function statementFile(string $name = 'mandiri-basic.xlsx'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('mutasi.xlsx', import_fixture($name));
}
