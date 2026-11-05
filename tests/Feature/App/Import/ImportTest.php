<?php

use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Enums\ProviderType;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Provider;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->provider = Provider::factory()->create([
        'name' => 'Mandiri',
        'slug' => 'mandiri',
        'type' => ProviderType::Bank,
    ]);

    [$this->user, $this->account] = userWithAccount(['provider_id' => $this->provider->id]);
});

function stagedBatch(User $user, Account $account): ImportBatch
{
    return resolve(ImportService::class)->upload($user, $account, statementFile());
}

test('stores an upload and redirects to the review page', function (): void {
    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()])
        ->assertRedirectToRoute('imports.show', ['batch' => ImportBatch::query()->sole()->id]);

    expect(ImportRow::count())->toBe(3);
});

test('rejects non-xlsx uploads', function (): void {
    $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => $file])
        ->assertInvalid('statement');
});

test('renders the review page with rows and accounts', function (): void {
    $batch = stagedBatch($this->user, $this->account);

    $this->actingAs($this->user)
        ->get(route('imports.show', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/imports/review')
            ->has('batch')
            ->has('rows', 3)
            ->has('accounts')
        );
});

test('updates a staged row via the row endpoint', function (): void {
    $batch = stagedBatch($this->user, $this->account);
    $row = $batch->rows->first();

    $this->actingAs($this->user)
        ->patch(route('imports.rows.update', ['batch' => $batch->id, 'row' => $row->id]), [
            'description' => 'Edited via HTTP',
            'category_id' => Category::Electricity->value,
        ])
        ->assertRedirectToRoute('imports.show', $batch);

    expect($row->fresh()->description)->toBe('Edited via HTTP');
});

test('requires a transfer destination when the type is transfer', function (): void {
    $batch = stagedBatch($this->user, $this->account);
    $row = $batch->rows->first();

    $this->actingAs($this->user)
        ->patch(route('imports.rows.update', ['batch' => $batch->id, 'row' => $row->id]), [
            'transaction_type' => 'transfer',
        ])
        ->assertSessionHasErrors('transfer_destination_account_id');
});

test('rejects a row that does not belong to the batch', function (): void {
    $batch = stagedBatch($this->user, $this->account);
    $otherRow = ImportRow::factory()->create();

    $this->actingAs($this->user)
        ->patch(route('imports.rows.update', ['batch' => $batch->id, 'row' => $otherRow->id]), [
            'description' => 'hijack',
        ])
        ->assertNotFound();
});

test('approves a batch and redirects to the account', function (): void {
    $batch = stagedBatch($this->user, $this->account);

    $this->actingAs($this->user)
        ->post(route('imports.approve', $batch))
        ->assertRedirectToRoute('accounts.show', $this->account);

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Approved);
});

test('discards a batch', function (): void {
    $batch = stagedBatch($this->user, $this->account);

    $this->actingAs($this->user)
        ->post(route('imports.discard', $batch))
        ->assertRedirectToRoute('accounts.show', $this->account);

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Discarded);
});

test('forbids every surface for a stranger', function (): void {
    $batch = stagedBatch($this->user, $this->account);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('imports.show', $batch))->assertForbidden();
    $this->actingAs($stranger)->post(route('imports.approve', $batch))->assertForbidden();
    $this->actingAs($stranger)->post(route('imports.discard', $batch))->assertForbidden();
});

test('account show exposes import support', function (): void {
    $unsupported = Account::factory()->for($this->user, 'owner')->create();

    $this->actingAs($this->user)->get(route('accounts.show', $this->account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('import_supported', true)
            ->has('imports', 0)
        );

    $this->actingAs($this->user)->get(route('accounts.show', $unsupported))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('import_supported', false)
            ->has('imports', 0)
        );
});

test('account show lists the last five batches of the account, newest first', function (): void {
    $batches = collect(range(1, 6))->map(
        fn (int $i): ImportBatch => ImportBatch::factory()->create([
            'created_by' => $this->user->id,
            'account_id' => $this->account->id,
            'provider_id' => $this->provider->id,
            'created_at' => now()->subDays(7 - $i),
        ]),
    );

    // A batch on another account never leaks into the account's list.
    ImportBatch::factory()->create(['created_by' => $this->user->id]);

    $this->actingAs($this->user)
        ->get(route('accounts.show', $this->account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/account/show')
            ->has('imports', 5)
            ->where('imports.0.id', $batches->last()->id)
            ->where('imports.4.id', $batches->get(1)->id)
        );
});

test('settings imports index lists the creator batches only', function (): void {
    $batch = stagedBatch($this->user, $this->account);

    $stranger = User::factory()->create();
    ImportBatch::factory()->create(['created_by' => $stranger->id]);

    $this->actingAs($this->user)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/imports')
            ->has('batches', 1)
            ->where('batches.0.id', $batch->id)
        );
});
