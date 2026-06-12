@extends('layout.app')
@section('header')
    - Order {{ $ecommerceOrder->reference }}
@endsection
@section('title')
    Ecommerce Order Detail
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ecommerce-orders.index') }}">Ecommerce Orders</a></li>
    <li class="breadcrumb-item text-muted">{{ $ecommerceOrder->reference }}</li>
@endsection
@section('styles')
    <style>
        /* Custom variant for the Preparing status — not a Bootstrap
           built-in, so we define the badge-light- pair locally. */
        .badge-light-preparing { background-color: #f3e8ff; color: #6b21a8; }

        /* Status history timeline */
        .qb-timeline { position: relative; padding-left: 24px; }
        .qb-timeline::before {
            content: ''; position: absolute;
            left: 7px; top: 6px; bottom: 6px;
            width: 2px; background: #e4e6ef;
            border-radius: 1px;
        }
        .qb-timeline-row {
            position: relative; padding-bottom: 18px;
        }
        .qb-timeline-row:last-child { padding-bottom: 0; }
        .qb-timeline-dot {
            position: absolute; left: -24px; top: 4px;
            width: 16px; height: 16px; border-radius: 50%;
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
    {{-- Flash banners are rendered by the global layout/messages.blade.php
         partial — don't duplicate them here. --}}

    <div class="row g-5">
        <div class="col-md-8">
            <div class="card card-flush">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Order Lines</h3>
                </div>
                <div class="card-body pt-0">
                    <table class="table table-row-bordered table-row-gray-200 align-middle gy-4">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ecommerceOrder->lines as $line)
                                <tr>
                                    <td>{{ $line->item_name }}</td>
                                    <td class="text-center">{{ $line->qty }}</td>
                                    <td class="text-end">{{ number_format($line->price, 2) }}</td>
                                    <td class="text-end">{{ number_format($line->sub_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-bold fs-5">Total:</td>
                                <td class="text-end fw-bolder fs-4 text-primary">{{ number_format($ecommerceOrder->total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Status History — every transition this order went through,
                 oldest first. Built from ecommerce_order_status_changes
                 which is written by EcommerceOrder::logStatusChange()
                 at every state-changing endpoint + the auto-advance to
                 PAID in SaleCreationService. --}}
            <div class="card card-flush mt-5">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Status History</h3>
                    <div class="card-toolbar">
                        <span class="text-muted fs-7">{{ $ecommerceOrder->statusChanges->count() }} {{ \Illuminate\Support\Str::plural('event', $ecommerceOrder->statusChanges->count()) }}</span>
                    </div>
                </div>
                <div class="card-body pt-0">
                    @if($ecommerceOrder->statusChanges->isEmpty())
                        <div class="text-muted fs-7">No history recorded yet.</div>
                    @else
                        <div class="qb-timeline">
                            @foreach($ecommerceOrder->statusChanges->sortByDesc('created_at') as $change)
                                @php
                                    $variant = $change->toBadgeVariant();
                                    $fromLabel = $change->fromLabel();
                                @endphp
                                <div class="qb-timeline-row">
                                    <div class="qb-timeline-dot" data-variant="{{ $variant }}"></div>
                                    <div class="qb-timeline-content">
                                        <div class="d-flex align-items-center flex-wrap gap-2">
                                            @if($fromLabel)
                                                <span class="text-muted fs-7">{{ $fromLabel }}</span>
                                                <i class="ki-outline ki-arrow-right text-muted fs-7"></i>
                                            @endif
                                            <span class="badge badge-light-{{ $variant }} fw-bold">{{ $change->toLabel() }}</span>
                                        </div>
                                        <div class="text-muted fs-7 mt-1">
                                            {{ $change->created_at->format('M d, Y h:i:s A') }} · {{ $change->created_at->diffForHumans() }}
                                            @if($change->changedBy)
                                                · by <span class="fw-semibold text-gray-800">{{ $change->changedBy->name }}</span>
                                            @else
                                                · by <span class="text-muted">customer</span>
                                            @endif
                                        </div>
                                        @if($change->note)
                                            <div class="text-muted fs-7 fst-italic mt-1">{{ $change->note }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-flush mb-5">
                <div class="card-header">
                    <h3 class="card-title fw-bold">Order Info</h3>
                </div>
                <div class="card-body pt-0">
                    <table class="table table-row-bordered table-row-gray-100 gy-3">
                        <tr>
                            <td class="fw-semibold text-gray-700">Reference</td>
                            <td>{{ $ecommerceOrder->reference }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-gray-700">Customer</td>
                            <td>{{ $ecommerceOrder->customer->name }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-gray-700">Status</td>
                            <td>
                                <span class="badge badge-light-{{ $ecommerceOrder->statusBadgeVariant() }}">
                                    {{ $ecommerceOrder->statusLabel() }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-gray-700">Total Items</td>
                            <td>{{ $ecommerceOrder->qty }}</td>
                        </tr>
                        @if($ecommerceOrder->paymentIntentLabel())
                            <tr>
                                <td class="fw-semibold text-gray-700">Customer's Preference</td>
                                <td>
                                    <span class="badge badge-light-info">{{ $ecommerceOrder->paymentIntentLabel() }}</span>
                                    <div class="text-muted fs-8 mt-1 fst-italic">FYI only — record whatever they actually paid with.</div>
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="fw-semibold text-gray-700">Date</td>
                            <td>{{ $ecommerceOrder->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                        @if($ecommerceOrder->note)
                            <tr>
                                <td class="fw-semibold text-gray-700">Note</td>
                                <td>{{ $ecommerceOrder->note }}</td>
                            </tr>
                        @endif
                        @if($ecommerceOrder->isVerified())
                            <tr>
                                <td class="fw-semibold text-gray-700">Verified By</td>
                                <td>{{ $ecommerceOrder->verifiedBy->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-gray-700">Verified At</td>
                                <td>{{ $ecommerceOrder->verified_at->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endif
                        @if($ecommerceOrder->isCancelled())
                            <tr>
                                <td class="fw-semibold text-gray-700">Cancelled By</td>
                                <td>{{ $ecommerceOrder->cancelledBy->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-gray-700">Cancelled At</td>
                                <td>{{ $ecommerceOrder->cancelled_at->format('M d, Y h:i A') }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if($ecommerceOrder->isPending())
                <div class="d-flex gap-3">
                    <form id="verifyOrderForm" action="{{ route('ecommerce-orders.verify', $ecommerceOrder) }}" method="POST" class="flex-fill">
                        @csrf
                        <button type="button" id="verifyOrderBtn" class="btn btn-success w-100">
                            <i class="ki-duotone ki-check fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                            Verify
                        </button>
                    </form>
                    <form id="cancelOrderForm" action="{{ route('ecommerce-orders.cancel', $ecommerceOrder) }}" method="POST" class="flex-fill">
                        @csrf
                        <button type="button" id="cancelOrderBtn" class="btn btn-danger w-100">
                            <i class="ki-duotone ki-cross fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                            Cancel
                        </button>
                    </form>
                </div>
            @endif

            {{-- Post-payment cancellation. Available on PAID / PREPARING
                 (where no goods have left the building yet — "Cancel")
                 and PICKED_UP (where the customer must physically bring
                 goods back — "Refund"). Same backend mechanics; UI just
                 renames the button so it reads accurately. --}}
            @php
                $postPaidCancellable = $ecommerceOrder->isPaid() || $ecommerceOrder->isPreparing() || $ecommerceOrder->isPickedUp();
                $cancelButtonLabel = $ecommerceOrder->isPickedUp() ? 'Refund' : 'Cancel';
                // Eager-load here so both this block AND the paid-sale
                // panel below can reuse the same hydrated relations.
                $paidSale = $ecommerceOrder->sale?->loadMissing(['paymentProofs', 'refundSales']);
                $refundSale = $paidSale?->refundSales->first();
                $alreadyRefunded = $refundSale !== null;
            @endphp

            @if($postPaidCancellable && ! $alreadyRefunded)
                <button type="button" class="btn btn-outline-danger w-100 mt-3" data-bs-toggle="modal" data-bs-target="#refundOrderModal">
                    <i class="ki-duotone ki-arrows-circle fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                    {{ $cancelButtonLabel }} order
                </button>

                <div class="modal fade" id="refundOrderModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('ecommerce-orders.cancel', $ecommerceOrder) }}" method="POST" id="refundOrderForm">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">
                                        {{ $cancelButtonLabel }} order {{ $ecommerceOrder->reference }}?
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-light-warning d-flex align-items-start mb-5">
                                        <i class="ki-duotone ki-information-2 fs-2x text-warning me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        <div class="fs-7">
                                            @if($ecommerceOrder->isPickedUp())
                                                The customer must <strong>physically return the goods</strong> before you process this. Submitting will:
                                            @else
                                                Submitting will:
                                            @endif
                                            <ul class="mb-0 mt-2">
                                                <li>Return <strong>{{ $ecommerceOrder->qty }} item(s)</strong> to inventory</li>
                                                <li>Record a refund Sale (<code>R-{{ $paidSale->son ?? '…' }}</code>)</li>
                                                @if(isset($paidSale) && in_array($paidSale->payment_type, [\App\Models\Pos\Sale::PAYMENT_EWALLET, \App\Models\Pos\Sale::PAYMENT_BANK_TRANSFER], true) && $paidSale->bank_id)
                                                    <li>Reverse <strong>₱{{ number_format($paidSale->bank_amount ?? $paidSale->total, 2) }}</strong> from the linked bank</li>
                                                @else
                                                    <li>Note: cash refund — physically hand the amount back to the customer</li>
                                                @endif
                                                <li>Move the order to <strong>Cancelled</strong> and text the customer</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold" for="cancelReason">Reason (optional)</label>
                                        <textarea name="reason" id="cancelReason" rows="3" maxlength="500"
                                                  class="form-control"
                                                  placeholder="e.g. Customer requested change of order"></textarea>
                                        <div class="form-text">Saved to the order's audit trail.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep order</button>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="ki-duotone ki-cross fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Yes, {{ strtolower($cancelButtonLabel) }} order
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if($paidSale)
                {{-- $paidSale + $refundSale were hydrated above the
                     post-payment cancel block so both renderers share
                     the same relations. --}}
                <div class="card card-flush mt-5 border {{ $refundSale ? 'border-danger' : 'border-success' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            @if($refundSale)
                                <i class="ki-duotone ki-arrows-circle fs-2x text-danger"><span class="path1"></span><span class="path2"></span></i>
                                <div>
                                    <div class="fw-bold fs-5">Refunded</div>
                                    <div class="text-muted fs-7">Original Sale #{{ $paidSale->son }} · Refund Sale #{{ $refundSale->son }}</div>
                                </div>
                            @else
                                <i class="ki-duotone ki-check-circle fs-2x text-success"><span class="path1"></span><span class="path2"></span></i>
                                <div>
                                    <div class="fw-bold fs-5">Paid</div>
                                    <div class="text-muted fs-7">Sale #{{ $paidSale->son }}</div>
                                </div>
                            @endif
                        </div>
                        @if(is_null($paidSale->pos_id))
                            <span class="badge badge-light-info">Recorded via web admin</span>
                        @else
                            <span class="badge badge-light-success">Rung up at POS</span>
                        @endif
                        @if($paidSale->cheque_status === \App\Models\Pos\Sale::CHEQUE_PENDING)
                            <span class="badge badge-light-warning ms-1">Cheque pending clearing</span>
                        @elseif($paidSale->cheque_status === \App\Models\Pos\Sale::CHEQUE_BOUNCED)
                            <span class="badge badge-light-danger ms-1">Cheque bounced</span>
                        @endif
                        @if($refundSale)
                            <span class="badge badge-light-danger ms-1">Stock returned to inventory</span>
                        @endif

                        @if($paidSale->paymentProofs->isNotEmpty())
                            <div class="mt-4">
                                <div class="fw-semibold text-gray-700 mb-2">Proof of Payment</div>
                                <div class="d-flex flex-wrap gap-2">
                                    {{-- FsLightbox groups thumbnails sharing the
                                         same data-fslightbox value into a gallery
                                         — arrows navigate between them, ESC
                                         closes, backdrop click closes. --}}
                                    @foreach($paidSale->paymentProofs as $proof)
                                        <a href="{{ $proof->url }}"
                                           data-fslightbox="payment-proofs-{{ $paidSale->id }}"
                                           data-type="image"
                                           style="cursor: zoom-in;">
                                            <img src="{{ $proof->url }}"
                                                 alt="Proof of payment"
                                                 style="width: 88px; height: 88px; object-fit: cover; border-radius: 8px; border: 1px solid #e4e6ef;">
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if($ecommerceOrder->isPaid())
                    <form id="markPreparingForm" action="{{ route('ecommerce-orders.mark-preparing', $ecommerceOrder) }}" method="POST" class="mt-3">
                        @csrf
                        <button type="button" id="markPreparingBtn" class="btn btn-info w-100">
                            <i class="ki-duotone ki-package fs-3 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Mark as Preparing
                        </button>
                    </form>
                @endif

                @if($ecommerceOrder->isPreparing())
                    <button type="button" class="btn btn-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#markPickedUpModal">
                        <i class="ki-duotone ki-handcart fs-3 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                        Mark as Picked Up
                    </button>

                    <div class="modal fade" id="markPickedUpModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form action="{{ route('ecommerce-orders.mark-picked-up', $ecommerceOrder) }}"
                                      method="POST"
                                      id="markPickedUpForm"
                                      enctype="multipart/form-data">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Confirm Pickup</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-light-success d-flex align-items-center mb-5">
                                            <i class="ki-duotone ki-handcart fs-2x text-success me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                            <div>
                                                <div class="fw-semibold">{{ $ecommerceOrder->reference }}</div>
                                                <div class="text-muted fs-7">{{ $ecommerceOrder->customer->name }} · ₱{{ number_format($ecommerceOrder->total, 2) }}</div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Proof of Pickup (optional)</label>
                                            <input type="file"
                                                   name="proofs[]"
                                                   id="pickupProofsInput"
                                                   class="form-control"
                                                   accept="image/*"
                                                   multiple>
                                            <div class="form-text">
                                                Up to 5 photos · JPG, PNG, WEBP, or HEIC · 5 MB each. Useful: signed receipt, customer holding the goods, packed-items handover shot.
                                            </div>
                                            <div id="pickupProofsPreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="ki-duotone ki-check fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                                            Confirm Pickup
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if($ecommerceOrder->isPickedUp())
                    @php
                        $pickupProofs = $ecommerceOrder->loadMissing('pickupProofs')->pickupProofs;
                    @endphp
                    <div class="card card-flush mt-3 border border-success">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <i class="ki-duotone ki-check-circle fs-2x text-success"><span class="path1"></span><span class="path2"></span></i>
                                <div>
                                    <div class="fw-bold">Picked Up</div>
                                    <div class="text-muted fs-7">Customer collected this order.</div>
                                </div>
                            </div>

                            @if($pickupProofs->isNotEmpty())
                                <div class="mt-3">
                                    <div class="fw-semibold text-gray-700 fs-7 mb-2">Proof of Pickup</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($pickupProofs as $proof)
                                            <a href="{{ $proof->url }}"
                                               data-fslightbox="pickup-proofs-{{ $ecommerceOrder->id }}"
                                               data-type="image"
                                               style="cursor: zoom-in;">
                                                <img src="{{ $proof->url }}"
                                                     alt="Pickup proof"
                                                     style="width: 72px; height: 72px; object-fit: cover; border-radius: 8px; border: 1px solid #e4e6ef;">
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @elseif(! $ecommerceOrder->isCancelled() && auth()->user()->can('record-cashless-payment'))
                <div class="mt-3">
                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                        <i class="ki-duotone ki-dollar fs-3 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Record Payment
                    </button>
                </div>

                <div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="{{ route('ecommerce-orders.record-payment', $ecommerceOrder) }}" method="POST" id="recordPaymentForm" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">Record Payment</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-light-primary d-flex align-items-center mb-5">
                                        <i class="ki-duotone ki-information-2 fs-2x text-primary me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        <div>
                                            <div class="fw-semibold">Order Total: ₱{{ number_format($ecommerceOrder->total, 2) }}</div>
                                            <div class="text-muted fs-7">{{ $ecommerceOrder->customer->name }} · {{ $ecommerceOrder->reference }}</div>
                                        </div>
                                    </div>

                                    <div class="mb-5">
                                        <label class="form-label fw-semibold required">Payment Method</label>
                                        @if($ecommerceOrder->paymentIntentLabel())
                                            <div class="alert alert-light-info py-2 px-3 mb-3 d-flex align-items-center" style="border-radius: 8px;">
                                                <i class="ki-duotone ki-information-2 fs-3 text-info me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                <div class="fs-7">
                                                    <span class="text-muted">Customer preferred</span>
                                                    <span class="fw-semibold">{{ $ecommerceOrder->paymentIntentLabel() }}</span>
                                                    <span class="text-muted">— change if they paid differently.</span>
                                                </div>
                                            </div>
                                        @endif
                                        <div class="row g-3" id="paymentTypeButtons">
                                            @php
                                                $paymentMethodOptions = [
                                                    \App\Models\Pos\Sale::PAYMENT_CASH => ['Cash', 'ki-cash'],
                                                    \App\Models\Pos\Sale::PAYMENT_EWALLET => ['GCash / E-Wallet', 'ki-wallet'],
                                                    \App\Models\Pos\Sale::PAYMENT_BANK_TRANSFER => ['Bank Transfer', 'ki-bank'],
                                                    \App\Models\Pos\Sale::PAYMENT_CHEQUE => ['Cheque', 'ki-bill'],
                                                ];
                                                $intendedType = $ecommerceOrder->intendedSalePaymentType();
                                                $defaultPaymentType = $intendedType ?? array_key_first($paymentMethodOptions);
                                            @endphp
                                            @foreach($paymentMethodOptions as $value => $meta)
                                                <div class="col-6">
                                                    <label class="btn btn-outline btn-outline-dashed btn-outline-default p-4 d-flex align-items-center w-100">
                                                        <input type="radio" name="payment_type" value="{{ $value }}" class="form-check-input me-3" @checked($value === $defaultPaymentType) required>
                                                        <i class="ki-duotone {{ $meta[1] }} fs-2x me-2"><span class="path1"></span><span class="path2"></span></i>
                                                        <span class="fw-semibold">{{ $meta[0] }}</span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mb-5">
                                        <label class="form-label fw-semibold required">Fulfilling Store</label>
                                        <select name="store_id"
                                                id="recordPaymentStoreSelect"
                                                class="form-select"
                                                data-placeholder="Search a store…"
                                                required>
                                        </select>
                                        <div class="form-text">Stock will be deducted from this store's inventory.</div>
                                    </div>

                                    <div class="js-bank-fields" style="display: none;">
                                        <div class="mb-5">
                                            <label class="form-label fw-semibold required">Bank</label>
                                            <select name="bank_id"
                                                    id="recordPaymentBankSelect"
                                                    class="form-select js-bank-field"
                                                    data-placeholder="Search a bank…">
                                            </select>
                                            <div class="form-text">Live list — banks added in Settings appear here without a refresh.</div>
                                        </div>

                                        <div class="mb-5">
                                            <label class="form-label fw-semibold required js-ref-label">Reference Number</label>
                                            <input type="text" name="reference_number" class="form-control js-bank-field" maxlength="120" placeholder="e.g. transfer reference / cheque number">
                                        </div>

                                        <div class="mb-5">
                                            <label class="form-label fw-semibold required">Amount Received</label>
                                            <input type="number" name="bank_amount" class="form-control js-bank-field" min="0" step="0.01" value="{{ number_format($ecommerceOrder->total, 2, '.', '') }}">
                                        </div>
                                    </div>

                                    <div class="mb-5">
                                        <label class="form-label fw-semibold">Proof of Payment (optional)</label>
                                        <input type="file"
                                               name="proofs[]"
                                               id="recordPaymentProofs"
                                               class="form-control"
                                               accept="image/*"
                                               multiple>
                                        <div class="form-text">
                                            Up to 5 photos · JPG, PNG, WEBP, or HEIC · 5 MB each. Useful for GCash screenshots, deposit slips, or a photo of the cheque.
                                        </div>
                                        <div id="recordPaymentProofsPreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Note (optional)</label>
                                        <textarea name="note" class="form-control" rows="2" maxlength="500"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ki-duotone ki-check fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Record Payment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('vendor-scripts')
    {{-- FsLightbox: in-page overlay for inspecting proof-of-payment
         photos at full size, with arrow navigation between multiple
         proofs attached to the same sale. Loaded conditionally because
         the script self-initializes by scanning data-fslightbox links
         on DOMContentLoaded. --}}
    <script src="{{ asset('assets/plugins/custom/fslightbox/fslightbox.bundle.js') }}"></script>
@endsection
@section('scripts')
    <script>
        $(function () {
            // SweetAlert confirms for Verify / Cancel — replaces the ugly
            // native browser confirm() dialog.
            $('#verifyOrderBtn').on('click', function () {
                Swal.fire({
                    title: 'Verify this order?',
                    text: 'The order will move to the verified queue for fulfilment.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, verify',
                    cancelButtonText: 'No',
                    customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-light' },
                    buttonsStyling: false,
                }).then(result => {
                    if (result.isConfirmed) {
                        $('#verifyOrderForm').trigger('submit');
                    }
                });
            });

            $('#cancelOrderBtn').on('click', function () {
                Swal.fire({
                    title: 'Cancel this order?',
                    text: 'This cannot be undone — the customer will need to place a new order.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, cancel',
                    cancelButtonText: 'Keep order',
                    customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
                    buttonsStyling: false,
                }).then(result => {
                    if (result.isConfirmed) {
                        $('#cancelOrderForm').trigger('submit');
                    }
                });
            });

            $('#markPreparingBtn').on('click', function () {
                Swal.fire({
                    title: 'Mark as Preparing?',
                    text: 'The store is now packing this order. The customer will see the updated status.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, preparing',
                    cancelButtonText: 'Not yet',
                    customClass: { confirmButton: 'btn btn-info', cancelButton: 'btn btn-light' },
                    buttonsStyling: false,
                }).then(result => {
                    if (result.isConfirmed) {
                        $('#markPreparingForm').trigger('submit');
                    }
                });
            });

            // Pickup-proof preview thumbnails inside the Confirm Pickup modal.
            $('#pickupProofsInput').on('change', function () {
                const $preview = $('#pickupProofsPreview').empty();
                const files = Array.from(this.files || []);
                if (files.length > 5) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Too many photos',
                        text: 'You can attach at most 5 proof photos. Only the first 5 will be uploaded.',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false,
                    });
                }
                files.slice(0, 5).forEach(file => {
                    if (!file.type.startsWith('image/')) return;
                    const url = URL.createObjectURL(file);
                    const $thumb = $(`
                        <div class="position-relative" style="width: 88px; height: 88px;">
                            <img src="${url}" alt="" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px; border: 1px solid #e4e6ef;">
                        </div>
                    `);
                    $preview.append($thumb);
                });
            });
            $('#markPickedUpModal').on('hidden.bs.modal', function () {
                $('#pickupProofsInput').val('');
                $('#pickupProofsPreview').empty();
            });

            const $modal = $('#recordPaymentModal');
            if (!$modal.length) return;

            const $bankFields = $modal.find('.js-bank-fields');
            const $bankInputs = $modal.find('.js-bank-field');
            const $refLabel = $modal.find('.js-ref-label');
            const $radios = $modal.find('input[name="payment_type"]');
            const $storeSelect = $('#recordPaymentStoreSelect');
            const $bankSelect = $('#recordPaymentBankSelect');

            const refLabelByType = {
                '{{ \App\Models\Pos\Sale::PAYMENT_EWALLET }}': 'GCash Reference Number',
                '{{ \App\Models\Pos\Sale::PAYMENT_BANK_TRANSFER }}': 'Transfer Reference',
                '{{ \App\Models\Pos\Sale::PAYMENT_CHEQUE }}': 'Cheque Number',
            };

            function toggleBank() {
                const selected = $modal.find('input[name="payment_type"]:checked').val();
                const needsBank = selected && selected !== '{{ \App\Models\Pos\Sale::PAYMENT_CASH }}';

                $bankFields.toggle(!!needsBank);
                $bankInputs.prop('required', !!needsBank);
                if (needsBank && refLabelByType[selected]) {
                    $refLabel[0].firstChild.nodeValue = refLabelByType[selected] + ' ';
                }
            }

            // Select2 with AJAX so the bank list stays current — admins
            // adding/editing banks in Settings see them immediately on
            // the next dropdown open without reloading this page.
            //
            // dropdownParent is the modal BODY (not the whole modal) so
            // Select2 positions the dropdown below the field rather than
            // at the click point — without this the dropdown appears
            // overlapping the cursor and the click that opened it lands
            // on whatever result was under the mouse.
            const $modalBody = $modal.find('.modal-body').first();
            const select2Base = {
                dropdownParent: $modalBody,
                minimumInputLength: 0,
                closeOnSelect: true,
                selectOnClose: false,
                width: '100%',
            };

            $storeSelect.select2($.extend({}, select2Base, {
                placeholder: 'Search a store…',
                ajax: {
                    url: "{{ route('stores.select') }}",
                    dataType: 'json',
                    delay: 200,
                    data: params => ({ term: params.term ?? '' }),
                    processResults: data => ({ results: data }),
                    cache: false,
                },
            }));

            $bankSelect.select2($.extend({}, select2Base, {
                placeholder: 'Search a bank…',
                ajax: {
                    url: "{{ route('banks.select') }}",
                    dataType: 'json',
                    delay: 200,
                    data: params => ({ term: params.term ?? '' }),
                    processResults: data => ({ results: data }),
                    cache: false,
                },
            }));

            // Bootstrap 5 modals steal focus immediately when the modal
            // gains focus, which yanks it away from the Select2 search
            // box and registers the original click as a result-click.
            // Pushing focus back to the search box on open prevents
            // that misclick.
            $(document).on('select2:open', function () {
                const search = document.querySelector('.select2-container--open .select2-search__field');
                if (search) {
                    setTimeout(() => search.focus(), 0);
                }
            });

            $radios.on('change', toggleBank);
            $modal.on('shown.bs.modal', toggleBank);

            // Reset selections when the modal closes so a re-open
            // doesn't carry over yesterday's choice.
            $modal.on('hidden.bs.modal', function () {
                $storeSelect.val(null).trigger('change');
                $bankSelect.val(null).trigger('change');
                const $proofs = $('#recordPaymentProofs');
                $proofs.val('');
                $('#recordPaymentProofsPreview').empty();
            });

            // Proof-of-payment client-side preview thumbnails. Doesn't
            // upload anything until the form is submitted — just a
            // visual confirmation the admin picked the right files.
            $('#recordPaymentProofs').on('change', function () {
                const $preview = $('#recordPaymentProofsPreview').empty();
                const files = Array.from(this.files || []);
                if (files.length > 5) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Too many photos',
                        text: 'You can attach at most 5 proof photos. Only the first 5 will be uploaded.',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false,
                    });
                }
                files.slice(0, 5).forEach(file => {
                    if (!file.type.startsWith('image/')) return;
                    const url = URL.createObjectURL(file);
                    const $thumb = $(`
                        <div class="position-relative" style="width: 88px; height: 88px;">
                            <img src="${url}" alt="" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px; border: 1px solid #e4e6ef;">
                        </div>
                    `);
                    $preview.append($thumb);
                });
            });
        });
    </script>
@endsection
