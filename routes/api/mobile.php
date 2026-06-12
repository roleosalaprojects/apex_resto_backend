<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile Back Office API Routes
|--------------------------------------------------------------------------
|
| Routes for the mobile back office application.
| Prefix: api/v1/mobile
|
*/

Route::name('api.')->prefix('v1/mobile')->group(function () {
    Route::post('/login', [\App\Http\Controllers\API\v1\mobile\UserController::class, 'login']);

    // Protected Routes
    Route::middleware('auth:api')->group(function () {
        // Get User Details
        Route::get('/getUser', [\App\Http\Controllers\API\v1\mobile\UserController::class, 'getUser']);
        Route::post('/logout', [\App\Http\Controllers\API\v1\mobile\UserController::class, 'logout']);
        Route::get('/sales-summary', [\App\Http\Controllers\API\v1\mobile\ReportController::class, 'salesSummary']);
        Route::get('/sales-by-item', [\App\Http\Controllers\API\v1\mobile\ReportController::class, 'itemsData']);
        Route::get('/products/{itemId}/performance', [\App\Http\Controllers\API\v1\mobile\ReportController::class, 'productPerformance']);

        // Dashboard Widgets
        Route::prefix('dashboard')->group(function () {
            Route::get('/sales-ticker', [\App\Http\Controllers\API\v1\mobile\DashboardController::class, 'salesTicker']);
            Route::get('/top-products', [\App\Http\Controllers\API\v1\mobile\DashboardController::class, 'topProducts']);
            Route::get('/revenue-comparison', [\App\Http\Controllers\API\v1\mobile\DashboardController::class, 'revenueComparison']);
            Route::get('/staff-leaderboard', [\App\Http\Controllers\API\v1\mobile\DashboardController::class, 'staffLeaderboard']);
        });

        // Customer Analytics
        Route::prefix('customers/analytics')->group(function () {
            Route::get('/top', [\App\Http\Controllers\API\v1\mobile\CustomerAnalyticsController::class, 'topCustomers']);
            Route::get('/trends', [\App\Http\Controllers\API\v1\mobile\CustomerAnalyticsController::class, 'trends']);
            Route::get('/points-history', [\App\Http\Controllers\API\v1\mobile\CustomerAnalyticsController::class, 'pointsHistory']);
            Route::get('/points-summary', [\App\Http\Controllers\API\v1\mobile\CustomerAnalyticsController::class, 'pointsSummary']);
            Route::get('/outstanding-credits', [\App\Http\Controllers\API\v1\mobile\CustomerAnalyticsController::class, 'outstandingCredits']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/inventory', [\App\Http\Controllers\API\v1\mobile\ReportsController::class, 'inventory']);
            Route::get('/inventory/low-stock', [\App\Http\Controllers\API\v1\mobile\ReportsController::class, 'lowStock']);
            Route::get('/suppliers', [\App\Http\Controllers\API\v1\mobile\ReportsController::class, 'suppliers']);
            Route::get('/categories', [\App\Http\Controllers\API\v1\mobile\ReportsController::class, 'categories']);
            Route::get('/categories/{categoryId}/items', [\App\Http\Controllers\API\v1\mobile\ReportsController::class, 'categoryItems']);
            Route::get('/refunds', [\App\Http\Controllers\API\v1\mobile\ReportsController::class, 'refunds']);
            Route::get('/refunds/summary', [\App\Http\Controllers\API\v1\mobile\ReportsController::class, 'refundSummary']);

            // Sales Summary (analytics)
            Route::get('/sales-summary', [\App\Http\Controllers\API\v1\Analytics\ReportController::class, 'salesSummary']);

            // Scheduled Report Recipients
            Route::get('/recipients', [\App\Http\Controllers\API\v1\Analytics\ReportController::class, 'recipients']);
            Route::post('/recipients', [\App\Http\Controllers\API\v1\Analytics\ReportController::class, 'storeRecipient']);
            Route::delete('/recipients/{reportRecipient}', [\App\Http\Controllers\API\v1\Analytics\ReportController::class, 'destroyRecipient']);
        });

        // Inventory Management
        Route::prefix('inventory')->group(function () {
            // Stock Adjustments
            Route::get('/adjustments', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'adjustments']);
            Route::post('/adjustments', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'createAdjustment']);
            Route::get('/adjustments/{id}', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'showAdjustment']);
            Route::get('/adjustment-reasons', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'adjustmentReasons']);
            // Stock Transfers
            Route::get('/transfers', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'transfers']);
            Route::post('/transfers', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'createTransfer']);
            Route::get('/transfers/{id}', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'showTransfer']);
            Route::patch('/transfers/{id}', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'updateTransfer']);
            // Inventory Counts
            Route::get('/counts', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'counts']);
            Route::post('/counts', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'createCount']);
            Route::get('/counts/{id}', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'showCount']);
            Route::patch('/counts/{id}/items', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'updateCountItem']);
            Route::delete('/counts/{id}/items/{lineId}', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'removeCountItem']);
            Route::post('/counts/{id}/finalize', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'finalizeCount']);
            Route::get('/count-sheet', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'countSheet']);
            // Low Stock Alerts
            Route::get('/low-stock', [\App\Http\Controllers\API\v1\mobile\InventoryController::class, 'lowStock']);
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\mobile\ItemController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\API\v1\mobile\ItemController::class, 'store']);

            // Product Image Updater routes (must be before {product} routes)
            Route::get('/without-images', [\App\Http\Controllers\API\ProductImageController::class, 'getProductsWithoutImages']);
            Route::post('/upload-image', [\App\Http\Controllers\API\ProductImageController::class, 'uploadImage']);
            Route::post('/batch-upload-update', [\App\Http\Controllers\API\ProductImageController::class, 'batchUploadAndUpdate']);

            Route::put('/{product}', [\App\Http\Controllers\API\v1\mobile\ItemController::class, 'update']);
            Route::post('/{product}/image', [\App\Http\Controllers\API\v1\mobile\ItemController::class, 'updateImage']);
            Route::get('/{product}/price-history', [\App\Http\Controllers\API\v1\mobile\ItemController::class, 'priceHistory']);
            Route::get('/{item}', [\App\Http\Controllers\API\v1\mobile\ItemController::class, 'show']);
            Route::get('/{item}/wholesale-tiers', [\App\Http\Controllers\Admin\Products\WholesalePriceTierController::class, 'index']);
            Route::post('/{item}/wholesale-tiers', [\App\Http\Controllers\Admin\Products\WholesalePriceTierController::class, 'store']);
            Route::put('/wholesale-tiers/{tier}', [\App\Http\Controllers\Admin\Products\WholesalePriceTierController::class, 'update']);
            Route::delete('/wholesale-tiers/{tier}', [\App\Http\Controllers\Admin\Products\WholesalePriceTierController::class, 'destroy']);
        });

        // Calendar
        Route::prefix('/calendar')->group(function () {
            Route::get('/events', [\App\Http\Controllers\API\v1\mobile\CalendarController::class, 'index']);
            Route::get('/purchases', [\App\Http\Controllers\API\v1\mobile\CalendarController::class, 'purchases']);
            Route::get('/credit-dues', [\App\Http\Controllers\API\v1\mobile\CalendarController::class, 'creditDues']);
        });

        // Units
        Route::prefix('/units')->group(function () {
            Route::get('/get', [\App\Http\Controllers\API\v1\mobile\UnitController::class, 'getUnits']);
        });

        // Purchase Order Custom Routes (must be before apiResources to avoid {purchase} catching these)
        Route::prefix('purchases')->group(function () {
            Route::get('/pending-approvals', [\App\Http\Controllers\API\v1\mobile\PurchaseController::class, 'pendingApprovals']);
            Route::get('/pending-approvals/count', [\App\Http\Controllers\API\v1\mobile\PurchaseController::class, 'pendingApprovalsCount']);
            Route::post('/{purchase}/approve', [\App\Http\Controllers\API\v1\mobile\PurchaseController::class, 'approve']);
            Route::post('/{purchase}/reject', [\App\Http\Controllers\API\v1\mobile\PurchaseController::class, 'reject']);
            Route::post('/{purchase}/submit-for-approval', [\App\Http\Controllers\API\v1\mobile\PurchaseController::class, 'submitForApproval']);
            // Payment Routes
            Route::post('/{purchase}/pay', [\App\Http\Controllers\API\v1\mobile\PurchaseController::class, 'pay']);
            Route::get('/{purchase}/payments', [\App\Http\Controllers\API\v1\mobile\PurchaseController::class, 'payments']);
            // Receiving Route
            Route::post('/{purchase}/receive', [\App\Http\Controllers\API\v1\mobile\PurchaseController::class, 'receive']);
        });

        // Attendance Management
        Route::prefix('attendance')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\mobile\AttendanceController::class, 'index']);
            Route::get('/summary', [\App\Http\Controllers\API\v1\mobile\AttendanceController::class, 'summary']);
            Route::get('/employees', [\App\Http\Controllers\API\v1\mobile\AttendanceController::class, 'employees']);
            Route::post('/', [\App\Http\Controllers\API\v1\mobile\AttendanceController::class, 'store']);
            Route::get('/{attendance}', [\App\Http\Controllers\API\v1\mobile\AttendanceController::class, 'show']);
            Route::put('/{attendance}', [\App\Http\Controllers\API\v1\mobile\AttendanceController::class, 'update']);
            Route::delete('/{attendance}', [\App\Http\Controllers\API\v1\mobile\AttendanceController::class, 'destroy']);
        });

        // Employee Schedules
        Route::prefix('employees')->group(function () {
            Route::get('/{user}/schedules', [\App\Http\Controllers\API\v1\mobile\EmployeeScheduleController::class, 'show']);
            Route::put('/{user}/schedules', [\App\Http\Controllers\API\v1\mobile\EmployeeScheduleController::class, 'update']);
        });

        // Ecommerce Orders — full lifecycle from pending → verified →
        // paid → preparing → picked_up. recordPayment and markPickedUp
        // accept multipart for optional proof photos.
        Route::prefix('ecommerce-orders')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\mobile\EcommerceOrderController::class, 'index']);
            Route::get('/pending', [\App\Http\Controllers\API\v1\mobile\EcommerceOrderController::class, 'pending']);
            Route::get('/{ecommerceOrder}', [\App\Http\Controllers\API\v1\mobile\EcommerceOrderController::class, 'show']);
            Route::post('/{ecommerceOrder}/verify', [\App\Http\Controllers\API\v1\mobile\EcommerceOrderController::class, 'verify']);
            Route::post('/{ecommerceOrder}/cancel', [\App\Http\Controllers\API\v1\mobile\EcommerceOrderController::class, 'cancel']);
            Route::post('/{ecommerceOrder}/record-payment', [\App\Http\Controllers\API\v1\mobile\EcommerceOrderController::class, 'recordPayment']);
            Route::post('/{ecommerceOrder}/mark-preparing', [\App\Http\Controllers\API\v1\mobile\EcommerceOrderController::class, 'markPreparing']);
            Route::post('/{ecommerceOrder}/mark-picked-up', [\App\Http\Controllers\API\v1\mobile\EcommerceOrderController::class, 'markPickedUp']);
        });

        // API Resources
        Route::apiResources([
            'purchases' => \App\Http\Controllers\API\v1\mobile\PurchaseController::class,
        ]);

        // Logs
        Route::prefix('log')->group(function () {
            Route::post('reprint_receipt', [\App\Http\Controllers\API\v1\pos\SaleController::class, 'logReprintReceipt']);
        });

        // Banking
        Route::prefix('banks')->name('mobile.banks.')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'index'])->name('index');
            Route::get('/summary', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'summary'])->name('summary');
            Route::get('/{bank}', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'show'])->name('show');
            Route::get('/{bank}/transactions', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'transactions'])->name('transactions');
            Route::post('/{bank}/deposit', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'deposit'])->name('deposit');
            Route::post('/{bank}/withdrawal', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'withdrawal'])->name('withdrawal');
            Route::post('/{bank}/transfer', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'transfer'])->name('transfer');
            Route::post('/transactions/{transaction}/proof', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'uploadTransactionProof'])->name('transactions.proof.upload');
            Route::delete('/transactions/{transaction}/proof', [\App\Http\Controllers\API\v1\mobile\BankController::class, 'deleteTransactionProof'])->name('transactions.proof.delete');
        });

        // Expenses
        Route::prefix('expenses')->name('mobile.expenses.')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'index'])->name('index');
            Route::get('/summary', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'summary'])->name('summary');
            Route::get('/categories', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'categories'])->name('categories');
            Route::post('/', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'store'])->name('store');
            Route::get('/{expense}', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'show'])->name('show');
            Route::put('/{expense}', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'update'])->name('update');
            Route::post('/{expense}/void', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'void'])->name('void');
            Route::post('/{expense}/receipt', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'uploadReceipt'])->name('receipt.upload');
            Route::delete('/{expense}/receipt', [\App\Http\Controllers\API\v1\mobile\ExpenseController::class, 'deleteReceipt'])->name('receipt.delete');
        });

        // Analytics (shared with POS)
        Route::prefix('analytics')->group(function () {
            Route::get('/peak-hours', [\App\Http\Controllers\API\v1\Analytics\PeakHoursController::class, 'peakHours']);
            Route::get('/hourly-breakdown', [\App\Http\Controllers\API\v1\Analytics\PeakHoursController::class, 'hourlyBreakdown']);
            Route::get('/profit-margins', [\App\Http\Controllers\API\v1\Analytics\ProfitMarginController::class, 'index']);
            Route::get('/profit-margins/{item}/trend', [\App\Http\Controllers\API\v1\Analytics\ProfitMarginController::class, 'trend']);
            Route::get('/margin-alerts', [\App\Http\Controllers\API\v1\Analytics\ProfitMarginController::class, 'marginAlerts']);
        });

        // Demand Forecasting & AI Insights
        Route::prefix('forecast')->name('mobile.forecast.')->group(function () {
            Route::get('/daily-sales', [\App\Http\Controllers\API\v1\ForecastController::class, 'dailySales'])->name('daily-sales');
            Route::get('/reorder-suggestions', [\App\Http\Controllers\API\v1\ForecastController::class, 'reorderSuggestions'])->name('reorder-suggestions');
            Route::post('/reorder-suggestions/{id}/acknowledge', [\App\Http\Controllers\API\v1\ForecastController::class, 'acknowledgeReorder'])->name('acknowledge-reorder');
            Route::get('/patterns', [\App\Http\Controllers\API\v1\ForecastController::class, 'patterns'])->name('patterns');
            Route::get('/items/{itemId}/demand', [\App\Http\Controllers\API\v1\ForecastController::class, 'itemDemand'])->name('item-demand');
            Route::get('/ai-status', [\App\Http\Controllers\API\v1\ForecastController::class, 'aiStatus'])->name('ai-status');
        });

        // Higher Access Management
        Route::prefix('auth/higher-access')->group(function () {
            Route::get('/pending', [\App\Http\Controllers\API\v1\pos\HigherAccessController::class, 'pending']);
            Route::post('/respond', [\App\Http\Controllers\API\v1\pos\HigherAccessController::class, 'respond']);
        });

        // Shop Announcements
        Route::prefix('announcements')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\mobile\AnnouncementController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\API\v1\mobile\AnnouncementController::class, 'store']);
            Route::get('/{announcement}', [\App\Http\Controllers\API\v1\mobile\AnnouncementController::class, 'show']);
            Route::put('/{announcement}', [\App\Http\Controllers\API\v1\mobile\AnnouncementController::class, 'update']);
            Route::post('/{announcement}', [\App\Http\Controllers\API\v1\mobile\AnnouncementController::class, 'update']);
            Route::delete('/{announcement}', [\App\Http\Controllers\API\v1\mobile\AnnouncementController::class, 'destroy']);
        });

        // Device Tokens (Push Notifications)
        Route::post('/device-tokens', [\App\Http\Controllers\API\v1\mobile\DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [\App\Http\Controllers\API\v1\mobile\DeviceTokenController::class, 'destroy']);

        // Vouchers
        Route::prefix('vouchers')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\mobile\VoucherController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\API\v1\mobile\VoucherController::class, 'store']);
            Route::get('/generate-code', [\App\Http\Controllers\API\v1\mobile\VoucherController::class, 'generateCode']);
            Route::get('/{voucher}', [\App\Http\Controllers\API\v1\mobile\VoucherController::class, 'show']);
            Route::put('/{voucher}', [\App\Http\Controllers\API\v1\mobile\VoucherController::class, 'update']);
            Route::delete('/{voucher}', [\App\Http\Controllers\API\v1\mobile\VoucherController::class, 'destroy']);
        });

        // Resources
        Route::name('mobile.')->group(function () {
            Route::resources([
                'units' => \App\Http\Controllers\API\v1\mobile\UnitController::class,
                'categories' => \App\Http\Controllers\API\v1\mobile\CategoryController::class,
                'suppliers' => \App\Http\Controllers\API\v1\mobile\SupplierController::class,
                'stores' => \App\Http\Controllers\API\v1\mobile\StoreController::class,
                'customers' => \App\Http\Controllers\API\v1\mobile\CustomerController::class,
                'roles' => \App\Http\Controllers\API\v1\mobile\RoleController::class,
            ]);

            // Customer Credit Payments
            Route::prefix('customers')->group(function () {
                Route::get('/{customer}/credit-balance', [\App\Http\Controllers\API\v1\pos\CustomerCreditController::class, 'balance']);
                Route::post('/{customer}/credit-payment', [\App\Http\Controllers\API\v1\pos\CustomerCreditController::class, 'payment']);
            });
        });
    });
});
