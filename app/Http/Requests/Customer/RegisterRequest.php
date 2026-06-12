<?php

namespace App\Http\Requests\Customer;

use App\Contracts\SmsRelayContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pre-normalise the phone so the unique-against-customers check
     * runs against the canonical 09XXXXXXXXX form. Without this an
     * attacker could register a second account by submitting the same
     * phone in a different format (e.g. +63… vs 09…).
     */
    protected function prepareForValidation(): void
    {
        $raw = $this->input('phone');
        if (is_string($raw) && trim($raw) !== '') {
            $this->merge([
                'phone' => app(SmsRelayContract::class)->normalizePhone($raw),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            // PH numbers — accept 09XXXXXXXXX (11) or 639XXXXXXXXX (12)
            // with optional + prefix; we've already normalized to 09…
            // in prepareForValidation, so the regex is the strict form.
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^09\d{9}$/',
                Rule::unique('customers', 'phone'),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
            'otp' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Please provide a valid PH mobile number (e.g. 09171234567).',
            'phone.unique' => 'This phone number is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
            'terms.accepted' => 'You must accept the Terms and Conditions to continue.',
            'otp.required' => 'Enter the verification code we texted you.',
            'otp.size' => 'The verification code is 6 digits.',
        ];
    }
}
