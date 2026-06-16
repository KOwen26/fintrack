<?php

namespace App\Console\Commands;

use App\Services\RecurringPresetService;
use Illuminate\Console\Command;

class RunRecurringPresets extends Command
{
    protected $signature = 'presets:run-recurring';

    protected $description = 'Execute all due recurring presets and generate their transactions.';

    public function __construct(private readonly RecurringPresetService $recurringPresetService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Running due recurring presets...');

        $result = $this->recurringPresetService->runDue();

        $this->info("Executed: {$result['executed']}  Failed: {$result['failed']}");

        if ($result['failed'] > 0) {
            $this->warn('Some presets failed. Check the application log for details.');
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
