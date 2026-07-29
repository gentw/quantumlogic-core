<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }} – {{ config('company.legal_name') }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1a1a2e; margin: 0; padding: 32px; font-size: 13px; }
        .head { display: flex; justify-content: space-between; margin-bottom: 32px; }
        .brand { font-size: 22px; font-weight: bold; }
        .brand span { color: #301068; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        table.lines { width: 100%; border-collapse: collapse; margin: 24px 0; }
        table.lines th, table.lines td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; }
        table.lines th:nth-child(n+2), table.lines td:nth-child(n+2) { text-align: right; }
        table.totals { margin-left: auto; border-collapse: collapse; }
        table.totals td { padding: 4px 8px; }
        table.totals td:last-child { text-align: right; min-width: 110px; }
        .totals .grand td { font-weight: bold; border-top: 2px solid #1a1a2e; }
        .note { background: #f5f2fb; border-radius: 4px; padding: 10px 12px; margin: 16px 0; }
        .meta { color: #555; }
        .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #ddd; font-size: 11px; color: #555; display: flex; justify-content: space-between; gap: 24px; }
        .qr { text-align: center; font-size: 11px; color: #555; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    <div class="head">
        <div>
            <div class="brand">Quantum<span>Logic</span></div>
            <div class="meta">
                {{ config('company.legal_name') }}<br>
                {{ config('company.address') }}<br>
                @if (config('company.uid')) UID: {{ config('company.uid') }}<br> @endif
                {{ config('company.email') }}@if (config('company.phone')) · {{ config('company.phone') }} @endif
            </div>
        </div>
        <div style="text-align: right;">
            <h1>{{ $invoice->type === \App\Enums\InvoiceType::CreditNote ? 'Gutschrift / Credit note' : 'Rechnung / Invoice' }}</h1>
            <strong>{{ $invoice->invoice_number }}</strong><br>
            <span class="meta">
                Rechnungsdatum: {{ $invoice->issued_at?->format('d.m.Y') }}<br>
                @if ($invoice->due_at) Zahlbar bis: {{ $invoice->due_at->format('d.m.Y') }}<br> @endif
                @if ($invoice->billing_period_start)
                    Leistungszeitraum: {{ $invoice->billing_period_start->format('d.m.Y') }} – {{ $invoice->billing_period_end?->format('d.m.Y') }}
                @else
                    Leistungsdatum: {{ $invoice->issued_at?->format('d.m.Y') }}
                @endif
            </span>
        </div>
    </div>

    <div>
        <strong>Rechnungsempfänger</strong><br>
        {{ trim($client->name.' '.$client->surname) }}<br>
        @if ($client->company_name) {{ $client->company_name }}<br> @endif
        @if ($client->address) {{ $client->address }}<br> @endif
        @if ($client->postal_code || $client->city) {{ $client->postal_code }} {{ $client->city }}@if ($client->country_code), {{ $client->country_code }}@endif<br> @endif
        @if ($invoice->reverse_charge && $client->vat_id) UID: {{ $client->vat_id }}<br> @endif
    </div>

    @if ($invoice->reference)
        <p class="meta">Referenz: {{ $invoice->reference }}</p>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th>Leistung</th>
                <th>Menge</th>
                <th>Einzelpreis (netto)</th>
                <th>Rabatt %</th>
                <th>USt %</th>
                <th>Netto</th>
                <th>Brutto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}{{ $item->unit ? ' '.$item->unit : '' }}</td>
                    <td>{{ number_format((float) $item->unit_price_net, 2, ',', '.') }} €</td>
                    <td>{{ number_format((float) $item->discount_percent, 0) }}</td>
                    <td>{{ number_format((float) $item->vat_rate, 0) }}</td>
                    <td>{{ number_format((float) $item->line_total_net, 2, ',', '.') }} €</td>
                    <td>{{ number_format((float) $item->line_total_gross, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Nettobetrag</td><td>{{ number_format((float) $invoice->subtotal_net, 2, ',', '.') }} €</td></tr>
        @if ((float) $invoice->discount_total > 0)
            <tr><td>Rabatt</td><td>-{{ number_format((float) $invoice->discount_total, 2, ',', '.') }} €</td></tr>
        @endif
        <tr><td>Umsatzsteuer</td><td>{{ number_format((float) $invoice->vat_total, 2, ',', '.') }} €</td></tr>
        <tr class="grand"><td>Gesamtbetrag</td><td>{{ number_format((float) $invoice->total_gross, 2, ',', '.') }} €</td></tr>
        @if ((float) $invoice->amount_paid > 0)
            <tr><td>Bereits bezahlt</td><td>-{{ number_format((float) $invoice->amount_paid, 2, ',', '.') }} €</td></tr>
            <tr><td><strong>Offener Betrag</strong></td><td><strong>{{ number_format((float) $invoice->amount_due, 2, ',', '.') }} €</strong></td></tr>
        @endif
    </table>

    @if ($invoice->reverse_charge)
        <div class="note">{{ \App\Services\TaxService::REVERSE_CHARGE_NOTE }}</div>
    @endif

    @if ($invoice->terms)
        <p class="meta">{{ $invoice->terms }}</p>
    @endif
    @if ($invoice->notes)
        <p class="meta">{{ $invoice->notes }}</p>
    @endif

    @if ($bank)
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 24px; margin-top: 24px;">
            <div class="meta">
                <strong>Bankverbindung</strong><br>
                {{ $bank['account_holder'] }}<br>
                IBAN: {{ $bank['iban'] }} · BIC: {{ $bank['bic'] }}<br>
                Verwendungszweck: <strong>{{ $bank['reference'] }}</strong>
            </div>
            <div class="qr">
                <img src="{{ $bank['epc_qr_png'] }}" width="110" height="110" alt="Giro-Code"><br>
                Mit Banking-App scannen
            </div>
        </div>
    @endif

    <div class="footer">
        <div>
            {{ config('company.legal_name') }} · {{ config('company.address') }}
            @if (config('company.uid'))<br>UID: {{ config('company.uid') }}@endif
            @if (config('company.register_number'))<br>FN {{ config('company.register_number') }}@if (config('company.register_court')), {{ config('company.register_court') }}@endif @endif
        </div>
        <div style="text-align: right;">
            @if (config('company.iban'))IBAN: {{ config('company.iban') }}<br>@endif
            @if (config('company.bic'))BIC: {{ config('company.bic') }}@endif
        </div>
    </div>
</body>
</html>
