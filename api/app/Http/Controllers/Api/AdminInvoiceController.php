<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminInvoiceDraftRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Services\BillingInvoiceService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminInvoiceController extends Controller
{
    public function __construct(
        private readonly BillingInvoiceService $invoices,
        private readonly PaymentService $payments,
    ) {}

    /** Filter drawer sends statuses[]; search covers number, reference, client. */
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->with(['user:id,name,surname,email', 'accountManager:id,name,surname', 'serviceOrder:id,order_number'])
            ->when($request->query('statuses'), fn ($q, $statuses) => $q->whereIn('status', (array) $statuses))
            ->when($request->query('search'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', "%{$term}%")
                    ->orWhere('reference', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")
                        ->orWhere('surname', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
            }))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 10), 100));

        return InvoiceResource::collection($invoices)->response();
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'items',
            'user:id,name,surname,email,address,city,postal_code,country_code,company_name,vat_id',
            'accountManager:id,name,surname',
            'serviceOrder:id,order_number,status',
            'payments' => fn ($q) => $q->latest(),
            'activities' => fn ($q) => $q->with('actor:id,name,surname'),
            'reminders',
            'creditNotes:id,invoice_number,parent_invoice_id,total_gross',
        ]);

        return response()->json([
            'invoice' => new InvoiceResource($invoice),
            'client' => $invoice->user,
            'account_manager' => $invoice->accountManager,
            'payments' => $invoice->payments->map(fn ($p) => [
                'id' => $p->id,
                'provider' => $p->provider->value,
                'status' => $p->status->value,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at?->toDateTimeString(),
                'method' => trim(($p->method_brand ?? '').' '.($p->method_last4 ? '•••• '.$p->method_last4 : '')) ?: null,
            ]),
            'activities' => $invoice->activities->map(fn ($a) => [
                'event' => $a->event,
                'actor' => $a->actor ? trim($a->actor->name.' '.$a->actor->surname) : 'System',
                'metadata' => $a->metadata,
                'at' => $a->created_at?->toDateTimeString(),
            ]),
            'reminders' => $invoice->reminders->map(fn ($r) => [
                'id' => $r->id,
                'offset_days' => $r->offset_days,
                'channel' => $r->channel,
                'scheduled_for' => $r->scheduled_for?->toDateString(),
                'sent_at' => $r->sent_at?->toDateTimeString(),
            ]),
            'credit_notes' => $invoice->creditNotes,
        ]);
    }

    /** Draft-only edits; the model lock guard is the backstop. */
    public function update(AdminInvoiceDraftRequest $request, Invoice $invoice): InvoiceResource
    {
        abort_unless($invoice->status === InvoiceStatus::Draft, 422, 'Only drafts are editable — issued invoices take a credit note.');

        $invoice->update($request->validated());

        return new InvoiceResource($invoice->refresh()->load('items'));
    }

    public function issue(Request $request, Invoice $invoice): InvoiceResource
    {
        $validated = $request->validate(['terms_days' => ['nullable', 'integer', 'between:0,90']]);

        return new InvoiceResource(
            $this->invoices->issue($invoice, $request->user(), $validated['terms_days'] ?? null)->load('items')
        );
    }

    public function cancel(Request $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoices->cancel($invoice, $request->user()));
    }

    public function creditNote(Request $request, Invoice $invoice): InvoiceResource
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new InvoiceResource(
            $this->invoices->creditNote($invoice, $request->user(), $validated['reason'] ?? null)->load('items')
        );
    }

    /** Record money that arrived outside the rails (cash desk is gone; think bank slip handled by phone). */
    public function recordManualPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $this->payments->apply(
            $invoice,
            PaymentProvider::Manual,
            'manual:'.Str::uuid(),
            min((float) $validated['amount'], (float) $invoice->amount_due),
            [
                'user_id' => $invoice->user_id,
                'metadata' => ['note' => $validated['note'] ?? null, 'recorded_by' => $request->user()->id],
            ],
        );

        return response()->json([
            'payment_id' => $payment->id,
            'invoice_status' => $invoice->refresh()->status->value,
            'amount_due' => (float) $invoice->amount_due,
        ], 201);
    }

    /** Reminder schedule relative to due date; billing:send-reminders sends. */
    public function addReminder(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'offset_days' => ['required', 'integer', 'between:-30,60'],
            'channel' => ['required', 'in:email,push,both'],
        ]);

        abort_if($invoice->due_at === null, 422, 'The invoice has no due date yet — issue it first.');

        $reminder = $invoice->reminders()->create([
            'offset_days' => $validated['offset_days'],
            'channel' => $validated['channel'],
            'scheduled_for' => $invoice->due_at->copy()->addDays($validated['offset_days']),
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['id' => $reminder->id, 'scheduled_for' => $reminder->scheduled_for->toDateString()], 201);
    }

    /** Mint (or rotate) the public pay link for an issued invoice. */
    public function publicLink(Invoice $invoice): JsonResponse
    {
        abort_if($invoice->status === InvoiceStatus::Draft, 422, 'Issue the invoice first.');

        $invoice->forceFill([
            'public_token' => Str::random(64),
            'public_token_expires_at' => now()->addDays(30),
        ])->save();

        return response()->json([
            'url' => rtrim(config('app.frontend_url'), '/').'/pay/'.$invoice->public_token,
            'expires_at' => $invoice->public_token_expires_at->toDateString(),
        ]);
    }

    /** The Billing card on the admin client detail page. */
    public function clientSummary(\App\Models\User $user): JsonResponse
    {
        $invoices = Invoice::query()->where('user_id', $user->id);

        return response()->json([
            'lifetime_value' => (float) $user->payments()->where('status', \App\Enums\PaymentStatus::Succeeded->value)->sum('amount'),
            'outstanding' => (float) (clone $invoices)->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::AwaitingConfirmation->value])->sum('amount_due'),
            'invoice_count' => (clone $invoices)->count(),
            'payment_method_count' => $user->paymentMethods()->count(),
            'recent_invoices' => InvoiceResource::collection(
                (clone $invoices)->latest('id')->limit(5)->get()
            ),
        ]);
    }

    /** Draft an invoice against an order (used by the create flow). */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_order_id' => ['required', 'integer', 'exists:service_orders,id'],
            'type' => ['required', 'in:deposit,milestone,balance,one_off,recurring'],
            'fraction' => ['nullable', 'numeric', 'between:0.01,1'],
        ]);

        $order = ServiceOrder::findOrFail($validated['service_order_id']);

        $invoice = $this->invoices->createDraftFromOrder(
            $order,
            InvoiceType::from($validated['type']),
            (float) ($validated['fraction'] ?? 1.0),
        );

        return (new InvoiceResource($invoice->load('items')))->response()->setStatusCode(201);
    }
}
