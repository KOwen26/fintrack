<?php

namespace App\Data\Import;

use App\Data\Category\CategoryData;
use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\ImportRow;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One staged review-table row, carrying its resolved category display data. */
#[TypeScript]
final class ImportRowData extends Data
{
    public function __construct(
        public int $id,
        public int $row_number,
        public ?CarbonInterface $transaction_date,
        public ?string $description,
        public ?float $amount,
        public ?TransactionType $transaction_type,
        public ?Category $category_id,
        public ?CategoryData $category,
        public bool $is_included,
        public bool $is_duplicate,
        public ?array $parse_errors,
        public ?int $transfer_destination_account_id,
    ) {}

    public static function fromRow(ImportRow $row): self
    {
        return new self(
            id: $row->id,
            row_number: $row->row_number,
            transaction_date: $row->transaction_date,
            description: $row->description,
            amount: $row->amount,
            transaction_type: $row->transaction_type,
            category_id: $row->category_id,
            category: $row->category_id !== null
                ? CategoryData::fromEnum($row->category_id, withGroupData: false)
                : null,
            is_included: $row->is_included,
            is_duplicate: $row->is_duplicate,
            parse_errors: $row->parse_errors,
            transfer_destination_account_id: $row->transfer_destination_account_id,
        );
    }
}
