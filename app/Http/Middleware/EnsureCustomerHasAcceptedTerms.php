<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate authenticated shop areas behind Terms acceptance.
 *
 * Customers who self-register via /shop accept the Terms at signup
 * (terms_accepted_at stamped by AuthController::register). Customers
 * created elsewhere — POS counter, admin Customer CRUD, imports — get
 * a NULL value and must accept before they can use authenticated shop
 * features (dashboard, cart, checkout, orders).
 *
 * The middleware redirects them to /shop/terms, where the same page
 * that serves the public policy renders an extra "Accept" form when
 * an unaccepted customer is signed in.
 */
class EnsureCustomerHasAcceptedTerms
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer && $customer->terms_accepted_at === null) {
            // Don't trap them on the Terms page itself, the accept
            // submission endpoint, or logout.
            $allowed = ['shops.terms', 'customer.terms.accept', 'customer.logout'];
            if (in_array($request->route()?->getName(), $allowed, true)) {
                return $next($request);
            }

            return redirect()
                ->route('shops.terms')
                ->with('mustAcceptTerms', true);
        }

        return $next($request);
    }
}
