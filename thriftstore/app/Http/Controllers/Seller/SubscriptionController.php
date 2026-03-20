<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    /**
     * Handle an incoming seller subscription payment request.
     */
    public function store(Request $request): RedirectResponse
    {
        $seller = Auth::guard('seller')->user()->seller;

        if (!$seller || $seller->status !== 'approved') {
            abort(403, 'Unauthorized seller status.');
        }

        $request->merge([
            'reference_number' => preg_replace('/\D+/', '', (string) $request->input('reference_number', '')),
        ]);

        $request->validate([
            'gcash_number' => ['required', 'string', 'max:50'],
            'reference_number' => ['required', 'digits:13', 'unique:payments,reference_number'],
            'payment_screenshot' => ['required', 'image', 'max:5120'],
        ]);

        $screenshotPath = $request->file('payment_screenshot')->store('payments', 'public');

        Payment::create([
            'seller_id' => $seller->id,
            'type' => 'subscription',
            'amount' => 500.00,
            'gcash_number' => $request->gcash_number,
            'reference_number' => $request->reference_number,
            'screenshot_path' => $screenshotPath,
            'status' => 'pending',
            'paid_at' => now(),
        ]);

        return redirect()->route('seller.status')->with('status', 'subscription-submitted');
    }
}
