<?php

namespace App\Http\Requests\Customer;

use App\Contracts\SmsRelayContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    public function rules(): array
    {
        $customer = $this->user('customer');
        $newPhone = $this->normalizedSubmittedPhone();
        $currentPhone = $customer?->phone;

        $phoneChanged = $newPhone !== null && $newPhone !== $currentPhone;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(\+?63|0)9\d{9}$/',
                Rule::unique('customers', 'phone')->ignore($customer?->id),
            ],
            'otp' => [
                $phoneChanged ? 'required' : 'nullable',
                'string',
                'size:6',
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'e_name' => ['nullable', 'string', 'max:255'],
            'e_phone' => ['nullable', 'string', 'max:20'],
            'e_address' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'sms_notifications_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Please provide a valid PH mobile number (e.g. 09171234567).',
            'phone.unique' => 'That phone number is already linked to another account.',
            'otp.required' => 'Enter the verification code sent to your new phone before saving.',
            'otp.size' => 'The verification code is 6 digits.',
            'avatar.image' => 'Avatar must be an image.',
            'avatar.mimes' => 'Avatar must be a JPG, PNG or WEBP file.',
            'avatar.max' => 'Avatar may not be larger than 2 MB.',
        ];
    }

    /**
     * Normalize whatever the user typed (+639, 639, 09…) to the canonical
     * 09XXXXXXXXX form used in the DB. Returns null if the field is blank
     * so we can detect "no change" cleanly.
     */
    public function normalizedSubmittedPhone(): ?string
    {
        $raw = $this->input('phone');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return app(SmsRelayContract::class)->normalizePhone($raw);
    }
}
