<?php

use App\Http\Controllers\Admin\Accounting\BankController;
use App\Http\Controllers\Admin\Accounting\PosLogController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CustomerRelations\CustomerController;
use App\Http\Controllers\Admin\Dashboards\CalendarController;
use App\Http\Controllers\Admin\Ecommerce\EcommerceOrderController;
use App\Http\Controllers\Admin\Ecommerce\ShopAnalyticsController;
use App\Http\Controllers\Admin\Ecommerce\ShopAnnouncementController;
use App\Http\Controllers\Admin\Employees\AttendanceController;
use App\Http\Controllers\Admin\Employees\EmployeeScheduleController;
use App\Http\Controllers\Admin\Employees\RoleController;
use App\Http\Controllers\Admin\Employees\ShiftController;
use App\Http\Controllers\Admin\ForecastController;
use App\Http\Controllers\Admin\InventoryManagement\AdjustmentController;
use App\Http\Controllers\Admin\InventoryManagement\CountController;
use App\Http\Controllers\Admin\InventoryManagement\PurchaseController;
use App\Http\Controllers\Admin\InventoryManagement\SupplierController;
use App\Http\Controllers\Admin\InventoryManagement\TransferController;
use App\Http\Controllers\Admin\ItemInsightsController;
use App\Http\Controllers\Admin\Pos\OrderController;
use App\Http\Controllers\Admin\Pos\VoucherController;
use App\Http\Controllers\Admin\Products\CategoryController;
use App\Http\Controllers\Admin\Products\DiscountController;
use App\Http\Controllers\Admin\Products\ItemController;
use App\Http\Controllers\Admin\Products\UnitController;
use App\Http\Controllers\Admin\Products\WholesalePriceTierController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\Reports\AuditLogController;
use App\Http\Controllers\Admin\Reports\BirController;
use App\Http\Controllers\Admin\Reports\BusinessIntelligenceController;
use App\Http\Controllers\Admin\Reports\CustomerIntelligenceController;
use App\Http\Controllers\Admin\Reports\PeakHoursController;
use App\Http\Controllers\Admin\Reports\ProfitMarginController;
use App\Http\Controllers\Admin\Reports\ReportController;
use App\Http\Controllers\Admin\Reports\ScheduledReportController;
use App\Http\Controllers\Admin\Settings\AdvertisementController;
use App\Http\Controllers\Admin\Settings\BrandingController as AdminBrandingController;
use App\Http\Controllers\Admin\Settings\PosController;
use App\Http\Controllers\Admin\Settings\StoreController;
use App\Http\Controllers\Admin\Settings\TaxController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;

Route::prefix('/admin')->group(function () {
    // Public registration intentionally disabled — admins are provisioned
    // via the `apex:create-admin` artisan command (first-time setup) and the
    // Employees module thereafter.
    Auth::routes(['register' => false]);
    Route::get('/home', [HomeController::class, 'index'])->name('admin.home');
});

Route::middleware('auth')->prefix('/admin')->group(function () {
    // User Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/new_password', [ProfileController::class, 'update_password'])->name('profile.update.password');

    // OpenClaw Token Management (owner-only, gated inside the Livewire component)
    Route::view('/openclaw-tokens', 'admin.openclaw-tokens.index')->name('openclaw-tokens.index');

    // POS higher-access requests — JSON-only surface for the navbar notification
    // bell. No dedicated page; the bell IS the UI.
    Route::get('/access-requests/pending', [\App\Http\Controllers\Admin\AccessRequestController::class, 'pending'])
        ->name('access-requests.pending');
    Route::post('/access-requests/{requestId}/respond', [\App\Http\Controllers\Admin\AccessRequestController::class, 'respond'])
        ->name('access-requests.respond');

    //  Route::get('/fixItemNames/rolworks/superadmin/set/default/password-ok!', 'ItemController@ItemNameFixer');
    //  Route::get('/normalizeProfit/rolworks/password-ok!', 'ExcessController@normalizeProfit');

    //  //Backup Database
    //  Route::get('/backup_database', 'SettingsController@backUpDB')->name('backup.db');

    // New Routes
    // Dashboard
    Route::prefix('home')->group(function () {});
    // Products
    Route::prefix('/products')->group(function () {
        // Items
        Route::get('/searchUom', [ItemController::class, 'search'])->name('uom-search');
        Route::get('/getUomFromSearch', [ItemController::class, 'getItemFromSearch'])->name('get-uom');
        Route::get('/checkBarcode', [ItemController::class, 'ItemBarcodeChecker'])->name('check-barcode');
        Route::get('/print-labels', [ItemController::class, 'PrintLabel'])->name('print-label');
        Route::get('/getItemForLabel', [ItemController::class, 'GetItemForLabel'])->name('get-item-for-label');
        Route::get('/getAllItemsForLabel', [ItemController::class, 'GetAllItemForLabel'])->name('get-all-items-for-label');
        Route::post('/ready-items', [ItemController::class, 'ReadyItems'])->name('ready-items');
        //        Route::post('/index-search', [ItemController::class, 'IndexSearch'])->name("items.search");
        Route::get('/item/insighttable', [ItemController::class, 'insightTable'])->name('item.summary.data');
        // Yajra for Items Sample
        //        Route::get('/items_datatable/{key}', [ItemController::class, 'dataTable'])->name("items.data.table");
        // Bulk Edit
        Route::get('/bulk-edit', [ItemController::class, 'bulkEdit'])->name('products.bulk-edit');
        Route::post('/bulk-update-prices', [ItemController::class, 'bulkUpdatePrices'])->name('products.bulk-update-prices');
        Route::post('/bulk-update-category', [ItemController::class, 'bulkUpdateCategory'])->name('products.bulk-update-category');
        Route::get('/export-csv', [ItemController::class, 'exportCsv'])->name('products.export-csv');
        Route::post('/import-csv', [ItemController::class, 'importCsv'])->name('products.import-csv');
        Route::get('/import-template', [ItemController::class, 'downloadImportTemplate'])->name('products.import-template');
        Route::get('/bulk-operation/{log}/status', [ItemController::class, 'getBulkOperationStatus'])->name('products.bulk-operation-status');
    });

    // Unit of Measures
    Route::prefix('units')->group(function () {
        Route::get('/table', [UnitController::class, 'table'])->name('units.table');
        Route::get('/select', [UnitController::class, 'select'])->name('units.select');
        Route::get('/get/{unit}', [UnitController::class, 'getUnit']);
    });

    // Categories
    Route::prefix('categories')->group(function () {
        // index
        Route::get('/table', [CategoryController::class, 'table'])->name('categories.table');
        Route::get('/get/{category}', [CategoryController::class, 'getCategory'])->name('category.get');
        Route::get('/select', [CategoryController::class, 'select'])->name('categories.select');
        Route::get('/show/{category}/table/', [CategoryController::class, 'showTable'])->name('category.show.table');
    });

    // Discounts
    Route::prefix('discounts')->group(function () {
        Route::get('/table', [DiscountController::class, 'table'])->name('discounts.table');
    });

    // Items / Products
    Route::prefix('items')->group(function () {
        Route::get('/table', [ItemController::class, 'table'])->name('items.table');
        Route::get('/select', [ItemController::class, 'select'])->name('items.select');
        Route::get('/get/{item}', [ItemController::class, 'getItem'])->name('item.get');
        Route::get('{item}/insight', [ItemController::class, 'insightTable'])->name('item.insight');
        Route::get('{item}/price-history', [ItemController::class, 'priceHistory'])->name('item.price-history');

        // Wholesale Price Tiers
        Route::get('{item}/wholesale-tiers', [WholesalePriceTierController::class, 'index'])->name('items.wholesale-tiers.index');
        Route::post('{item}/wholesale-tiers', [WholesalePriceTierController::class, 'store'])->name('items.wholesale-tiers.store');
        Route::put('wholesale-tiers/{tier}', [WholesalePriceTierController::class, 'update'])->name('items.wholesale-tiers.update');
        Route::delete('wholesale-tiers/{tier}', [WholesalePriceTierController::class, 'destroy'])->name('items.wholesale-tiers.destroy');
    });

    // Suppliers
    Route::prefix('suppliers')->group(function () {
        Route::get('/table', [SupplierController::class, 'table']);
        Route::get('/get/{supplier}', [SupplierController::class, 'getSupplier'])->name('supplier.get');
        Route::get('/select', [SupplierController::class, 'select'])->name('suppliers.select');
        Route::get('/items', [SupplierController::class, 'getItems'])->name('supplier.items');
        Route::get('/insight', [SupplierController::class, 'insight'])->name('supplier.insight');
        Route::post('/saveItems/{supplier}', [SupplierController::class, 'saveItems'])->name('supplier.store.items');
    });

    // Customers
    Route::prefix('customers')->group(function () {
        Route::get('/table', [CustomerController::class, 'table'])->name('customers.table');
        Route::post('/{user}/activate', [CustomerController::class, 'activate'])->name('customer.activate');
        Route::post('/generate/{id}', [CustomerController::class, 'generate_id'])->name('customer.generate.id');
        // Track member spending
        Route::get('/members', [\App\Http\Controllers\Admin\CustomerRelations\CustomerController::class, 'membersReport'])->name('customers.members.report');
        Route::get('/nonMembers', [\App\Http\Controllers\Admin\CustomerRelations\CustomerController::class, 'nonMembersReport'])->name('customers.non-members.report');
        Route::get('/members-report', [CustomerController::class, 'membersReportTable'])->name('customers.members.report.table');
        Route::get('/non-members-report', [CustomerController::class, 'nonMembersReportTable'])->name('customers.non-members.report.table');
    });

    // Stores/Locations
    Route::prefix('stores')->group(function () {
        Route::get('/table', [StoreController::class, 'table'])->name('stores.table');
        Route::get('/select', [StoreController::class, 'select'])->name('stores.select');
        Route::get('/get/{store}', [StoreController::class, 'getStore'])->name('store.get');
    });

    // Employees
    Route::prefix('employees')->group(function () {
        Route::post('/{user}/activate', [UserController::class, 'activate'])->name('employee.activate');
        Route::get('/table', [UserController::class, 'table'])->name('employees.table');
        // For Vis Timeline
        Route::get('/timeline', [UserController::class, 'timeline'])->name('employee.timeline');
    });

    // Shifts
    Route::prefix('shifts')->group(function () {
        Route::get('/table', [ShiftController::class, 'table'])->name('shifts.table');
        Route::post('/{shift}/clock-out', [ShiftController::class, 'clockOut'])->name('shifts.clock-out');
        Route::post('/{shift}/start-break', [ShiftController::class, 'startBreak'])->name('shifts.start-break');
        Route::post('/{shift}/end-break', [ShiftController::class, 'endBreak'])->name('shifts.end-break');
    });

    // Attendance
    Route::prefix('attendance')->group(function () {
        Route::get('/table', [AttendanceController::class, 'table'])->name('attendance.table');
        Route::get('/export', [AttendanceController::class, 'export'])->name('attendance.export');
        Route::get('/summary', [AttendanceController::class, 'summary'])->name('attendance.summary');
        Route::get('/calendar-events', [AttendanceController::class, 'calendarEvents'])->name('attendance.calendar-events');
        Route::get('/{attendance}/audit-log', [AttendanceController::class, 'auditLog'])->name('attendance.audit-log');
    });
    Route::resource('attendance', AttendanceController::class);

    // Employee Schedules
    Route::prefix('schedules')->group(function () {
        Route::get('/table', [EmployeeScheduleController::class, 'table'])->name('schedules.table');
    });
    Route::resource('schedules', EmployeeScheduleController::class)->only(['index', 'edit', 'update']);

    // Roles
    Route::prefix('roles')->group(function () {});

    // Tax
    Route::prefix('taxes')->group(function () {
        Route::get('/table', [TaxController::class, 'table'])->name('taxes.table');
        Route::get('/select', [TaxController::class, 'select'])->name('tax.select');
        Route::get('/get/{tax}', [TaxController::class, 'getTax'])->name('tax.get');
        Route::get('/show/table/{tax}', [TaxController::class, 'showTaxTable'])->name('tax.show.table');
    });

    // Purchase Orders
    Route::prefix('purchases')->group(function () {
        Route::get('/table', [PurchaseController::class, 'table'])->name('purchases.table');
        Route::get('/print/{purchase}', [PurchaseController::class, 'printPO'])->name('purchase.print');
        Route::get('/receive/{id}', [PurchaseController::class, 'receive'])->name('purchase.receive');
        Route::post('/receive/{id}', [PurchaseController::class, 'receiveNow'])->name('purchase.receive.now');
        Route::post('/{purchase}/approve', [PurchaseController::class, 'approve'])->name('purchase.approve');
        Route::post('/{purchase}/reject', [PurchaseController::class, 'reject'])->name('purchase.reject');
        Route::post('/{purchase}/payment', [PurchaseController::class, 'recordPayment'])->name('purchase.record-payment');
        Route::get('/{purchase}/payments', [PurchaseController::class, 'paymentHistory'])->name('purchase.payments');
        Route::get('/banks', [PurchaseController::class, 'getBanks'])->name('purchase.banks');
    });

    // Transfer Orders
    Route::prefix('transfers')->group(function () {
        Route::get('/table', [TransferController::class, 'table'])->name('transfers.table');
        Route::get('/item-search', [TransferController::class, 'getItem'])->name('transfer.get.item');
        Route::get('/print/{transfer}', [TransferController::class, 'print'])->name('transfers.print');
        Route::get('/receive/{transfer}', [TransferController::class, 'receive'])->name('transfers.receive');
        Route::post('/receive/{transfer}', [TransferController::class, 'receiveNow'])->name('transfers.receive.now');
    });

    // Stock Adjustments
    Route::prefix('adjustments')->group(function () {
        Route::get('/table', [AdjustmentController::class, 'table'])->name('adjustments.table');
        Route::get('/getAdjustmentItemFromSearch', [AdjustmentController::class, 'getItemFromSearch'])->name('adjustments.item.get');
        Route::get('/items/get', [AdjustmentController::class, 'getItems'])->name('adjustments.table.item');
        Route::post('/approve/{adjustment}', [AdjustmentController::class, 'approve'])->name('adjustment.approve');
    });

    // Inventory Counts
    Route::prefix('counts')->group(function () {
        Route::get('/table', [CountController::class, 'table'])->name('counts.table');
        Route::get('/get-item-for-inventory-count', [CountController::class, 'get_item'])->name('ic-get-item');
        Route::get('/inventory-count/table/get', [CountController::class, 'getItems'])->name('ic.table.items');
        Route::get('/inventory-count/print{id}', [CountController::class, 'printIC'])->name('print.ic');
        Route::post('/{count}/finalize', [CountController::class, 'finalize'])->name('counts.finalize');
        Route::patch('/{count}/update-line', [CountController::class, 'updateLine'])->name('counts.update-line');
        Route::post('/{count}/add-line', [CountController::class, 'addLine'])->name('counts.add-line');
        Route::delete('/{count}/delete-line', [CountController::class, 'deleteLine'])->name('counts.delete-line');
    });

    // Suppliers
    Route::prefix('suppliers')->group(function () {
        Route::get('/table', [SupplierController::class, 'table'])->name('suppliers.table');
    });

    // Sales Orders
    Route::prefix('orders')->controller(OrderController::class)->group(function () {
        Route::get('table', 'table')->name('orders.table');
        Route::post('/confirmed/{order}', 'confirmOrCancelOrder')->name('orders.confirmed');
        Route::post('/assign/{order}/{pos}', 'setAssignedTerminal')->name('orders.assign');
        Route::put('/order-prepared/{order}', 'orderPrepared')->name('orders.prepared');
        Route::put('/orders-complete/{order}', 'orderComplete')->name('orders.complete');
    });

    // Reports
    Route::prefix('reports')->group(function () {
        // Daily Summary — operational view that includes admin cashless sales
        Route::get('/daily-summary', [\App\Http\Controllers\Admin\Reports\DailySummaryController::class, 'index'])
            ->name('reports.daily-summary');

        // Sales Summary
        Route::prefix('sales_summary')->group(function () {
            Route::get('/', [ReportController::class, 'salesSummary'])->name('reports.sales_summary');
            Route::get('/data', [ReportController::class, 'getSalesSummaryData'])->name('reports.sales_summary.data');
        });
        // Receipts
        Route::prefix('receipts')->group(function () {
            Route::get('/', [ReportController::class, 'receipts'])->name('reports.receipts');
            Route::get('/data', [ReportController::class, 'getReceiptsData'])->name('report.receipts.data');
            Route::get('receipts/{sale}', [ReportController::class, 'viewReceipt'])->name('receipts.show');
            Route::get('receipt/{sale}/print', [ReportController::class, 'printReceipt'])->name('receipt.print');
        });
        // Category
        Route::prefix('categories')->group(function () {
            Route::get('/', [ReportController::class, 'categories'])->name('reports.categories');
            Route::get('/data', [ReportController::class, 'getCategoriesData'])->name('reports.categories.data');
        });
        // Suppliers
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [ReportController::class, 'suppliers'])->name('reports.suppliers');
            Route::get('/data', [ReportController::class, 'getSupplierData'])->name('reports.suppliers.data');
        });
        // Readings
        Route::prefix('readings')->group(function () {
            Route::get('/', [ReportController::class, 'readings'])->name('reports.readings');
            Route::get('/data', [ReportController::class, 'getReadingsData'])->name('reports.readings.data');
            Route::get('/{type}/{id}', [ReportController::class, 'reading'])->name('readings.show');
        });
        // Sales By Item
        Route::prefix('sales_by_item')->group(function () {
            Route::get('/', [ReportController::class, 'items'])->name('reports.sales.items');
            Route::get('/data', [ReportController::class, 'itemsData'])->name('reports.sales.items.data');
        });
        // Terminals
        Route::prefix('terminals')->group(function () {
            Route::get('/', [ReportController::class, 'terminals'])->name('reports.terminals');
            Route::get('/data', [ReportController::class, 'terminalsData'])->name('reports.terminals.data');
        });
        // Employees
        Route::prefix('employees')->group(function () {
            Route::get('/', [ReportController::class, 'employees'])->name('reports.employees');
            Route::get('/data', [ReportController::class, 'employeesData'])->name('reports.employees.data');
        });
        // Year by Year Comparison
        Route::prefix('year_by_year_comparison')->group(function () {
            Route::get('/', [ReportController::class, 'yearByYearComparison'])->name('reports.year_by_year_comparison');
            Route::get('/data', [ReportController::class, 'getYearByYearComparisonData'])->name('reports.year_by_year_comparison.data');
        });
        // BIR
        Route::prefix('bir')->group(function () {
            Route::prefix('vat')->group(function () {
                Route::get('/', [BirController::class, 'vat'])->name('reports.bir.vat');
                Route::get('/data', [BirController::class, 'vatData'])->name('reports.bir.vat.data');
                Route::get('/individual', [BirController::class, 'vatIndividual'])->name('reports.bir.vat.individual');
                Route::get('/individual/new/{pos}', [BirController::class, 'printVAT'])->name('reports.bir.vat.individual.new');
                Route::get('/overall', [BirController::class, 'vatOverall'])->name('reports.bir.vat.overall');
            });
            Route::prefix('special_discounts')->group(function () {
                // Senior Citizen
                Route::get('/senior', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\SeniorCitizenSpecialDiscountReportController::class, 'index'])->name('reports.bir.specialDiscounts.senior.index');
                Route::get('/senior/{pos}', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\SeniorCitizenSpecialDiscountReportController::class, 'seniorCitizenIndividualReport'])->name('reports.bir.specialDiscounts.senior');
                Route::get('/senior/individual/table', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\SeniorCitizenSpecialDiscountReportController::class, 'seniorCitizenIndividualTable'])->name('reports.bir.specialDiscounts.senior.table');
                // PWD
                Route::get('/pwd', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\PWDDiscountReportController::class, 'index'])->name('reports.bir.specialDiscounts.pwd.index');
                Route::get('/pwd/{pos}', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\PWDDiscountReportController::class, 'pwdIndividualPosReport'])->name('reports.bir.specialDiscounts.pwd.individual.pos');
                Route::get('/pwd/individual/table', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\PWDDiscountReportController::class, 'pwdIndividualPosReportTable'])->name('reports.bir.specialDiscounts.pwd.individual.pos.table');
                // Solo Parent
                Route::get('/solo_parent', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\SoloParentDiscountReportController::class, 'index'])->name('reports.bir.specialDiscounts.soloParent.index');
                Route::get('/solo_parent/{pos}', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\SoloParentDiscountReportController::class, 'spIndividualPosReport'])->name('reports.bir.specialDiscounts.soloParent.individual.pos');
                Route::get('/solo_parent/individual/table', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\SoloParentDiscountReportController::class, 'spIndividualPosReportTable'])->name('reports.bir.specialDiscounts.soloParent.individual.pos.table');
                // National Coaches and Coaches
                Route::get('/naac', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\NationalAthletesAndCoachesReportController::class, 'index'])->name('reports.bir.specialDiscounts.naac.index');
                Route::get('/naac/{pos}', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\NationalAthletesAndCoachesReportController::class, 'naacIndividualPosReport'])->name('reports.bir.specialDiscounts.naac.individual.pos');
                Route::get('/naac/individual/table', [\App\Http\Controllers\Admin\Reports\SpecialDiscountReports\NationalAthletesAndCoachesReportController::class, 'naacIndividualPosReportTable'])->name('reports.bir.specialDiscounts.naac.individual.pos.table');
            });

            // BIR Annex F statutory reports (RMO 24-2023)
            Route::prefix('annexf')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\Reports\BirReportController::class, 'index'])->name('reports.bir.annexf');
                Route::get('/sales-summary', [\App\Http\Controllers\Admin\Reports\BirReportController::class, 'salesSummary'])->name('reports.bir.annexf.sales-summary');
                Route::get('/voided', [\App\Http\Controllers\Admin\Reports\BirReportController::class, 'voided'])->name('reports.bir.annexf.voided');
                Route::get('/discount-book', [\App\Http\Controllers\Admin\Reports\BirReportController::class, 'discountBook'])->name('reports.bir.annexf.discount-book');
                Route::get('/adjustments', [\App\Http\Controllers\Admin\Reports\BirReportController::class, 'adjustments'])->name('reports.bir.annexf.adjustments');
                Route::get('/vat-class', [\App\Http\Controllers\Admin\Reports\BirReportController::class, 'vatClass'])->name('reports.bir.annexf.vat-class');
                Route::get('/export/{report}', [\App\Http\Controllers\Admin\Reports\BirReportController::class, 'export'])->name('reports.bir.annexf.export');
            });
        });
        // MAFFISCO
        Route::prefix('maffisco')->group(function () {
            Route::get('/members-report', [CustomerController::class, 'membersReportTable'])->name('maffisco.members.report.table');
            Route::get('/non-members-report', [CustomerController::class, 'nonMembersReportTable'])->name('maffisco.non-members.report.table');
        });
        // Audit Logs
        Route::prefix('audit_logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index'])->name('audit_logs.index');
            Route::get('/table', [AuditLogController::class, 'table'])->name('audit_logs.table');
            Route::get('/{auditLog}', [AuditLogController::class, 'show'])->name('audit_logs.show');
        });
        // Business Health (P&L dashboard fed by the daily BI aggregates)
        Route::prefix('business_intelligence')->group(function () {
            Route::get('/', [BusinessIntelligenceController::class, 'index'])->name('reports.business_intelligence');
            Route::get('/data', [BusinessIntelligenceController::class, 'data'])->name('reports.business_intelligence.data');
            Route::get('/export', [BusinessIntelligenceController::class, 'export'])->name('reports.business_intelligence.export');
        });
        // Customer Intelligence (RFM segments + ecommerce funnel, fed by the daily BI aggregates)
        Route::prefix('customer_intelligence')->group(function () {
            Route::get('/', [CustomerIntelligenceController::class, 'index'])->name('reports.customer_intelligence');
            Route::get('/data', [CustomerIntelligenceController::class, 'data'])->name('reports.customer_intelligence.data');
            Route::get('/export', [CustomerIntelligenceController::class, 'export'])->name('reports.customer_intelligence.export');
        });
        // Peak Hours Analysis
        Route::prefix('peak_hours')->group(function () {
            Route::get('/', [PeakHoursController::class, 'index'])->name('reports.peak_hours');
            Route::get('/data', [PeakHoursController::class, 'data'])->name('reports.peak_hours.data');
        });
        // Profit Margins
        Route::prefix('profit_margins')->group(function () {
            Route::get('/', [ProfitMarginController::class, 'index'])->name('reports.profit_margins');
            Route::get('/data', [ProfitMarginController::class, 'data'])->name('reports.profit_margins.data');
        });
        // Scheduled Reports
        Route::prefix('scheduled')->group(function () {
            Route::get('/', [ScheduledReportController::class, 'index'])->name('reports.scheduled.index');
            Route::post('/recipients', [ScheduledReportController::class, 'storeRecipient'])->name('reports.scheduled.recipients.store');
            Route::delete('/recipients/{reportRecipient}', [ScheduledReportController::class, 'destroyRecipient'])->name('reports.scheduled.recipients.destroy');
            Route::get('/preview', [ScheduledReportController::class, 'preview'])->name('reports.scheduled.preview');
        });
    });

    // Payment Methods
    Route::prefix('accounts')->group(function () {
        Route::controller(\App\Http\Controllers\Admin\Accounting\AccountController::class)->group(function () {
            Route::get('/table', 'table')->name('accounts.table');
        });
    });

    // Special Customers
    Route::prefix('special_customers')->group(function () {
        Route::controller(\App\Http\Controllers\Admin\CustomerRelations\SpecialCustomerController::class)->group(function () {
            Route::get('/table', 'table')->name('special.customers.table');
            Route::get('/get/{customer}', 'getCustomer')->name('special.customers.get');
        });
    });

    // Dashboards
    Route::controller(CalendarController::class)->group(function () {
        Route::prefix('calendar')->group(function () {
            Route::get('/calendar', 'index')->name('dashboards.calendar');
            Route::get('/events', 'events')->name('calendars.events');
            Route::get('/sales', 'salesData')->name('calendar.salesData');
            Route::get('/purchases', 'purchasesData')->name('calendars.purchases');
        });
    });

    // Dashboard Data
    Route::prefix('dashboard')->group(function () {
        Route::get('/default', [HomeController::class, 'default'])->name('dashboard.default');
    });

    // Demand Forecasting
    Route::prefix('forecast')->controller(ForecastController::class)->group(function () {
        Route::get('/', 'index')->name('forecast.index');
        Route::get('/daily-sales', 'dailySales')->name('forecast.daily-sales');
        Route::get('/reorder-suggestions', 'reorderSuggestions')->name('forecast.reorder-suggestions');
        Route::get('/reorder-suggestions/export', 'exportReorderSuggestions')->name('forecast.export-reorder');
        Route::get('/reorder-summary', 'reorderSummary')->name('forecast.reorder-summary');
        Route::post('/acknowledge/{id}', 'acknowledge')->name('forecast.acknowledge');
        Route::get('/patterns', 'patterns')->name('forecast.patterns');
        Route::get('/ai-status', 'aiStatus')->name('forecast.ai-status');
    });

    // AI Item Insights
    Route::prefix('insights')->controller(ItemInsightsController::class)->group(function () {
        Route::get('/', 'index')->name('insights.index');
        Route::get('/data', 'data')->name('insights.data');
        Route::get('/summary', 'summary')->name('insights.summary');
    });

    // POS Terminals
    Route::prefix('terminals')->group(function () {
        Route::get('/select', [PosController::class, 'select'])->name('pos.select');
        Route::get('/table', [PosController::class, 'table'])->name('pos.table');
    });

    // Banks
    Route::prefix('banks')->group(function () {
        Route::get('table', [BankController::class, 'table'])->name('banks.table');
        Route::get('select', [BankController::class, 'select'])->name('banks.select');
        Route::get('get/{bank}', [BankController::class, 'getBank'])->name('banks.get');
        Route::post('{bank}/deposit', [BankController::class, 'deposit'])->name('banks.deposit');
        Route::post('{bank}/withdrawal', [BankController::class, 'withdrawal'])->name('banks.withdrawal');
        Route::post('{bank}/transfer', [BankController::class, 'transfer'])->name('banks.transfer');
        Route::get('{bank}/transactions', [BankController::class, 'transactionsTable'])->name('banks.transactions');
    });

    // Pending Cheques (admin-recorded cheque sales awaiting clearing).
    Route::prefix('pending-cheques')
        ->controller(\App\Http\Controllers\Admin\Accounting\PendingChequeController::class)
        ->group(function () {
            Route::get('/', 'index')->name('pending-cheques.index');
            Route::get('/table', 'table')->name('pending-cheques.table');
            Route::post('/{sale}/clear', 'clear')->name('pending-cheques.clear');
            Route::post('/{sale}/bounce', 'bounce')->name('pending-cheques.bounce');
        });

    // Advertisements
    Route::prefix('advertisements')->group(function () {
        Route::get('table', [AdvertisementController::class, 'table'])->name('advertisements.table');
    });

    // Vouchers
    Route::prefix('vouchers')->group(function () {
        Route::get('table', [VoucherController::class, 'table'])->name('vouchers.table');
        Route::get('generate-code', [VoucherController::class, 'generateCode'])->name('vouchers.generate-code');
    });

    // Restaurant module (tables, kitchen stations, reservations)
    Route::prefix('restaurant-tables')->group(function () {
        Route::get('/floorplan', [\App\Http\Controllers\Admin\Restaurant\RestaurantTableController::class, 'floorplan'])->name('restaurant-tables.floorplan');
        Route::get('/floorplan-data', [\App\Http\Controllers\Admin\Restaurant\RestaurantTableController::class, 'floorplanData'])->name('restaurant-tables.floorplan-data');
        Route::get('/table', [\App\Http\Controllers\Admin\Restaurant\RestaurantTableController::class, 'table'])->name('restaurant-tables.table');
        Route::get('/select', [\App\Http\Controllers\Admin\Restaurant\RestaurantTableController::class, 'select'])->name('restaurant-tables.select');
    });
    Route::prefix('kitchen-stations')->group(function () {
        Route::get('/table', [\App\Http\Controllers\Admin\Restaurant\KitchenStationController::class, 'table'])->name('kitchen-stations.table');
        Route::get('/select', [\App\Http\Controllers\Admin\Restaurant\KitchenStationController::class, 'select'])->name('kitchen-stations.select');
    });
    Route::prefix('reservations')->group(function () {
        Route::get('/table', [\App\Http\Controllers\Admin\Restaurant\ReservationController::class, 'table'])->name('reservations.table');
        Route::get('/calendar-events', [\App\Http\Controllers\Admin\Restaurant\ReservationController::class, 'calendarEvents'])->name('reservations.calendar-events');
    });
    Route::resource('restaurant-tables', \App\Http\Controllers\Admin\Restaurant\RestaurantTableController::class)->except(['show']);
    Route::resource('kitchen-stations', \App\Http\Controllers\Admin\Restaurant\KitchenStationController::class)->except(['show']);
    Route::resource('reservations', \App\Http\Controllers\Admin\Restaurant\ReservationController::class)->except(['show']);

    // Route Resources
    Route::resource('items', ItemController::class);
    Route::resource('units', UnitController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('discounts', DiscountController::class);
    Route::resource('purchases', PurchaseController::class);
    Route::resource('counts', CountController::class);
    Route::resource('adjustments', AdjustmentController::class);
    Route::resource('transfers', TransferController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('employees', UserController::class);
    Route::resource('shifts', ShiftController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('stores', StoreController::class);
    Route::resource('taxes', TaxController::class);
    Route::resource('pos', PosController::class);
    Route::resource('accounts', \App\Http\Controllers\Admin\Accounting\AccountController::class);
    Route::resource('orders', OrderController::class);
    Route::resource('special_customers', \App\Http\Controllers\Admin\CustomerRelations\SpecialCustomerController::class);
    Route::resource('banks', BankController::class);
    // Expenses
    Route::prefix('expenses')->group(function () {
        Route::get('table', [\App\Http\Controllers\Admin\Accounting\ExpenseController::class, 'table'])->name('expenses.table');
        Route::get('export', [\App\Http\Controllers\Admin\Accounting\ExpenseController::class, 'export'])->name('expenses.export');
        Route::get('get/{expense}', [\App\Http\Controllers\Admin\Accounting\ExpenseController::class, 'getExpense'])->name('expenses.get');
        Route::post('{expense}/receipt', [\App\Http\Controllers\Admin\Accounting\ExpenseController::class, 'uploadReceipt'])->name('expenses.receipt.upload');
        Route::delete('{expense}/receipt', [\App\Http\Controllers\Admin\Accounting\ExpenseController::class, 'deleteReceipt'])->name('expenses.receipt.delete');
    });
    Route::resource('expenses', \App\Http\Controllers\Admin\Accounting\ExpenseController::class);

    // Bank-transaction proof slips (deposit slips, transfer screenshots, etc.)
    Route::prefix('bank-transactions')->group(function () {
        Route::post('{transaction}/proof', [\App\Http\Controllers\Admin\Accounting\BankController::class, 'uploadTransactionProof'])->name('bank-transactions.proof.upload');
        Route::delete('{transaction}/proof', [\App\Http\Controllers\Admin\Accounting\BankController::class, 'deleteTransactionProof'])->name('bank-transactions.proof.delete');
    });

    // POS Logs
    Route::prefix('pos-logs')->group(function () {
        Route::get('/', [PosLogController::class, 'index'])->name('pos-logs.index');
        Route::get('/table', [PosLogController::class, 'table'])->name('pos-logs.table');
        Route::get('/export', [PosLogController::class, 'export'])->name('pos-logs.export');
    });

    // Expense Categories
    Route::prefix('expense_categories')->group(function () {
        Route::get('table', [\App\Http\Controllers\Admin\Accounting\ExpenseCategoryController::class, 'table'])->name('expense_categories.table');
        Route::get('export', [\App\Http\Controllers\Admin\Accounting\ExpenseCategoryController::class, 'export'])->name('expense_categories.export');
        Route::get('get/{expense_category}', [\App\Http\Controllers\Admin\Accounting\ExpenseCategoryController::class, 'getCategory'])->name('expense_categories.get');
        Route::get('all', [\App\Http\Controllers\Admin\Accounting\ExpenseCategoryController::class, 'getAll'])->name('expense_categories.all');
    });
    Route::resource('expense_categories', \App\Http\Controllers\Admin\Accounting\ExpenseCategoryController::class);
    Route::resource('calendars', CalendarController::class);
    Route::resource('advertisements', AdvertisementController::class);
    Route::resource('vouchers', VoucherController::class);

    // Tenant Branding — palette + logo + brand name applied to /shop, the
    // admin UI, and emails. Palettes are curated by SuperAdmin; tenants
    // only choose from active ones.
    Route::prefix('settings/branding')->name('admin.settings.branding.')
        ->controller(AdminBrandingController::class)
        ->group(function () {
            Route::get('/', 'show')->name('show');
            Route::put('/', 'update')->name('update');
        });

    // Outbound SMS log — forensic record of every VeroSMS dispatch,
    // with a "Refresh status" action that re-polls the relay's
    // delivery state on demand.
    Route::prefix('sms-logs')
        ->controller(\App\Http\Controllers\Admin\Settings\OutboundSmsLogController::class)
        ->group(function () {
            Route::get('/', 'index')->name('sms-logs.index');
            Route::get('/table', 'table')->name('sms-logs.table');
            Route::post('/{smsLog}/refresh-status', 'refreshStatus')->name('sms-logs.refresh-status');
            Route::post('/bulk-poll', 'bulkPoll')->name('sms-logs.bulk-poll');
        });

    // SMS templates — admin-editable body copy used by the queued
    // SendOrderUpdateSmsJob (and future transactional senders). Keys
    // are app-defined: index + edit only, no create / destroy.
    Route::prefix('settings/sms-templates')
        ->controller(\App\Http\Controllers\Admin\Settings\SmsTemplateController::class)
        ->group(function () {
            Route::get('/', 'index')->name('sms-templates.index');
            Route::get('/{smsTemplate}/edit', 'edit')->name('sms-templates.edit');
            Route::put('/{smsTemplate}', 'update')->name('sms-templates.update');
        });

    // Scheduled jobs — toggle / run-now surface for the Laravel
    // scheduler. Keys are app-defined (the seeder owns the list), so
    // create/destroy are absent.
    Route::prefix('settings/scheduled-jobs')
        ->controller(\App\Http\Controllers\Admin\Settings\ScheduledJobController::class)
        ->group(function () {
            Route::get('/', 'index')->name('scheduled-jobs.index');
            Route::post('/{scheduledJob}/toggle', 'toggle')->name('scheduled-jobs.toggle');
            Route::post('/{scheduledJob}/run-now', 'runNow')->name('scheduled-jobs.run-now');
        });

    // Ecommerce Orders
    Route::prefix('ecommerce-orders')->group(function () {
        Route::get('/', [EcommerceOrderController::class, 'index'])->name('ecommerce-orders.index');
        Route::get('/pending-feed', [EcommerceOrderController::class, 'pendingFeed'])->name('ecommerce-orders.pending-feed');
        Route::get('/lookup/{reference}', [EcommerceOrderController::class, 'lookupByReference'])
            ->where('reference', 'ECO-[A-Z0-9]+')
            ->name('ecommerce-orders.lookup');
        Route::get('/table', [EcommerceOrderController::class, 'table'])->name('ecommerce-orders.table');
        Route::get('/{ecommerceOrder}', [EcommerceOrderController::class, 'show'])->name('ecommerce-orders.show');
        Route::post('/{ecommerceOrder}/verify', [EcommerceOrderController::class, 'verify'])->name('ecommerce-orders.verify');
        Route::post('/{ecommerceOrder}/cancel', [EcommerceOrderController::class, 'cancel'])->name('ecommerce-orders.cancel');
        Route::post('/{ecommerceOrder}/record-payment', [EcommerceOrderController::class, 'recordPayment'])
            ->name('ecommerce-orders.record-payment');
        Route::post('/{ecommerceOrder}/mark-preparing', [EcommerceOrderController::class, 'markPreparing'])
            ->name('ecommerce-orders.mark-preparing');
        Route::post('/{ecommerceOrder}/mark-picked-up', [EcommerceOrderController::class, 'markPickedUp'])
            ->name('ecommerce-orders.mark-picked-up');
    });

    // Shop Curation — manual featured Categories + Products on /shop homepage.
    Route::prefix('shop/curation')->name('shop.curation.')
        ->controller(\App\Http\Controllers\Admin\Ecommerce\ShopCurationController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');

            // Categories
            Route::get('/categories/featured', 'categoriesFeatured')->name('categories.featured');
            Route::get('/categories/search', 'categoriesSearch')->name('categories.search');
            Route::post('/categories/{category}/feature', 'featureCategory')->name('categories.feature');
            Route::delete('/categories/{category}/feature', 'unfeatureCategory')->name('categories.unfeature');
            Route::post('/categories/reorder', 'reorderCategories')->name('categories.reorder');

            // Items
            Route::get('/items/featured', 'itemsFeatured')->name('items.featured');
            Route::get('/items/search', 'itemsSearch')->name('items.search');
            Route::post('/items/{item}/feature', 'featureItem')->name('items.feature');
            Route::delete('/items/{item}/feature', 'unfeatureItem')->name('items.unfeature');
            Route::post('/items/reorder', 'reorderItems')->name('items.reorder');
        });

    // Shop Announcements
    Route::get('shop-announcements/table', [ShopAnnouncementController::class, 'table'])->name('shop-announcements.table');
    Route::resource('shop-announcements', ShopAnnouncementController::class);

    // Shop Analytics
    Route::prefix('analytics/visitors')->name('analytics.visitors.')->group(function () {
        Route::get('/', [ShopAnalyticsController::class, 'index'])->name('index');
        Route::get('/data', [ShopAnalyticsController::class, 'data'])->name('data');
        Route::get('/charts', [ShopAnalyticsController::class, 'charts'])->name('charts');
        Route::get('/export', [ShopAnalyticsController::class, 'export'])->name('export');
    });

    // Contact Messages
    Route::get('contact-messages/table', [ContactMessageController::class, 'table'])->name('contact-messages.table');
    Route::post('contact-messages/{contactMessage}/mark-replied', [ContactMessageController::class, 'markAsReplied'])->name('contact-messages.mark-replied');
    Route::post('contact-messages/{contactMessage}/archive', [ContactMessageController::class, 'archive'])->name('contact-messages.archive');
    Route::resource('contact-messages', ContactMessageController::class)->only(['index', 'show', 'destroy']);

    // Tests
    Route::prefix('tests')->group(function () {
        Route::get('/data', [CalendarController::class, 'data'])->name('tests.calendar.data');
        Route::prefix('cash_receipt_journal')->group(function () {
            Route::get('/', [\App\Http\Controllers\Test\CashJournalController::class, 'index'])->name('tests.cash_receipt_journal');
            Route::get('/data', [\App\Http\Controllers\Test\CashJournalController::class, 'data'])->name('tests.cash_receipt_journal.data');
        });
        // Test Routes
        Route::get('/gcash', function () {
            return view('test.gcash');
        });
        //        Email
        Route::post('/email', [\App\Http\Controllers\Test\TestContoller::class, 'sendMail'])->name('test.mail');
        // Index Test Route
        Route::get('/index', [\App\Http\Controllers\Test\TestContoller::class, 'index'])->name('tests.index');
        // SMS Messages
        Route::view('/sms', 'test.sms');
        Route::post('/sms/send', [\App\Http\Controllers\Admin\CustomerRelations\SmsController::class, 'sendMessage'])->name('sms.send');
    });
});
