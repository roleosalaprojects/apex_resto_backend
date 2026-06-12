<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductImageController;
use App\Http\Controllers\API\v1\desktop\AttendanceController;
use App\Http\Controllers\API\v1\pos\ItemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
| Note: POS and Mobile routes are in separate files:
| - routes/api/pos.php (POS terminal app)
| - routes/api/mobile.php (Mobile back office app)
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::name('api.')->group(function () {
    // Public Contact Form API
    Route::post('/v1/contact', [\App\Http\Controllers\API\v1\ContactController::class, 'store'])
        ->name('contact.store');

    // Price Checker APP
    Route::group(['prefix' => 'desktop'], function () {
        Route::get('/items/search', [ItemController::class, 'searchItem']);

        // Attendance Module
        Route::prefix('v1/attendance')->group(function () {
            Route::post('/time-in', [AttendanceController::class, 'timeIn']);
            Route::post('/time-out', [AttendanceController::class, 'timeOut']);
            Route::get('/today', [AttendanceController::class, 'today']);
            Route::get('/history', [AttendanceController::class, 'history']);
            Route::get('/summary', [AttendanceController::class, 'summary']);
            Route::get('/lookup', [AttendanceController::class, 'lookup']);
        });
    });

    // Customer E-commerce API
    Route::group(['prefix' => 'v1/customer'], function () {
        // Public routes (no authentication required)
        Route::post('/login', [\App\Http\Controllers\API\v1\customer\AuthController::class, 'login']);
        Route::post('/register', [\App\Http\Controllers\API\v1\customer\AuthController::class, 'register']);

        // Protected routes (customer authentication required)
        Route::middleware('auth:customer-api')->group(function () {
            Route::get('/me', [\App\Http\Controllers\API\v1\customer\AuthController::class, 'me']);
            Route::post('/logout', [\App\Http\Controllers\API\v1\customer\AuthController::class, 'logout']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| Product Image Updater API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1/image-updater')->group(function () {
    // Public route — image-updater login only. Users are provisioned via the
    // admin Employees module; there is no public registration on this surface
    // (removed for security; the old route minted full auth:api tokens to any
    // anonymous caller).
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes - require authentication (using Passport)
    Route::middleware(['auth:api'])->group(function () {
        // Auth routes
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);

        // Product image routes
        Route::get('/products/without-images', [ProductImageController::class, 'getProductsWithoutImages']);
        Route::post('/products/upload-image', [ProductImageController::class, 'uploadImage']);
        Route::put('/products/{id}/image', [ProductImageController::class, 'updateProductImage']);
        Route::post('/products/batch-upload-update', [ProductImageController::class, 'batchUploadAndUpdate']);
    });
});
