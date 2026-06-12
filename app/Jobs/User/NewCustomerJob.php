<?php

namespace App\Jobs\User;

use AndroidSmsGateway\Client;
use AndroidSmsGateway\Domain\Message;
use App\Models\CustomerRelations\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NewCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $phone;

    private string $customerName;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Customer $customer)
    {
        $this->phone = $customer->phone;
        $this->customerName = $customer->name;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = env('SMS_USER');
        $password = env('SMS_PASSWORD');
        $server = env('SMS_SERVER');

        $client = new Client($user, $password, $server);

        $message = "CONGRATULATIONS $this->customerName! You're now part of the exclusive LETERES STORE family!
 
As a valued member, you'll get access to EXCLUSIVE DISCOUNTS on select products - only available to members like you! 
 
But that's not all! Our point rewarding system is designed to show our appreciation for customers like you who support us. Earn points with every purchase, and redeem them for exclusive rewards, early access to new products, or even free merchandise!
 
We're so excited to have you on board and can't wait to share our quality products with you!";
        $message = new Message($message, [$this->phone]);

        try {
            $messageState = $client->Send($message);
            \Log::debug('Message sent with ID: '.$messageState->ID().PHP_EOL);
        } catch (Exception $e) {
            \Log::debug('Error sending message'.$e->getMessage().PHP_EOL);
            exit(1);
        }

        try {
            $messageState = $client->GetState($messageState->ID());
            \Log::debug('Message state: '.$messageState->State().PHP_EOL);
        } catch (Exception $e) {
            \Log::debug('Error getting message state: '.$e->getMessage().PHP_EOL);
            exit(1);
        }
    }
}
