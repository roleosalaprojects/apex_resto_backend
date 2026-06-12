<?php

namespace App\Http\Controllers\Customer;

use App\Contracts\SmsRelayContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use App\Http\Requests\Customer\RegisterRequest;
use App\Models\CustomerRelations\Customer;
use App\Models\Reports\AuditLog;
use App\Services\VeroSmsService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('customer.auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::guard('customer')->attempt(array_merge($credentials, ['status' => true]), $remember)) {
            $request->session()->regenerate();

            return $this->safeIntended($request, route('customer.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegistrationForm(): View
    {
        return view('customer.auth.register');
    }

    /**
     * AJAX endpoint hit by the register form's "Send Code" button.
     * Generates an OTP, dispatches via VeroSMS (or the dev log), and
     * returns JSON the form's JS uses to flip into "code sent" state.
     */
    public function sendRegisterOtp(Request $request, SmsRelayContract $sms): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^(\+?63|0)9\d{9}$/'],
        ], [
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Please provide a valid PH mobile number (e.g. 09171234567).',
        ]);

        $result = $sms->sendOtp($validated['phone'], $request->ip());

        $status = match ($result['status']) {
            VeroSmsService::RESULT_OK => 200,
            VeroSmsService::RESULT_COOLDOWN => 429,
            VeroSmsService::RESULT_HOURLY_CAP => 429,
            default => 502,
        };

        // Dev mode echoes the code back so QA doesn't have to dig
        // through the log file. Never reached when VEROSMS_BASE_URL
        // is configured.
        return response()->json($result, $status);
    }

    public function register(RegisterRequest $request, SmsRelayContract $sms): RedirectResponse
    {
        if (! $sms->verify($request->input('phone'), $request->input('otp'))) {
            return back()
                ->withErrors(['otp' => 'That code didn\'t match. Request a new one and try again.'])
                ->onlyInput('name', 'email', 'phone', 'address');
        }

        $now = now();

        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $sms->normalizePhone($request->phone),
            'phone_verified_at' => $now,
            'email_verified_at' => $now,
            'address' => $request->address,
            'password' => $request->password,
            'code' => $this->generateCustomerCode(),
            'status' => true,
            'user_id' => 0,
            'points' => 0,
            'terms_accepted_at' => $now,
        ]);

        // Customer is the actor — no admin involved. audit_logs.user_id
        // FKs to users (admins), so we create the row directly rather
        // than going through AuditLog::record() (whose null-fallback
        // would mis-attribute this to whoever happens to be in the auth
        // guards). auditable_id IS the customer id — traceability is
        // preserved without violating the FK.
        AuditLog::create([
            'user_id' => null,
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'event' => 'customer_registered',
            'source' => 'web',
            'new_values' => [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'phone_verified' => true,
                'email_verified_via_phone_otp' => true,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
        ]);

        event(new Registered($customer));

        Auth::guard('customer')->login($customer);

        return redirect()->route('customer.dashboard')
            ->with('success', 'Registration successful! Welcome to Quick Baskets.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login')
            ->with('success', 'You have been logged out.');
    }

    /**
     * Stamp terms_accepted_at for the authenticated customer.
     * Used by customers created at POS / admin who must accept on
     * first authenticated visit to the shop.
     */
    public function acceptTerms(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if ($customer && $customer->terms_accepted_at === null) {
            $customer->forceFill(['terms_accepted_at' => now()])->save();
        }

        return $this->safeIntended($request, route('customer.dashboard'))
            ->with('success', 'Thank you. Your acceptance has been recorded.');
    }

    /**
     * Customer-safe version of redirect()->intended().
     *
     * Laravel stores `url.intended` in the session whenever a guest
     * gets bounced off a guarded route. That session key is global —
     * it doesn't know which guard triggered the bounce. So if an
     * anonymous user first tries /admin/anything and THEN logs in as
     * a customer, redirect()->intended() will happily send them to
     * the admin URL, which kicks them right back to /admin/login.
     *
     * Only honour the intended URL when it's actually a customer-side
     * path (/customer/* or /shop*). Otherwise drop it and use the
     * default fallback.
     */
    private function safeIntended(\Illuminate\Http\Request $request, string $fallback): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        if ($intended) {
            $path = parse_url($intended, PHP_URL_PATH) ?? '';
            if (str_starts_with($path, '/customer/') || $path === '/customer' || str_starts_with($path, '/shop')) {
                return redirect($intended);
            }
        }

        return redirect($fallback);
    }

    protected function generateCustomerCode(): string
    {
        do {
            $code = 'CUST-'.strtoupper(Str::random(8));
        } while (Customer::where('code', $code)->exists());

        return $code;
    }
}
