<?php

namespace App\Http\Controllers\Admin\CustomerRelations;

use AndroidSmsGateway\Client;
use AndroidSmsGateway\Domain\Message;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Exception;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'recipient' => ['required'],
            'message' => ['required', 'string'],
        ]);
        $login = 'sms';
        $password = env('SMS_PASSWORD');
        $smsServer = env('SMS_SERVER');

        $client = new Client($login, $password, $smsServer);
        // or
        // $encryptor = new Encryptor('your_passphrase');
        // $client = new Client($login, $password, Client::DEFAULT_URL, $httpClient, $encryptor);

        $message = new Message($request->message, [$request->recipient]);

        try {
            $messageState = $client->Send($message);
            echo 'Message sent with ID: '.$messageState->ID().PHP_EOL;
        } catch (Exception $e) {
            echo 'Error sending message: '.$e->getMessage().PHP_EOL;
            exit(1);
        }

        try {
            $messageState = $client->GetState($messageState->ID());
            echo 'Message state: '.$messageState->State().PHP_EOL;
        } catch (Exception $e) {
            echo 'Error getting message state: '.$e->getMessage().PHP_EOL;
            exit(1);
        }
    }
}
