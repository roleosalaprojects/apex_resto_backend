@extends('customer.layouts.app')

@section('styles')
    <style>
        /* Custom variant for the Preparing status — not a Bootstrap
           built-in, so we define the badge-light- pair locally. */
        .badge-light-preparing { background-color: #f3e8ff; color: #6b21a8; }

        /* Vertical timeline for the customer-facing status history */
        .qb-timeline { position: relative; padding-left: 28px; }
        .qb-timeline::before {
            content: ''; position: absolute;
            left: 9px; top: 6px; bottom: 6px;
            width: 2px; background: #e4e6ef;
            border-radius: 1px;
        }
        .qb-timeline-row { position: relative; padding-bottom: 18px; }
        .qb-timeline-row:last-child { padding-bottom: 0; }
        .qb-timeline-dot {
            position: absolute; left: -28px; top: 4px;
            width: 18px; height: 18px; border-radius: 50%;
            background: #cbd5e1;
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px #e4e6ef;
        }
        .qb-timeline-dot[data-variant="warning"]   { background: #f59e0b; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25); }
        .qb-timeline-dot[data-variant="primary"]   { background: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25); }
        .qb-timeline-dot[data-variant="info"]      { background: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.25); }
        .qb-timeline-dot[data-variant="preparing"] { background: #8b5cf6; box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.25); }
        .qb-timeline-dot[data-variant="success"]   { background: #10b981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25); }
        .qb-timeline-dot[data-variant="danger"]    { background: #ef4444; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25); }
    </style>
@endsection

@section('content')
    @php
        $isCancelled = $ecommerceOrder->isCancelled();
        // Stepper definition. We render Cancelled inline below if hit,
        // and otherwise walk forward through the lifecycle.
        $steps = [
            ['key' => 'pending', 'label' => 'Pending', 'min' => 0, 'icon' => 'ki-time'],
            ['key' => 'verified', 'label' => 'Verified', 'min' => 1, 'icon' => 'ki-check'],
            ['key' => 'paid', 'label' => 'Paid', 'min' => 3, 'icon' => 'ki-dollar'],
            ['key' => 'preparing', 'label' => 'Preparing', 'min' => 4, 'icon' => 'ki-package'],
            ['key' => 'picked_up', 'label' => 'Picked Up', 'min' => 5, 'icon' => 'ki-handcart'],
        ];
        $current = (int) $ecommerceOrder->status;
        $effective = $current === \App\Models\Ecommerce\EcommerceOrder::STATUS_PAID ? 3
            : ($current === \App\Models\Ecommerce\EcommerceOrder::STATUS_PREPARING ? 4
            : ($current === \App\Models\Ecommerce\EcommerceOrder::STATUS_PICKED_UP ? 5
            : ($current === \App\Models\Ecommerce\EcommerceOrder::STATUS_VERIFIED ? 1
            : 0)));
    @endphp

    <div class="d-flex align-items-center mb-5">
        <a href="{{ route('customer.orders') }}" class="text-muted text-decoration-none">
            <i class="ki-duotone ki-arrow-left fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
            Back to My Orders
        </a>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-3 mb-5">
        <h1 class="fs-2x fw-bold m-0" style="color: #1a1a2e;">{{ $ecommerceOrder->reference }}</h1>
        <span class="badge badge-light-{{ $ecommerceOrder->statusBadgeVariant() }}"
              style="border-radius: 20px; padding: 6px 14px;">
            {{ $ecommerceOrder->statusLabel() }}
        </span>
        {{-- Intent badge — surface only while payment is still pending,
             once the sale exists the Payment card below tells the real
             story and the intent becomes noise. --}}
        @if(! $ecommerceOrder->sale && $ecommerceOrder->paymentIntentLabel())
            <span class="badge badge-light-info" style="border-radius: 20px; padding: 6px 14px;">
                Paying: {{ $ecommerceOrder->paymentIntentLabel() }}
            </span>
        @endif
        <span class="text-muted fs-7 ms-auto">Placed {{ $ecommerceOrder->created_at->format('M d, Y h:i A') }}</span>
    </div>

    {{-- Status timeline. Cancelled orders skip the stepper. --}}
    @if (! $isCancelled)
        <div class="card qb-card mb-5">
            <div class="card-body py-5">
                <div class="d-flex justify-content-between flex-wrap gap-3 position-relative">
                    @foreach ($steps as $i => $step)
                        @php
                            $isDone = $effective >= $step['min'];
                            $isCurrent = $effective === $step['min'];
                        @endphp
                        <div class="d-flex flex-column align-items-center flex-grow-1 position-relative" style="min-width: 80px;">
                            @if ($i > 0)
                                <div class="position-absolute"
                                     style="top: 22px; left: -50%; width: 100%; height: 3px; background: {{ $isDone ? 'var(--qb-primary)' : '#e4e6ef' }}; z-index: 0;">
                                </div>
                            @endif
                            <div class="d-flex align-items-center justify-content-center rounded-circle position-relative"
                                 style="width: 46px; height: 46px; z-index: 1; background: {{ $isDone ? 'var(--qb-primary)' : '#f1f1f4' }}; color: {{ $isDone ? '#fff' : '#a1a5b7' }}; {{ $isCurrent ? 'box-shadow: 0 0 0 4px rgba(var(--qb-primary-rgb), 0.18);' : '' }}">
                                <i class="ki-duotone {{ $step['icon'] }} fs-2" style="color: inherit;">
                                    <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span>
                                </i>
                            </div>
                            <div class="fs-7 fw-semibold mt-2 text-center {{ $isCurrent ? 'text-dark' : 'text-muted' }}">
                                {{ $step['label'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-danger d-flex align-items-center mb-5">
            <i class="ki-duotone ki-cross-circle fs-2x me-3 text-danger"><span class="path1"></span><span class="path2"></span></i>
            <div>
                <div class="fw-bold">Order cancelled</div>
                @if ($ecommerceOrder->cancelled_at)
                    <div class="fs-7 text-muted">on {{ $ecommerceOrder->cancelled_at->format('M d, Y h:i A') }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- Status history — same data as the admin timeline but trimmed
         to what the customer cares about: the transition and when it
         happened. We hide the actor (admin name) and the internal
         notes since "Cashless payment recorded" / "POS sale #..."
         aren't customer-facing language. --}}
    @if ($ecommerceOrder->statusChanges->isNotEmpty())
        <div class="card qb-card mb-5">
            <div class="card-header">
                <h3 class="card-title fw-bold">Order Timeline</h3>
                <div class="card-toolbar">
                    <span class="text-muted fs-7">{{ $ecommerceOrder->statusChanges->count() }} {{ \Illuminate\Support\Str::plural('event', $ecommerceOrder->statusChanges->count()) }}</span>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="qb-timeline">
                    @foreach ($ecommerceOrder->statusChanges->sortByDesc('created_at') as $change)
                        @php
                            $variant = $change->toBadgeVariant();
                            $fromLabel = $change->fromLabel();
                        @endphp
                        <div class="qb-timeline-row">
                            <div class="qb-timeline-dot" data-variant="{{ $variant }}"></div>
                            <div>
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    @if ($fromLabel)
                                        <span class="text-muted fs-7">{{ $fromLabel }}</span>
                                        <i class="ki-outline ki-arrow-right text-muted fs-7"></i>
                                    @endif
                                    <span class="badge badge-light-{{ $variant }} fw-bold">{{ $change->toLabel() }}</span>
                                </div>
                                <div class="text-muted fs-7 mt-1">
                                    {{ $change->created_at->format('M d, Y h:i A') }} · {{ $change->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="row g-5">
        <div class="col-md-8">
            <div class="card qb-card">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Items</h3>
                </div>
                <div class="card-body pt-0">
                    <table class="table table-row-bordered table-row-gray-100 align-middle gy-3 mb-0">
                        <thead>
                            <tr class="fw-bold text-muted fs-7">
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ecommerceOrder->lines as $line)
                                <tr>
                                    <td>{{ $line->item_name }}</td>
                                    <td class="text-center">{{ $line->qty }}</td>
                                    <td class="text-end">₱{{ number_format($line->price, 2) }}</td>
                                    <td class="text-end">₱{{ number_format($line->sub_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total:</td>
                                <td class="text-end fw-bolder fs-5 qb-price">₱{{ number_format($ecommerceOrder->total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card qb-card mb-4">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Order Info</h3>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted fs-7">Reference</span>
                        <span class="fw-semibold">{{ $ecommerceOrder->reference }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted fs-7">Total Items</span>
                        <span class="fw-semibold">{{ $ecommerceOrder->qty }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted fs-7">Placed</span>
                        <span class="fw-semibold">{{ $ecommerceOrder->created_at->format('M d, Y') }}</span>
                    </div>
                    @if ($ecommerceOrder->verified_at)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted fs-7">Verified</span>
                            <span class="fw-semibold">{{ $ecommerceOrder->verified_at->format('M d, Y') }}</span>
                        </div>
                    @endif
                    @if ($ecommerceOrder->sale)
                        <div class="d-flex justify-content-between py-2">
                            <span class="text-muted fs-7">Sale</span>
                            <span class="fw-semibold">#{{ $ecommerceOrder->sale->son }}</span>
                        </div>
                    @endif
                </div>
            </div>

            @php
                $sale = $ecommerceOrder->sale;
                $paymentLabels = [
                    \App\Models\Pos\Sale::PAYMENT_CASH => 'Cash',
                    \App\Models\Pos\Sale::PAYMENT_EWALLET => 'GCash / E-Wallet',
                    \App\Models\Pos\Sale::PAYMENT_CREDIT => 'Credit',
                    \App\Models\Pos\Sale::PAYMENT_BANK_TRANSFER => 'Bank Transfer',
                    \App\Models\Pos\Sale::PAYMENT_CHEQUE => 'Cheque',
                ];
            @endphp

            <div class="card qb-card mb-4">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Payment</h3>
                </div>
                <div class="card-body pt-0">
                    @if ($sale)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted fs-7">Method</span>
                            <span class="fw-semibold">{{ $paymentLabels[(int) $sale->payment_type] ?? 'Recorded' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted fs-7">Amount</span>
                            <span class="fw-semibold qb-price">₱{{ number_format($sale->total, 2) }}</span>
                        </div>
                        @if ($sale->bank)
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted fs-7">Bank</span>
                                <span class="fw-semibold text-end">{{ $sale->bank->bank_name }}</span>
                            </div>
                        @endif
                        @if ($sale->reference_number)
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted fs-7">Reference</span>
                                <span class="fw-semibold" style="word-break: break-all;">{{ $sale->reference_number }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between py-2 {{ $sale->cheque_status ? 'border-bottom' : '' }}">
                            <span class="text-muted fs-7">Recorded</span>
                            <span class="fw-semibold">{{ $sale->created_at->format('M d, Y h:i A') }}</span>
                        </div>
                        @if ($sale->cheque_status === \App\Models\Pos\Sale::CHEQUE_PENDING)
                            <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center">
                                <i class="ki-duotone ki-time fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                                <div class="fs-7">Cheque is waiting to clear with the bank.</div>
                            </div>
                        @elseif ($sale->cheque_status === \App\Models\Pos\Sale::CHEQUE_CLEARED)
                            <div class="alert alert-success mt-3 mb-0 d-flex align-items-center">
                                <i class="ki-duotone ki-check-circle fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                                <div class="fs-7">Cheque cleared with the bank.</div>
                            </div>
                        @elseif ($sale->cheque_status === \App\Models\Pos\Sale::CHEQUE_BOUNCED)
                            <div class="alert alert-danger mt-3 mb-0 d-flex align-items-center">
                                <i class="ki-duotone ki-cross-circle fs-2 me-2"><span class="path1"></span><span class="path2"></span></i>
                                <div class="fs-7">Cheque did not clear — please get in touch with us.</div>
                            </div>
                        @endif

                        @if ($sale->paymentProofs->isNotEmpty())
                            <div class="mt-4">
                                <div class="fw-semibold text-muted fs-7 mb-2">Receipts / Proof</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($sale->paymentProofs as $proof)
                                        <a href="{{ $proof->url }}"
                                           data-fslightbox="customer-payment-proof-{{ $sale->id }}"
                                           data-type="image"
                                           style="cursor: zoom-in;">
                                            <img src="{{ $proof->url }}"
                                                 alt="Payment proof"
                                                 style="width: 72px; height: 72px; object-fit: cover; border-radius: 8px; border: 1px solid #e4e6ef;">
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="ki-duotone ki-time fs-2x text-muted mb-2"><span class="path1"></span><span class="path2"></span></i>
                            <div class="fw-semibold">Awaiting payment</div>
                            <div class="text-muted fs-7">You'll see the payment confirmation here once it's recorded.</div>
                        </div>
                    @endif
                </div>
            </div>

            @if ($ecommerceOrder->pickupProofs->isNotEmpty())
                <div class="card qb-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title fw-bold">Pickup Confirmation</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="text-muted fs-7 mb-2">Photos captured at handover.</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($ecommerceOrder->pickupProofs as $proof)
                                <a href="{{ $proof->url }}"
                                   data-fslightbox="customer-pickup-proof-{{ $ecommerceOrder->id }}"
                                   data-type="image"
                                   style="cursor: zoom-in;">
                                    <img src="{{ $proof->url }}"
                                         alt="Pickup proof"
                                         style="width: 72px; height: 72px; object-fit: cover; border-radius: 8px; border: 1px solid #e4e6ef;">
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if ($ecommerceOrder->note)
                <div class="card qb-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title fw-bold">Your Note</h3>
                    </div>
                    <div class="card-body pt-0">
                        <p class="mb-0 text-gray-700">{{ $ecommerceOrder->note }}</p>
                    </div>
                </div>
            @endif

            @php
                // QR is shown to the cashier — they scan it from the
                // customer's phone, hit the admin lookup endpoint, and
                // get redirected to the order. URL never embeds the
                // numeric id, only the random reference.
                $lookupUrl = route('ecommerce-orders.lookup', $ecommerceOrder->reference);
                try {
                    $qrSvg = \Endroid\QrCode\Builder\Builder::create()
                        ->writer(new \Endroid\QrCode\Writer\SvgWriter())
                        ->data($lookupUrl)
                        ->size(220)
                        ->margin(4)
                        ->build()
                        ->getString();
                } catch (\Throwable $e) {
                    $qrSvg = null;
                }
            @endphp

            @if ($qrSvg)
                <div class="card qb-card">
                    <div class="card-header">
                        <h3 class="card-title fw-bold">Show This at the Store</h3>
                    </div>
                    <div class="card-body pt-0 text-center">
                        <div class="d-inline-block p-3 bg-white" style="border-radius: 12px; border: 1px solid #e4e6ef;">
                            {!! $qrSvg !!}
                        </div>
                        <div class="fs-7 text-muted mt-3">
                            Staff can scan this QR to pull up your order.
                        </div>
                        <div class="fs-7 fw-semibold mt-1" style="letter-spacing: 1px;">
                            {{ $ecommerceOrder->reference }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@php
    $needsLightbox = ($ecommerceOrder->sale && $ecommerceOrder->sale->paymentProofs->isNotEmpty())
        || $ecommerceOrder->pickupProofs->isNotEmpty();
@endphp
@if ($needsLightbox)
    @section('scripts')
        {{-- Lightbox for payment / pickup proof thumbnails. Self-initialises
             by scanning data-fslightbox links on DOMContentLoaded. --}}
        <script src="{{ asset('assets/plugins/custom/fslightbox/fslightbox.bundle.js') }}"></script>
    @endsection
@endif
