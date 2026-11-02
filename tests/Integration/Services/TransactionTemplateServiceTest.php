<?php

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\TransactionTemplateService;

it('creates a template scoped to its creator with enum and decoration casts', function (): void {
    $user = User::factory()->create();

    $template = resolve(TransactionTemplateService::class)->create($user, [
        'name' => 'Netflix',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 54_000,
        'description' => 'Monthly subscription',
        'account_id' => null,
        'destination_account_id' => null,
        'fee_amount' => null,
        'decorations' => ['icon' => 'tv', 'color' => 'red-500'],
    ]);

    expect($template->user_id)->toBe($user->id)
        ->and($template->type)->toBe(TransactionType::Expense)
        ->and($template->category_id)->toBe(Category::Streaming)
        ->and((float) $template->amount)->toEqual(54_000.0)
        ->and($template->decorations?->icon)->toBe('tv')
        ->and($template->decorations?->color)->toBe('red-500');
});

it('updates a template in place and returns a fresh instance', function (): void {
    $user = User::factory()->create();
    $service = resolve(TransactionTemplateService::class);

    $template = $service->create($user, [
        'name' => 'Netflix',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 54_000,
    ]);

    $updated = $service->update($template, [
        'name' => 'Netflix Premium',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 65_000,
    ]);

    expect($updated->id)->toBe($template->id)
        ->and($updated->name)->toBe('Netflix Premium')
        ->and((float) $updated->amount)->toEqual(65_000.0);
});

it('hard-deletes a template', function (): void {
    $template = TransactionTemplate::factory()->create();

    resolve(TransactionTemplateService::class)->delete($template);

    expect(TransactionTemplate::query()->count())->toBe(0);
});

it('lists only the user templates newest first with accounts eager-loaded', function (): void {
    $user = User::factory()->create();
    $source = Account::factory()->create(['owner_id' => $user->id]);
    $older = TransactionTemplate::factory()->create(['user_id' => $user->id]);
    $newer = TransactionTemplate::factory()->create(['user_id' => $user->id, 'account_id' => $source->id]);
    TransactionTemplate::factory()->create(); // another user's

    $templates = TransactionTemplateService::getTemplatesByUser($user);

    expect($templates)->toHaveCount(2)
        ->and($templates->first()->id)->toBe($newer->id)
        ->and($templates->last()->id)->toBe($older->id)
        ->and($templates->first()->relationLoaded('account'))->toBeTrue();
});

it('getTemplateById returns only owned templates', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id]);
    $foreign = TransactionTemplate::factory()->create();

    expect(TransactionTemplateService::getTemplateById($user, $template->id)?->id)->toBe($template->id)
        ->and(TransactionTemplateService::getTemplateById($user, $foreign->id))->toBeNull()
        ->and(TransactionTemplateService::getTemplateById($user, 999_999))->toBeNull();
});

it('getQuickAccessTemplates nulls archived default sources but keeps selectable ones', function (): void {
    $user = User::factory()->create();
    $selectable = Account::factory()->create(['owner_id' => $user->id]);
    $archived = Account::factory()->archived()->create(['owner_id' => $user->id]);
    // Created oldest-first: the list is latest-first, so [1] is the archived one.
    TransactionTemplate::factory()->create(['user_id' => $user->id, 'account_id' => $archived->id]);
    TransactionTemplate::factory()->create(['user_id' => $user->id, 'account_id' => $selectable->id]);

    $templates = TransactionTemplateService::getQuickAccessTemplates($user);

    expect($templates[0]->account_id)->toBe($selectable->id)
        ->and($templates[1]->account_id)->toBeNull();
});

it('getQuickAccessTemplates nulls unselectable transfer destinations', function (): void {
    $user = User::factory()->create();
    $archived = Account::factory()->archived()->create(['owner_id' => $user->id]);
    TransactionTemplate::factory()->transfer()->create([
        'user_id' => $user->id,
        'destination_account_id' => $archived->id,
    ]);

    $templates = TransactionTemplateService::getQuickAccessTemplates($user);

    expect($templates[0]->destination_account_id)->toBeNull();
});

it('getTemplateById prunes an archived default source for seeding', function (): void {
    $user = User::factory()->create();
    $archived = Account::factory()->archived()->create(['owner_id' => $user->id]);
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id, 'account_id' => $archived->id]);

    $seed = TransactionTemplateService::getTemplateById($user, $template->id);

    expect($seed?->account_id)->toBeNull();
});
