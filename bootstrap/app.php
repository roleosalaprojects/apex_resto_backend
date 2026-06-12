<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/admin.php',
            __DIR__.'/../routes/superadmin.php',
            __DIR__.'/../routes/ecommerce.php',
            __DIR__.'/../routes/advertisements.php',
        ],
        api: [
            __DIR__.'/../routes/api.php',
            __DIR__.'/../routes/api/pos.php',
            __DIR__.'/../routes/api/mobile.php',
            __DIR__.'/../routes/api/openclaw.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\NoSniffHeader::class);

        // Webhook endpoints authenticate via HMAC-SHA256 signatures
        // in the controller, not via a Laravel session cookie. CSRF
        // doesn't apply (and would always fail since the caller has
        // no session to read the token from).
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->alias([
            'customer.auth' => \App\Http\Middleware\CustomerAuthenticate::class,
            'customer.api.auth' => \App\Http\Middleware\CustomerApiAuthenticate::class,
            'customer.guest' => \App\Http\Middleware\RedirectIfCustomerAuthenticated::class,
            'customer.verified' => \App\Http\Middleware\EnsureCustomerEmailIsVerified::class,
            'customer.terms' => \App\Http\Middleware\EnsureCustomerHasAcceptedTerms::class,
            'log.shop.visit' => \App\Http\Middleware\LogShopVisit::class,
            'openclaw.ability' => \App\Http\Middleware\CheckOpenclawAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
