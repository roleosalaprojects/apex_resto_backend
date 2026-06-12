<?php

namespace App\Http\Controllers\Customer;

use App\Contracts\SmsRelayContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateProfileRequest;
use App\Models\CustomerRelations\Customer;
use App\Models\Reports\AuditLog;
use App\Services\VeroSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('customer.profile.edit', [
            'customer' => $request->user('customer'),
        ]);
    }

    public function update(UpdateProfileRequest $request, SmsRelayContract $sms): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $newPhone = $request->normalizedSubmittedPhone();
        $phoneChanged = $newPhone !== null && $newPhone !== $customer->phone;

        // Phone is the fraud-anchor. A swap without proving control of
        // the new number would defeat the OTP-at-registration guarantee,
        // so we re-verify before persisting.
        if ($phoneChanged && ! $sms->verify($newPhone, (string) $request->input('otp'))) {
            return back()
                ->withErrors(['otp' => 'That code didn\'t match. Request a new one and try again.'])
                ->withInput();
        }

        $data = $request->only([
            'name',
            'address',
            'city',
            'zip',
            'province',
            'country',
            'e_name',
            'e_phone',
            'e_address',
        ]);

        $data['phone'] = $newPhone ?? $customer->phone;

        // Unchecked checkbox -> field absent in request, so fall back to
        // false explicitly. Customers who never see the toggle (e.g.
        // POST without the field) won't accidentally flip the flag.
        $data['sms_notifications_enabled'] = $request->boolean('sms_notifications_enabled');

        if ($phoneChanged) {
            $data['phone_verified_at'] = now();
        }

        if ($request->boolean('remove_avatar')) {
            $this->deleteAvatar($customer->image);
            $data['image'] = null;
        }

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($customer->image);
            $data['image'] = $this->storeAvatar($request);
        }

        $oldPhone = $customer->phone;
        $customer->update($data);

        // Phone is the fraud-anchor. A change must leave a permanent
        // trail beyond `phone_verified_at` — if a customer later
        // disputes an order, this row proves the swap happened, when,
        // and that the new number was OTP-verified before the save.
        if ($phoneChanged) {
            // Customer-driven action — write directly so user_id stays
            // NULL (audit_logs.user_id FKs to users/admins, not
            // customers). See AuthController::register for the rationale.
            AuditLog::create([
                'user_id' => null,
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'event' => 'customer_phone_changed',
                'source' => 'web',
                'old_values' => ['phone' => $oldPhone],
                'new_values' => [
                    'customer_id' => $customer->id,
                    'new_phone' => $customer->phone,
                    'verified_via_otp' => true,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]);
        }

        return redirect()
            ->route('customer.profile.edit')
            ->with('success', $phoneChanged
                ? 'Profile updated. Your new phone number has been verified.'
                : 'Profile updated successfully.');
    }

    /**
     * Authenticated counterpart to the registration OTP endpoint —
     * lets a logged-in customer request a code on a NEW phone they want
     * to switch to. Throttled at the route layer.
     */
    public function sendPhoneOtp(Request $request, SmsRelayContract $sms): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^(\+?63|0)9\d{9}$/'],
        ], [
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Please provide a valid PH mobile number (e.g. 09171234567).',
        ]);

        $candidate = $sms->normalizePhone($validated['phone']);
        $customer = $request->user('customer');

        // "Already taken" and "same as current" collapse into a single
        // generic response. Distinct messages would let an authenticated
        // attacker walk a phone book and learn which numbers belong to
        // OTHER customers (account enumeration). Save-time validation
        // still rejects the swap, so blocking dispatch here is enough.
        $unavailable = $candidate === $customer->phone
            || Customer::where('phone', $candidate)
                ->where('id', '!=', $customer->id)
                ->exists();

        if ($unavailable) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'This phone number can\'t be used for your account.',
            ], 422);
        }

        $result = $sms->sendOtp($candidate, $request->ip());

        $status = match ($result['status']) {
            VeroSmsService::RESULT_OK => 200,
            VeroSmsService::RESULT_COOLDOWN => 429,
            VeroSmsService::RESULT_HOURLY_CAP => 429,
            default => 502,
        };

        return response()->json($result, $status);
    }

    protected function storeAvatar(Request $request): string
    {
        $file = $request->file('avatar');
        $extension = $file->guessExtension();

        abort_unless(
            in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true),
            422,
            'Unsupported avatar format.'
        );

        $name = Str::random(24).'.'.$extension;
        $location = 'img/customers/';

        $file->move(public_path($location), $name);

        return $location.$name;
    }

    protected function deleteAvatar(?string $path): void
    {
        if (! $path) {
            return;
        }

        $absolute = public_path($path);

        if (is_file($absolute)) {
            unlink($absolute);
        }
    }
}
