<?php

namespace App\Services;

use App\Mail\BillingMail;
use App\Models\Invoice;
use App\Models\NotificationList;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One door for billing lifecycle notifications: queued email (delivered
 * after commit), an in-app notification row, and best-effort FCM push.
 * External channels never break a billing transaction — push failures log.
 */
class BillingNotifier
{
    private const FRONTEND_PATHS = [
        'invoice' => '/client/billing/invoices/',
    ];

    public function invoiceIssued(Invoice $invoice): void
    {
        $user = $invoice->user;
        if (! $user) {
            return;
        }

        $this->send($user, new \App\Mail\InvoiceIssuedMail(
            $user->name,
            $invoice->invoice_number,
            (float) $invoice->total_gross,
            $invoice->due_at?->format('d.m.Y') ?? '—',
            $this->invoiceUrl($invoice),
        ), "Invoice {$invoice->invoice_number} issued");
    }

    public function paymentReceived(Invoice $invoice, float $amount): void
    {
        $user = $invoice->user;
        if (! $user) {
            return;
        }

        $this->send($user, new \App\Mail\PaymentReceiptMail(
            $user->name,
            $invoice->invoice_number,
            $amount,
            (float) $invoice->amount_due,
            $this->invoiceUrl($invoice),
        ), "Payment received for {$invoice->invoice_number}");
    }

    public function paymentFailed(Invoice $invoice, string $reason): void
    {
        $user = $invoice->user;
        if (! $user) {
            return;
        }

        $this->send($user, new \App\Mail\PaymentFailedMail(
            $user->name,
            $invoice->invoice_number,
            $reason,
            $this->invoiceUrl($invoice),
        ), "Payment failed for {$invoice->invoice_number}");
    }

    public function proofReceived(Invoice $invoice, string $uploaderName): void
    {
        foreach (User::where('role', 'admin')->get() as $admin) {
            $this->send($admin, new \App\Mail\PaymentProofReceivedMail(
                $admin->name,
                $invoice->invoice_number,
                $uploaderName,
                rtrim(config('app.frontend_url'), '/').'/admin/payments/reconciliation',
            ), "Payment proof uploaded for {$invoice->invoice_number}");
        }
    }

    public function proofRejected(Invoice $invoice, string $reason): void
    {
        $user = $invoice->user;
        if (! $user) {
            return;
        }

        $this->send($user, new \App\Mail\PaymentProofRejectedMail(
            $user->name,
            $invoice->invoice_number,
            $reason,
            $this->invoiceUrl($invoice),
        ), "Payment proof for {$invoice->invoice_number} was rejected");
    }

    /**
     * @param  string|null  $setPasswordCode  when the account has no password yet
     * @param  string|null  $plainPassword  the one the buyer chose at checkout
     */
    public function guestWelcome(User $user, ?string $setPasswordCode = null, ?string $plainPassword = null): void
    {
        $frontend = rtrim(config('app.frontend_url'), '/');

        $this->send($user, new \App\Mail\GuestWelcomeMail(
            userName: $user->name,
            email: $user->email,
            setPasswordUrl: $setPasswordCode !== null ? $frontend.'/reset/password/'.$setPasswordCode : null,
            plainPassword: $plainPassword,
            loginUrl: $frontend.'/login',
        ), 'Welcome to QuantumLogic');
    }

    public function recurringChargeFailed(Invoice $invoice, string $reason, bool $handedToAdmin): void
    {
        $user = $invoice->user;
        if ($user) {
            $this->send($user, new \App\Mail\RecurringChargeFailedMail(
                $user->name,
                $invoice->invoice_number,
                $reason,
                $this->invoiceUrl($invoice),
            ), 'We could not charge your recurring service');
        }

        if ($handedToAdmin) {
            foreach (User::where('role', 'admin')->get() as $admin) {
                $this->inApp($admin, "Recurring plan past due — invoice {$invoice->invoice_number} needs attention");
            }
        }
    }

    public function invoiceReminder(Invoice $invoice, string $channel): void
    {
        $user = $invoice->user;
        if (! $user) {
            return;
        }

        if (in_array($channel, ['email', 'both'], true)) {
            Mail::to($user->email)->queue(new \App\Mail\InvoiceReminderMail(
                $user->name,
                $invoice->invoice_number,
                (float) $invoice->amount_due,
                $invoice->due_at?->format('d.m.Y') ?? '—',
                $this->invoiceUrl($invoice),
            ));
        }

        if (in_array($channel, ['push', 'both'], true)) {
            $this->inApp($user, "Reminder: invoice {$invoice->invoice_number} is awaiting payment");
        }
    }

    private function send(User $user, BillingMail $mail, string $inAppMessage): void
    {
        Mail::to($user->email)->queue($mail);
        $this->inApp($user, $inAppMessage);
    }

    /** In-app row + best-effort FCM; neither may break the calling flow. */
    private function inApp(User $user, string $message): void
    {
        try {
            NotificationList::create([
                // Sender is NOT NULL by schema; system notifications are
                // recorded as from the recipient themselves.
                'user_id' => $user->id,
                'recipient_user_id' => $user->id,
                'type' => 'billing',
                'message' => $message,
                'read' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Billing in-app notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        try {
            if (\App\Models\FirebaseToken::where('user_id', $user->id)->exists()) {
                $user->notify(new \App\Notifications\MyFcmNotification($message));
            }
        } catch (\Throwable $e) {
            Log::error('Billing FCM push failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function invoiceUrl(Invoice $invoice): string
    {
        return rtrim(config('app.frontend_url'), '/').self::FRONTEND_PATHS['invoice'].$invoice->id;
    }
}
