<?php

use App\Http\Controllers\API\v1\openclaw\AlertsController;
use App\Http\Controllers\API\v1\openclaw\AnalyticsController;
use App\Http\Controllers\API\v1\openclaw\AttendanceController;
use App\Http\Controllers\API\v1\openclaw\AuditLogController;
use App\Http\Controllers\API\v1\openclaw\BankController;
use App\Http\Controllers\API\v1\openclaw\CashOutController;
use App\Http\Controllers\API\v1\openclaw\CustomerController;
use App\Http\Controllers\API\v1\openclaw\EcommerceOrderController;
use App\Http\Controllers\API\v1\openclaw\ExpenseCategoryController;
use App\Http\Controllers\API\v1\openclaw\ExpenseController;
use App\Http\Controllers\API\v1\openclaw\InventoryController;
use App\Http\Controllers\API\v1\openclaw\PurchaseController;
use App\Http\Controllers\API\v1\openclaw\SalesController;
use App\Http\Controllers\API\v1\openclaw\SettingsController;
use App\Http\Controllers\API\v1\openclaw\SnapshotController;
use App\Http\Controllers\API\v1\openclaw\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OpenClaw API Routes
|--------------------------------------------------------------------------
|
| Read-mostly data API consumed by OpenClaw / LeteresBot.
| Authenticates via Bearer token against api_tokens (openclaw guard).
| Tenant scoping: every endpoint filters by auth()->user()->user_id.
|
| Authorization is by token "abilities" (api_tokens.abilities JSON column):
|   - openclaw:read              — all GET endpoints
|   - openclaw:expenses:create   — POST /expenses
|   - *                          — wildcard, all current and future abilities
| When a token's abilities is NULL, it defaults to ['openclaw:read'] so
| pre-existing tokens never silently gain write access.
|
| Prefix: api/v1/openclaw
|
*/

Route::name('api.openclaw.')
    ->prefix('v1/openclaw')
    ->middleware(['auth:openclaw', 'throttle:openclaw'])
    ->group(function () {
        // --- Read endpoints (require openclaw:read) ---
        Route::middleware('openclaw.ability:openclaw:read')->group(function () {
            Route::get('/snapshot', [SnapshotController::class, 'index'])->name('snapshot');

            Route::prefix('sales')->name('sales.')->group(function () {
                Route::get('/summary', [SalesController::class, 'summary'])->name('summary');
                Route::get('/by-item', [SalesController::class, 'byItem'])->name('by-item');
                Route::get('/refunds', [SalesController::class, 'refunds'])->name('refunds');
            });

            Route::prefix('inventory')->name('inventory.')->group(function () {
                Route::get('/stock', [InventoryController::class, 'stock'])->name('stock');
                Route::get('/low-stock', [InventoryController::class, 'lowStock'])->name('low-stock');
                Route::get('/suppliers', [InventoryController::class, 'suppliers'])->name('suppliers');
            });

            Route::prefix('customers')->name('customers.')->group(function () {
                Route::get('/top', [CustomerController::class, 'top'])->name('top');
                Route::get('/outstanding-credit', [CustomerController::class, 'outstandingCredit'])->name('outstanding-credit');
                Route::get('/points-summary', [CustomerController::class, 'pointsSummary'])->name('points-summary');
            });

            Route::prefix('analytics')->name('analytics.')->group(function () {
                Route::get('/peak-hours', [AnalyticsController::class, 'peakHours'])->name('peak-hours');
            });

            Route::prefix('attendance')->name('attendance.')->group(function () {
                Route::get('/summary', [AttendanceController::class, 'summary'])->name('summary');
                Route::get('/records', [AttendanceController::class, 'records'])->name('records');
            });

            Route::get('/cash-outs', [CashOutController::class, 'index'])->name('cash-outs.index');

            Route::prefix('banks')->name('banks.')->group(function () {
                Route::get('/', [BankController::class, 'accounts'])->name('index');
                Route::get('/balances', [BankController::class, 'balances'])->name('balances');
                Route::get('/accounts', [BankController::class, 'accounts'])->name('accounts');
                Route::get('/summary', [BankController::class, 'summary'])->name('summary');
                Route::get('/transactions', [BankController::class, 'transactions'])->name('transactions');
            });

            Route::prefix('expenses')->name('expenses.')->group(function () {
                Route::get('/', [ExpenseController::class, 'index'])->name('index');
                Route::get('/summary', [ExpenseController::class, 'summary'])->name('summary');
                Route::get('/categories', [ExpenseController::class, 'categories'])->name('categories');
            });

            Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');

            Route::prefix('suppliers')->name('suppliers.')->group(function () {
                Route::get('/payables-summary', [SupplierController::class, 'payablesSummary'])->name('payables-summary');
                Route::get('/{supplier}/payable', [SupplierController::class, 'payable'])->name('payable');
            });

            Route::prefix('purchases')->name('purchases.')->group(function () {
                Route::get('/', [PurchaseController::class, 'index'])->name('index');
                Route::get('/pending-approvals', [PurchaseController::class, 'pendingApprovals'])->name('pending-approvals');
                Route::get('/{purchase}', [PurchaseController::class, 'show'])->name('show');
                Route::get('/{purchase}/payments', [PurchaseController::class, 'payments'])->name('payments');
            });

            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

            Route::get('/alerts', [AlertsController::class, 'index'])->name('alerts.index');

            Route::prefix('ecommerce-orders')->name('ecommerce-orders.')->group(function () {
                Route::get('/', [EcommerceOrderController::class, 'index'])->name('index');
                Route::get('/pending', [EcommerceOrderController::class, 'pending'])->name('pending');
                Route::get('/{ecommerceOrder}', [EcommerceOrderController::class, 'show'])->name('show');
            });
        });

        // --- Write endpoints (each declares its own ability) ---
        Route::post('/expenses', [ExpenseController::class, 'store'])
            ->middleware('openclaw.ability:openclaw:expenses:create')
            ->name('expenses.store');

        Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])
            ->middleware('openclaw.ability:openclaw:expenses:update')
            ->name('expenses.update');

        Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void'])
            ->middleware('openclaw.ability:openclaw:expenses:void')
            ->name('expenses.void');

        Route::post('/expenses/categories', [ExpenseCategoryController::class, 'store'])
            ->middleware('openclaw.ability:openclaw:expense-categories:write')
            ->name('expenses.categories.store');

        Route::patch('/expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
            ->middleware('openclaw.ability:openclaw:expense-categories:write')
            ->name('expenses.categories.update');

        Route::patch('/settings', [SettingsController::class, 'update'])
            ->middleware('openclaw.ability:openclaw:settings:write')
            ->name('settings.update');

        Route::patch('/items/{item}/alert', [InventoryController::class, 'setItemAlert'])
            ->middleware('openclaw.ability:openclaw:items:write')
            ->name('items.alert');

        Route::patch('/banks/{bank}/alert', [BankController::class, 'setAlert'])
            ->middleware('openclaw.ability:openclaw:banks:write')
            ->name('banks.alert');

        Route::post('/expenses/{expense}/receipt', [ExpenseController::class, 'uploadReceipt'])
            ->middleware('openclaw.ability:openclaw:expenses:upload-receipt')
            ->name('expenses.receipt.upload');

        Route::delete('/expenses/{expense}/receipt', [ExpenseController::class, 'deleteReceipt'])
            ->middleware('openclaw.ability:openclaw:expenses:upload-receipt')
            ->name('expenses.receipt.delete');

        Route::post('/banks/transactions/{transaction}/proof', [BankController::class, 'uploadTransactionProof'])
            ->middleware('openclaw.ability:openclaw:banks:write')
            ->name('banks.transactions.proof.upload');

        Route::delete('/banks/transactions/{transaction}/proof', [BankController::class, 'deleteTransactionProof'])
            ->middleware('openclaw.ability:openclaw:banks:write')
            ->name('banks.transactions.proof.delete');

        Route::patch('/suppliers/{supplier}/payment-terms', [SupplierController::class, 'setPaymentTerms'])
            ->middleware('openclaw.ability:openclaw:suppliers:write')
            ->name('suppliers.payment-terms');

        Route::post('/banks/{bank}/adjustment', [BankController::class, 'adjust'])
            ->middleware('openclaw.ability:openclaw:banks:adjust')
            ->name('banks.adjustment');

        Route::post('/banks/{bank}/deposit', [BankController::class, 'deposit'])
            ->middleware('openclaw.ability:openclaw:banks:movements')
            ->name('banks.deposit');

        Route::post('/banks/{bank}/withdrawal', [BankController::class, 'withdrawal'])
            ->middleware('openclaw.ability:openclaw:banks:movements')
            ->name('banks.withdrawal');

        Route::post('/banks/{bank}/transfer', [BankController::class, 'transfer'])
            ->middleware('openclaw.ability:openclaw:banks:movements')
            ->name('banks.transfer');

        Route::post('/purchases/{purchase}/approve', [PurchaseController::class, 'approve'])
            ->middleware('openclaw.ability:openclaw:purchases:approve')
            ->name('purchases.approve');

        Route::post('/purchases/{purchase}/reject', [PurchaseController::class, 'reject'])
            ->middleware('openclaw.ability:openclaw:purchases:approve')
            ->name('purchases.reject');

        Route::post('/purchases/{purchase}/pay', [PurchaseController::class, 'pay'])
            ->middleware('openclaw.ability:openclaw:purchases:pay')
            ->name('purchases.pay');

        Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])
            ->middleware('openclaw.ability:openclaw:purchases:receive')
            ->name('purchases.receive');

        Route::post('/purchases/{purchase}/payments/{payment}/void', [PurchaseController::class, 'voidPayment'])
            ->middleware('openclaw.ability:openclaw:purchases:void-payment')
            ->name('purchases.payments.void');

        // --- Ecommerce order lifecycle ---
        Route::post('/ecommerce-orders/{ecommerceOrder}/verify', [EcommerceOrderController::class, 'verify'])
            ->middleware('openclaw.ability:openclaw:ecommerce-orders:verify')
            ->name('ecommerce-orders.verify');

        Route::post('/ecommerce-orders/{ecommerceOrder}/cancel', [EcommerceOrderController::class, 'cancel'])
            ->middleware('openclaw.ability:openclaw:ecommerce-orders:cancel')
            ->name('ecommerce-orders.cancel');

        Route::post('/ecommerce-orders/{ecommerceOrder}/record-payment', [EcommerceOrderController::class, 'recordPayment'])
            ->middleware('openclaw.ability:openclaw:ecommerce-orders:record-payment')
            ->name('ecommerce-orders.record-payment');

        Route::post('/ecommerce-orders/{ecommerceOrder}/mark-preparing', [EcommerceOrderController::class, 'markPreparing'])
            ->middleware('openclaw.ability:openclaw:ecommerce-orders:mark-preparing')
            ->name('ecommerce-orders.mark-preparing');

        Route::post('/ecommerce-orders/{ecommerceOrder}/mark-picked-up', [EcommerceOrderController::class, 'markPickedUp'])
            ->middleware('openclaw.ability:openclaw:ecommerce-orders:mark-picked-up')
            ->name('ecommerce-orders.mark-picked-up');
    });
