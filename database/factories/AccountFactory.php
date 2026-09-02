<?php

namespace Database\Factories;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Provider;
use App\Models\User;
use Database\Factories\Concerns\HasDecorations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    use HasDecorations;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'provider_id' => null,
            'name' => fake()->words(2, true),
            'type' => AccountType::DebitAccount,
            'access_type' => AccountAccessType::Personal,
            'initial_balance' => 0,
            'credit_card_limit' => null,
            'currency' => Account::DEFAULT_CURRENCY,
            'decorations' => $this->randomDecorations(),
            'archived_at' => null,
        ];
    }

    public function creditCard(): static
    {
        return $this->state([
            'type' => AccountType::CreditCard,
            'credit_card_limit' => 5_000_000,
        ]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }

    public function withoutDecorations(): static
    {
        return $this->state(['decorations' => null]);
    }

    /**
     * Link the account to a provider and derive its name from it, e.g. "BCA Savings".
     */
    public function forProvider(Provider $provider): static
    {
        return $this->state(function (array $attributes) use ($provider): array {
            $type = $attributes['type'] ?? AccountType::DebitAccount;
            $type = $type instanceof AccountType ? $type : AccountType::from($type);

            return [
                'provider_id' => $provider->getKey(),
                'name' => $provider->name . ' ' . $this->accountSuffix($type),
            ];
        });
    }

    private function accountSuffix(AccountType $type): string
    {
        return match ($type) {
            AccountType::CreditCard => 'Card',
            AccountType::CashWallet => 'Cash',
            AccountType::EWallet => 'Wallet',
            AccountType::Investment => 'Investment',
            AccountType::DebitAccount => 'Savings',
        };
    }
}
