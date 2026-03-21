<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public ?int $activeConversationId = null;
    public string $body = '';

    public function getCustomerProperty()
    {
        return Auth::guard('web')->user();
    }

    public function getConversationsProperty()
    {
        $customer = $this->customer;
        if (! $customer) {
            return collect();
        }

        return Conversation::query()
            ->with('seller.user')
            ->where('type', 'seller-customer')
            ->where('customer_id', $customer->id)
            ->orderByDesc('updated_at')
            ->get();
    }

    public function getMessagesProperty()
    {
        if (! $this->activeConversationId) {
            return collect();
        }

        return Message::query()
            ->where('conversation_id', $this->activeConversationId)
            ->orderBy('created_at')
            ->get();
    }

    public function mount(): void
    {
        $sellerId = request()->query('seller');
        if ($sellerId && $this->customer) {
            $this->startWithSeller((int) $sellerId);
            return;
        }
        if ($this->conversations->isNotEmpty()) {
            $this->activeConversationId = $this->conversations->first()->id;
        }
    }

    public function openConversation(int $conversationId): void
    {
        $this->activeConversationId = $conversationId;
    }

    public function startWithSeller(int $sellerId): void
    {
        $customer = $this->customer;
        if (! $customer) abort(403);

        $conv = Conversation::query()
            ->where('type', 'seller-customer')
            ->where('customer_id', $customer->id)
            ->where('seller_id', $sellerId)
            ->first();

        if (! $conv) {
            $conv = Conversation::create([
                'seller_id'   => $sellerId,
                'customer_id' => $customer->id,
                'type'        => 'seller-customer',
            ]);
        }

        $this->activeConversationId = $conv->id;
        $this->body = '';
    }

    public function send(): void
    {
        $customer = $this->customer;
        if (! $customer) abort(403);

        if (! $this->activeConversationId) {
            return;
        }

        $this->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        Message::create([
            'conversation_id' => $this->activeConversationId,
            'sender_id'       => $customer->id,
            'sender_type'     => 'customer',
            'body'            => trim($this->body),
            'is_read'         => false,
        ]);

        Conversation::where('id', $this->activeConversationId)->update(['updated_at' => now()]);

        $this->body = '';
    }

    public function getSellersFromOrdersProperty()
    {
        $customer = $this->customer;
        if (! $customer) {
            return collect();
        }

        return Order::query()
            ->with('seller')
            ->where('customer_id', $customer->id)
            ->selectRaw('DISTINCT seller_id')
            ->get()
            ->pluck('seller')
            ->filter();
    }
};
?>

@push('styles')
@verbatim
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    .msg-wrap * { font-family: 'Inter', sans-serif; box-sizing: border-box; }

    /* ── Page header ─────────────────────────────────────── */
    .msg-page-header {
        background: #ffffff;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        padding: 20px 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .msg-page-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1A1A2E;
        margin: 0 0 4px;
    }
    .msg-page-sub {
        font-size: 0.8125rem;
        color: #4A5568;
        margin: 0;
    }

    /* ── Layout grid ─────────────────────────────────────── */
    .msg-grid {
        display: grid;
        gap: 16px;
    }
    @media (min-width: 1024px) {
        .msg-grid { grid-template-columns: 280px 1fr; }
    }

    /* ── Sidebar ─────────────────────────────────────────── */
    .msg-sidebar {
        background: #ffffff;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .msg-sidebar-header {
        padding: 16px 18px 12px;
        border-bottom: 1px solid #E2E8F0;
    }
    .msg-sidebar-title {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #1A1A2E;
        letter-spacing: 0.01em;
        margin: 0;
    }

    /* conversation list */
    .msg-conv-list { list-style: none; margin: 0; padding: 6px 0; }
    .msg-conv-item { margin: 0; }
    .msg-conv-btn {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 10px 18px;
        background: transparent;
        border: none;
        text-align: left;
        cursor: pointer;
        border-left: 3px solid transparent;
        transition: background 0.13s, border-color 0.13s;
    }
    .msg-conv-btn:hover { background: #F9FAFB; border-left-color: #52B788; }
    .msg-conv-btn.active {
        background: #F0FFF4;
        border-left-color: #2D6A4F;
    }
    .msg-conv-name {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #1A1A2E;
    }
    .msg-conv-time {
        font-size: 0.6875rem;
        color: #4A5568;
    }
    .msg-conv-btn.active .msg-conv-name { color: #2D6A4F; }

    /* empty state */
    .msg-empty-text {
        padding: 16px 18px;
        font-size: 0.8125rem;
        color: #4A5568;
    }

    /* new conversation section */
    .msg-new-section {
        padding: 14px 18px 16px;
        border-top: 1px solid #E2E8F0;
        background: #F9FAFB;
        margin-top: auto;
    }
    .msg-new-label {
        font-size: 0.6875rem;
        font-weight: 700;
        color: #4A5568;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        margin: 0 0 10px;
    }
    .msg-seller-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
    .msg-seller-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 7px 10px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #2D6A4F;
        background: transparent;
        border: 1px solid #B7E4C7;
        border-radius: 7px;
        cursor: pointer;
        text-align: left;
        transition: background 0.13s, color 0.13s, border-color 0.13s;
    }
    .msg-seller-btn:hover { background: #2D6A4F; color: #fff; border-color: #2D6A4F; }
    .msg-seller-btn svg { flex-shrink: 0; }

    /* ── Chat panel ──────────────────────────────────────── */
    .msg-chat-panel {
        background: #ffffff;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        display: flex;
        flex-direction: column;
        min-height: 540px;
    }
    .msg-chat-header {
        padding: 14px 20px;
        border-bottom: 1px solid #E2E8F0;
        display: flex;
        align-items: center;
        gap: 10px;
        background: #F9FAFB;
        border-radius: 12px 12px 0 0;
    }
    .msg-chat-avatar {
        width: 34px; height: 34px;
        border-radius: 9999px;
        background: #B7E4C7;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .msg-chat-avatar svg { color: #2D6A4F; }
    .msg-chat-header-name {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #1A1A2E;
        margin: 0;
    }
    .msg-chat-header-sub {
        font-size: 0.75rem;
        color: #4A5568;
        margin: 1px 0 0;
    }

    /* messages area */
    .msg-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #F9FAFB;
    }

    /* placeholder */
    .msg-placeholder {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 40px 20px;
        background: #F9FAFB;
    }
    .msg-placeholder-icon {
        width: 52px; height: 52px;
        border-radius: 9999px;
        background: #B7E4C7;
        display: flex; align-items: center; justify-content: center;
    }
    .msg-placeholder-icon svg { color: #2D6A4F; }
    .msg-placeholder-text { font-size: 0.875rem; color: #4A5568; text-align: center; margin: 0; }

    /* bubbles */
    .msg-bubble-row { display: flex; }
    .msg-bubble-row.me  { justify-content: flex-end; }
    .msg-bubble-row.them { justify-content: flex-start; }

    .msg-bubble {
        max-width: 68%;
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 0.875rem;
        line-height: 1.5;
    }
    .msg-bubble-row.me .msg-bubble {
        background: #2D6A4F;
        color: #ffffff;
        border-bottom-right-radius: 4px;
    }
    .msg-bubble-row.them .msg-bubble {
        background: #ffffff;
        color: #1A1A2E;
        border: 1px solid #E2E8F0;
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .msg-bubble-time {
        display: block;
        font-size: 0.6875rem;
        margin-top: 4px;
        opacity: 0.65;
    }
    .msg-bubble-row.me  .msg-bubble-time { text-align: right; }
    .msg-bubble-row.them .msg-bubble-time { text-align: left; color: #4A5568; }

    /* input bar */
    .msg-input-bar {
        padding: 14px 16px;
        border-top: 1px solid #E2E8F0;
        display: flex;
        gap: 10px;
        align-items: center;
        background: #ffffff;
        border-radius: 0 0 12px 12px;
    }
    .msg-input {
        flex: 1;
        padding: 9px 14px;
        font-size: 0.875rem;
        color: #1A1A2E;
        background: #F9FAFB;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        font-family: 'Inter', sans-serif;
    }
    .msg-input::placeholder { color: #4A5568; opacity: 0.7; }
    .msg-input:focus { border-color: #52B788; box-shadow: 0 0 0 3px rgba(82,183,136,0.15); background: #fff; }
    .msg-send-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #ffffff;
        background: #2D6A4F;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s;
        font-family: 'Inter', sans-serif;
        white-space: nowrap;
    }
    .msg-send-btn:hover { background: #52B788; }
    .msg-send-btn:disabled { opacity: 0.55; cursor: not-allowed; }
</style>
@endverbatim
@endpush

<div class="msg-wrap space-y-4">

    {{-- Page header --}}
    <div class="msg-page-header">
        <h3 class="msg-page-title">Messages</h3>
        <p class="msg-page-sub">Ask questions and communicate directly with sellers about your orders.</p>
    </div>

    <div class="msg-grid">

        {{-- ── Sidebar ───────────────────────────────────── --}}
        <div class="msg-sidebar">
            <div class="msg-sidebar-header">
                <p class="msg-sidebar-title">Conversations</p>
            </div>

            @php $conversations = $this->conversations; @endphp

            @if ($conversations->isEmpty())
                <p class="msg-empty-text">No conversations yet. Start one below.</p>
            @else
                <ul class="msg-conv-list">
                    @foreach ($conversations as $conv)
                        <li class="msg-conv-item">
                            <button type="button"
                                    wire:click="openConversation({{ $conv->id }})"
                                    class="msg-conv-btn {{ $activeConversationId === $conv->id ? 'active' : '' }}">
                                <span class="msg-conv-name">
                                    {{ $conv->seller->store_name ?? 'Seller #'.$conv->seller_id }}
                                </span>
                                <span class="msg-conv-time">
                                    {{ optional($conv->updated_at)->diffForHumans() }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="msg-new-section">
                <p class="msg-new-label">Start new conversation</p>
                @php $sellers = $this->sellersFromOrders; @endphp
                @if ($sellers->isEmpty())
                    <p style="font-size:0.75rem;color:#4A5568;margin:0;">
                        Place an order first to message a seller.
                    </p>
                @else
                    <ul class="msg-seller-list">
                        @foreach ($sellers as $seller)
                            <li>
                                <button type="button"
                                        wire:click="startWithSeller({{ $seller->id }})"
                                        class="msg-seller-btn">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    {{ $seller->store_name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- ── Chat Panel ────────────────────────────────── --}}
        <div class="msg-chat-panel">

            {{-- Header --}}
            @if ($activeConversationId)
                @php
                    $activeConv = $conversations->firstWhere('id', $activeConversationId);
                    $sellerName = $activeConv?->seller?->store_name ?? ('Seller #' . ($activeConv?->seller_id ?? ''));
                @endphp
                <div class="msg-chat-header">
                    <div class="msg-chat-avatar">
                        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="msg-chat-header-name">{{ $sellerName }}</p>
                        <p class="msg-chat-header-sub">Seller</p>
                    </div>
                </div>
            @else
                <div class="msg-chat-header">
                    <div>
                        <p class="msg-chat-header-name" style="color:#4A5568;font-weight:500;">No conversation selected</p>
                    </div>
                </div>
            <?php endif; ?>

            {{-- Messages --}}
            @php $messages = $this->messages; @endphp
            @if ($activeConversationId && $messages->isNotEmpty())
                <div class="msg-body">
                    @php $customer = $this->customer; @endphp
                    @foreach ($messages as $msg)
                        @php $isMe = $msg->sender_type === 'customer' && $msg->sender_id === $customer->id; @endphp
                        <div class="msg-bubble-row {{ $isMe ? 'me' : 'them' }}">
                            <div class="msg-bubble">
                                {{ $msg->body }}
                                <span class="msg-bubble-time">
                                    {{ optional($msg->created_at)->format('M d, H:i') }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($activeConversationId)
                <div class="msg-placeholder">
                    <div class="msg-placeholder-icon">
                        <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <p class="msg-placeholder-text">No messages yet.<br>Say hello to get the conversation started!</p>
                </div>
            @endif

            {{-- Input bar --}}
            <form wire:submit.prevent="send" class="msg-input-bar">
                <input type="text"
                       wire:model.defer="body"
                       class="msg-input"
                       placeholder="Type a message..."
                       {{ !$activeConversationId ? 'disabled' : '' }}>
                <button type="submit"
                        class="msg-send-btn"
                        {{ !$activeConversationId ? 'disabled' : '' }}>
                    <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Send
                </button>
            </form>
        </div>

    </div>
</div>