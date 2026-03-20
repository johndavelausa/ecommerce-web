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

<div class="flex gap-4">
    <div class="w-80 shrink-0 bg-white rounded-lg shadow overflow-hidden flex flex-col max-h-[70vh]">
        <div class="p-3 border-b flex items-center justify-between">
            <span class="font-medium">Conversations</span>
            @php
                $hasAdminConv = $this->conversations->getCollection()->contains('type', 'seller-admin');
            @endphp
            @if(!$hasAdminConv)
                <button type="button" wire:click="inquireAdmin" title="Message Admin" class="p-1 text-indigo-600 hover:bg-indigo-50 rounded">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>
            @endif
        </div>
        <div class="overflow-y-auto flex-1 divide-y">
            @forelse($this->conversations as $conv)
                <button type="button"
                        wire:click="selectConversation({{ $conv->id }})"
                        class="w-full text-left p-3 hover:bg-gray-50 {{ $selectedConversationId === $conv->id ? 'bg-indigo-50' : '' }}">
                    <div class="flex justify-between items-start">
                        <span class="font-medium text-sm">
                            @if($conv->type === 'seller-admin')
                                <span class="text-indigo-600 font-bold">Support Admin</span>
                            @else
                                {{ $conv->customer->name ?? 'Customer #'.$conv->customer_id }}
                            @endif
                        </span>
                        @if($conv->unread_count > 0)
                            <span class="bg-indigo-600 text-white text-xs rounded-full px-2 py-0.5">
                                {{ $conv->unread_count }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between mt-0.5">
                        <div class="text-xs text-gray-400 truncate flex-1">
                            {{ $conv->latestMessage?->body ?? 'No messages yet.' }}
                        </div>
                        <div class="text-[10px] text-gray-400 ml-2 shrink-0">
                            {{ optional($conv->latestMessage?->created_at)->diffForHumans(null, true) }}
                        </div>
                    </div>
                </button>
            @empty
                <div class="p-4 text-center text-gray-500 text-sm">No customer conversations yet.</div>
            @endforelse
        </div>
        @if($this->conversations->hasPages())
            <div class="p-2 border-t">
                {{ $this->conversations->links() }}
            </div>
        @endif
    </div>

    <div class="flex-1 bg-white rounded-lg shadow overflow-hidden flex flex-col max-h-[70vh]">
        @if($this->selectedConversation)
            @php($conv = $this->selectedConversation)
            <div class="p-3 border-b flex justify-between items-center">
                <span class="font-medium">
                    @if($conv->type === 'seller-admin')
                        Support Admin
                    @else
                        {{ $conv->customer->name ?? 'Customer #'.$conv->customer_id }}
                    @endif
                </span>
                <span class="text-xs text-gray-500">
                    Conversation #{{ $conv->id }}
                </span>
            </div>
            <div
                x-data="{
                    scrollToBottom() {
                        this.$el.scrollTop = this.$el.scrollHeight;
                    }
                }"
                x-init="
                    scrollToBottom();
                    new MutationObserver(() => scrollToBottom()).observe($el, { childList: true, subtree: true });
                "
                x-on:scroll-to-bottom.window="
                    $nextTick(() => scrollToBottom());
                "
                wire:poll.5s
                class="flex-1 overflow-y-auto p-4 space-y-3"
            >
                @php($sellerUser = auth('seller')->user())
                @foreach($conv->messages as $msg)
                    @php($isSeller = $msg->sender_type === 'seller' && $msg->sender_id === $sellerUser?->id)
                    <div class="flex {{ $isSeller ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-lg px-3 py-2 {{ $isSeller ? 'bg-indigo-100' : 'bg-gray-100' }}">
                            <div class="text-[11px] text-gray-500 mb-1">
                                {{ ucfirst($msg->sender_type) }} · {{ $msg->created_at?->format('M d, H:i') }}
                            </div>
                            <div class="text-sm whitespace-pre-wrap">
                                {{ $msg->body }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="p-3 border-t" x-data="{ body: '' }">
                <textarea
                    x-model="body"
                    x-ref="messageInput"
                    x-on:keydown.enter.prevent="if(body.trim() !== '') { @this.set('replyBody', body); @this.sendReply().then(() => { body = ''; $refs.messageInput.focus(); }); }"
                    wire:loading.attr="disabled"
                    wire:target="sendReply"
                    rows="2"
                    class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                    placeholder="Type your message... (Enter to send)"></textarea>
                @error('replyBody') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                <button type="button"
                        x-on:click="if(body.trim() !== '') { @this.set('replyBody', body); @this.sendReply().then(() => { body = ''; $refs.messageInput.focus(); }); }"
                        wire:loading.attr="disabled"
                        class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="sendReply">Send</span>
                    <span wire:loading wire:target="sendReply">Sending...</span>
                </button>
            </div>
        @else
            <div class="flex-1 flex items-center justify-center text-gray-500">
                Select a conversation from the left.
            </div>
        @endif
    </div>
</div>

