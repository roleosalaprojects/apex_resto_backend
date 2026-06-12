<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerRelations\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): RedirectResponse|View
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->intended(route('customer.dashboard'));
        }

        return view('customer.auth.verify-email');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        if (! $customer || ! hash_equals((string) $id, (string) $customer->getKey())) {
            abort(403);
        }

        if (! hash_equals($hash, sha1($customer->getEmailForVerification()))) {
            abort(403);
        }

        if ($customer->hasVerifiedEmail()) {
            return redirect()->intended(route('customer.dashboard').'?verified=1');
        }

        if ($customer->markEmailAsVerified()) {
            event(new Verified($customer));
        }

        return redirect()->intended(route('customer.dashboard').'?verified=1');
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->intended(route('customer.dashboard'));
        }

        $customer->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
