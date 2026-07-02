<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Invoice — {{ $sale->son }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{--
        Self-contained, theme-independent print layout. Intentionally does
        NOT extend the admin Metronic layout — print pages should not be
        affected by dark/light theme, sidebar state, or theme grays that
        wash out on paper. Forces always-light styling for screen preview
        AND print, with print-color-adjust so the browser keeps borders
        and shaded cells when sent to the printer.
    --}}

    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            background: #fff !important;
            color: #000 !important;
            margin: 0;
            padding: 0;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .invoice {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 14px;
            border-bottom: 2px solid #000;
            margin-bottom: 18px;
        }

        .invoice-header .brand {
            font-size: 22pt;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .invoice-header .brand-sub {
            font-size: 9pt;
            color: #333;
            margin-top: 2px;
            max-width: 360px;
            line-height: 1.35;
        }

        .invoice-header .title {
            text-align: right;
        }

        .invoice-header .title h1 {
            margin: 0 0 6px 0;
            font-size: 22pt;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .invoice-header .title .meta {
            font-size: 10pt;
        }

        .invoice-header .title .meta div {
            margin-bottom: 2px;
        }

        .invoice-header .title .meta .label {
            display: inline-block;
            min-width: 70px;
            color: #555;
        }

        .invoice-parties {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 20px;
        }

        .invoice-parties .party {
            flex: 1;
        }

        .invoice-parties .party h3 {
            margin: 0 0 6px 0;
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: 1px;
            color: #555;
            text-transform: uppercase;
        }

        .invoice-parties .party .name {
            font-size: 12pt;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .invoice-parties .party .line {
            font-size: 10pt;
            color: #333;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        table.lines thead th {
            background: #000;
            color: #fff !important;
            padding: 8px 10px;
            font-size: 9.5pt;
            text-align: left;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        table.lines thead th.num {
            text-align: right;
        }

        table.lines tbody td {
            padding: 8px 10px;
            font-size: 11pt;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        table.lines tbody td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        table.lines tbody tr:nth-child(even) td {
            background: #f7f7f7;
        }

        .totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 24px;
        }

        .totals table {
            border-collapse: collapse;
            min-width: 320px;
        }

        .totals td {
            padding: 4px 10px;
            font-size: 10.5pt;
        }

        .totals td.label {
            color: #555;
            text-align: right;
            min-width: 160px;
        }

        .totals td.value {
            text-align: right;
            font-variant-numeric: tabular-nums;
            min-width: 120px;
        }

        .totals tr.grand td {
            border-top: 2px solid #000;
            padding-top: 8px;
            margin-top: 6px;
            font-size: 13pt;
            font-weight: 700;
        }

        .payment-block {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
            padding: 12px 14px;
            background: #f7f7f7;
            border: 1px solid #ddd;
        }

        .payment-block .left h3,
        .payment-block .right h3 {
            margin: 0 0 6px 0;
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: 1px;
            color: #555;
            text-transform: uppercase;
        }

        .payment-block .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 10.5pt;
            margin-bottom: 2px;
        }

        .payment-block .row .v {
            font-variant-numeric: tabular-nums;
        }

        .footer {
            padding-top: 14px;
            border-top: 1px solid #999;
            font-size: 9pt;
            color: #333;
            line-height: 1.5;
        }

        .footer .footer-grid {
            display: flex;
            justify-content: space-between;
            gap: 24px;
        }

        .footer .col h4 {
            margin: 0 0 4px 0;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #555;
        }

        .signature {
            margin-top: 32px;
            text-align: right;
        }

        .signature .sig-line {
            display: inline-block;
            border-top: 1px solid #000;
            min-width: 220px;
            margin-bottom: 4px;
        }

        .signature .sig-label {
            font-size: 9pt;
            color: #555;
        }

        .toolbar {
            max-width: 800px;
            margin: 14px auto 0;
            padding: 0 20px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .toolbar button {
            font: inherit;
            padding: 8px 14px;
            border: 1px solid #000;
            background: #fff;
            cursor: pointer;
        }

        .toolbar button.primary {
            background: #000;
            color: #fff;
        }

        @media print {
            .toolbar {
                display: none;
            }

            .invoice {
                max-width: none;
                padding: 0;
            }

            table.lines thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.history.back()">Back</button>
    <button type="button" class="primary" onclick="window.print()">Print</button>
</div>

<div class="invoice">

    {{-- Header --}}
    <div class="invoice-header">
        <div>
            <div class="brand">{{ $sale->store->name }}</div>
            <div class="brand-sub">
                {{ $sale->store->header }}<br>
                @if($sale->store->tin) TIN: {{ $sale->store->tin }}<br> @endif
                @if($sale->pos?->min)    MIN: {{ $sale->pos->min }} @endif
                @if($sale->pos?->serial) · Serial: {{ $sale->pos->serial }} @endif
            </div>
        </div>
        <div class="title">
            <h1>SALES INVOICE</h1>
            <div class="meta">
                <div><span class="label">Invoice #</span><strong>{{ $sale->son }}</strong></div>
                <div><span class="label">Date</span>{{ $sale->created_at->format('M d, Y h:i A') }}</div>
                @if($sale->sold_by)
                    <div><span class="label">Cashier</span>{{ $sale->sold_by->name }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Bill-to + payment method --}}
    <div class="invoice-parties">
        <div class="party">
            <h3>Billed To</h3>
            @if($sale->customer)
                <div class="name">{{ $sale->customer->name }}</div>
                @if($sale->customer->tin)
                    <div class="line">TIN: {{ $sale->customer->tin }}</div>
                @endif
                @if($sale->customer->address)
                    <div class="line">{{ $sale->customer->address }}</div>
                @endif
            @else
                <div class="name">Walk-In Customer</div>
            @endif
        </div>
        <div class="party" style="text-align: right;">
            <h3>Payment Method</h3>
            <div class="name">
                @switch((int) $sale->payment_type)
                    @case(1) Cash @break
                    @case(2) E-Wallet @break
                    @case(3) Credit @break
                    @case(4) Bank Transfer @break
                    @case(5) Cheque @break
                    @case(6) Card @break
                    @case(7) Gift Certificate @break
                    @case(8) Split Tender @break
                    @default Cash
                @endswitch
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <table class="lines">
        <thead>
        <tr>
            <th style="width: 45%;">Description</th>
            <th class="num" style="width: 10%;">Qty</th>
            <th style="width: 15%;">Unit</th>
            <th class="num" style="width: 15%;">Price</th>
            <th class="num" style="width: 15%;">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($sale->lines as $line)
            <tr>
                <td>{{ $line->item->name ?? '—' }}</td>
                <td class="num">{{ number_format($line->qty, 0) }}</td>
                <td>{{ $line->unit_id ? $line->unit : 'PC' }} ({{ $line->unit_qty }})</td>
                <td class="num">{{ number_format($line->price - $line->discount, 2) }}</td>
                <td class="num">₱ {{ number_format($line->sub_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Totals (BIR-style summary on the right) --}}
    <div class="totals">
        <table>
            <tr>
                <td class="label">VATable Sales</td>
                <td class="value">₱ {{ number_format($sale->vatable, 2) }}</td>
            </tr>
            <tr>
                <td class="label">VAT Amount</td>
                <td class="value">₱ {{ number_format($sale->vat, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Non-VAT</td>
                <td class="value">₱ {{ number_format($sale->non_vat, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Zero Rated</td>
                <td class="value">₱ {{ number_format($sale->zero_rated, 2) }}</td>
            </tr>
            <tr class="grand">
                <td class="label">TOTAL AMOUNT</td>
                <td class="value">₱ {{ number_format($sale->total, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Payment detail (cash sales show tendered/change; split-tender sales show the per-tender breakdown) --}}
    @if((int) $sale->payment_type === 1)
        <div class="payment-block">
            <div class="left">
                <h3>Tendered</h3>
                <div class="row"><span>Cash</span><span class="v">₱ {{ number_format($sale->cash, 2) }}</span></div>
                <div class="row"><span>Change</span><span class="v">₱ {{ number_format($sale->change, 2) }}</span></div>
            </div>
        </div>
    @elseif((int) $sale->payment_type === 8)
        @php
            $tenderLabels = [1 => 'Cash', 2 => 'E-Wallet', 4 => 'Bank Transfer', 6 => 'Card', 7 => 'Gift Certificate'];
        @endphp
        <div class="payment-block">
            <div class="left">
                <h3>Tendered</h3>
                @foreach($sale->payments as $tender)
                    <div class="row">
                        <span>{{ $tenderLabels[(int) $tender->payment_type] ?? 'Other' }}{{ $tender->reference_number ? ' · '.$tender->reference_number : '' }}</span>
                        {{-- Cash rows store the applied amount; show what was handed over instead. --}}
                        <span class="v">₱ {{ number_format((int) $tender->payment_type === 1 ? $sale->cash : $tender->amount, 2) }}</span>
                    </div>
                @endforeach
                <div class="row"><span>Change</span><span class="v">₱ {{ number_format($sale->change, 2) }}</span></div>
            </div>
        </div>
    @endif

    {{-- Footer: business info + signatory --}}
    <div class="footer">
        <div class="footer-grid">
            <div class="col">
                <h4>{{ $supplier->name ?? '' }}</h4>
                <div>{{ $supplier->header ?? '' }}</div>
                @if($supplier?->tin)         <div>TIN: {{ $supplier->tin }}</div> @endif
                @if($supplier?->email)       <div>Email: {{ $supplier->email }}</div> @endif
                @if($supplier?->ptu)         <div>PTU: {{ $supplier->ptu }}</div> @endif
                @if($supplier?->accredition) <div>Accreditation: {{ $supplier->accredition }}</div> @endif
            </div>
            <div class="col signature">
                <div class="sig-line"></div>
                <div class="sig-label">Authorized Signatory</div>
            </div>
        </div>
    </div>

</div>

<script>
    // Auto-open the print dialog on load. Users opening /print/{id} expect
    // a print prompt; the toolbar Print button is for re-prints from the
    // same tab.
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
</script>

</body>
</html>
