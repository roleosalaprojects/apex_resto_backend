@extends('customer.layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-6">
        <h1 class="fw-bolder fs-2x mb-0" style="color: #1a1a2e;">My Profile</h1>
        <a href="{{ route('customer.password.edit') }}" class="btn fw-bold qb-btn-primary" style="border-radius: 8px;">
            <i class="ki-duotone ki-lock fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
            Change Password
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger d-flex align-items-start mb-5" style="border-radius: 8px;">
            <i class="ki-duotone ki-shield-cross fs-2x me-3 text-danger"><span class="path1"></span><span class="path2"></span></i>
            <div>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card qb-card">
        <div class="card-body p-8">
            <form action="{{ route('customer.profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-8 mb-8">
                    <div class="col-md-3 text-center">
                        <div class="mb-4">
                            @if ($customer->image)
                                <img src="{{ asset($customer->image) }}" alt="Avatar"
                                     class="rounded-circle"
                                     style="width: 140px; height: 140px; object-fit: cover; border: 4px solid var(--qb-primary);">
                            @else
                                <div class="d-flex align-items-center justify-content-center rounded-circle mx-auto"
                                     style="width: 140px; height: 140px; background: rgba(var(--qb-primary-rgb, 255, 140, 105), 0.12); border: 4px solid var(--qb-primary);">
                                    <i class="ki-duotone ki-profile-circle fs-4x qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                </div>
                            @endif
                        </div>
                        <label class="form-label fw-semibold">Profile Photo</label>
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                               class="form-control @error('avatar') is-invalid @enderror" style="border-radius: 8px;">
                        <small class="text-gray-500 d-block mt-1">JPG, PNG or WEBP. Max 2 MB.</small>
                        @error('avatar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror

                        @if ($customer->image)
                            <div class="form-check mt-3">
                                <input type="checkbox" name="remove_avatar" value="1" id="remove_avatar" class="form-check-input">
                                <label for="remove_avatar" class="form-check-label fw-semibold text-gray-700">Remove current photo</label>
                            </div>
                        @endif
                    </div>

                    <div class="col-md-9">
                        <div class="row g-5">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold required">Full Name</label>
                                <input type="text" name="name" value="{{ old('name', $customer->name) }}"
                                       class="form-control @error('name') is-invalid @enderror" style="border-radius: 8px;" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" value="{{ $customer->email }}" class="form-control" style="border-radius: 8px;" disabled readonly>
                                <small class="text-gray-500 d-block mt-1">Email cannot be changed.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold required">Phone</label>
                                <input type="text" name="phone" id="phoneField"
                                       value="{{ old('phone', $customer->phone) }}"
                                       data-original-phone="{{ $customer->phone }}"
                                       class="form-control @error('phone') is-invalid @enderror" style="border-radius: 8px;">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror

                                {{-- OTP block: hidden by default. JS reveals when the
                                     phone value diverges from the value on file, since
                                     we must prove control of the new number before save. --}}
                                <div id="phoneOtpBlock" class="mt-3 p-3"
                                     style="display: none; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px;">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ki-duotone ki-shield-tick fs-3 me-2 text-warning"><span class="path1"></span><span class="path2"></span></i>
                                        <small class="fw-semibold text-gray-800">Verify your new phone number</small>
                                    </div>
                                    <div class="d-flex gap-2 mb-2">
                                        <input type="text" name="otp" id="phoneOtp" inputmode="numeric" maxlength="6"
                                               placeholder="6-digit code"
                                               class="form-control form-control-sm @error('otp') is-invalid @enderror"
                                               style="border-radius: 6px;" autocomplete="one-time-code">
                                        <button type="button" id="sendPhoneOtpBtn" class="btn btn-sm fw-bold qb-btn-primary"
                                                style="border-radius: 6px; white-space: nowrap;">Send Code</button>
                                    </div>
                                    <small id="phoneOtpStatus" class="d-block text-gray-600"></small>
                                    @error('otp')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Country</label>
                                <input type="text" name="country" value="{{ old('country', $customer->country) }}"
                                       class="form-control @error('country') is-invalid @enderror" style="border-radius: 8px;">
                                @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Address</label>
                                <textarea name="address" rows="2"
                                          class="form-control @error('address') is-invalid @enderror" style="border-radius: 8px;">{{ old('address', $customer->address) }}</textarea>
                                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">City</label>
                                <input type="text" name="city" value="{{ old('city', $customer->city) }}"
                                       class="form-control @error('city') is-invalid @enderror" style="border-radius: 8px;">
                                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Province</label>
                                <input type="text" name="province" value="{{ old('province', $customer->province) }}"
                                       class="form-control @error('province') is-invalid @enderror" style="border-radius: 8px;">
                                @error('province')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">ZIP</label>
                                <input type="text" name="zip" value="{{ old('zip', $customer->zip) }}"
                                       class="form-control @error('zip') is-invalid @enderror" style="border-radius: 8px;">
                                @error('zip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="fw-bold fs-4 mb-4" style="color: #1a1a2e;">Notifications</h3>
                <div class="row g-5 mb-8">
                    <div class="col-12">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input type="hidden" name="sms_notifications_enabled" value="0">
                            <input type="checkbox" name="sms_notifications_enabled" value="1"
                                   id="smsNotificationsToggle"
                                   class="form-check-input"
                                   {{ old('sms_notifications_enabled', $customer->sms_notifications_enabled) ? 'checked' : '' }}>
                            <label for="smsNotificationsToggle" class="form-check-label fw-semibold ms-3">
                                Text me when my order status changes
                                <small class="d-block text-gray-600 fw-normal mt-1">
                                    We'll send a short SMS for key updates (verified, paid, ready for pickup, cancelled). Standard carrier rates apply.
                                </small>
                            </label>
                        </div>
                    </div>
                </div>

                <h3 class="fw-bold fs-4 mb-4" style="color: #1a1a2e;">Emergency Contact</h3>
                <div class="row g-5 mb-8">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Name</label>
                        <input type="text" name="e_name" value="{{ old('e_name', $customer->e_name) }}"
                               class="form-control @error('e_name') is-invalid @enderror" style="border-radius: 8px;">
                        @error('e_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Phone</label>
                        <input type="text" name="e_phone" value="{{ old('e_phone', $customer->e_phone) }}"
                               class="form-control @error('e_phone') is-invalid @enderror" style="border-radius: 8px;">
                        @error('e_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Contact Address</label>
                        <textarea name="e_address" rows="2"
                                  class="form-control @error('e_address') is-invalid @enderror" style="border-radius: 8px;">{{ old('e_address', $customer->e_address) }}</textarea>
                        @error('e_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ route('customer.dashboard') }}" class="btn btn-light fw-semibold" style="border-radius: 8px;">Cancel</a>
                    <button type="submit" id="profileSaveBtn" class="btn fw-bold qb-btn-primary" style="border-radius: 8px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    (function () {
        const phoneField = document.getElementById('phoneField');
        const otpBlock = document.getElementById('phoneOtpBlock');
        const otpInput = document.getElementById('phoneOtp');
        const sendBtn = document.getElementById('sendPhoneOtpBtn');
        const statusLine = document.getElementById('phoneOtpStatus');
        const saveBtn = document.getElementById('profileSaveBtn');

        if (!phoneField || !otpBlock || !sendBtn) return;

        const originalPhone = (phoneField.dataset.originalPhone || '').trim();
        const sendUrl = @json(route('customer.profile.send-phone-otp'));
        const csrf = @json(csrf_token());

        let cooldownTimer = null;
        let lastSentForPhone = null;

        function setStatus(text, kind) {
            statusLine.textContent = text;
            statusLine.style.color = kind === 'error' ? '#dc3545'
                : kind === 'ok' ? '#198754'
                : '#64748b';
        }

        // Compare loosely — strip whitespace so "  09171234567 " == "09171234567".
        function isPhoneChanged() {
            return phoneField.value.trim() !== originalPhone;
        }

        function refreshUi() {
            if (isPhoneChanged()) {
                otpBlock.style.display = '';
                if (!statusLine.textContent) {
                    setStatus('Phone changed. Tap Send Code to verify the new number.', 'pending');
                }
            } else {
                otpBlock.style.display = 'none';
                otpInput.value = '';
                setStatus('', 'pending');
            }
        }

        phoneField.addEventListener('input', function () {
            // Editing the phone after a code was sent invalidates the code —
            // wipe what they typed and prompt for a fresh request.
            if (lastSentForPhone !== null && phoneField.value.trim() !== lastSentForPhone) {
                otpInput.value = '';
                if (cooldownTimer) { clearInterval(cooldownTimer); cooldownTimer = null; }
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send Code';
                setStatus('Phone number changed — tap Send Code again to verify the new one.', 'pending');
                lastSentForPhone = null;
            }
            refreshUi();
        });

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
            const phone = phoneField.value.trim();
            if (!phone) {
                setStatus('Enter your phone number first.', 'error');
                phoneField.focus();
                return;
            }
            if (!isPhoneChanged()) {
                setStatus('This is already your current phone number.', 'error');
                return;
            }
            setStatus('Sending…', 'pending');
            sendBtn.disabled = true;

            try {
                const res = await fetch(sendUrl, {
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
                    let msg = body.message || 'Code sent. Check your phone.';
                    if (body.dev_code) { msg += ` (dev: ${body.dev_code})`; }
                    setStatus(msg, 'ok');
                    lastSentForPhone = phone;
                    otpInput.focus();
                    startCooldown(60);
                } else if (res.status === 429) {
                    const retryIn = Number(body.retry_in) || 60;
                    setStatus(body.message || 'Please wait before trying again.', 'error');
                    startCooldown(retryIn);
                } else {
                    setStatus(body.message || 'Could not send code. Please try again.', 'error');
                    sendBtn.disabled = false;
                }
            } catch (_) {
                setStatus('Network error. Please try again.', 'error');
                sendBtn.disabled = false;
            }
        });

        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        // Save-button guard: surface the issue inline so the customer
        // doesn't post the form, get bounced, and have to figure out
        // what went wrong from a generic validation error.
        saveBtn.addEventListener('click', function (e) {
            if (!isPhoneChanged()) return;
            if (otpInput.value.trim().length !== 6) {
                e.preventDefault();
                if (lastSentForPhone === null) {
                    setStatus('Tap Send Code to receive a verification code first.', 'error');
                } else {
                    setStatus('Enter the 6-digit code we texted you.', 'error');
                }
                otpInput.focus();
            }
        });

        // Initial render — handles old() input on validation bounce.
        refreshUi();
    })();
</script>
@endsection
