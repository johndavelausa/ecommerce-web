<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageAdminController extends Controller
{
    public function create(): View
    {
        return view('seller.message-admin');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $seller = auth('seller')->user()->seller;
        if (!$seller) {
            return redirect()->route('seller.dashboard');
        }

        $conv = Conversation::query()
            ->where('type', 'seller-admin')
            ->where('seller_id', $seller->id)
            ->first();

        if (!$conv) {
            $conv = Conversation::create([
                'seller_id' => $seller->id,
                'customer_id' => null,
                'type' => 'seller-admin',
            ]);
        }

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => auth('seller')->id(),
            'sender_type' => 'seller',
            'body' => trim($request->input('body')),
            'is_read' => false,
        ]);

        return redirect()->route('seller.message-admin')->with('status', 'Message sent.');
    }
}
