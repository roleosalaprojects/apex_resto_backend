@php
    $branding = app(\App\Services\BrandingService::class)->forStorefront();
    $brandName = $branding['brand_name'] ?: 'Quick Baskets';
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="light" data-bs-theme="light">
<head>
    <base href=""/>
    <title>{{ $brandName }} - Register</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{asset('assets/media/logos/favicon.ico')}}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="{{asset('assets/plugins/global/plugins.bundle.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/css/style.bundle.css')}}" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --qb-primary: {{ $branding['primary'] }};
            --qb-primary-dark: {{ $branding['secondary'] }};
            --qb-accent: {{ $branding['accent'] }};
            --bs-primary: {{ $branding['primary'] }};
        }
    </style>
</head>
<body id="kt_body" class="app-blank" style="background: linear-gradient(135deg, var(--qb-primary), var(--qb-primary-dark)); background-attachment: fixed; min-height: 100vh;">
<script>
    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.setAttribute('data-bs-theme', 'light');
</script>
<div class="d-flex flex-column flex-root" id="kt_app_root">
    <div class="d-flex flex-center flex-column flex-column-fluid p-10">
        <div class="card w-md-500px" style="border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
            <div class="card-body p-10">
                <form class="form w-100" novalidate="novalidate" id="customer_register_form" action="{{ route('customer.register.submit') }}" method="POST">
                    @csrf
                    <div class="text-center mb-11">
                        @if (! empty($branding['logo_url']))
                            <img src="{{ $branding['logo_url'] }}" alt="{{ $brandName }}" class="mb-3" style="max-height: 60px;">
                        @else
                            <h1 class="fw-bolder mb-3" style="color: var(--qb-primary);">{{ $brandName }}</h1>
                        @endif
                        <h3 class="fw-bold mb-3" style="color: #1a1a2e;">Customer Registration</h3>
                    </div>
                    <div class="fv-row mb-8">
                        <input type="text" placeholder="Full Name" name="name" value="{{ old('name') }}" autocomplete="name" class="form-control bg-transparent @error('name') is-invalid @enderror" style="border-radius: 8px;" required/>
                        @error('name')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="fv-row mb-8">
                        <input type="email" placeholder="Email Address" name="email" value="{{ old('email') }}" autocomplete="email" class="form-control bg-transparent @error('email') is-invalid @enderror" style="border-radius: 8px;" required/>
                        @error('email')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="fv-row mb-3">
                        <div class="d-flex gap-2">
                            <input type="text"
                                   placeholder="Phone (e.g. 09171234567)"
                                   name="phone"
                                   id="customer_phone"
                                   value="{{ old('phone') }}"
                                   autocomplete="tel"
                                   class="form-control bg-transparent @error('phone') is-invalid @enderror"
                                   style="border-radius: 8px;"
                                   required/>
                            <button type="button"
                                    id="customer_send_otp_btn"
                                    class="btn fw-semibold"
                                    style="background: var(--qb-primary); color: {{ $branding['on_primary'] }}; border-radius: 8px; white-space: nowrap; min-width: 120px;">
                                Send Code
                            </button>
                        </div>
                        @error('phone')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                        <div class="d-flex align-items-start mt-2 fs-8 text-gray-600">
                            <i class="ki-duotone ki-shield-tick fs-6 me-1" style="color: var(--qb-primary);"><span class="path1"></span><span class="path2"></span></i>
                            <span>We'll text a 6-digit code to your phone to confirm it's yours. Tap <strong>Send Code</strong> before registering.</span>
                        </div>
                        <div id="customer_otp_status" class="fs-7 mt-2" style="min-height: 18px;"></div>
                    </div>
                    <div class="fv-row mb-8" id="customer_otp_row" style="display: none;">
                        <input type="text"
                               placeholder="6-digit verification code"
                               name="otp"
                               id="customer_otp"
                               inputmode="numeric"
                               maxlength="6"
                               autocomplete="one-time-code"
                               class="form-control bg-transparent @error('otp') is-invalid @enderror"
                               style="border-radius: 8px; letter-spacing: 6px; text-align: center; font-weight: 700;"/>
                        @error('otp')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="fv-row mb-8">
                        <textarea placeholder="Address" name="address" rows="2" autocomplete="street-address" class="form-control bg-transparent @error('address') is-invalid @enderror" style="border-radius: 8px;">{{ old('address') }}</textarea>
                        @error('address')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="fv-row mb-8">
                        <input type="password" placeholder="Password" name="password" autocomplete="new-password" class="form-control bg-transparent @error('password') is-invalid @enderror" style="border-radius: 8px;" required/>
                        @error('password')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="fv-row mb-8">
                        <input type="password" placeholder="Confirm Password" name="password_confirmation" autocomplete="new-password" class="form-control bg-transparent" style="border-radius: 8px;" required/>
                    </div>
                    <div class="fv-row mb-6">
                        <label class="form-check form-check-inline align-items-start" style="cursor: pointer;">
                            <input class="form-check-input mt-1 @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" {{ old('terms') ? 'checked' : '' }} required>
                            <span class="form-check-label fw-semibold text-gray-700 fs-7 ms-2">
                                I have read and agree to the
                                <a href="{{ route('shops.terms') }}" target="_blank" rel="noopener" style="color: var(--qb-primary); font-weight: 600;">Terms and Conditions</a>
                                and consent to the processing of my personal information as described.
                            </span>
                        </label>
                        @error('terms')
                            <div class="text-danger fs-7 mt-1"><strong>{{ $message }}</strong></div>
                        @enderror
                    </div>
                    <div class="d-flex justify-content-end mb-10">
                        <a href="{{ route('customer.login') }}" class="page-link" style="color: var(--qb-primary);">Already have an account? Sign In</a>
                    </div>
                    <div class="d-grid mb-10">
                        <button type="submit" id="customer_register_submit" class="btn fw-bold" style="background: var(--qb-primary); color: {{ $branding['on_primary'] }}; border-radius: 8px; padding: 12px;">
                            <span class="indicator-label">Register</span>
                            <span class="indicator-progress">Please wait...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                    <div class="text-center">
                        <a href="{{ route('shops.') }}" class="text-gray-500 fw-semibold text-decoration-none">Back to Shop</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="{{asset('assets/plugins/global/plugins.bundle.js')}}"></script>
<script src="{{asset('assets/js/scripts.bundle.js')}}"></script>
<script src="{{asset('assets/js/custom/landing.js')}}"></script>
<script>
    $(document).ready(function(){
        var form, submitButton, validator;
        form = document.querySelector("#customer_register_form");
        submitButton = document.querySelector("#customer_register_submit");
        validator = FormValidation.formValidation(
            form,
            {
                fields: {
                    'name': {
                        validators: { notEmpty: { message: "Full name is required." } }
                    },
                    'email': {
                        validators: {
                            notEmpty: { message: "Email is required." },
                            emailAddress: { message: "Invalid email address." }
                        }
                    },
                    'password': {
                        validators: {
                            notEmpty: { message: "Password is required." },
                            stringLength: { min: 8, message: "Password must be at least 8 characters." }
                        }
                    },
                    'password_confirmation': {
                        validators: {
                            notEmpty: { message: "Please confirm your password." },
                            identical: {
                                compare: function() { return form.querySelector('[name="password"]').value; },
                                message: "Passwords do not match."
                            }
                        }
                    },
                    'terms': {
                        validators: {
                            notEmpty: { message: "You must accept the Terms and Conditions to continue." }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: '.fv-row',
                        eleInvalidClass: "",
                        eleValidClass: "",
                    })
                }
            }
        )

        submitButton.addEventListener('click', function(e){
            e.preventDefault();

            // Phone OTP is the fraud anchor — block submit until the
            // customer has requested AND entered a code. This shows a
            // friendly nudge instead of the server-side validation error.
            const phoneEl = document.getElementById('customer_phone');
            const otpEl = document.getElementById('customer_otp');
            const otpRowEl = document.getElementById('customer_otp_row');
            const statusEl = document.getElementById('customer_otp_status');

            if (phoneEl.value.trim() && otpRowEl.style.display === 'none') {
                statusEl.textContent = 'Tap Send Code to receive a verification code first.';
                statusEl.style.color = '#dc3545';
                phoneEl.focus();
                return;
            }
            if (phoneEl.value.trim() && otpEl.value.trim().length !== 6) {
                statusEl.textContent = 'Enter the 6-digit code we texted you.';
                statusEl.style.color = '#dc3545';
                otpEl.focus();
                return;
            }

            validator.validate().then(function (status){
                if(status == 'Valid'){
                    submitButton.setAttribute('data-kt-indicator', 'on');
                    submitButton.disabled = true;
                    form.submit();
                }
            });
        });
    })
</script>
<script>
    (function () {
        const phoneInput = document.getElementById('customer_phone');
        const sendBtn = document.getElementById('customer_send_otp_btn');
        const otpRow = document.getElementById('customer_otp_row');
        const otpInput = document.getElementById('customer_otp');
        const statusLine = document.getElementById('customer_otp_status');
        const sendOtpUrl = @json(route('customer.register.send-otp'));
        const csrf = @json(csrf_token());

        let cooldownTimer = null;

        function setStatus(text, kind) {
            statusLine.textContent = text;
            statusLine.style.color = kind === 'error' ? '#dc3545'
                : kind === 'ok' ? '#198754'
                : kind === 'pending' ? '#64748b'
                : '#64748b';
        }

        function startCooldown(seconds) {
            sendBtn.disabled = true;
            let remaining = seconds;
            sendBtn.textContent = `Resend (${remaining}s)`;
            if (cooldownTimer) clearInterval(cooldownTimer);
            cooldownTimer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(cooldownTimer);
                    cooldownTimer = null;
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Resend Code';
                } else {
                    sendBtn.textContent = `Resend (${remaining}s)`;
                }
            }, 1000);
        }

        sendBtn.addEventListener('click', async function () {
            const phone = phoneInput.value.trim();
            if (!phone) {
                setStatus('Enter your phone number first.', 'error');
                phoneInput.focus();
                return;
            }
            setStatus('Sending…', 'pending');
            sendBtn.disabled = true;

            try {
                const res = await fetch(sendOtpUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ phone }),
                });
                const body = await res.json().catch(() => ({}));

                if (res.ok) {
                    otpRow.style.display = '';
                    otpInput.focus();
                    let msg = body.message || 'Code sent. Check your messages.';
                    // Dev mode echoes the code for QA convenience.
                    if (body.dev_code) {
                        msg += ` (dev: ${body.dev_code})`;
                    }
                    setStatus(msg, 'ok');
                    startCooldown(60);
                } else if (res.status === 429) {
                    const retryIn = Number(body.retry_in) || 60;
                    setStatus(body.message || 'Please wait before trying again.', 'error');
                    startCooldown(retryIn);
                } else if (res.status === 422 && body.errors?.phone) {
                    setStatus(body.errors.phone[0], 'error');
                    sendBtn.disabled = false;
                } else {
                    setStatus(body.message || 'Could not send code. Please try again.', 'error');
                    sendBtn.disabled = false;
                }
            } catch (_) {
                setStatus('Network error. Please try again.', 'error');
                sendBtn.disabled = false;
            }
        });

        // Strip non-digit input from the OTP box so paste with spaces
        // / dashes still works.
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        // If the customer edits the phone AFTER a code was sent, the
        // OTP they entered is for a different number — invalidate it
        // and force them to re-request, otherwise the server will
        // reject the verify() check with a confusing error.
        let lastSentForPhone = null;
        const origSendHandler = sendBtn.onclick;
        sendBtn.addEventListener('click', function () {
            lastSentForPhone = phoneInput.value.trim();
        });
        phoneInput.addEventListener('input', function () {
            if (lastSentForPhone !== null && this.value.trim() !== lastSentForPhone) {
                otpRow.style.display = 'none';
                otpInput.value = '';
                setStatus('Phone number changed — tap Send Code again to verify the new one.', 'pending');
                if (cooldownTimer) {
                    clearInterval(cooldownTimer);
                    cooldownTimer = null;
                }
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send Code';
            }
        });
    })();
</script>
</body>
</html>
