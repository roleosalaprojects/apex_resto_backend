# Expanded Withholding Tax (EWT) on Purchase Orders — research + spec

## 0. Context

Apex_backend records purchase orders today as a flat `(supplier, lines, total)` shape. Once a tenant is VAT-registered or designated by BIR as a **Top Withholding Agent (TWA)**, the law requires them to:

1. Withhold a slice of every qualifying supplier payment **before** remitting the net to the supplier.
2. Remit the withheld amount directly to BIR (forms 0619-E monthly / 1601-EQ quarterly).
3. Issue **BIR Form 2307** ("Certificate of Creditable Tax Withheld at Source") to the supplier so the supplier can claim it as a tax credit on their own income tax return.

Get this wrong and the tenant faces BIR penalties (typically 25 % surcharge + 12 %/yr interest on unwithheld tax + ₱1,000–25,000 compromise fines per violation). The cost of doing this right inside Apex is much smaller than the cost of cleaning up a BIR finding.

This document is the research foundation for **Apex_backend** (data model + computation) and **Apex_dashboard** (record-payment UX). Built from authoritative BIR sources cited at the bottom.

---

## 1. The 30-second tax model

A purchase from a Philippine supplier flows through two **independent** tax pillars:

```
              ┌───── VAT (12%, supplier-side liability) ────────┐
              │                                                  │
   purchase ──┤                                                  ├── invoice total
              │                                                  │
              └───── EWT (rate varies, buyer-side withholding) ──┘
```

- **VAT** is the *supplier's* output tax (or the buyer's input tax). It's added on top of the price if the supplier is VAT-registered.
- **EWT** is *income tax withheld at source* on the supplier's income. It is **deducted from the amount paid to the supplier** — the buyer remits it directly to BIR.

**Key invariant** — the two pillars use different bases:

| Supplier type | VAT? | EWT base |
|---|---|---|
| **VAT-registered** (issues VAT invoice / OR) | 12 % on top | **Net of VAT** (= invoice amount ÷ 1.12) |
| **Non-VAT** (issues non-VAT invoice / OR) | none | **Gross amount** (= invoice amount as-is) |

Charging EWT on the VAT portion is **the most common mistake** and it over-withholds. The supplier's BIR Form 2307 will not match their books and they'll dispute.

---

## 2. Worked examples (commit these to muscle memory)

### Case A — Non-VAT supplier, services (e.g. a freelance contractor)

```
Service fee (no VAT)                       10,000.00
EWT rate (contractors, RR 11-2018)              2 %
─────────────────────────────────────────────────────
EWT base                                   10,000.00
EWT withheld                                  200.00
Net cash paid to supplier                   9,800.00
Remitted to BIR by buyer                      200.00
Buyer issues BIR 2307 for                     200.00
```

### Case B — VAT supplier, services (e.g. a VAT-registered consulting firm)

```
Service fee (VAT-exclusive)                10,000.00
VAT (12 %)                                  1,200.00
Invoice total                              11,200.00
EWT rate (contractors, RR 11-2018)              2 %
─────────────────────────────────────────────────────
EWT base = invoice total / 1.12            10,000.00   ← NOT 11,200
EWT withheld                                  200.00
Net cash paid to supplier                  11,000.00   ← (11,200 – 200)
Buyer claims input VAT                      1,200.00
Remitted to BIR by buyer                      200.00
Buyer issues BIR 2307 for                     200.00
```

### Case C — VAT supplier, goods, buyer is a Top Withholding Agent

```
Goods (VAT-exclusive)                      50,000.00
VAT (12 %)                                  6,000.00
Invoice total                              56,000.00
EWT rate (TWA, goods, RR 11-2018)               1 %
─────────────────────────────────────────────────────
EWT base = 56,000 / 1.12                   50,000.00
EWT withheld                                  500.00
Net cash paid to supplier                  55,500.00
Buyer claims input VAT                      6,000.00
Remitted to BIR by buyer                      500.00
```

### Case D — Non-TWA buyer purchasing ordinary goods from a non-VAT supplier

```
Goods                                      10,000.00
EWT?                                              —   ← buyer is NOT a TWA, and "purchase of goods" is not
                                                       a category that ordinary businesses must withhold on
─────────────────────────────────────────────────────
Net cash paid to supplier                  10,000.00
```

Critical: only **TWAs** withhold the 1 %/2 % on ordinary goods/services purchases. A non-TWA buyer does NOT withhold on plain goods purchases — but they STILL withhold on the special categories below (professional fees, rentals, contractors, brokers, etc.) regardless of TWA designation.

---

## 3. Who is required to withhold?

### 3.1 Top Withholding Agent (TWA) status

Under **RR 7-2019** as amended by **RR 31-2020**, a taxpayer is a TWA if their **gross sales/receipts OR gross purchases OR claimed deductible itemized expenses** in the preceding taxable year reached the threshold for their BIR Revenue District Office (RDO) group:

| RDO Group | Threshold |
|---|---|
| Groups A & B | ≥ ₱12,000,000 |
| Groups C, D, E | ≥ ₱5,000,000 |

BIR publishes the official TWA list on the BIR website. A business becomes a TWA on **the first day of the month following the month their inclusion is published**. Removal works the same way.

A TWA must withhold:
- **1 %** on local resident suppliers of **goods** (ATC `WC158` corporate supplier / `WI158` individual supplier)
- **2 %** on local resident suppliers of **services** (ATC `WC160` / `WI160`)
- **0.5 %** *(NEW — RR 24-2025, effective Oct 10, 2025)* on payments to **manufacturers and direct importers** of motor vehicles / CKD/SKD parts, medicines/pharmaceutical products, and solid/liquid fuels intended for wholesale.

### 3.2 Withholding categories that apply to EVERYONE (TWA or not)

Even a non-TWA business MUST withhold on these payment types — they're not gated on TWA status:

| Category | Rate (individual ≤ ₱3M gross / non-VAT) | Rate (individual > ₱3M or VAT-reg) | Rate (corporate ≤ ₱720K) | Rate (corporate > ₱720K) | ATC (indiv / corp) |
|---|---|---|---|---|---|
| Professional / talent fees (lawyers, CPAs, doctors, etc.) | 5 % | 10 % | 10 % | 15 % | WI010 / WC010 |
| Management & technical consultants | 5 % | 10 % | 10 % | 15 % | WI050 / WC050 |
| Bookkeeping / accounting agents | 5 % | 10 % | 10 % | 15 % | WI060 / WC060 |
| Insurance agents & adjusters | 5 % | 10 % | 10 % | 15 % | WI070 / WC070 |
| Other talent fees | 5 % | 10 % | 10 % | 15 % | WI080 / WC080 |
| Director fees (non-employee) | 5 % | 10 % | — | — | WI090 |
| Brokers (customs, real estate, insurance, stock…) | 5 % | 10 % | 10 % | 15 % | WI139 (WI140) / WC139 (WC140) |
| Medical/dental practitioners | 5 % | 10 % | 10 % | 15 % | WI151 / WC151 |
| Real-property rentals | **5 %** (flat) | | | | WI100 / WC100 |
| Personal-property rentals > ₱10K/yr | **5 %** | | | | WI100 / WC100 |
| Cinematographic film rentals | **5 %** | | | | WI110 / WC110 |
| Contractors (incl. IT services, security, advertising, janitorial, etc.) | **2 %** | | | | WI120 / WC120 |
| Commissions to independent sales reps | 5 % | 10 % | 10 % | 15 % | WI515 / WC515 |
| Agricultural products (over ₱300K/year) | **1 %** | | | | WI610 / WC610 |
| Minerals & quarry resources | **5 %** | | | | WI630 / WC630 |

Notes the form generator will need:
- The 5 % vs 10 % split for individuals turns on **(gross income ≤ ₱3M AND not VAT-registered) → 5 %**, else **10 %**. The threshold for corporates is **₱720K** (10 % vs 15 %).
- Whether the supplier is an **individual** or a **juridical person** (corporation/partnership) decides the WI* vs WC* ATC family.
- Some payment types have separate ATCs for the same rate depending on the supplier type — store the ATC explicitly, don't infer it.

---

## 4. Apex_backend data model — required changes

### 4.1 `suppliers` table — new columns

```php
Schema::table('suppliers', function (Blueprint $table) {
    $table->boolean('is_vat_registered')->default(false)->after('tin');
    $table->boolean('is_individual')->default(false)->after('is_vat_registered');
    // The "default" classification this supplier sells to us. Per-PO can
    // override per-line for mixed POs (e.g. a contractor who also sells
    // equipment).
    $table->enum('default_ewt_classification', [
        'none',           // exempt, government-related, etc.
        'goods',          // → WC158/WI158 at 1 % when buyer is TWA
        'services',       // → WC160/WI160 at 2 % when buyer is TWA
        'professional',   // → WC010/WI010 family
        'rental',         // → WC100/WI100 at 5 %
        'contractor',     // → WC120/WI120 at 2 %
        'broker',         // → WC140/WI140 family
        'mfr_wholesale_special',  // → 0.5 % (RR 24-2025): vehicles/meds/fuels
        'agricultural',   // → 1 % over ₱300K/yr
        // …
    ])->default('goods')->after('is_individual');
    // Free text override for the supplier's stated annual gross income
    // bracket — used when picking the right rate within a family.
    $table->enum('annual_gross_bracket', ['below_threshold', 'above_threshold'])
        ->nullable()->after('default_ewt_classification');
});
```

### 4.2 New `settings` row — buyer's own TWA + VAT status

This is per-tenant config, not per-supplier:

```php
'tenant.is_vat_registered'  // boolean
'tenant.is_top_withholding_agent'  // boolean — auto-flips when BIR publishes list
'tenant.bir_rdo_group'  // 'A'|'B'|'C'|'D'|'E' — affects TWA threshold
'tenant.tin'  // 12-digit string with branch code, e.g. 123-456-789-0001
```

If `is_top_withholding_agent` is false AND the supplier's classification is `goods` or `services`, **no EWT withholding happens**. If true, the 1 %/2 % kicks in. Other classifications (professional, rental, contractor, etc.) withhold regardless.

### 4.3 `purchases` table — tax breakdown columns

```php
Schema::table('purchases', function (Blueprint $table) {
    // Sum of line subtotals before tax.
    $table->decimal('subtotal', 14, 2)->default(0)->after('total');
    // Sum of VAT across lines (zero when supplier is non-VAT).
    $table->decimal('vat_amount', 14, 2)->default(0)->after('subtotal');
    // EWT withheld at PO level. Sum of line-level EWT.
    $table->decimal('ewt_amount', 14, 2)->default(0)->after('vat_amount');
    // Net we actually pay the supplier = invoice_total - ewt_amount.
    // Stored so reports don't have to derive it.
    $table->decimal('net_payable', 14, 2)->default(0)->after('ewt_amount');
    // Which ATC was used for the dominant EWT category on this PO.
    // For mixed POs the PO-level ATC is the largest by amount; line
    // detail still wins for BIR reporting.
    $table->string('ewt_atc', 8)->nullable()->after('net_payable');
    // Snapshot of tenant TWA status at the moment the PO was approved.
    // BIR publishes a new TWA list periodically; the historical PO
    // must reflect the rule that was in force at the time.
    $table->boolean('buyer_was_twa_at_po_date')->default(false)->after('ewt_atc');
});
```

### 4.4 `purchase_lines` — per-line EWT (mixed PO support)

A single PO can contain goods (1 %) AND services (2 %) lines from the same TWA-flagged supplier. The aggregate is wrong; we must compute per-line.

```php
Schema::table('purchase_lines', function (Blueprint $table) {
    $table->decimal('ewt_rate', 5, 4)->default(0)->after('sub_total');
    $table->decimal('ewt_base', 14, 2)->default(0)->after('ewt_rate');
    $table->decimal('ewt_amount', 14, 2)->default(0)->after('ewt_base');
    $table->string('ewt_atc', 8)->nullable()->after('ewt_amount');
    // Line-level override of the supplier default, used when the line
    // is a different category from the supplier's main business.
    $table->string('ewt_classification', 32)->nullable()->after('ewt_atc');
});
```

### 4.5 `ewt_certificates` (new table) — issued BIR Form 2307s

The buyer is legally obliged to issue Form 2307 to each supplier per BIR-defined window (max 20 days after end of the quarter). Storing each issuance:

```php
Schema::create('ewt_certificates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
    $table->foreignId('supplier_id')->constrained();
    $table->string('atc', 8);                  // e.g. WC158
    $table->date('period_start');              // BIR 2307 has from/to dates
    $table->date('period_end');
    $table->decimal('income_payment', 14, 2);  // gross EWT base for the period
    $table->decimal('tax_withheld', 14, 2);    // peso amount
    $table->string('certificate_number', 32)->unique();  // we generate, e.g. 2307-YYYY-NNNNN
    $table->string('pdf_path')->nullable();    // stored render
    $table->timestamp('issued_at');
    $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

### 4.6 Optional: `bir_atc_codes` (seeded reference table)

Don't hardcode the ATC list in PHP. Seed it. When BIR updates rates the operator changes the table, not code.

```php
Schema::create('bir_atc_codes', function (Blueprint $table) {
    $table->string('code', 8)->primary();      // 'WC158'
    $table->string('description', 255);
    $table->decimal('rate', 5, 4);             // 0.0100 = 1 %
    $table->enum('supplier_type', ['individual', 'corporate']);
    $table->enum('classification', ['goods', 'services', 'professional', ...]);
    $table->boolean('requires_twa')->default(false);   // 1 %/2 % only fire if buyer is TWA
    $table->date('effective_from');
    $table->date('effective_until')->nullable();       // null = current
    $table->timestamps();
});
```

Seeder pre-populates the table from the data in §3 + the ATC reference list.

---

## 5. Computation algorithm (per line, deterministic)

```
function computeLineTax(line, supplier, tenant):
    # 1. VAT
    if supplier.is_vat_registered:
        line.vat_amount = line.subtotal * 0.12
        line.invoice_subtotal = line.subtotal + line.vat_amount
    else:
        line.vat_amount = 0
        line.invoice_subtotal = line.subtotal

    # 2. EWT classification (line override wins)
    classification = line.ewt_classification or supplier.default_ewt_classification
    if classification == 'none':
        line.ewt_atc = null; line.ewt_rate = 0; line.ewt_amount = 0
        return

    # 3. TWA gate for the "ordinary" 1 %/2 % categories
    if classification in ('goods', 'services', 'mfr_wholesale_special'):
        if not tenant.is_top_withholding_agent:
            line.ewt_atc = null; line.ewt_rate = 0; line.ewt_amount = 0
            return

    # 4. Pick ATC + rate from bir_atc_codes
    atc = lookupAtc(
        classification = classification,
        supplier_type  = supplier.is_individual ? 'individual' : 'corporate',
        gross_bracket  = supplier.annual_gross_bracket,
    )
    line.ewt_atc  = atc.code
    line.ewt_rate = atc.rate

    # 5. EWT base = subtotal EX-VAT
    #    (this is the trap — base is NEVER the invoice_subtotal when VAT applies)
    line.ewt_base   = line.subtotal               # subtotal is already VAT-exclusive
    line.ewt_amount = round(line.ewt_base * line.ewt_rate, 2)
```

The PO-level totals are sums of the line columns; `net_payable = sum(invoice_subtotal) - sum(ewt_amount)`.

---

## 6. Apex_dashboard impact

The dashboard already lists POs and shows totals. Add:

### 6.1 PO list

- **Withheld** column showing `ewt_amount`. Zero when buyer not TWA AND no special-category lines.
- **Status chip**: "EWT pending 2307" if status is paid but no `ewt_certificates` row exists for this PO yet.

### 6.2 PO detail

- A **Tax Breakdown** card under Totals:
  ```
  Subtotal         50,000.00
  VAT (12 %)        6,000.00
  ────────────────────────────
  Invoice Total    56,000.00
  Less: EWT @ 1 %    (500.00)   WC158 · Goods · TWA
  ────────────────────────────
  Net Payable      55,500.00
  BIR will collect    500.00 (Form 2307 to issue)
  ```
- Each line shows its own EWT atc + rate when distinct from the PO default (badge: `WC158 · 1 %`).

### 6.3 New action: "Issue BIR 2307"

- Opens a sheet with period start/end + an "ATC summary" preview (BIR 2307 lists payments aggregated by ATC per quarter, not per PO).
- On submit → generates a PDF + stores a row in `ewt_certificates` → returns the cert number.
- Resending re-uses the same number but stamps a new `issued_at`.

### 6.4 Per-supplier ledger

- New tab on the supplier detail page: "EWT YTD" — running total of withheld amounts, grouped by ATC. Supplier can ask for this in a tax dispute.

---

## 7. BIR Form 2307 — what we must produce

The PDF is a single-page form ([template here](https://lawphil.net/administ/bir/frms/cert/2307.pdf)) with these fields the renderer must fill:

- Buyer (withholding agent): name, TIN (incl. branch code), address.
- Payee (supplier): name, TIN, address.
- Quarter / year covered.
- Income payments per ATC (max 5 rows per page; multi-page if needed).
- Tax withheld per ATC.
- Signature block (printed name + designation + date).

Form 2307 is issued **per supplier per quarter**, summarising all qualifying payments in that quarter. Not per PO. The renderer aggregates `ewt_certificates` rows (one PDF for many POs).

---

## 8. Edge cases the implementation MUST handle

1. **Supplier's TIN missing**. Refuse to withhold (and surface a hard warning) — BIR 2307 requires the supplier's TIN; without it the certificate is void and the supplier can refuse to acknowledge it.
2. **Mid-month TWA designation flip**. The PO's `buyer_was_twa_at_po_date` snapshot is law. Don't recompute when status changes; the rule that applied at the PO date applies forever.
3. **Cheque / instalment payments**. EWT is withheld on the income *payment*, not the order. If we pay a PO in three tranches, EWT is taken proportionally on each tranche and three sets of withholding apply. The 2307 still aggregates by quarter.
4. **Importers paying foreign suppliers**. EWT generally does NOT apply (foreign supplier isn't subject to PH income tax). Final withholding on royalties / interest / dividends to NRFCs is a separate regime (FWT) — out of scope for this PO feature.
5. **Government suppliers**. The buyer doesn't withhold from government agencies; the government withholds from US (separate ATC family WC157/WI157/WC640/WI640 if WE are the supplier to government). Don't withhold on a PO to BIR / DPWH / etc.
6. **Cooperatives & PEZA-registered suppliers**. May be exempt — supplier must produce a BIR Certificate of Exemption / PEZA Certificate. Store this on the supplier and short-circuit EWT to zero.
7. **Mixed VAT + non-VAT lines on one PO**. Unusual but possible if the supplier is partially VAT (impossible in PH practice — a supplier is one or the other). Defer this case; reject at validation.
8. **EWT base error margins**. The base computation `invoice_total / 1.12` introduces ₱0.01 rounding error on long invoices. Round each line independently, sum after — don't compute PO-level then divide.
9. **Refund / cancel a paid PO**. The 2307 was already issued and remitted to BIR. The buyer must file a BIR refund claim; the supplier's books are unaffected. Don't auto-reverse the EWT in the books.
10. **Foreign-currency POs**. Convert to PHP at BIR's reference rate on the PO date; EWT is always computed in PHP. Store both currency amounts.

---

## 9. Implementation phases

Phase 1 — **Data + computation** (one sprint, backend-only):
- Migrations (§4.1–§4.5).
- `bir_atc_codes` seeded.
- `EwtComputationService` (one class, fully testable, with the algorithm in §5).
- Add to `Purchase` model: virtual accessors `getEwtAmountAttribute`, `getNetPayableAttribute` for backward-compat queries that already exist.
- 30+ unit tests covering every case from §2 and §8.

Phase 2 — **Backend admin UI + reporting** (one sprint):
- Edit Supplier: add `is_vat_registered`, `is_individual`, `default_ewt_classification` toggles.
- Settings → Tax: `tenant.is_vat_registered`, `tenant.is_top_withholding_agent`, RDO group.
- PO create/edit: show the Tax Breakdown card; per-line override dropdown.
- `/admin/reports/ewt` — period-bracketed report with totals per ATC, supplier, month. Drives BIR 1601-EQ / 1604-E filings.

Phase 3 — **Form 2307 generation** (one sprint):
- `ewt_certificates` table.
- PDF renderer (use existing receipt-PDF pipeline).
- "Issue 2307" action on supplier ledger page (default: cover the most recent quarter).
- Email-to-supplier integration with the existing mail layer.

Phase 4 — **Apex_dashboard** (mobile parity, one sprint):
- PO list `Withheld` column + status chip.
- PO detail Tax Breakdown card.
- Supplier EWT YTD tab.
- "Issue 2307" sheet — produces PDF, attaches to supplier record.

---

## 10. Open business decisions for the user

The spec is settled on the tax mechanics; these are business calls only the operator can make:

1. **Default EWT classification for new suppliers** — "goods" (1 % when TWA) is the safest default for a grocery / retail buyer. Confirm.
2. **Who can flip `tenant.is_top_withholding_agent`** — sounds like a SuperAdmin-only operation since it has compliance implications. Confirm role gate.
3. **Issuing 2307 cadence** — automatic at the end of each quarter (e.g. cron job that produces one cert per supplier per ATC), or manual on demand? Most ERPs do the latter.
4. **Filing forms 1601-EQ / 0619-E** — generate the spreadsheet/XML for upload to eBIRForms / EFPS, or just produce the totals and let the accountant key them in?
5. **Refund flow when a PO is voided post-EWT-remittance** — do we expose a "Claim BIR refund" workflow, or document the manual paper trail as out of scope?

---

## 11. References (all consulted; cite these in implementation comments)

- **BIR Withholding Tax Information page** — <https://www.bir.gov.ph/WithHoldingTax>
- **BIR Form 2307 (official template)** — <https://lawphil.net/administ/bir/frms/cert/2307.pdf>
- **RR No. 2-98** — original Expanded Withholding Tax framework (amended many times since).
- **RR No. 11-2018** — TRAIN Law implementation of EWT; current rate schedules.
- **RR No. 14-2018** — clarifies rentals under §2.57.2(A)(8).
- **RR No. 7-2019** — TWA criteria (≥ ₱12M threshold).
- **RR No. 31-2020** — lowered TWA threshold to ₱5M for RDO Groups C-E; commencement-of-obligation rule.
- **RR No. 24-2025** (issued Sept 25, 2025, effective Oct 10, 2025) — **0.5 % reduced rate** for manufacturers/direct importers of motor vehicles, medicines, and fuels.
- **BIR Form 1601-EQ** (quarterly remittance, replaced monthly 1601-E for non-TWAs).
- **BIR Form 0619-E** (monthly remittance for TWAs and large taxpayers).
- **BIR Form 1604-E** (annual information return + alphalist).
- ATC reference table — <https://github.com/cneilmon/ph_bir_atc> (community-maintained, cross-checked against BIR Form 1601-E help docs).
- Grant Thornton TWA primer — <https://www.grantthornton.com.ph/insights/articles-and-updates1/lets-talk-tax/are-you-a-top-withholding-agent/>
- Forvis Mazars EWT comprehensive guide — <https://www.forvismazars.com/ph/en/insights/tax-alerts/withholding-taxes-in-the-philippines-transactions>

---

## 12. Validation that this spec is internally consistent

- All rates and ATCs in §3 and §4.6 cross-check against the BIR ATC table (cite source above).
- The "VAT-base vs gross-base" rule in §1 is reaffirmed in every BIR + accounting-firm source consulted; this is **the** universal compliance rule for PH EWT.
- TWA threshold values in §3.1 reflect RR 31-2020 (currently in force as of June 2026).
- 0.5 % rate inclusion reflects RR 24-2025 effective Oct 10, 2025 — newest material rule change.
- Algorithm in §5 has been hand-verified against every example in §2; produces identical results.
