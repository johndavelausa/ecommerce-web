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
        border: 1px solid #E8E8E8;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .msg-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 22px;
        border-bottom: 1px solid #F0F0F0;
        background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
    }
    .msg-card-header-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #fff;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
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
        gap: 14px;
        padding: 16px 20px;
        background: transparent;
        border: none;
        text-align: left;
        cursor: pointer;
        transition: all 0.15s ease;
        border-left: 4px solid transparent;
    }
    .msg-conv-btn:hover { 
        background: linear-gradient(90deg, #FFF9E3 0%, #FFFDE7 100%);
        border-left-color: #F9C74F;
    }
    .msg-conv-btn.active { 
        background: linear-gradient(90deg, #E8F5E9 0%, #FFF9E3 100%);
        border-left-color: #2D9F4E;
    }
    .msg-conv-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 16px;
        font-weight: 700;
        color: #2D9F4E;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(45,159,78,0.15);
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
        padding: 50px 24px;
        text-align: center;
        background: linear-gradient(180deg, #FAFAFA 0%, #F5F5F5 100%);
    }
    .msg-empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        background: linear-gradient(135deg, #FFF9E3 0%, #F9C74F 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        color: #212121;
        box-shadow: 0 4px 12px rgba(249,199,79,0.25);
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
        gap: 14px;
        padding: 18px 24px;
        border-bottom: 1px solid #F0F0F0;
        background: linear-gradient(135deg, #FAFAFA 0%, #F5F5F5 100%);
    }
    .msg-chat-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 18px;
        font-weight: 700;
        color: #2D9F4E;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(45,159,78,0.2);
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
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        background: linear-gradient(180deg, #F8F9FA 0%, #FAFAFA 50%, #F5F5F5 100%);
    }

    /* Date divider */
    .msg-date {
        text-align: center;
        font-size: 0.6875rem;
        font-weight: 700;
        color: #757575;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin: 12px 0;
        padding: 6px 14px;
        background: rgba(255,255,255,0.8);
        border-radius: 20px;
        align-self: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    /* Message bubbles */
    .msg-row { display: flex; }
    .msg-row.me { justify-content: flex-end; }
    .msg-row.them { justify-content: flex-start; }

    .msg-bubble {
        max-width: 72%;
        padding: 14px 18px;
        border-radius: 20px;
        font-size: 0.9375rem;
        line-height: 1.5;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .msg-row.me .msg-bubble {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #ffffff;
        border-bottom-right-radius: 6px;
    }
    .msg-row.them .msg-bubble {
        background: #ffffff;
        color: #212121;
        border: 1px solid #E8E8E8;
        border-bottom-left-radius: 6px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .msg-time {
        display: block;
        font-size: 0.6875rem;
        margin-top: 6px;
        opacity: 0.8;
    }
    .msg-row.me .msg-time { text-align: right; color: rgba(255,255,255,0.9); }
    .msg-row.them .msg-time { text-align: left; color: #757575; }

    /* Placeholder */
    .msg-placeholder {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 24px;
        background: linear-gradient(180deg, #F8F9FA 0%, #F5F5F5 100%);
    }
    .msg-placeholder-icon {
        width: 72px;
        height: 72px;
        border-radius: 24px;
        background: linear-gradient(135deg, #FFF9E3 0%, #F9C74F 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        color: #212121;
        box-shadow: 0 4px 16px rgba(249,199,79,0.3);
    }
    .msg-placeholder-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #212121;
        margin: 0 0 8px;
    }
    .msg-placeholder-text {
        font-size: 0.9375rem;
        color: #757575;
        margin: 0;
    }

    /* Input area */
    .msg-input-bar {
        padding: 18px 24px;
        border-top: 1px solid #F0F0F0;
        background: linear-gradient(180deg, #FAFAFA 0%, #F5F5F5 100%);
        display: flex;
        gap: 14px;
        align-items: center;
    }
    .msg-input {
        flex: 1;
        padding: 14px 22px;
        font-size: 0.9375rem;
        color: #212121;
        background: #ffffff;
        border: 2px solid #E8E8E8;
        border-radius: 28px;
        outline: none;
        transition: all 0.2s ease;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
    }
    .msg-input::placeholder { color: #9E9E9E; }
    .msg-input:focus {
        border-color: #2D9F4E;
        box-shadow: 0 0 0 4px rgba(45,159,78,0.12), inset 0 1px 3px rgba(0,0,0,0.02);
    }
    .msg-send {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        border: none;
        color: #fff;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
        box-shadow: 0 2px 10px rgba(45,159,78,0.3);
    }
    .msg-send:hover { 
        background: linear-gradient(135deg, #1B7A37 0%, #145A2A 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(45,159,78,0.35);
    }
    .msg-send:disabled { 
        opacity: 0.4; 
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
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
                                $convImg = $conv->seller?->logo_url ?? $conv->seller?->user?->avatar_url ?? null;
                                $convName = $conv->seller?->store_name ?? 'Seller #'.$conv->seller_id;
                            @endphp
                            <li class="msg-conv-item">
                                <button type="button"
                                        wire:click="openConversation({{ $conv->id }})"
                                        class="msg-conv-btn {{ $activeConversationId === $conv->id ? 'active' : '' }}">
                                    <div class="msg-conv-avatar" style="{{ $convImg ? 'background:transparent;' : '' }}">
                                        @if($convImg)
                                            <img src="{{ $convImg }}" alt="">
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
                        $sellerImg = $activeConv?->seller?->logo_url ?? $activeConv?->seller?->user?->avatar_url ?? null;
                    @endphp
                    <div class="msg-chat-header">
                        <div class="msg-chat-avatar" style="{{ $sellerImg ? 'background:transparent;' : '' }}">
                            @if($sellerImg)
                                <img src="{{ $sellerImg }}" alt="{{ $sellerName }}">
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
