<?php

namespace App\Notifications\Customer;

use App\Services\BrandingService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $branding = app(BrandingService::class)->forStorefront();
        $brandName = $branding['brand_name'] ?: config('app.name', 'Apex');
        $fromAddress = config('mail.from.address') ?: 'no-reply@'.parse_url(config('app.url'), PHP_URL_HOST);

        return (new MailMessage)
            ->from($fromAddress, $brandName)
            ->subject("{$brandName} - Verify Your Email Address")
            ->markdown('customer.emails.verify-email', [
                'url' => $this->verificationUrl($notifiable),
                'brandName' => $brandName,
            ]);
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'customer.verification.verify',
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
