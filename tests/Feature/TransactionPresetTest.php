<?php

use App\Enums\TransactionPresetType;
use App\Models\TransactionPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists only the authenticated user presets', function (): void {
    $user = User::factory()->create();
    $mine = TransactionPreset::factory()->create(['user_id' => $user->id]);
    TransactionPreset::factory()->create(); // another user

    $this->actingAs($user)->get(route('transaction-presets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transaction-presets/index')
            ->has('presets', 1)
            ->where('presets.0.id', $mine->id)
        );
});

it('stores a new expense preset', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('transaction-presets.store'), [
        'name' => 'Morning Coffee',
        'type' => TransactionPresetType::Expense->value,
        'default_amount' => 30000,
        'default_description' => null,
        'default_category_id' => null,
        'default_source_account_id' => null,
        'default_destination_account_id' => null,
        'default_transfer_fee' => null,
    ])->assertRedirect();

    expect(TransactionPreset::where('name', 'Morning Coffee')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('updates own preset', function (): void {
    $user = User::factory()->create();
    $preset = TransactionPreset::factory()->expense()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(route('transaction-presets.update', $preset), [
        'name' => 'Updated Name',
        'type' => TransactionPresetType::Expense->value,
        'default_amount' => 50000,
        'default_description' => null,
        'default_category_id' => null,
        'default_source_account_id' => null,
        'default_destination_account_id' => null,
        'default_transfer_fee' => null,
    ])->assertRedirect();

    expect($preset->fresh()->name)->toBe('Updated Name');
});

it('prevents updating another user preset', function (): void {
    $user = User::factory()->create();
    $other = TransactionPreset::factory()->create();

    $this->actingAs($user)->put(route('transaction-presets.update', $other), [
        'name' => 'Hacked',
        'type' => TransactionPresetType::Expense->value,
        'default_amount' => null,
        'default_description' => null,
        'default_category_id' => null,
        'default_source_account_id' => null,
        'default_destination_account_id' => null,
        'default_transfer_fee' => null,
    ])->assertForbidden();
});

it('soft-deletes own preset', function (): void {
    $user = User::factory()->create();
    $preset = TransactionPreset::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('transaction-presets.destroy', $preset))
        ->assertRedirect();

    expect($preset->fresh())->toBeNull();
    expect(TransactionPreset::withTrashed()->find($preset->id))->not->toBeNull();
});

it('prevents deleting another user preset', function (): void {
    $user = User::factory()->create();
    $other = TransactionPreset::factory()->create();

    $this->actingAs($user)->delete(route('transaction-presets.destroy', $other))
        ->assertForbidden();
});
