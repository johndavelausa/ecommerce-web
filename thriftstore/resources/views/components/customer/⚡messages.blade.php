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
    /* Messages page — matches checkout design */
    .msg-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }
    .msg-page-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #212121;
        margin: 0 0 4px;
    }
    .msg-page-sub {
        font-size: 0.875rem;
        color: #9E9E9E;
        margin: 0;
    }

    /* Two column layout like checkout */
    .msg-layout {
        display: flex;
        flex-direction: column;
        gap: 24px;
        max-height: calc(100vh - 200px);
        min-height: 500px;
    }
    @media (min-width: 1024px) {
        .msg-layout { flex-direction: row; align-items: stretch; }
    }

    /* Sidebar — conversation list */
    .msg-sidebar {
        width: 100%;
        flex-shrink: 0;
    }
    @media (min-width: 1024px) {
        .msg-sidebar { width: 320px; }
    }
    .msg-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #F5F5F5;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        overflow: hidden;
    }
    .msg-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px 20px;
        border-bottom: 1px solid #F5F5F5;
        background: #FAFAFA;
    }
    .msg-card-header-icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: #E8F5E9;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #2D9F4E;
    }
    .msg-card-header-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #212121;
        margin: 0;
    }

    /* Conversation list items */
    .msg-conv-list { list-style: none; margin: 0; padding: 0; }
    .msg-conv-item { margin: 0; border-bottom: 1px solid #F5F5F5; }
    .msg-conv-item:last-child { border-bottom: none; }
    .msg-conv-btn {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        background: transparent;
        border: none;
        text-align: left;
        cursor: pointer;
        transition: background 0.12s;
    }
    .msg-conv-btn:hover { background: #F8F9FA; }
    .msg-conv-btn.active { background: #FFF9E3; }
    .msg-conv-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #E8F5E9;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 15px;
        font-weight: 700;
        color: #2D9F4E;
        overflow: hidden;
    }
    .msg-conv-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .msg-conv-info { flex: 1; min-width: 0; }
    .msg-conv-name {
        font-size: 0.875rem;
        font-weight: 600;
        color: #212121;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
        margin-bottom: 2px;
    }
    .msg-conv-btn.active .msg-conv-name { color: #2D9F4E; }
    .msg-conv-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        color: #9E9E9E;
    }
    .msg-conv-preview {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .msg-conv-unread {
        width: 8px;
        height: 8px;
        border-radius: 9999px;
        background: #2D9F4E;
        flex-shrink: 0;
    }

    /* Empty state */
    .msg-empty {
        padding: 40px 20px;
        text-align: center;
    }
    .msg-empty-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: #E8F5E9;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px;
        color: #2D9F4E;
    }
    .msg-empty-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #212121;
        margin: 0 0 4px;
    }
    .msg-empty-text {
        font-size: 0.8125rem;
        color: #9E9E9E;
        margin: 0;
    }

    /* Chat panel */
    .msg-chat {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }
    .msg-chat .msg-card {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .msg-chat-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid #F5F5F5;
        background: #FAFAFA;
    }
    .msg-chat-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #E8F5E9;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 16px;
        font-weight: 700;
        color: #2D9F4E;
        overflow: hidden;
    }
    .msg-chat-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .msg-chat-info { flex: 1; min-width: 0; }
    .msg-chat-name {
        font-size: 1rem;
        font-weight: 700;
        color: #212121;
        margin: 0 0 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .msg-chat-status {
        font-size: 0.8125rem;
        color: #9E9E9E;
        margin: 0;
    }

    /* Messages body */
    .msg-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #FAFAFA;
    }

    /* Date divider */
    .msg-date {
        text-align: center;
        font-size: 0.6875rem;
        font-weight: 600;
        color: #9E9E9E;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 8px 0;
    }

    /* Message bubbles */
    .msg-row { display: flex; }
    .msg-row.me { justify-content: flex-end; }
    .msg-row.them { justify-content: flex-start; }

    .msg-bubble {
        max-width: 70%;
        padding: 12px 16px;
        border-radius: 18px;
        font-size: 0.9375rem;
        line-height: 1.5;
    }
    .msg-row.me .msg-bubble {
        background: #2D9F4E;
        color: #ffffff;
        border-bottom-right-radius: 4px;
    }
    .msg-row.them .msg-bubble {
        background: #ffffff;
        color: #212121;
        border: 1px solid #E0E0E0;
        border-bottom-left-radius: 4px;
    }
    .msg-time {
        display: block;
        font-size: 0.6875rem;
        margin-top: 6px;
        opacity: 0.7;
    }
    .msg-row.me .msg-time { text-align: right; }
    .msg-row.them .msg-time { text-align: left; color: #9E9E9E; opacity: 1; }

    /* Placeholder */
    .msg-placeholder {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        background: #FAFAFA;
    }
    .msg-placeholder-icon {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        background: #E8F5E9;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        color: #2D9F4E;
    }
    .msg-placeholder-title {
        font-size: 1rem;
        font-weight: 600;
        color: #212121;
        margin: 0 0 6px;
    }
    .msg-placeholder-text {
        font-size: 0.875rem;
        color: #9E9E9E;
        margin: 0;
    }

    /* Input area */
    .msg-input-bar {
        padding: 16px 20px;
        border-top: 1px solid #F5F5F5;
        background: #ffffff;
        display: flex;
        gap: 12px;
        align-items: center;
    }
    .msg-input {
        flex: 1;
        padding: 12px 18px;
        font-size: 0.9375rem;
        color: #212121;
        background: #F8F9FA;
        border: 1px solid #E0E0E0;
        border-radius: 24px;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .msg-input::placeholder { color: #9E9E9E; }
    .msg-input:focus {
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.10);
        background: #fff;
    }
    .msg-send {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #2D9F4E;
        border: none;
        color: #fff;
        cursor: pointer;
        transition: background 0.15s;
        flex-shrink: 0;
    }
    .msg-send:hover { background: #1B7A37; }
    .msg-send:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
@endverbatim
@endpush

<div>
    {{-- Page header like checkout --}}
    <div class="msg-page-header">
        <div>
            <h1 class="msg-page-title">Messages</h1>
            <p class="msg-page-sub">Ask questions and communicate directly with sellers about your orders</p>
        </div>
    </div>

    {{-- Two column layout like checkout --}}
    <div class="msg-layout">

        {{-- Sidebar: Conversations --}}
        <div class="msg-sidebar">
            <div class="msg-card">
                <div class="msg-card-header">
                    <div class="msg-card-header-icon">
                        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <h3 class="msg-card-header-title">Conversations</h3>
                </div>

                @php $conversations = $this->conversations; @endphp

                @if ($conversations->isEmpty())
                    <div class="msg-empty">
                        <div class="msg-empty-icon">
                            <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </div>
                        <p class="msg-empty-title">No conversations yet</p>
                        <p class="msg-empty-text">Start one from a seller's store page</p>
                    </div>
                @else
                    <ul class="msg-conv-list">
                        @foreach ($conversations as $conv)
                            @php
                                $lastMsg = $conv->messages()->latest()->first();
                                $hasUnread = $conv->messages()
                                    ->where('sender_type', 'seller')
                                    ->where('is_read', false)
                                    ->exists();
                                $convImg = $conv->seller?->logo_path ?? $conv->seller?->user?->avatar ?? null;
                                $convName = $conv->seller?->store_name ?? 'Seller #'.$conv->seller_id;
                            @endphp
                            <li class="msg-conv-item">
                                <button type="button"
                                        wire:click="openConversation({{ $conv->id }})"
                                        class="msg-conv-btn {{ $activeConversationId === $conv->id ? 'active' : '' }}">
                                    <div class="msg-conv-avatar" style="{{ $convImg ? 'background:transparent;' : '' }}">
                                        @if($convImg)
                                            <img src="{{ asset('storage/'.$convImg) }}" alt="">
                                        @else
                                            {{ strtoupper(substr($convName, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="msg-conv-info">
                                        <span class="msg-conv-name">{{ $convName }}</span>
                                        <div class="msg-conv-meta">
                                            @if($lastMsg)
                                                <span class="msg-conv-preview">
                                                    {{ $lastMsg->sender_type === 'customer' ? 'You: ' : '' }}{{ \Illuminate\Support\Str::limit($lastMsg->body, 22) }}
                                                </span>
                                            @else
                                                <span class="msg-conv-preview">No messages yet</span>
                                            @endif
                                            @if($hasUnread)
                                                <span class="msg-conv-unread"></span>
                                            @endif
                                        </div>
                                    </div>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Chat Panel --}}
        <div class="msg-chat">
            <div class="msg-card">
                @if ($activeConversationId)
                    @php
                        $activeConv = $conversations->firstWhere('id', $activeConversationId);
                        $sellerName = $activeConv?->seller?->store_name ?? ('Seller #' . ($activeConv?->seller_id ?? ''));
                        $sellerImg = $activeConv?->seller?->logo_path ?? $activeConv?->seller?->user?->avatar ?? null;
                    @endphp
                    <div class="msg-chat-header">
                        <div class="msg-chat-avatar" style="{{ $sellerImg ? 'background:transparent;' : '' }}">
                            @if($sellerImg)
                                <img src="{{ asset('storage/'.$sellerImg) }}" alt="{{ $sellerName }}">
                            @else
                                {{ strtoupper(substr($sellerName, 0, 1)) }}
                            @endif
                        </div>
                        <div class="msg-chat-info">
                            <p class="msg-chat-name">{{ $sellerName }}</p>
                            <p class="msg-chat-status">Seller</p>
                        </div>
                    </div>
                @else
                    <div class="msg-chat-header">
                        <div class="msg-chat-info">
                            <p class="msg-chat-name" style="color:#9E9E9E;font-weight:500;">No conversation selected</p>
                        </div>
                    </div>
                @endif

                @php $messages = $this->messages; @endphp
                @if ($activeConversationId && $messages->isNotEmpty())
                    <div class="msg-body">
                        @php
                            $customer = $this->customer;
                            $prevDate = null;
                        @endphp
                        @foreach ($messages as $msg)
                            @php
                                $msgDate = optional($msg->created_at)->format('Y-m-d');
                                $isMe = $msg->sender_type === 'customer' && $msg->sender_id === $customer->id;
                                $dateLabel = $msgDate === now()->format('Y-m-d') ? 'Today'
                                           : ($msgDate === now()->subDay()->format('Y-m-d') ? 'Yesterday'
                                           : optional($msg->created_at)->format('M j'));
                            @endphp
                            @if($msgDate !== $prevDate)
                                <div class="msg-date">{{ $dateLabel }}</div>
                                @php $prevDate = $msgDate; @endphp
                            @endif
                            <div class="msg-row {{ $isMe ? 'me' : 'them' }}">
                                <div class="msg-bubble">
                                    {{ $msg->body }}
                                    <span class="msg-time">{{ optional($msg->created_at)->format('g:i A') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif ($activeConversationId)
                    <div class="msg-placeholder">
                        <div class="msg-placeholder-icon">
                            <svg style="width:28px;height:28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </div>
                        <p class="msg-placeholder-title">No messages yet</p>
                        <p class="msg-placeholder-text">Say hello to get the conversation started!</p>
                    </div>
                @else
                    <div class="msg-placeholder">
                        <div class="msg-placeholder-icon">
                            <svg style="width:28px;height:28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <p class="msg-placeholder-title">Select a conversation</p>
                        <p class="msg-placeholder-text">Choose a seller to start messaging</p>
                    </div>
                @endif

                <form wire:submit.prevent="send" class="msg-input-bar">
                    <input type="text"
                           wire:model.defer="body"
                           class="msg-input"
                           placeholder="Type a message..."
                           {{ !$activeConversationId ? 'disabled' : '' }}>
                    <button type="submit"
                            class="msg-send"
                            {{ !$activeConversationId ? 'disabled' : '' }}>
                        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>