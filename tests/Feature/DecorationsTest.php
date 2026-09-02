<?php

use App\Data\DecorationData;
use App\Models\Account;
use App\Models\Category;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns random active decorations to accounts by default', function (): void {
    $account = Account::factory()->create();

    expect($account->decorations)->toBeInstanceOf(DecorationData::class)
        ->and($account->decorations->icon)->toBeString()
        ->and($account->decorations->color)->toBeString();
});

it('assigns random active decorations to providers by default', function (): void {
    $provider = Provider::factory()->create();

    expect($provider->decorations)->toBeInstanceOf(DecorationData::class)
        ->and($provider->decorations->icon)->toBeString()
        ->and($provider->decorations->color)->toBeString();
});

it('assigns random active decorations to categories by default', function (): void {
    $category = Category::factory()->create();

    expect($category->decorations)->toBeInstanceOf(DecorationData::class)
        ->and($category->decorations->icon)->toBeString()
        ->and($category->decorations->color)->toBeString();
});

it('allows nullable decorations via the withoutDecorations state', function (): void {
    $account = Account::factory()->withoutDecorations()->create();
    $category = Category::factory()->withoutDecorations()->create();
    $provider = Provider::factory()->withoutDecorations()->create();

    expect($account->decorations)->toBeNull()
        ->and($category->decorations)->toBeNull()
        ->and($provider->decorations)->toBeNull();
});

it('reads persisted null decorations back as null', function (): void {
    $account = Account::factory()->withoutDecorations()->create();

    expect($account->fresh()->decorations)->toBeNull();
});

it('accepts null icon and color in decoration data', function (): void {
    $decorations = DecorationData::from(['icon' => null, 'color' => null]);

    expect($decorations->icon)->toBeNull()
        ->and($decorations->color)->toBeNull();
});
