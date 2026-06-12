<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\mobile\Purchase\PaymentRequest;
use App\Http\Requests\API\v1\mobile\Purchase\StoreRequest;
use App\Http\Requests\API\v1\mobile\Purchase\UpdateRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchaseApproval;
use App\Models\InventoryManagement\PurchaseLine;
use App\Models\InventoryManagement\PurchasePayment;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\ItemUnit;
use App\Models\User;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Purchase::where(function ($q) use ($request) {
            $q->where('po', 'like', '%'.$request->query('term').'%');
            $q->orWhereRelation('store', 'name', 'like', '%'.$request->query('term').'%');
            $q->orWhere('purchased', 'like', '%'.$request->query('term').'%');
            $q->orWhere('invoice_no', 'like', '%'.$request->query('term').'%');
            $q->orWhereRelation('supplier', 'name', 'like', '%'.$request->query('term').'%');
            $q->orWhereRelation('creator', 'name', 'like', '%'.$request->query('term').'%');
        })->with([
            'lines' => function ($q) {
                $q->with(['item' => function ($q) {
                    $q->with([
                        'itemUnits' => function ($q) {
                            $q->with(['unit']);
                        },
                    ]);
                }]);
                $q->with('unit');
            },
            'store',
            'supplier',
            'creator',
            'receiver',
            'latestApproval.approver',
        ])
            ->where('status', '<', 3);

        // Filter by approval status if provided
        if ($request->has('approval_status') && $request->query('approval_status') !== null) {
            $query->where('approval_status', $request->query('approval_status'));
        }

        // Filter by receiving status if provided
        // Frontend values: 1 = Open, 2 = Partial, 3 = Received
        // Backend values: 1 = Open/Pending, 0 = Has received items
        if ($request->has('status') && $request->query('status') !== null) {
            $statusFilter = (int) $request->query('status');

            if ($statusFilter === 1) {
                // Open: status = 1 (no items received yet)
                $query->where('status', 1);
            } elseif ($statusFilter === 2) {
                // Partial: status = 0 AND received < items
                $query->where('status', 0)
                    ->whereColumn('received', '<', 'items');
            } elseif ($statusFilter === 3) {
                // Received: status = 0 AND received >= items
                $query->where('status', 0)
                    ->whereColumn('received', '>=', 'items');
            }
        }

        // Filter by supplier if provided
        if ($request->has('supplier_id') && $request->query('supplier_id') !== null) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        // Filter by store if provided
        if ($request->has('store_id') && $request->query('store_id') !== null) {
            $query->where('store_id', $request->query('store_id'));
        }

        $purchases = $query->orderBy('id', 'desc')->get();

        return $this->success(['purchases' => $purchases]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $po = Purchase::where('user_id', auth()->user()->user_id)->where('status', '<>', 3)->latest()->first();
        $po = ($po) ? $po->po + 1 : 1000;
        $validated['po'] = $po;
        $validated['purchased'] = Carbon::parse($validated['purchased'])->toDateTimeString();
        $purchase = Purchase::create($validated);
        $purchaseLine = [];
        foreach ($validated['lines'] as $line) {
            $purchaseLine[] = [
                'item_id' => $line['product_id'],
                'qty' => $line['qty'],
                'cost' => $line['price'],
                'unit_id' => $line['unit_id'],
                'unit_qty' => $line['unit_qty'],
                'unit_name' => $line['unit_name'],
                'received' => 0,
                'purchase_id' => $purchase->id,
            ];
        }
        PurchaseLine::insert($purchaseLine);
        $purchase = Purchase::where('id', $purchase->id)
            ->with([
                'lines' => function ($query) {
                    $query->with(['item' => function ($query) {
                        $query->with([
                            'itemUnits' => function ($query) {
                                $query->with(['unit']);
                            },
                        ]);
                    }]);
                    $query->with('unit');
                },
                'supplier',
                'store',
                'creator',
                'receiver',
            ])
            ->latest()
            ->first();

        return $this->created(['purchase_order' => $purchase], 'Purchase Order successfully created!');
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load([
            'lines' => function ($query) {
                $query->with(['item' => function ($query) {
                    $query->with([
                        'itemUnits' => function ($query) {
                            $query->with(['unit']);
                        },
                    ]);
                }]);
                $query->with('unit');
            },
            'store',
            'supplier',
            'creator',
            'receiver',
            'latestApproval.approver',
            'payments.bank',
            'payments.createdBy',
        ]);

        return $this->success($purchase);
    }

    public function update(UpdateRequest $request, Purchase $purchase): JsonResponse
    {
        // Prevent editing approved POs
        if ($purchase->isApproved()) {
            return $this->error('This purchase order has been approved and cannot be edited.', 403);
        }

        // Prevent editing POs that have received items
        if ($purchase->status != 1) {
            return $this->error('This purchase order has items received and cannot be edited.', 403);
        }

        $validated = $request->validated();
        $purchase->update([
            'supplier_id' => $validated['supplier_id'],
            'store_id' => $validated['store_id'],
            'purchased' => Carbon::parse($validated['purchased'])->toDateTimeString(),
            'expected' => $validated['expected'],
            'invoice_no' => $validated['invoice_no'],
            'items' => $validated['items'],
            'total' => $validated['total'],
        ]);
        PurchaseLine::where('purchase_id', $purchase->id)->delete();
        $purchaseLine = [];
        foreach ($validated['lines'] as $line) {
            $purchaseLine[] = [
                'item_id' => $line['product_id'],
                'qty' => $line['qty'],
                'cost' => $line['price'],
                'unit_id' => $line['unit_id'],
                'unit_qty' => $line['unit_qty'],
                'unit_name' => $line['unit_name'],
                'received' => 0,
                'purchase_id' => $purchase->id,
            ];
        }
        PurchaseLine::insert($purchaseLine);
        $purchase = Purchase::where('id', $purchase->id)
            ->with([
                'lines' => function ($query) {
                    $query->with(['item']);
                },
                'supplier',
                'store',
                'creator',
                'receiver',
            ])
            ->latest()
            ->first();

        return $this->success(['purchase_order' => $purchase], 'Purchase Order successfully updated!');
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        return $this->success(null);
    }

    /**
     * Get purchase orders pending approval
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $purchases = Purchase::where('approval_status', Purchase::APPROVAL_PENDING)
            ->where('user_id', auth()->user()->user_id)
            ->with([
                'lines' => function ($query) {
                    $query->with(['item', 'unit']);
                },
                'store',
                'supplier',
                'creator',
                'receiver',
                'latestApproval.approver',
            ])
            ->orderBy('id', 'desc')
            ->get();

        return $this->success(['purchases' => $purchases]);
    }

    /**
     * Get count of pending approvals
     */
    public function pendingApprovalsCount(): JsonResponse
    {
        $count = Purchase::where('approval_status', Purchase::APPROVAL_PENDING)
            ->where('user_id', auth()->user()->user_id)
            ->count();

        return $this->success(['count' => $count]);
    }

    /**
     * Approve a purchase order
     */
    public function approve(Purchase $purchase): JsonResponse
    {
        // Check if user has permission to approve
        $user = auth()->user();
        if (! $user->role || ! $user->role->prchs_approve) {
            return $this->error('You do not have permission to approve purchase orders.', 403);
        }

        // Prevent self-approval
        if ($purchase->created_by === $user->id) {
            return $this->error('You cannot approve your own purchase order.', 403);
        }

        // Check if PO is pending approval
        if (! $purchase->isPendingApproval()) {
            return $this->error('This purchase order is not pending approval.', 400);
        }

        // Update approval status
        $purchase->update(['approval_status' => Purchase::APPROVAL_APPROVED]);

        // Create approval record
        PurchaseApproval::create([
            'purchase_id' => $purchase->id,
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        // Reload with relationships
        $purchase->load([
            'lines.item',
            'store',
            'supplier',
            'creator',
            'receiver',
            'latestApproval.approver',
        ]);

        return $this->success(
            ['purchase_order' => $purchase],
            'Purchase order approved successfully.'
        );
    }

    /**
     * Reject a purchase order
     */
    public function reject(Request $request, Purchase $purchase): JsonResponse
    {
        // Validate rejection comment
        $request->validate([
            'rejection_comment' => 'required|string|min:10',
        ]);

        // Check if user has permission to approve/reject
        $user = auth()->user();
        if (! $user->role || ! $user->role->prchs_approve) {
            return $this->error('You do not have permission to reject purchase orders.', 403);
        }

        // Prevent self-rejection
        if ($purchase->created_by === $user->id) {
            return $this->error('You cannot reject your own purchase order.', 403);
        }

        // Check if PO is pending approval
        if (! $purchase->isPendingApproval()) {
            return $this->error('This purchase order is not pending approval.', 400);
        }

        // Update approval status
        $purchase->update(['approval_status' => Purchase::APPROVAL_REJECTED]);

        // Create approval record with rejection comment
        PurchaseApproval::create([
            'purchase_id' => $purchase->id,
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_comment' => $request->rejection_comment,
        ]);

        // Reload with relationships
        $purchase->load([
            'lines.item',
            'store',
            'supplier',
            'creator',
            'receiver',
            'latestApproval.approver',
        ]);

        return $this->success(
            ['purchase_order' => $purchase],
            'Purchase order rejected.'
        );
    }

    /**
     * Submit a purchase order for approval (change from Draft/Rejected to Pending)
     */
    public function submitForApproval(Purchase $purchase): JsonResponse
    {
        // Only allow draft or rejected POs to be submitted
        if ($purchase->approval_status !== Purchase::APPROVAL_DRAFT &&
            $purchase->approval_status !== Purchase::APPROVAL_REJECTED) {
            return $this->error('This purchase order cannot be submitted for approval.', 400);
        }

        // Update approval status to pending
        $purchase->update(['approval_status' => Purchase::APPROVAL_PENDING]);

        // Create new pending approval record
        PurchaseApproval::create([
            'purchase_id' => $purchase->id,
            'status' => 'pending',
        ]);

        // Notify users with purchase approval permission
        try {
            $approverRoleIds = Role::where('prchs_approve', true)->pluck('id');
            $approverIds = User::whereIn('role_id', $approverRoleIds)->pluck('id')->toArray();
            $supplierName = $purchase->supplier->name ?? 'Unknown';
            (new FcmService)->sendToUsers(
                $approverIds,
                'PO Approval Needed',
                "PO #{$purchase->id} from {$supplierName} needs your approval.",
                ['type' => 'po_approval', 'id' => (string) $purchase->id]
            );
        } catch (\Exception $e) {
            \Log::warning('FCM notification failed for PO approval: '.$e->getMessage());
        }

        // Reload with relationships
        $purchase->load([
            'lines.item',
            'store',
            'supplier',
            'creator',
            'receiver',
            'latestApproval.approver',
        ]);

        return $this->success(
            ['purchase_order' => $purchase],
            'Purchase order submitted for approval.'
        );
    }

    /**
     * Record a payment for a purchase order
     */
    public function pay(PaymentRequest $request, Purchase $purchase): JsonResponse
    {
        $validated = $request->validated();
        $bank = Bank::findOrFail($validated['bank_id']);

        $result = DB::transaction(function () use ($validated, $purchase, $bank) {
            $balanceBefore = $bank->balance;
            $balanceAfter = $balanceBefore - $validated['amount'];

            // Create bank transaction (withdrawal)
            $bankTransaction = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_WITHDRAWAL,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Payment for PO #'.$purchase->po,
                'payee' => $purchase->supplier?->name ?? 'Supplier',
                'transaction_date' => $validated['payment_date'],
                'created_by' => auth()->id(),
            ]);

            // Update bank balance
            $bank->update(['balance' => $balanceAfter]);

            // Create purchase payment record
            $payment = PurchasePayment::create([
                'reference_number' => PurchasePayment::generateReferenceNumber(),
                'purchase_id' => $purchase->id,
                'bank_id' => $bank->id,
                'bank_transaction_id' => $bankTransaction->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'check_number' => $validated['check_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Update purchase amount_paid and payment status
            $purchase->amount_paid = ($purchase->amount_paid ?? 0) + $validated['amount'];
            $purchase->save();
            $purchase->updatePaymentStatus();

            return [
                'payment' => $payment,
                'bank_transaction' => $bankTransaction,
            ];
        });

        $result['payment']->load(['bank', 'createdBy']);

        // Reload purchase with updated data
        $purchase->refresh();
        $purchase->load([
            'lines.item',
            'store',
            'supplier',
            'creator',
            'receiver',
            'latestApproval.approver',
            'payments.bank',
            'payments.createdBy',
        ]);

        return $this->created([
            'purchase_order' => $purchase,
            'payment' => $result['payment'],
            'new_bank_balance' => $bank->fresh()->balance,
        ], 'Payment of '.number_format($validated['amount'], 2).' recorded successfully.');
    }

    /**
     * Get payment history for a purchase order
     */
    public function payments(Purchase $purchase): JsonResponse
    {
        $payments = $purchase->payments()
            ->with(['bank', 'bankTransaction', 'createdBy'])
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'reference_number' => $payment->reference_number,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    'payment_method' => $payment->payment_method,
                    'payment_method_name' => $payment->payment_method_name,
                    'check_number' => $payment->check_number,
                    'notes' => $payment->notes,
                    'bank' => $payment->bank ? [
                        'id' => $payment->bank->id,
                        'bank_name' => $payment->bank->bank_name,
                        'account_name' => $payment->bank->account_name,
                    ] : null,
                    'created_by' => $payment->createdBy ? [
                        'id' => $payment->createdBy->id,
                        'name' => $payment->createdBy->name,
                    ] : null,
                    'created_at' => $payment->created_at,
                ];
            });

        return $this->success([
            'payments' => $payments,
            'summary' => [
                'total' => $purchase->total ?? 0,
                'amount_paid' => $purchase->amount_paid ?? 0,
                'remaining_balance' => $purchase->remaining_balance,
                'payment_status' => $purchase->payment_status,
                'payment_status_label' => $purchase->payment_status_label,
            ],
            'payment_methods' => PurchasePayment::paymentMethods(),
        ]);
    }

    /**
     * Receive items for a purchase order
     */
    public function receive(Request $request, Purchase $purchase): JsonResponse
    {
        // Validate request
        $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.line_id' => 'required|integer|exists:purchase_lines,id',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.update_cost' => 'boolean',
        ]);

        // §C6 of development/specs/purchase_order_audit_and_remediation.md
        // — standardize on 409 Conflict (the OpenClaw shape). The resource
        // exists but is in the wrong state for the operation; that's a
        // conflict, not an authorization failure (which 403 implies).
        if (! $purchase->isApproved()) {
            return $this->error('This purchase order must be approved before items can be received.', 409);
        }

        $result = DB::transaction(function () use ($request, $purchase) {
            $totalReceived = 0;

            foreach ($request->lines as $lineData) {
                $quantityToReceive = $lineData['quantity'];

                // Skip if nothing to receive
                if ($quantityToReceive <= 0) {
                    continue;
                }

                $line = PurchaseLine::find($lineData['line_id']);

                // Validate line belongs to this purchase
                if ($line->purchase_id != $purchase->id) {
                    throw new \Exception('Line item does not belong to this purchase order.');
                }

                // Check if receiving more than remaining
                $remaining = $line->qty - $line->received;
                if ($quantityToReceive > $remaining) {
                    throw new \Exception("Cannot receive more than remaining quantity for {$line->item->name}. Remaining: {$remaining}");
                }

                // Update line received count
                $line->update([
                    'received' => $line->received + $quantityToReceive,
                ]);

                // Get unit conversion
                $itemUnit = ItemUnit::where('unit_id', $line->unit_id)
                    ->where('item_id', $line->item_id)
                    ->first();
                $unitQty = $itemUnit ? $itemUnit->qty : 1;

                // Update stock in item_stores
                $itemStore = ItemStore::where('item_id', $line->item_id)
                    ->where('store_id', $purchase->store_id)
                    ->first();

                if ($itemStore) {
                    $itemStore->update([
                        'stock' => $itemStore->stock + ($quantityToReceive * $unitQty),
                    ]);
                } else {
                    // Create item_store record if doesn't exist
                    ItemStore::create([
                        'item_id' => $line->item_id,
                        'store_id' => $purchase->store_id,
                        'stock' => $quantityToReceive * $unitQty,
                        'status' => 1,
                    ]);
                }

                // Optionally update item cost
                if (! empty($lineData['update_cost'])) {
                    $item = Item::find($line->item_id);
                    if ($item) {
                        $item->update([
                            'prev_cost' => $item->cost,
                            'cost' => $line->cost / $unitQty,
                        ]);
                    }
                }

                $totalReceived += $quantityToReceive;
            }

            // Update purchase totals
            $purchase->update([
                'received' => $purchase->received + $totalReceived,
                'received_by' => auth()->user()->id,
                'status' => 0, // 0 = has received items
            ]);

            return $totalReceived;
        });

        // Reload purchase with relationships
        $purchase->refresh();
        $purchase->load([
            'lines' => function ($query) {
                $query->with(['item', 'unit']);
            },
            'store',
            'supplier',
            'creator',
            'receiver',
            'latestApproval.approver',
            'payments.bank',
            'payments.createdBy',
        ]);

        // §C5 — additively bring the response into parity with the
        // OpenClaw receive shape: a `lines` summary array with per-line
        // ordered/received/remaining quantities, and a top-level
        // `received_this_call` field. Existing `purchase_order` key
        // stays unchanged so the dashboard's current consumers don't
        // break — the new fields are additions, not replacements.
        $linesSummary = $purchase->lines->map(fn (PurchaseLine $l) => [
            'id' => (int) $l->id,
            'item_id' => (int) $l->item_id,
            'ordered_qty' => round((float) $l->qty, 4),
            'received_qty' => round((float) ($l->received ?? 0), 4),
            'remaining_qty' => round((float) $l->qty - (float) ($l->received ?? 0), 4),
        ])->values();

        return $this->success(
            [
                'purchase_order' => $purchase,
                'lines' => $linesSummary,
                'received_this_call' => round((float) $result, 4),
            ],
            "Successfully received {$result} item(s) for PO #{$purchase->po}."
        );
    }
}
