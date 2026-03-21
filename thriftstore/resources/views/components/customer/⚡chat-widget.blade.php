<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public bool   $open               = false;
    public ?int   $activeConversationId = null;
    public string $body               = '';
    public string $search             = '';

    public function getCustomerProperty()
    {
        return Auth::guard('web')->user();
    }

    public function getConversationsProperty()
    {
        $customer = $this->customer;
        if (! $customer) return collect();

        $q = Conversation::query()
            ->with(['seller.user'])
            ->where('type', 'seller-customer')
            ->where('customer_id', $customer->id)
            ->orderByDesc('updated_at');

        if ($this->search !== '') {
            $q->whereHas('seller', function ($sq) {
                $sq->where('store_name', 'like', '%' . $this->search . '%');
            });
        }

        return $q->get();
    }

    public function getMessagesProperty()
    {
        if (! $this->activeConversationId) return collect();

        return Message::query()
            ->where('conversation_id', $this->activeConversationId)
            ->orderBy('created_at')
            ->get();
    }

    public function getUnreadCountProperty(): int
    {
        $customer = $this->customer;
        if (! $customer) return 0;

        return Message::query()
            ->whereHas('conversation', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)->where('type', 'seller-customer');
            })
            ->where('sender_type', 'seller')
            ->where('is_read', false)
            ->count();
    }

    public function getSellersFromOrdersProperty()
    {
        $customer = $this->customer;
        if (! $customer) return collect();

        return Order::query()
            ->with('seller')
            ->where('customer_id', $customer->id)
            ->selectRaw('DISTINCT seller_id')
            ->get()
            ->pluck('seller')
            ->filter();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function openConversation(int $conversationId): void
    {
        $this->activeConversationId = $conversationId;

        // Mark seller messages as read
        Message::query()
            ->where('conversation_id', $conversationId)
            ->where('sender_type', 'seller')
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function startWithSeller(int $sellerId): void
    {
        $customer = $this->customer;
        if (! $customer) return;

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
        $this->open = true;
    }

    public function send(): void
    {
        $customer = $this->customer;
        if (! $customer || ! $this->activeConversationId) return;

        $this->validate(['body' => ['required', 'string', 'max:5000']]);

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

    public function back(): void
    {
        $this->activeConversationId = null;
    }
};
?>

{{-- CSS is output in app.blade.php <head> to avoid @stack timing issues --}}
{{-- REMOVED STYLE BLOCK START
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9000;
        width: 52px; height: 52px;
        border-radius: 9999px;
        background: #2D6A4F;
        border: none;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 20px rgba(45,106,79,0.45);
        transition: background 0.18s, transform 0.18s, box-shadow 0.18s;
        color: #fff;
        font-family: 'Inter', sans-serif;
    }
    .fcw-fab:hover {
        background: #1B4332;
        transform: scale(1.06);
        box-shadow: 0 6px 28px rgba(45,106,79,0.55);
    }
    .fcw-fab-badge {
        position: absolute;
        top: -2px; right: -2px;
        min-width: 18px; height: 18px;
        padding: 0 4px;
        border-radius: 9999px;
        background: #E53E3E;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid #fff;
        line-height: 1;
    }

    /* Panel */
    .fcw-panel {
        position: fixed;
        bottom: 88px;
        right: 24px;
        z-index: 9000;
        width: 680px;
        max-width: calc(100vw - 32px);
        height: 520px;
        max-height: calc(100vh - 120px);
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 16px 60px rgba(0,0,0,0.18), 0 4px 16px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font-family: 'Inter', sans-serif;
    }
    @media (max-width: 640px) {
        .fcw-panel {
            width: calc(100vw - 32px);
            height: 480px;
        }
    }

    /* Panel header */
    .fcw-header {
        background: #2D6A4F;
        padding: 12px 16px;
        display: flex; align-items: center; justify-content: space-between;
        flex-shrink: 0;
        border-radius: 14px 14px 0 0;
    }
    .fcw-header-left { display: flex; align-items: center; gap: 8px; }
    .fcw-header-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }
    .fcw-header-badge {
        background: #B7E4C7;
        color: #1B4332;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 9999px;
        line-height: 1.4;
    }
    .fcw-header-btn {
        background: rgba(255,255,255,0.15);
        border: none;
        border-radius: 6px;
        width: 28px; height: 28px;
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        cursor: pointer;
        transition: background 0.13s;
    }
    .fcw-header-btn:hover { background: rgba(255,255,255,0.28); }

    /* Body grid */
    .fcw-body {
        display: flex;
        flex: 1;
        overflow: hidden;
    }

    /* ── Sidebar ── */
    .fcw-sidebar {
        width: 220px;
        flex-shrink: 0;
        border-right: 1px solid #E2E8F0;
        display: flex;
        flex-direction: column;
        background: #F9FAFB;
    }
    @media (max-width: 480px) {
        .fcw-sidebar { width: 100%; }
        .fcw-chat { display: none; }
        .fcw-sidebar.hide-on-mobile { display: none; }
        .fcw-chat.show-on-mobile { display: flex; }
    }

    /* Search */
    .fcw-search-wrap {
        padding: 10px 12px 8px;
        border-bottom: 1px solid #E2E8F0;
        flex-shrink: 0;
    }
    .fcw-search {
        width: 100%;
        padding: 6px 10px 6px 30px;
        font-size: 0.8rem;
        background: #fff;
        border: 1px solid #E2E8F0;
        border-radius: 20px;
        outline: none;
        color: #1A1A2E;
        font-family: 'Inter', sans-serif;
        transition: border-color 0.13s;
        box-sizing: border-box;
    }
    .fcw-search:focus { border-color: #52B788; }
    .fcw-search-icon {
        position: absolute;
        left: 22px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
        pointer-events: none;
    }
    .fcw-search-wrap { position: relative; }

    /* Conv list */
    .fcw-conv-list { flex: 1; overflow-y: auto; }
    .fcw-conv-item {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 10px 12px;
        cursor: pointer;
        border-left: 3px solid transparent;
        transition: background 0.12s, border-color 0.12s;
        border-bottom: 1px solid #F3F4F6;
    }
    .fcw-conv-item:hover { background: #F0FFF4; border-left-color: #52B788; }
    .fcw-conv-item.active { background: #DCFCE7; border-left-color: #2D6A4F; }
    .fcw-conv-avatar {
        width: 32px; height: 32px;
        border-radius: 9999px;
        background: #B7E4C7;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        color: #2D6A4F;
    }
    .fcw-conv-info { flex: 1; min-width: 0; }
    .fcw-conv-name {
        font-size: 0.8rem;
        font-weight: 600;
        color: #1A1A2E;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .fcw-conv-preview {
        font-size: 0.7rem;
        color: #6B7280;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 1px;
    }
    .fcw-conv-time {
        font-size: 0.65rem;
        color: #9CA3AF;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .fcw-conv-unread {
        width: 7px; height: 7px;
        border-radius: 9999px;
        background: #2D6A4F;
        flex-shrink: 0;
        margin-top: 6px;
    }

    /* New chat section */
    .fcw-new-section {
        border-top: 1px solid #E2E8F0;
        background: #fff;
        flex-shrink: 0;
    }
    .fcw-new-toggle {
        width: 100%;
        padding: 8px 12px;
        display: flex; align-items: center; justify-content: space-between;
        font-size: 0.7rem;
        font-weight: 700;
        color: #4A5568;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        background: transparent;
        border: none;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
    }
    .fcw-new-toggle:hover { background: #F9FAFB; }
    .fcw-seller-btn {
        display: flex; align-items: center; gap: 6px;
        padding: 6px 12px;
        width: 100%;
        font-size: 0.75rem;
        font-weight: 500;
        color: #2D6A4F;
        background: transparent;
        border: none;
        border-top: 1px solid #F3F4F6;
        cursor: pointer;
        text-align: left;
        font-family: 'Inter', sans-serif;
        transition: background 0.12s;
    }
    .fcw-seller-btn:hover { background: #F0FFF4; }

    /* ── Chat panel ── */
    .fcw-chat {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .fcw-chat-header {
        padding: 10px 14px;
        border-bottom: 1px solid #E2E8F0;
        display: flex; align-items: center; gap: 8px;
        background: #fff;
        flex-shrink: 0;
    }
    .fcw-back-btn {
        display: none;
        background: transparent;
        border: none;
        padding: 3px;
        cursor: pointer;
        color: #4A5568;
    }
    @media (max-width: 480px) { .fcw-back-btn { display: flex; } }
    .fcw-chat-avatar {
        width: 28px; height: 28px;
        border-radius: 9999px;
        background: #B7E4C7;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        color: #2D6A4F;
    }
    .fcw-chat-name { font-size: 0.875rem; font-weight: 600; color: #1A1A2E; }
    .fcw-chat-sub  { font-size: 0.7rem; color: #6B7280; }

    /* Messages */
    .fcw-messages {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: #F9FAFB;
    }
    .fcw-date-divider {
        text-align: center;
        font-size: 0.65rem;
        color: #9CA3AF;
        font-weight: 500;
        margin: 4px 0;
    }
    .fcw-bubble-row { display: flex; }
    .fcw-bubble-row.me   { justify-content: flex-end; }
    .fcw-bubble-row.them { justify-content: flex-start; }
    .fcw-bubble {
        max-width: 72%;
        padding: 8px 12px;
        border-radius: 12px;
        font-size: 0.8125rem;
        line-height: 1.45;
    }
    .fcw-bubble-row.me .fcw-bubble {
        background: #2D6A4F;
        color: #fff;
        border-bottom-right-radius: 3px;
    }
    .fcw-bubble-row.them .fcw-bubble {
        background: #fff;
        color: #1A1A2E;
        border: 1px solid #E2E8F0;
        border-bottom-left-radius: 3px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .fcw-bubble-time {
        display: block;
        font-size: 0.6rem;
        margin-top: 3px;
        opacity: 0.6;
    }
    .fcw-bubble-row.me   .fcw-bubble-time { text-align: right; }
    .fcw-bubble-row.them .fcw-bubble-time { color: #6B7280; }

    /* Placeholder */
    .fcw-placeholder {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: #F9FAFB;
        padding: 20px;
    }
    .fcw-placeholder-icon {
        width: 44px; height: 44px;
        border-radius: 9999px;
        background: #DCFCE7;
        display: flex; align-items: center; justify-content: center;
        color: #2D6A4F;
    }
    .fcw-placeholder-text { font-size: 0.8rem; color: #6B7280; text-align: center; margin: 0; }

    /* Input */
    .fcw-input-bar {
        padding: 10px 12px;
        border-top: 1px solid #E2E8F0;
        display: flex; gap: 8px; align-items: center;
        background: #fff;
        flex-shrink: 0;
    }
    .fcw-input {
        flex: 1;
        padding: 7px 12px;
        font-size: 0.8125rem;
        color: #1A1A2E;
        background: #F9FAFB;
        border: 1px solid #E2E8F0;
        border-radius: 20px;
        outline: none;
        font-family: 'Inter', sans-serif;
        transition: border-color 0.13s;
    }
    .fcw-input:focus { border-color: #52B788; background: #fff; }
    .fcw-input::placeholder { color: #9CA3AF; }
    .fcw-send-btn {
        width: 32px; height: 32px;
        border-radius: 9999px;
        background: #2D6A4F;
        border: none;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        flex-shrink: 0;
        transition: background 0.13s;
    }
    .fcw-send-btn:hover    { background: #1B4332; }
    .fcw-send-btn:disabled { opacity: 0.45; cursor: not-allowed; }

    /* Empty sidebar */
    .fcw-empty-state {
        padding: 16px 12px;
        font-size: 0.75rem;
        color: #6B7280;
        text-align: center;
        line-height: 1.5;
    }
REMOVED STYLE BLOCK END --}}

@php
    $customer = $this->customer;
    $unread   = $this->unreadCount;
@endphp

{{-- Single root wrapper required by Livewire --}}
<div style="display:contents;">

{{-- ── FAB button (hidden when panel is open) ──────── --}}
<button type="button"
        wire:click="toggle"
        class="fcw-fab"
        title="Messages"
        aria-label="Open chat"
        style="{{ $open ? 'display:none;' : '' }}">
    <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
    </svg>
    @if($unread > 0)
        <span class="fcw-fab-badge">{{ $unread > 99 ? '99+' : $unread }}</span>
    @endif
</button>

{{-- ── Floating Panel ──────────────────────────────── --}}
<div class="fcw-panel" style="{{ $open ? '' : 'display:none;' }}">

    {{-- Header --}}
    <div class="fcw-header">
        <div class="fcw-header-left">
            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
            <p class="fcw-header-title">Messages</p>
            @if($unread > 0)
                <span class="fcw-header-badge">{{ $unread }}</span>
            @endif
        </div>
        <div class="fcw-header-actions">
            @php
                $activeConv   = $this->conversations->firstWhere('id', $activeConversationId);
                $fullChatUrl  = route('customer.messages')
                    . ($activeConv?->seller_id ? '?seller=' . $activeConv->seller_id : '');
            @endphp
            <a href="{{ $fullChatUrl }}" class="fcw-header-btn" title="Open full chat">
                <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
            <button type="button" wire:click="toggle" class="fcw-header-btn" title="Minimise">
                <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Body --}}
    <div class="fcw-body">

        {{-- ── Sidebar ── --}}
        @php $conversations = $this->conversations; @endphp
        <div class="fcw-sidebar">

            {{-- Search --}}
            <div class="fcw-search-wrap">
                <svg class="fcw-search-icon" style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <input type="text"
                       wire:model.debounce.300ms="search"
                       placeholder="Search..."
                       class="fcw-search">
            </div>

            {{-- Conversation list --}}
            <div class="fcw-conv-list">
                @forelse($conversations as $conv)
                    @php
                        $lastMsg = $conv->messages()->latest()->first();
                        $hasUnread = $conv->messages()
                            ->where('sender_type', 'seller')
                            ->where('is_read', false)
                            ->exists();
                    @endphp
                    <div wire:click="openConversation({{ $conv->id }})"
                         class="fcw-conv-item {{ $activeConversationId === $conv->id ? 'active' : '' }}">
                        <div class="fcw-conv-avatar">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div class="fcw-conv-info">
                            <div class="fcw-conv-row">
                                <span class="fcw-conv-name">{{ $conv->seller->store_name ?? 'Seller #'.$conv->seller_id }}</span>
                                <span class="fcw-conv-time">{{ optional($conv->updated_at)->format('M d') }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:5px;margin-top:2px;">
                                @if($lastMsg)
                                    <span class="fcw-conv-preview" style="flex:1;">
                                        {{ $lastMsg->sender_type === 'customer' ? 'You: ' : '' }}{{ \Illuminate\Support\Str::limit($lastMsg->body, 26) }}
                                    </span>
                                @endif
                                @if($hasUnread)
                                    <span class="fcw-conv-dot" style="flex-shrink:0;"></span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="fcw-empty-state">No conversations yet.<br>Start one from your order history.</p>
                @endforelse
            </div>

            {{-- New conversation --}}
            @php $sellers = $this->sellersFromOrders; @endphp
            @if($sellers->isNotEmpty())
                <div class="fcw-new-section"
                     x-data="{ open: false }">
                    <button type="button" class="fcw-new-toggle" @click="open = !open">
                        <span>Start new chat</span>
                        <svg :style="'width:12px;height:12px;flex-shrink:0;transition:transform .2s;' + (open ? 'transform:rotate(180deg)' : 'transform:rotate(0deg)')"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak class="fcw-seller-list">
                        @foreach($sellers as $seller)
                            <button type="button"
                                    wire:click="startWithSeller({{ $seller->id }})"
                                    class="fcw-seller-btn">
                                <span class="fcw-seller-icon">
                                    <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </span>
                                {{ $seller->store_name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ── Chat window ── --}}
        <div class="fcw-chat">

            @if($activeConversationId)
                @php
                    $activeConv = $conversations->firstWhere('id', $activeConversationId);
                    $sellerName = $activeConv?->seller?->store_name ?? ('Seller #' . ($activeConv?->seller_id ?? ''));
                    $messages   = $this->messages;
                    $customer   = $this->customer;
                @endphp

                {{-- Chat header --}}
                <div class="fcw-chat-header">
                    <button type="button" wire:click="back" class="fcw-back-btn">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <div class="fcw-chat-avatar">
                        <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="fcw-chat-name">{{ $sellerName }}</p>
                        <p class="fcw-chat-sub">Seller</p>
                    </div>
                </div>

                {{-- Messages --}}
                @if($messages->isNotEmpty())
                    <div class="fcw-messages" id="fcw-msg-area">
                        @php $prevDate = null; @endphp
                        @foreach($messages as $msg)
                            @php
                                $msgDate  = optional($msg->created_at)->format('Y-m-d');
                                $isMe     = $msg->sender_type === 'customer' && $msg->sender_id === $customer->id;
                                $dateLabel = $msgDate === now()->format('Y-m-d') ? 'Today'
                                           : ($msgDate === now()->subDay()->format('Y-m-d') ? 'Yesterday'
                                           : optional($msg->created_at)->format('M j'));
                            @endphp
                            @if($msgDate !== $prevDate)
                                <div class="fcw-date-divider">{{ $dateLabel }}</div>
                                @php $prevDate = $msgDate; @endphp
                            @endif
                            <div class="fcw-bubble-row {{ $isMe ? 'me' : 'them' }}">
                                <div class="fcw-bubble">
                                    {{ $msg->body }}
                                    <span class="fcw-bubble-time">
                                        {{ optional($msg->created_at)->format('g:i A') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <script>
                        (function() {
                            var el = document.getElementById('fcw-msg-area');
                            if (el) el.scrollTop = el.scrollHeight;
                        })();
                    </script>
                @else
                    <div class="fcw-placeholder">
                        <div class="fcw-placeholder-icon">
                            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </div>
                        <p class="fcw-placeholder-text">No messages yet. Say hello!</p>
                    </div>
                @endif

                {{-- Input --}}
                <form wire:submit.prevent="send" class="fcw-input-bar">
                    <input type="text"
                           wire:model.defer="body"
                           placeholder="Type a message here"
                           class="fcw-input">
                    <button type="submit" class="fcw-send-btn" {{ !trim($body ?? '') ? 'disabled' : '' }}>
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </form>

            @else
                {{-- No conversation selected --}}
                <div class="fcw-placeholder">
                    <div class="fcw-placeholder-icon">
                        <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <p class="fcw-placeholder-text">Select a conversation<br>or start a new one.</p>
                </div>
            @endif

        </div>
    </div>
</div>{{-- .fcw-panel --}}

</div>{{-- end display:contents wrapper --}}
