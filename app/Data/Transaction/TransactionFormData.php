<?php

namespace App\Data\Transaction;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\Transfer;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Read-side form payload shared by the create and edit surfaces. Collapses
 * the two persistence shapes — a plain transaction row and a transfer unit —
 * into one source of truth, so the form never folds transfer members itself.
 *
 * `id` is always the SOURCE (outflow) row id: it doubles as the delete target
 * for both variants (destroy routes unit members to deleteUnit). `transfer_id`
 * marks a unit edit and is the aggregate key for PUT /transfers.
 */
#[TypeScript]
class TransactionFormData extends Data
{
    public function __construct(
        public ?int $id,

        public TransactionType $type,

        public ?float $amount,

        public CarbonInterface $transaction_date,

        public ?string $description,

        public ?int $account_id,

        public ?int $destination_account_id,

        public ?Category $category_id,

        public ?float $fee_amount,

        public ?int $transfer_id,
    ) {}

    /**
     * Default from query params for form initial state / data.
     */
    public static function defaultFromRequest(Request $request): self
    {
        return new self(
            id: null,
            type: TransactionType::tryFrom($request->query('type', '')) ?? TransactionType::Expense,
            amount: null,
            transaction_date: now(),
            description: null,
            account_id: $request->integer('account_id') ?: null,
            destination_account_id: null,
            category_id: null,
            fee_amount: null,
            transfer_id: null,
        );
    }

    public static function fromTransaction(Transaction $transaction): self
    {
        return new self(
            id: $transaction->id,
            type: $transaction->type,
            amount: (float) $transaction->amount,
            transaction_date: $transaction->transaction_date,
            description: $transaction->description,
            account_id: $transaction->account_id,
            destination_account_id: null,
            category_id: $transaction->category_id,
            fee_amount: null,
            transfer_id: null,
        );
    }

    public static function fromTransfer(Transfer $transfer): self
    {
        $transfer->loadMissing(['sourceTransaction', 'destinationTransaction']);

        return new self(
            id: $transfer->sourceTransaction?->id,
            type: TransactionType::Transfer,
            amount: (float) $transfer->amount,
            transaction_date: $transfer->transaction_date,
            description: $transfer->description,
            account_id: $transfer->sourceTransaction?->account_id,
            destination_account_id: $transfer->destinationTransaction?->account_id,
            category_id: null,
            fee_amount: $transfer->fee_amount !== null ? (float) $transfer->fee_amount : null,
            transfer_id: $transfer->id,
        );
    }

    /**
     * Fold a transaction template into create-form defaults. Pruned input
     * expected (TransactionTemplateService::getTemplateById) so unselectable
     * accounts never reach the form. The context account — an explicit
     * account_id query param — wins over the template's default; a
     * destination equal to the resolved source is dropped rather than
     * tripping the form's different-account rule.
     */
    public static function fromTemplate(TransactionTemplate $template, ?int $contextAccountId = null): self
    {
        $accountId = $contextAccountId ?? $template->account_id;

        return new self(
            id: null,
            type: $template->type,
            amount: (float) $template->amount,
            transaction_date: now(),
            description: $template->description,
            account_id: $accountId,
            destination_account_id: $template->type === TransactionType::Transfer && $template->destination_account_id !== $accountId
                ? $template->destination_account_id
                : null,
            category_id: $template->category_id,
            fee_amount: $template->fee_amount !== null ? (float) $template->fee_amount : null,
            transfer_id: null,
        );
    }
}
