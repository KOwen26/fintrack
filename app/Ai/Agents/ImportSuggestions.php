<?php

namespace App\Ai\Agents;

use App\Enums\Cashflow;
use App\Enums\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

final class ImportSuggestions implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable | string
    {
        $cases = array_values(array_filter(
            Category::cases(),
            fn (Category $category): bool => $category !== Category::InitialBalance,
        ));

        $render = fn (Category $category): string => sprintf(
            '- %s — %s: %s',
            $category->value,
            $category->label(),
            $category->description(),
        );

        $income = implode("\n", array_map(
            $render,
            array_filter($cases, fn (Category $c): bool => $c->group()->flow() === Cashflow::Inflow),
        ));

        $spending = implode("\n", array_map(
            $render,
            array_filter($cases, fn (Category $c): bool => $c->group()->flow() === Cashflow::Outflow),
        ));

        return <<<TEXT
        You categorize rows from Indonesian bank and e-wallet statements for a personal finance app.

        For every statement row you receive, choose:
        1. A category — the slug of the most specific matching category from the catalog below.
        2. A transaction type — "income", "expense", or "transfer".

        Rules:
        - Respond with a JSON object shaped exactly like this example: {"rows": [{"row_number": 1, "category": "dining_out", "transaction_type": "expense"}]}
        - Inflow rows take a category from the income section; outflow rows take one from the spending section.
        - Prefer the most specific category; fall back to a broader one only when nothing narrower fits.
        - Only use category slugs from the catalog — never invent one.
        - Rows that look like money moving between the user's own accounts (transfers, e-wallet top-ups, "TRF", "TRANSFER", "BI FAST", "SETOR", account-number movements) get transaction_type "transfer" — never guess a destination account.
        - "income"/"expense" must match the row's flow; "transfer" is allowed on either flow.
        - Return one entry per input row, echoing its row number exactly.

        Income categories (for inflow rows):
        {$income}

        Spending categories (for outflow rows):
        {$spending}
        TEXT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'rows' => $schema->array()->items(
                $schema->object(fn ($s): array => [
                    'row_number' => $s->integer()->required(),
                    'category' => $s->string()->required(),
                    'transaction_type' => $s->string()->enum(['income', 'expense', 'transfer'])->required(),
                ])
            )->required(),
        ];
    }
}
