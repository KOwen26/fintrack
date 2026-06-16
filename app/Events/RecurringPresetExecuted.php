<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\TransactionRecurringPreset;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecurringPresetExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TransactionRecurringPreset $preset,
        public readonly Transaction $transaction,
    ) {}
}
