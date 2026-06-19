<?php

use App\Http\Controllers\Admin\Accounting\BankController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\v1\pos\BrandingController;
use App\Http\Controllers\API\v1\pos\CategoryController;
use App\Http\Controllers\API\v1\pos\CustomerCreditController;
use App\Http\Controllers\API\v1\pos\HigherAccessController;
use App\Http\Controllers\API\v1\pos\ItemController;
use App\Http\Controllers\API\v1\pos\OrderController;
use App\Http\Controllers\API\v1\pos\StoreController;
use App\Http\Controllers\API\v1\pos\TaxController;
use App\Http\Controllers\API\v1\pos\UnitController;
use App\Http\Controllers\API\v1\pos\VoucherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS API Routes
|--------------------------------------------------------------------------
|
| Routes for the POS terminal application.
| Prefix: api/v1
|
*/

Route::name('api.')->prefix('v1')->group(function () {
    Route::post('/login', [UserController::class, 'login']);
    Route::get('/user', [UserController::class, 'getUser']);

    Route::middleware('auth:api')->group(function () {
        // Branding — tenant palette + logo for apex_pos theming
        Route::get('/branding', [BrandingController::class, 'show'])->name('pos.branding');

        // Ecommerce Orders (verified)
        Route::prefix('ecommerce-orders')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\pos\EcommerceOrderController::class, 'index']);
            Route::get('/{ecommerceOrder}', [\App\Http\Controllers\API\v1\pos\EcommerceOrderController::class, 'show']);
            Route::get('/{ecommerceOrder}/cart-data', [\App\Http\Controllers\API\v1\pos\EcommerceOrderController::class, 'cartData']);
            Route::post('/{ecommerceOrder}/ping', [\App\Http\Controllers\API\v1\pos\EcommerceOrderController::class, 'pingAdmin']);
        });
        // Higher Access Authentication (Legacy)
        Route::post('/auth/higher_access', [UserController::class, 'higher_access']);
        Route::post('/auth/verify/uniqid', [UserController::class, 'verifyUiniqid']);

        // Higher Access Request (Polling-based)
        Route::prefix('auth/higher-access')->group(function () {
            Route::post('/request', [HigherAccessController::class, 'store']);
            Route::get('/status/{requestId}', [HigherAccessController::class, 'status']);
            Route::post('/cancel/{requestId}', [HigherAccessController::class, 'cancel']);
            Route::get('/pending', [HigherAccessController::class, 'pending']);
            Route::post('/respond', [HigherAccessController::class, 'respond']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/index', [\App\Http\Controllers\API\v1\pos\ReportController::class, 'index']);
        });

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/search', [OrderController::class, 'showProducts']);
        });

        // Get Cart Item Information
        Route::prefix('items')->group(function () {
            Route::get('/search', [ItemController::class, 'searchItemsFromKey']);
            Route::get('/get', [ItemController::class, 'getItems']);
        });

        // POS Readings
        Route::prefix('readings')->group(function () {
            Route::get('{pos}', [\App\Http\Controllers\API\v1\pos\ReadingController::class, 'getReadings']);
        });

        Route::prefix('xreadings')->group(function () {
            Route::get('/generate/{pos}', [\App\Http\Controllers\API\v1\pos\XreadingController::class, 'generateReading']);
            Route::get('/apex/generate/{pos}', [\App\Http\Controllers\API\v1\pos\XreadingController::class, 'apexReading']);
            Route::post('/save/{pos}', [\App\Http\Controllers\API\v1\pos\XreadingController::class, 'saveReading']);
        });

        Route::prefix('zreadings')->group(function () {
            Route::post('save/{pos}', [\App\Http\Controllers\API\v1\pos\ZreadingController::class, 'saveZReading']);
        });

        // Shift Readings
        Route::prefix('shift-readings')->group(function () {
            Route::post('save/{pos}', [\App\Http\Controllers\API\v1\pos\ShiftReadingController::class, 'save']);
        });

        // Sales
        Route::prefix('sales')->group(function () {
            Route::get('/{pos}', [\App\Http\Controllers\API\v1\pos\SaleController::class, 'getReceiptsByPos']);
            Route::post('/refund/{sale}', [\App\Http\Controllers\API\v1\pos\SaleController::class, 'refundReceipt']);
            Route::post('/void/{sale}', [\App\Http\Controllers\API\v1\pos\SaleController::class, 'void']);
            Route::post('/reprint/{sale}', [\App\Http\Controllers\API\v1\pos\SaleController::class, 'reprint']);
        });

        // Restaurant Orders (waiter terminal)
        Route::prefix('restaurant-orders')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'store']);
            Route::get('/{order}', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'show']);
            Route::post('/{order}/rounds', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'rounds']);
            Route::post('/{order}/transfer-table', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'transferTable']);
            Route::post('/{order}/settle', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'settle']);
            Route::post('/{order}/split-settle', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'splitSettle']);
            Route::post('/{order}/cancel', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'cancel']);
            Route::post('/{order}/lines/{line}/void', [\App\Http\Controllers\API\v1\pos\RestaurantOrderController::class, 'voidLine']);
        });

        // Kitchen Display System (polling)
        Route::prefix('kds')->group(function () {
            Route::get('/stations', [\App\Http\Controllers\API\v1\pos\KdsController::class, 'stations']);
            Route::get('/stations/{station}/queue', [\App\Http\Controllers\API\v1\pos\KdsController::class, 'queue']);
            Route::post('/lines/{line}/bump', [\App\Http\Controllers\API\v1\pos\KdsController::class, 'bumpLine']);
            Route::post('/orders/{order}/bump', [\App\Http\Controllers\API\v1\pos\KdsController::class, 'bumpOrder']);
        });

        // Restaurant Tables
        Route::get('/tables', [\App\Http\Controllers\API\v1\pos\TableController::class, 'index']);

        // Reservations
        Route::prefix('reservations')->group(function () {
            Route::get('/', [\App\Http\Controllers\API\v1\pos\ReservationController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\API\v1\pos\ReservationController::class, 'store']);
            Route::post('/{reservation}/status', [\App\Http\Controllers\API\v1\pos\ReservationController::class, 'updateStatus']);
        });

        // Customers
        Route::prefix('customers')->group(function () {
            Route::get('/search', [\App\Http\Controllers\API\v1\pos\CustomerController::class, 'searchCustomers']);
            Route::get('/{customer}/credit-balance', [CustomerCreditController::class, 'balance']);
            Route::post('/{customer}/credit-payment', [CustomerCreditController::class, 'payment']);
        });

        // Banks
        Route::prefix('banks')->group(function () {
            Route::get('e-wallets', [BankController::class, 'getEWallets']);
            Route::get('bank-accounts', [BankController::class, 'getBankAccounts']);
        });

        // Cash-Out
        Route::prefix('pos-logs')->group(function () {
            Route::post('/cash-out', [\App\Http\Controllers\API\v1\pos\PosLogController::class, 'cashOut']);
            Route::post('/void-cash-out/{posLog}', [\App\Http\Controllers\API\v1\pos\PosLogController::class, 'voidCashOut']);
            Route::get('/cash-outs', [\App\Http\Controllers\API\v1\pos\PosLogController::class, 'getCashOuts']);
        });

        // Vouchers
        Route::prefix('vouchers')->group(function () {
            Route::post('/check', [VoucherController::class, 'check']);
            Route::post('/apply', [VoucherController::class, 'apply']);
        });

        // Authentications
        Route::prefix('/authentications')->group(function () {
            Route::get('/roles', [\App\Http\Controllers\API\v1\pos\AuthenticationController::class, 'getUserWithRole']);
        });

        // Resource Controllers
        Route::name('pos.')->group(function () {
            Route::resources([
                'customers' => \App\Http\Controllers\API\v1\pos\CustomerController::class,
                'categories' => CategoryController::class,
                'taxes' => TaxController::class,
                'units' => UnitController::class,
                'stores' => StoreController::class,
                'items' => ItemController::class,
                'orders' => OrderController::class,
                'sales' => \App\Http\Controllers\API\v1\pos\SaleController::class,
                'xreadings' => \App\Http\Controllers\API\v1\pos\XreadingController::class,
                'zreadings' => \App\Http\Controllers\API\v1\pos\ZreadingController::class,
                'pos_logs' => \App\Http\Controllers\API\v1\pos\PosLogController::class,
                'authentications' => \App\Http\Controllers\API\v1\pos\AuthenticationController::class,
            ]);
        });

        // Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/peak-hours', [\App\Http\Controllers\API\v1\Analytics\PeakHoursController::class, 'peakHours']);
            Route::get('/hourly-breakdown', [\App\Http\Controllers\API\v1\Analytics\PeakHoursController::class, 'hourlyBreakdown']);
            Route::get('/profit-margins', [\App\Http\Controllers\API\v1\Analytics\ProfitMarginController::class, 'index']);
            Route::get('/profit-margins/{item}/trend', [\App\Http\Controllers\API\v1\Analytics\ProfitMarginController::class, 'trend']);
            Route::get('/margin-alerts', [\App\Http\Controllers\API\v1\Analytics\ProfitMarginController::class, 'marginAlerts']);
            Route::get('/item-insights', [\App\Http\Controllers\API\v1\Analytics\ItemInsightsController::class, 'index']);
        });

        // Scheduled Reports
        Route::prefix('reports')->group(function () {
            Route::get('/sales-summary', [\App\Http\Controllers\API\v1\Analytics\ReportController::class, 'salesSummary']);
            Route::get('/recipients', [\App\Http\Controllers\API\v1\Analytics\ReportController::class, 'recipients']);
            Route::post('/recipients', [\App\Http\Controllers\API\v1\Analytics\ReportController::class, 'storeRecipient']);
            Route::delete('/recipients/{reportRecipient}', [\App\Http\Controllers\API\v1\Analytics\ReportController::class, 'destroyRecipient']);
        });

        Route::get('/logout', [UserController::class, 'logout']);
    });
});
