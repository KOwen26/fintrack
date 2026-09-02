<?php

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('summarizes total, available and investment balances across account types', function (): void {
    $user = User::factory()->create();

    $accounts = collect([
        Account::factory()->create(['owner_id' => $user->id, 'initial_balance' => 1_000_000]),
        Account::factory()->create([
            'owner_id' => $user->id,
            'type' => AccountType::CashWallet,
            'initial_balance' => 200_000,
        ]),
        Account::factory()->create([
            'owner_id' => $user->id,
            'type' => AccountType::EWallet,
            'initial_balance' => 300_000,
        ]),
        Account::factory()->create([
            'owner_id' => $user->id,
            'type' => AccountType::Investment,
            'initial_balance' => 4_850_000,
        ]),
        Account::factory()->creditCard()->create([
            'owner_id' => $user->id,
            'initial_balance' => -500_000,
        ]),
    ]);

    $summary = AccountService::summarize($accounts);

    expect($summary['total_balance'])->toBe(5_850_000.0)
        ->and($summary['available_balance'])->toBe(1_500_000.0)
        ->and($summary['investment_balance'])->toBe(4_850_000.0)
        ->and($summary['total_accounts'])->toBe(5);
});

it('keeps credit card debt out of available and investment balances', function (): void {
    $user = User::factory()->create();

    $accounts = collect([
        Account::factory()->creditCard()->create([
            'owner_id' => $user->id,
            'initial_balance' => -750_000,
        ]),
    ]);

    $summary = AccountService::summarize($accounts);

    expect($summary['total_balance'])->toBe(-750_000.0)
        ->and($summary['available_balance'])->toBe(0.0)
        ->and($summary['investment_balance'])->toBe(0.0);
});

it('returns zero balances and nulls for an empty collection', function (): void {
    $summary = AccountService::summarize(collect());

    expect($summary['total_balance'])->toBe(0.0)
        ->and($summary['available_balance'])->toBe(0.0)
        ->and($summary['investment_balance'])->toBe(0.0)
        ->and($summary['oldest_account_years'])->toBeNull();
});
