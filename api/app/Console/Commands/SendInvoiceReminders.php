<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\InvoiceReminder;
use App\Services\BillingNotifier;
use Illuminate\Console\Command;

class SendInvoiceReminders extends Command
{
    protected $signature = 'billing:send-reminders';

    protected $description = 'Send due invoice reminders (dunning), driven by invoice_reminders.';

    public function __construct(private readonly BillingNotifier $notifier)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = $pruned = 0;

        InvoiceReminder::query()
            ->whereNull('sent_at')
            ->where('scheduled_for', '<=', now())
            ->with('invoice')
            ->orderBy('id')
            ->chunkById(100, function ($reminders) use (&$sent, &$pruned) {
                foreach ($reminders as $reminder) {
                    $invoice = $reminder->invoice;

                    // Settled, cancelled or in-review invoices don't get dunned;
                    // their pending reminders are moot and removed.
                    if (! $invoice
                        || $invoice->status !== InvoiceStatus::Sent
                        || (float) $invoice->amount_due <= 0) {
                        $reminder->delete();
                        $pruned++;

                        continue;
                    }

                    $this->notifier->invoiceReminder($invoice, $reminder->channel);
                    $reminder->forceFill(['sent_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("[reminders] {$sent} sent, {$pruned} moot reminders pruned");

        return self::SUCCESS;
    }
}
