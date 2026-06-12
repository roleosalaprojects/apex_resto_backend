<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Mail\OrderShipped;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TestContoller extends Controller
{
    public function index(){
        return view('test.index');
    }
    public function sendMail(Request $request): RedirectResponse
    {
//        dd($request->content);
        Mail::to('roleosala@gmail.com')->send(new OrderShipped($request->payload));
        return redirect(route('tests.index'))->with('success', 'Email sent successfully!');
    }
}
