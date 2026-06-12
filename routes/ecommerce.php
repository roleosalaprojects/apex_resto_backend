<?php

use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\EmailVerificationController;
use App\Http\Controllers\Customer\PasswordController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Ecommerce\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('customer')->name('customer.')->group(function () {
    // Guest routes (not logged in)
    Route::middleware('customer.guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.submit');
        Route::get('register', [AuthController::class, 'showRegistrationForm'])->name('register');
        Route::post('register', [AuthController::class, 'register'])->name('register.submit');
        Route::post('register/send-otp', [AuthController::class, 'sendRegisterOtp'])
            ->middleware('throttle:5,1')
            ->name('register.send-otp');
    });

    // Authenticated routes
    Route::middleware('customer.auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        // Accept Terms — used by customers created outside /shop register
        // (POS counter, admin Customer CRUD, imports) on first authenticated
        // visit. The middleware below routes them here.
        Route::post('terms/accept', [AuthController::class, 'acceptTerms'])->name('terms.accept');

        Route::get('email/verify', [EmailVerificationController::class, 'notice'])
            ->name('verification.notice');
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');
        Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::middleware(['customer.verified', 'customer.terms'])->group(function () {
            Route::get('dashboard', function () {
                return view('customer.dashboard');
            })->name('dashboard');
            Route::get('orders', function () {
                return view('customer.orders');
            })->name('orders');
            // Bind by reference (random string like ECO-A1B2C3D4) instead
            // of the sequential integer id so a customer can't tweak the URL
            // and walk every order on the system.
            Route::get('orders/{ecommerceOrder:reference}', function (\App\Models\Ecommerce\EcommerceOrder $ecommerceOrder) {
                abort_unless(
                    $ecommerceOrder->customer_id === auth('customer')->id(),
                    403,
                    'You do not have access to this order.',
                );
                $ecommerceOrder->load([
                    'lines.item',
                    'sale.bank',
                    'sale.paymentProofs',
                    'pickupProofs',
                    'statusChanges',
                ]);

                return view('customer.order-show', compact('ecommerceOrder'));
            })->name('orders.show');

            Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('profile/send-phone-otp', [ProfileController::class, 'sendPhoneOtp'])
                ->middleware('throttle:5,1')
                ->name('profile.send-phone-otp');

            Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
            Route::put('password', [PasswordController::class, 'update'])->name('password.update');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Shop Routes (Public)
|--------------------------------------------------------------------------
*/

Route::prefix('/shop')->name('shops.')->middleware('log.shop.visit')->group(function () {
    Route::get('/', function () {
        // Spotlight categories: featured first, fall back to the full
        // active list (capped) so the homepage never goes blank during
        // initial adoption. See development/specs/shop_featured_curation/plan.md.
        $featuredCategories = \App\Models\Products\Category::featuredSpotlight()
            ->withCount('items')
            ->limit(12)
            ->get();

        $categories = $featuredCategories->isNotEmpty()
            ? $featuredCategories
            : \App\Models\Products\Category::where('status', true)
                ->withCount('items')
                ->orderBy('name')
                ->limit(12)
                ->get();

        // Featured Products: hidden when nothing is featured (no
        // existing section to regress, hidden is cleaner than fallback).
        $featuredItems = \App\Models\Products\Item::featuredSpotlight()
            ->with('category')
            ->limit(12)
            ->get();

        $announcements = \App\Models\Ecommerce\ShopAnnouncement::active()
            ->scheduled()
            ->hero()
            ->ordered()
            ->get();

        // Hero stats + in-page brand copy. Branding is composed into the
        // layout component only; slots render in this scope, so the page
        // needs its own copy (cached by BrandingService).
        $branding = app(\App\Services\BrandingService::class)->forStorefront();
        $productCount = \App\Models\Products\Item::where('status', true)->count();
        $categoryCount = \App\Models\Products\Category::where('status', true)->count();

        return view('ecommerce.index', compact(
            'categories', 'featuredItems', 'announcements',
            'branding', 'productCount', 'categoryCount',
        ));
    });
    Route::resource('products', ProductController::class);

    Route::view('/terms', 'ecommerce.terms')->name('terms');

    Route::middleware(['customer.auth', 'customer.verified', 'customer.terms'])->group(function () {
        Route::get('/cart', function () {
            return view('ecommerce.cart');
        })->name('cart');
    });
});
