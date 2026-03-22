<?php

use App\Models\Conversation;
use App\Models\Message;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $selectedConversationId = null;
    public string $replyBody = '';
    public string $search = '';

    public function getConversationsProperty()
    {
        $q = Conversation::query()
            ->whereIn('type', ['seller-admin', 'guest'])
            ->with(['seller.user', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('is_read', false)->where('sender_type', '!=', 'admin')]);

        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $q->where(function ($query) use ($term) {
                $query->whereHas('messages', function ($m) use ($term) {
                    $m->where('body', 'like', $term);
                })->orWhereHas('seller', function ($s) use ($term) {
                    $s->where('store_name', 'like', $term)
                        ->orWhereHas('user', function ($u) use ($term) {
                            $u->where('name', 'like', $term)->orWhere('email', 'like', $term);
                        });
                });
            });
        }

        return $q->orderByRaw('(SELECT MAX(created_at) FROM messages WHERE messages.conversation_id = conversations.id) DESC')
            ->paginate(20);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
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
            ->where('sender_type', '!=', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function sendReply(): void
    {
        $this->validate(['replyBody' => 'required|string|max:5000']);
        $conv = Conversation::query()->findOrFail($this->selectedConversationId);
        if ($conv->type !== 'seller-admin') {
            return;
        }
        Message::create([
            'conversation_id' => $conv->id,
            'sender_id' => auth('admin')->id(),
            'sender_type' => 'admin',
            'body' => trim($this->replyBody),
            'is_read' => false,
        ]);
        $this->replyBody = '';
        $this->dispatch('scroll-to-bottom');
    }

    public function closeThread(): void
    {
        $this->selectedConversationId = null;
        $this->replyBody = '';
    }

    public function getSelectedConversationProperty()
    {
        if (!$this->selectedConversationId) {
            return null;
        }
        return Conversation::query()
            ->with(['seller.user', 'messages' => fn ($q) => $q->orderBy('created_at')])
            ->find($this->selectedConversationId);
    }
};
?>

<style>
    .inbox-sidebar { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; display: flex; flex-direction: column; max-height: 75vh; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .inbox-title { font-size: 1rem; font-weight: 800; color: #0F3D22; }
    .inbox-search { border-radius: 50px; border: 1.5px solid #D4E8DA; padding: 7px 14px; font-size: 0.8125rem; background: #fff; color: #424242; transition: all 0.15s; }
    .inbox-search:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .inbox-conv-item { width: 100%; text-align: left; padding: 10px 14px; border-bottom: 1px solid #F0F7F2; background: #fff; transition: background 0.12s; cursor: pointer; border: none; }
    .inbox-conv-item:hover { background: #F5FBF7; }
    .inbox-conv-item.active { background: #E8F5E9; border-left: 3px solid #1B7A37; }
    .inbox-unread { background: #1B7A37; color: #fff; font-size: 0.7rem; border-radius: 50px; padding: 2px 7px; font-weight: 700; }
    .inbox-thread { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; display: flex; flex-direction: column; max-height: 75vh; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .inbox-thread-header { padding: 12px 16px; border-bottom: 1px solid #D4E8DA; background: #F5FBF7; display: flex; justify-content: space-between; align-items: center; }
    .inbox-bubble-admin { background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #fff; border-radius: 16px 16px 4px 16px; padding: 8px 14px; max-width: 80%; font-size: 0.875rem; }
    .inbox-bubble-other { background: #F5F5F5; color: #424242; border-radius: 16px 16px 16px 4px; padding: 8px 14px; max-width: 80%; font-size: 0.875rem; }
    .inbox-reply-area { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.875rem; color: #424242; transition: all 0.15s; resize: none; }
    .inbox-reply-area:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
    .inbox-send-btn { padding: 8px 18px; border-radius: 50px; font-size: 0.8125rem; font-weight: 700; background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #fff; border: none; cursor: pointer; transition: all 0.15s; }
    .inbox-send-btn:hover { box-shadow: 0 4px 14px rgba(15,61,34,0.25); }
    .inbox-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .inbox-close-btn { font-size: 0.8125rem; font-weight: 600; color: #9E9E9E; border: 1.5px solid #D4E8DA; border-radius: 50px; padding: 4px 12px; background: #fff; cursor: pointer; }
    .inbox-close-btn:hover { color: #C0392B; border-color: #C0392B; }
</style>

<div class="flex gap-4">
    <div class="inbox-sidebar w-80 shrink-0">
        <div style="padding:12px 14px;border-bottom:1px solid #D4E8DA;background:#F5FBF7;display:flex;flex-direction:column;gap:8px;">
            <div class="inbox-title">Inbox</div>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search sender or message..."
                   class="inbox-search w-full">
        </div>
        <div class="overflow-y-auto flex-1">
            @forelse($this->conversations as $conv)
                <button type="button" wire:click="selectConversation({{ $conv->id }})"
                        class="inbox-conv-item {{ $selectedConversationId === $conv->id ? 'active' : '' }}">
                    <div class="flex justify-between items-start">
                        <span style="font-weight:600;font-size:0.875rem;color:#0F3D22;">
                            @if($conv->type === 'seller-admin' && $conv->seller)
                                {{ $conv->seller->user->name ?? $conv->seller->store_name }}
                            @else
                                <span style="font-style:italic;color:#757575;">Guest inquiry</span>
                            @endif
                        </span>
                        @if($conv->unread_count > 0)
                            <span class="inbox-unread">{{ $conv->unread_count }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-2 mt-1">
                        <div style="font-size:0.75rem;color:#9E9E9E;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;font-style:italic;">
                            {{ $conv->latestMessage?->body ?? 'No messages yet.' }}
                        </div>
                        <div style="font-size:0.6875rem;color:#9E9E9E;white-space:nowrap;font-style:italic;">
                            {{ optional($conv->latestMessage?->created_at)->diffForHumans(null, true) }}
                        </div>
                    </div>
                </button>
            @empty
                <div style="padding:24px;text-align:center;color:#9E9E9E;font-style:italic;font-size:0.875rem;">No conversations yet.</div>
            @endforelse
        </div>
        @if($this->conversations->hasPages())
            <div style="padding:8px 12px;border-top:1px solid #D4E8DA;">
                {{ $this->conversations->links() }}
            </div>
        @endif
    </div>

    <div class="inbox-thread flex-1">
        @if($this->selectedConversation)
            @php($conv = $this->selectedConversation)
            <div class="inbox-thread-header">
                <div>
                    <div style="font-weight:700;color:#0F3D22;font-size:0.9375rem;">
                        @if($conv->type === 'seller-admin' && $conv->seller)
                            {{ $conv->seller->user->name ?? $conv->seller->store_name }}
                        @else
                            <span style="font-style:italic;color:#757575;">Guest inquiry</span>
                        @endif
                    </div>
                    <div style="font-size:0.75rem;color:#9E9E9E;font-style:italic;">{{ ucfirst(str_replace('-', ' ', $conv->type)) }}</div>
                </div>
                <button type="button" wire:click="closeThread" class="inbox-close-btn">Close</button>
            </div>
            <div
                x-data="{ scrollToBottom() { this.$el.scrollTop = this.$el.scrollHeight; } }"
                x-init="scrollToBottom(); new MutationObserver(() => scrollToBottom()).observe($el, { childList: true, subtree: true });"
                x-on:scroll-to-bottom.window="$nextTick(() => scrollToBottom());"
                wire:poll.5s
                style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#FAFAFA;"
            >
                @foreach($conv->messages as $msg)
                    <div style="display:flex;justify-content:{{ $msg->sender_type === 'admin' ? 'flex-end' : 'flex-start' }};">
                        <div>
                            <div style="font-size:0.6875rem;color:#9E9E9E;font-style:italic;margin-bottom:3px;text-align:{{ $msg->sender_type === 'admin' ? 'right' : 'left' }};">
                                {{ $msg->sender_type === 'admin' ? 'You (Admin)' : ucfirst($msg->sender_type) }} · {{ $msg->created_at?->format('M d, H:i') }}
                            </div>
                            <div class="{{ $msg->sender_type === 'admin' ? 'inbox-bubble-admin' : 'inbox-bubble-other' }}">
                                {{ $msg->body }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($conv->type === 'seller-admin')
                <div style="padding:12px 14px;border-top:1px solid #D4E8DA;background:#fff;display:flex;flex-direction:column;gap:8px;" x-data="{ body: '' }">
                    <textarea
                        x-model="body"
                        x-ref="messageInput"
                        x-on:keydown.enter.prevent="if(body.trim() !== '') { @this.set('replyBody', body); @this.sendReply().then(() => { body = ''; $refs.messageInput.focus(); }); }"
                        wire:loading.attr="disabled"
                        wire:target="sendReply"
                        rows="2"
                        class="inbox-reply-area w-full"
                        placeholder="Reply… (Enter to send)"></textarea>
                    <div class="flex justify-end">
                        <button type="button"
                                x-on:click="if(body.trim() !== '') { @this.set('replyBody', body); @this.sendReply().then(() => { body = ''; $refs.messageInput.focus(); }); }"
                                wire:loading.attr="disabled"
                                class="inbox-send-btn">
                            <span wire:loading.remove wire:target="sendReply">Send</span>
                            <span wire:loading wire:target="sendReply">Sending…</span>
                        </button>
                    </div>
                </div>
            @else
                <div style="padding:12px 14px;border-top:1px solid #D4E8DA;font-size:0.8125rem;color:#9E9E9E;font-style:italic;background:#F5FBF7;">
                    Guest conversations are read-only.
                </div>
            @endif
        @else
            <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#9E9E9E;font-style:italic;font-size:0.9375rem;">
                Select a conversation to view messages
            </div>
        @endif
    </div>
</div>
