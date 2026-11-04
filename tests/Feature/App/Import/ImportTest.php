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

test('renders the upload page for the account owner', function (): void {
    $this->actingAs($this->user)
        ->get(route('imports.create', $this->account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/account/import')
            ->has('account')
            ->where('provider_name', 'Mandiri')
        );
});

test('forbids the upload page for non-owners even when shareable', function (): void {
    $shareable = Account::factory()
        ->shareable()
        ->for($this->user, 'owner')
        ->create(['provider_id' => $this->provider->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('imports.create', $shareable))
        ->assertForbidden();
});

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

test('renders the review page with rows, categories and accounts', function (): void {
    $batch = stagedBatch($this->user, $this->account);

    $this->actingAs($this->user)
        ->get(route('imports.show', $batch))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/import/review')
            ->has('batch')
            ->has('rows', 3)
            ->has('categories')
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
        ->assertInertia(fn ($page) => $page->where('import_supported', true));

    $this->actingAs($this->user)->get(route('accounts.show', $unsupported))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('import_supported', false));
});
