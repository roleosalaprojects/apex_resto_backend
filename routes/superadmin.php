<?php

use App\Http\Controllers\Admin\Settings\PosController;
use App\Http\Controllers\SuperAdmin\AdjustmentController as AdminAdjustmentController;
use App\Http\Controllers\SuperAdmin\AdminController;
use App\Http\Controllers\SuperAdmin\ColorPaletteController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\PriorityItemController;
use App\Http\Controllers\SuperAdmin\ReceiptController;
use App\Http\Controllers\SuperAdmin\UserController as AdminUserController;

Route::prefix('/superadmin')->group(function () {
    Route::middleware('auth:superadmin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('/admin', AdminUserController::class);
        Route::post('/user/{user}/activate', [AdminUserController::class, 'activate'])->name('user.activate');

        Route::get('/receipt', [ReceiptController::class, 'index'])->name('receipt.index');
        Route::put('/receipt/{receipt}', [ReceiptController::class, 'update'])->name('receipt.update');

        // Hocus-pocus
        Route::get('/hocus-pocus', [ReceiptController::class, 'hocuspocus'])->name('hocus.pocus');
        Route::post('/hocus-pocus', [ReceiptController::class, 'hpUpdate'])->name('hocus.pocus.update');

        // Manual Adjustment
        Route::view('/adjustment', 'superadmin.adjustment.index');
        Route::get('/adjustments', [AdminAdjustmentController::class, 'getReceipts'])->name('superadmin.adjustment.receipts');
        Route::post('/adjustments/adjust', [AdminAdjustmentController::class, 'updateReceipts'])->name('superadmin.adjustment.receipts.adjust');
        Route::post('/adjustments/adjust2', [AdminAdjustmentController::class, 'adjustReceipts'])->name('superadmin.adjustment.receipts.adjust2');
        // Readings Adjustment
        Route::view('/adjustment/readings', 'superadmin.adjustment.readings');
        Route::get('/adjustment/readings/data', [AdminAdjustmentController::class, 'readings'])->name('superadmin.adjustment.readings');
        Route::post('/adjustments/readings/adjust', [AdminAdjustmentController::class, 'adjustReadings'])->name('superadmin.adjustment.readings.adjust');
        // Normalize Receipts
        Route::view('/normalize/receipts', 'superadmin.normalize.index');
        Route::post('/normalize/receipts', [AdminAdjustmentController::class, 'normalizeReceipts'])->name('superadmin.adjustment.receipts.normalize');

        // Priority Items — curated list of items for the upcoming admin
        // dashboard widget. Does NOT participate in the adjustment / BIR /
        // e-journal / zreadings flow.
        Route::get('/priority-items', [PriorityItemController::class, 'index'])->name('superadmin.priority-items.index');
        Route::get('/priority-items/data', [PriorityItemController::class, 'data'])->name('superadmin.priority-items.data');
        Route::get('/priority-items/available', [PriorityItemController::class, 'available'])->name('superadmin.priority-items.available');
        Route::post('/priority-items/add', [PriorityItemController::class, 'add'])->name('superadmin.priority-items.add');
        Route::post('/priority-items/{item}/remove', [PriorityItemController::class, 'remove'])->name('superadmin.priority-items.remove');

        // POS Selection
        Route::get('/pos/select', [PosController::class, 'select'])->name('superadmin.settings.pos.select');

        // Appearance — tenant-facing brand palettes managed by SuperAdmin.
        // Tenants pick from active palettes in their /admin/settings/branding
        // page; the default palette is the fallback for new and unconfigured
        // tenants. The default palette cannot be deleted or deactivated.
        Route::prefix('/color-palettes')->name('superadmin.color-palettes.')
            ->controller(ColorPaletteController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/data', 'data')->name('data');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{palette}/edit', 'edit')->name('edit');
                Route::put('/{palette}', 'update')->name('update');
                Route::delete('/{palette}', 'destroy')->name('destroy');
                Route::post('/{palette}/set-default', 'setDefault')->name('set-default');
                Route::post('/{palette}/toggle-active', 'toggleActive')->name('toggle-active');
            });

        // Logout
        Route::get('/logout', [AdminController::class, 'logout']);
    });
    // Auth Admin — superadmin accounts are provisioned via the
    // `apex:create-superadmin` artisan command. Public registration is
    // intentionally not exposed.
    Route::get('/login', [AdminController::class, 'index']);
    Route::post('/login', [AdminController::class, 'store'])->name('superadmin.login');
});
