<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $conv = Conversation::create([
            'seller_id' => null,
            'customer_id' => null,
            'type' => 'guest',
        ]);

        $header = trim(implode(' | ', array_filter([
            $request->input('name') ? 'Name: ' . $request->input('name') : null,
            $request->input('email') ? 'Email: ' . $request->input('email') : null,
        ])));
        $body = $header ? $header . "\n\n" . $request->input('message') : $request->input('message');

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => null,
            'sender_type' => 'guest',
            'body' => $body,
            'is_read' => false,
        ]);

        return redirect()->route('contact')->with('status', 'Message sent. We will get back to you if needed.');
    }
}
