<?php

namespace App\Console\Commands;

use App\Services\RecurringBillingService;
use Illuminate\Console\Command;

class ChargeRecurringPlans extends Command
{
    protected $signature = 'billing:charge-recurring';

    protected $description = 'Generate and charge due recurring-plan invoices (hosting, retainers).';

    public function __construct(private readonly RecurringBillingService $recurring)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('[recurring] sweep starting');

        $stats = $this->recurring->chargeDue();

        $this->info(sprintf(
            '[recurring] sweep complete: %d charged, %d need authentication, %d failed',
            $stats['charged'],
            $stats['requires_action'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
