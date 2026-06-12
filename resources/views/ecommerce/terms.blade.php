<x-ecommerce.layout.app>
    @slot('styles')
    <style>
        .terms-wrap {
            max-width: 860px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }
        .terms-wrap h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--qb-primary);
        }
        .terms-wrap .terms-meta {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .terms-wrap h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            color: #1a1a2e;
        }
        .terms-wrap p, .terms-wrap li {
            color: #2d3142;
            line-height: 1.65;
        }
        .terms-wrap ul {
            padding-left: 1.25rem;
        }
        .terms-wrap a.back-to-register {
            display: inline-block;
            margin-top: 2rem;
            color: var(--qb-primary);
            font-weight: 600;
            text-decoration: none;
        }
    </style>
    @endslot

    <div class="container py-5">
        <div class="terms-wrap">
            @php
                $brandName = $branding['brand_name'] ?? config('app.name', 'this Shop');
            @endphp

            <h1>Terms and Conditions</h1>
            <p class="terms-meta">Last updated: {{ now()->format('F j, Y') }}</p>

            @auth('customer')
                @php($needsToAccept = auth('customer')->user()->terms_accepted_at === null)
                @if ($needsToAccept)
                    <div class="alert alert-warning d-flex align-items-start gap-3" style="border-radius: 10px; background: #fff7e6; border: 1px solid #ffd58a;">
                        <i class="ki-duotone ki-information-5 fs-2x text-warning"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div>
                            <div class="fw-bold mb-1">Please review and accept our Terms to continue.</div>
                            <div class="fs-7 text-muted">
                                Your account was set up outside the storefront (in-store at the counter or by an administrator). Before you can use your account on the shop, we need you to confirm that you have read and agree to the Terms below.
                            </div>
                        </div>
                    </div>
                @endif
            @endauth

            <p>
                Welcome to {{ $brandName }}. By creating an account or using our shop, you agree to these Terms and Conditions and to our practices for collecting, using, and protecting your personal information as described below.
            </p>

            <h2>1. Personal Information We Collect</h2>
            <p>To process your registration and orders we collect:</p>
            <ul>
                <li>Your name, email address, mobile number, and delivery address;</li>
                <li>Account credentials (password, stored only in hashed form);</li>
                <li>Your order history, cart activity, and payment confirmation details;</li>
                <li>Technical information such as device, browser, IP address, and visit patterns, used to keep the shop secure and to improve the experience.</li>
            </ul>

            <h2>2. How We Use Your Information</h2>
            <ul>
                <li>To create and manage your customer account;</li>
                <li>To process, fulfill, and deliver your orders;</li>
                <li>To communicate with you about your orders, account, and reasonable service updates;</li>
                <li>To prevent fraud, abuse, and unauthorized access to your account;</li>
                <li>To comply with applicable laws and respond to lawful requests.</li>
            </ul>

            <h2>3. Sharing With Third Parties</h2>
            <p>
                We share the minimum information needed with trusted service providers — for example, delivery couriers (name, contact number, and delivery address), payment processors (amount and transaction reference), and email/SMS providers (your contact details for transactional messages). We do not sell your personal information.
            </p>

            <h2>4. Storage and Security</h2>
            <p>
                Your information is stored on secured servers. We apply reasonable technical and organizational measures to protect it from loss, misuse, and unauthorized access. No system is perfectly secure, but we take steps proportionate to the sensitivity of the data.
            </p>

            <h2>5. Your Rights (Republic Act No. 10173 — Data Privacy Act of 2012)</h2>
            <p>You have the right to:</p>
            <ul>
                <li>Be informed about how your data is processed;</li>
                <li>Access the data we hold about you;</li>
                <li>Correct or update inaccurate information;</li>
                <li>Object to processing or request deletion of your data, subject to lawful exceptions (such as transaction records we are required to retain);</li>
                <li>Withdraw your consent at any time, without affecting the lawfulness of processing already carried out;</li>
                <li>File a complaint with the National Privacy Commission if you believe your rights have been violated.</li>
            </ul>

            <h2>6. Cookies and Similar Technologies</h2>
            <p>
                We use cookies to keep you signed in, remember items in your cart, and understand how the shop is used. You can control cookies through your browser settings; disabling them may break parts of the shop (for example, your cart will not persist).
            </p>

            <h2>7. Account Responsibilities</h2>
            <ul>
                <li>Keep your password confidential. You are responsible for activity on your account.</li>
                <li>Provide accurate information. Orders placed using inaccurate or false information may be cancelled.</li>
                <li>Use the shop lawfully. We may suspend accounts used for fraud, abuse, or violations of these Terms.</li>
            </ul>

            <h2>8. Changes to These Terms</h2>
            <p>
                We may update these Terms from time to time. When we do, we will update the "Last updated" date above. If the changes are material we will make a reasonable effort to notify you (for example, by email or an on-site banner) before they take effect.
            </p>

            <h2>9. Contact</h2>
            <p>
                For privacy questions, access requests, or to withdraw consent, contact us at the email address shown on our shop. Please include enough information for us to identify your account.
            </p>

            <hr>
            <p class="text-muted fs-7">
                This document is provided as a starting point and does not constitute legal advice. {{ $brandName }} should review and adapt it with qualified legal counsel before relying on it for compliance.
            </p>

            @auth('customer')
                @if (($needsToAccept ?? false) === true)
                    <form method="POST" action="{{ route('customer.terms.accept') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="btn fw-bold" style="background: var(--qb-primary); color: {{ $branding['on_primary'] ?? '#fff' }}; border-radius: 8px; padding: 12px 28px;">
                            I have read and accept the Terms
                        </button>
                    </form>
                @else
                    <a href="{{ route('customer.dashboard') }}" class="back-to-register">&larr; Back to Dashboard</a>
                @endif
            @else
                <a href="{{ route('customer.register') }}" class="back-to-register">&larr; Back to Registration</a>
            @endauth
        </div>
    </div>

    @slot('scripts')@endslot
</x-ecommerce.layout.app>
