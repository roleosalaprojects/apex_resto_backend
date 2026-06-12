<?php

use App\Http\Controllers\Ecommerce\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Root → Quick Baskets shop. The RLCPS marketing landing page lives
// separately now; this domain (leteres.com) serves the shop directly.
Route::redirect('/', '/shop', 301);

// Inbound webhooks. CSRF-exempt (see bootstrap/app.php) — every
// handler authenticates via the provider's own signature scheme.
Route::post('webhooks/sms-gate', [\App\Http\Controllers\Webhooks\SmsGateWebhookController::class, 'handle'])
    ->name('webhooks.sms-gate');

Route::prefix('/shop')->name('shops.')->group(function () {
    Route::get('/', function () {
        $categories = \App\Models\Products\Category::where('status', true)->get();

        return view('ecommerce.index', compact('categories'));
    });
    Route::resource('products', ProductController::class);
});
