<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public ?int $selectedConversationId = null;
    public string $replyBody = '';

    public function inquireAdmin(): void
    {
        $seller = Auth::guard('seller')->user()?->seller;
        if (! $seller) return;

        $conv = Conversation::firstOrCreate([
            'seller_id' => $seller->id,
            'type'      => 'seller-admin',
        ]);

        $this->selectedConversationId = $conv->id;
        $this->replyBody = '';
    }

    public function getConversationsProperty()
    {
        $seller = Auth::guard('seller')->user()?->seller;
        if (! $seller) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        return Conversation::query()
            ->whereIn('type', ['seller-customer', 'seller-admin'])
            ->where('seller_id', $seller->id)
            ->with(['customer', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('is_read', false)->whereIn('sender_type', ['customer', 'admin'])])
            ->orderByRaw('(SELECT MAX(created_at) FROM messages WHERE messages.conversation_id = conversations.id) DESC')
            ->paginate(20);
    }

    public function selectConversation(int $id): void
    {
        $this->selectedConversationId = $id;
        $this->replyBody = '';
        $this->markConversationRead($id);
        $this->dispatch('scroll-to-bottom');
    }

    public function markConversationRead(int $conversationId): void
    {
        Message::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('sender_type', ['customer', 'admin'])
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function sendReply(): void
    {
        $this->validate(['replyBody' => 'required|string|max:5000']);

        $sellerUser = Auth::guard('seller')->user();
        $seller = $sellerUser?->seller;
        if (! $seller || ! $this->selectedConversationId) {
            return;
        }

        $conv = Conversation::query()
            ->where('id', $this->selectedConversationId)
            ->where('seller_id', $seller->id)
            ->whereIn('type', ['seller-customer', 'seller-admin'])
            ->firstOrFail();

        Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => $sellerUser->id,
            'sender_type'     => 'seller',
            'body'            => trim($this->replyBody),
            'is_read'         => false,
        ]);

        $conv->update(['updated_at' => now()]);

        $this->replyBody = '';
        $this->dispatch('scroll-to-bottom');
    }

    #[Computed]
    public function selectedConversation()
    {
        if (! $this->selectedConversationId) {
            return null;
        }

        return Conversation::query()
            ->with(['customer', 'messages' => fn ($q) => $q->orderBy('created_at')])
            ->find($this->selectedConversationId);
    }
};
?>

@push('styles')
@verbatim
<style>
    /* Messages Brand Styles */
    .msg-container {
        background: linear-gradient(135deg, #FFFEF5 0%, #F8FDF9 100%);
        border: 1px solid #E0E0E0;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .msg-sidebar {
        background: white;
        border: 1px solid #E0E0E0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .msg-sidebar-header {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: white;
        padding: 14px 16px;
        font-weight: 600;
        font-size: 0.875rem;
    }
    .msg-conv-item {
        padding: 14px 16px;
        text-align: left;
        width: 100%;
        transition: all 0.15s;
        border-bottom: 1px solid #F5F5F5;
    }
    .msg-conv-item:hover {
        background: linear-gradient(90deg, #F8FDF9 0%, #FFFEF5 100%);
    }
    .msg-conv-item.active {
        background: #FFF9E6;
        border-left: 3px solid #F9C74F;
    }
    .msg-conv-name {
        font-weight: 600;
        font-size: 0.8125rem;
        color: #212121;
    }
    .msg-conv-name.admin {
        color: #2D9F4E;
    }
    .msg-unread-badge {
        background: linear-gradient(135deg, #F9C74F 0%, #E6B340 100%);
        color: white;
        font-size: 0.625rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 999px;
    }
    .msg-preview {
        font-size: 0.6875rem;
        color: #9E9E9E;
        margin-top: 4px;
        truncate: ellipsis;
    }
    .msg-time {
        font-size: 0.625rem;
        color: #BDBDBD;
    }

    .msg-chat {
        background: white;
        border: 1px solid #E0E0E0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .msg-chat-header {
        background: #FAFAFA;
        border-bottom: 1px solid #E0E0E0;
        padding: 14px 16px;
    }
    .msg-chat-name {
        font-weight: 600;
        font-size: 0.875rem;
        color: #212121;
    }
    .msg-chat-id {
        font-size: 0.625rem;
        color: #9E9E9E;
    }

    .msg-bubble {
        max-width: 80%;
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 0.8125rem;
        line-height: 1.5;
    }
    .msg-bubble.sent {
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: white;
        border-bottom-right-radius: 4px;
    }
    .msg-bubble.received {
        background: #F5F5F5;
        color: #424242;
        border-bottom-left-radius: 4px;
    }
    .msg-bubble-meta {
        font-size: 0.625rem;
        opacity: 0.8;
        margin-bottom: 4px;
    }

    .msg-input-area {
        border-top: 1px solid #E0E0E0;
        padding: 14px 16px;
        background: #FAFAFA;
    }
    .msg-textarea {
        width: 100%;
        border: 1px solid #E0E0E0;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 0.8125rem;
        resize: none;
        transition: all 0.2s;
        background: white;
    }
    .msg-textarea:focus {
        outline: none;
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(249,199,79,0.15);
    }
    .msg-btn-send {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #2D9F4E 0%, #F9C74F 100%);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
    }
    .msg-btn-send:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,159,78,0.35);
    }
    .msg-btn-icon {
        background: transparent;
        color: #2D9F4E;
        border: 1px solid #2D9F4E;
        border-radius: 6px;
        padding: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .msg-btn-icon:hover {
        background: #E8F5E9;
    }

    .msg-empty {
        color: #9E9E9E;
        font-size: 0.875rem;
    }
    .msg-empty-state {
        text-align: center;
        padding: 48px 24px;
    }
    .msg-empty-icon {
        width: 64px;
        height: 64px;
        background: #F5F5F5;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
    }
</style>
@endverbatim
@endpush

<div class="msg-container">
    <div class="flex gap-4">
        {{-- Sidebar --}}
        <div class="w-80 shrink-0 msg-sidebar flex flex-col max-h-[70vh]">
            <div class="msg-sidebar-header flex items-center justify-between">
                <span>Conversations</span>
                @php
                    $hasAdminConv = $this->conversations->getCollection()->contains('type', 'seller-admin');
                @endphp
                @if(!$hasAdminConv)
                    <button type="button" wire:click="inquireAdmin" title="Message Admin" class="msg-btn-icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>
                @endif
            </div>
            <div class="overflow-y-auto flex-1">
                @forelse($this->conversations as $conv)
                    <button type="button"
                            wire:click="selectConversation({{ $conv->id }})"
                            class="msg-conv-item {{ $selectedConversationId === $conv->id ? 'active' : '' }}">
                        <div class="flex justify-between items-start">
                            <span class="msg-conv-name {{ $conv->type === 'seller-admin' ? 'admin' : '' }}">
                                @if($conv->type === 'seller-admin')
                                    Support Admin
                                @else
                                    {{ $conv->customer->name ?? 'Customer #'.$conv->customer_id }}
                                @endif
                            </span>
                            @if($conv->unread_count > 0)
                                <span class="msg-unread-badge">{{ $conv->unread_count }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <div class="msg-preview truncate flex-1">
                                {{ $conv->latestMessage?->body ?? 'No messages yet.' }}
                            </div>
                            <div class="msg-time ml-2 shrink-0">
                                {{ optional($conv->latestMessage?->created_at)->diffForHumans(null, true) }}
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="msg-empty-state">
                        <div class="msg-empty-icon">
                            <svg class="w-8 h-8 text-[#9E9E9E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                        </div>
                        <p class="msg-empty">No conversations yet.</p>
                    </div>
                @endforelse
            </div>
            @if($this->conversations->hasPages())
                <div class="p-2 border-t border-[#E0E0E0]">
                    {{ $this->conversations->links() }}
                </div>
            @endif
        </div>

        {{-- Chat Area --}}
        <div class="flex-1 msg-chat flex flex-col max-h-[70vh]">
            @if($this->selectedConversation)
                @php($conv = $this->selectedConversation)
                <div class="msg-chat-header flex justify-between items-center">
                    <span class="msg-chat-name">
                        @if($conv->type === 'seller-admin')
                            <span class="text-[#2D9F4E]">Support Admin</span>
                        @else
                            {{ $conv->customer->name ?? 'Customer #'.$conv->customer_id }}
                        @endif
                    </span>
                    <span class="msg-chat-id">Conversation #{{ $conv->id }}</span>
                </div>
                <div
                    x-data="{ scrollToBottom() { this.$el.scrollTop = this.$el.scrollHeight; } }"
                    x-init="scrollToBottom(); new MutationObserver(() => scrollToBottom()).observe($el, { childList: true, subtree: true });"
                    x-on:scroll-to-bottom.window="$nextTick(() => scrollToBottom());"
                    wire:poll.5s
                    class="flex-1 overflow-y-auto p-4 space-y-3"
                >
                    @php($sellerUser = auth('seller')->user())
                    @foreach($conv->messages as $msg)
                        @php($isSeller = $msg->sender_type === 'seller' && $msg->sender_id === $sellerUser?->id)
                        <div class="flex {{ $isSeller ? 'justify-end' : 'justify-start' }}">
                            <div class="msg-bubble {{ $isSeller ? 'sent' : 'received' }}">
                                <div class="msg-bubble-meta">
                                    {{ ucfirst($msg->sender_type) }} · {{ $msg->created_at?->format('M d, H:i') }}
                                </div>
                                <div class="whitespace-pre-wrap">{{ $msg->body }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="msg-input-area" x-data="{ body: '' }">
                    <textarea
                        x-model="body"
                        x-ref="messageInput"
                        x-on:keydown.enter.prevent="if(body.trim() !== '') { @this.set('replyBody', body); @this.sendReply().then(() => { body = ''; $refs.messageInput.focus(); }); }"
                        wire:loading.attr="disabled"
                        wire:target="sendReply"
                        rows="2"
                        class="msg-textarea"
                        placeholder="Type your message... (Enter to send)"></textarea>
                    @error('replyBody') <div class="text-xs text-[#E53935] mt-1">{{ $message }}</div> @enderror
                    <div class="mt-2 flex justify-end">
                        <button type="button"
                                x-on:click="if(body.trim() !== '') { @this.set('replyBody', body); @this.sendReply().then(() => { body = ''; $refs.messageInput.focus(); }); }"
                                wire:loading.attr="disabled"
                                class="msg-btn-send">
                            <span wire:loading.remove wire:target="sendReply">Send</span>
                            <span wire:loading wire:target="sendReply">Sending...</span>
                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @else
                <div class="flex-1 flex items-center justify-center msg-empty">
                    <div class="text-center">
                        <div class="msg-empty-icon mb-3">
                            <svg class="w-8 h-8 text-[#9E9E9E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <p>Select a conversation from the left</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

