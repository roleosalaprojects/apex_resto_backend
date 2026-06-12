<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreRequest;
use App\Http\Traits\ApiResponse;
use App\Mail\ContactFormNotification;
use App\Models\CustomerRelations\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    use ApiResponse;

    /**
     * Store a new contact message.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $contactMessage = ContactMessage::create([
            ...$validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Send email notification
        Mail::to(config('mail.admin_email', 'roleosala@gmail.com'))
            ->send(new ContactFormNotification($contactMessage));

        return $this->created(
            ['id' => $contactMessage->id],
            'Thank you for your message! We will get back to you within 24-48 hours.'
        );
    }
}
