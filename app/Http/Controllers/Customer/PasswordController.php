<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdatePasswordRequest;
use App\Models\CustomerRelations\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('customer.profile.password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $customer->update([
            'password' => $request->validated('password'),
        ]);

        return redirect()
            ->route('customer.password.edit')
            ->with('success', 'Password updated successfully.');
    }
}
