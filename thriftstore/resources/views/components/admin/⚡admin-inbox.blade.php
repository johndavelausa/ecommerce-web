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
            ->with(['seller.user', 'messages' => fn ($q) => $q->orderBy('created_at')])
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
        $this->dispatch('reply-sent');
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

<div class="flex gap-4">
    <div class="w-80 shrink-0 bg-white rounded-lg shadow overflow-hidden flex flex-col max-h-[70vh]">
        <div class="p-3 border-b space-y-2">
            <div class="font-medium">Inbox</div>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search sender or message..."
                   class="w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="overflow-y-auto flex-1 divide-y">
            @forelse($this->conversations as $conv)
                <button type="button" wire:click="selectConversation({{ $conv->id }})" class="w-full text-left p-3 hover:bg-gray-50 {{ $selectedConversationId === $conv->id ? 'bg-indigo-50' : '' }}">
                    <div class="flex justify-between items-start">
                        <span class="font-medium text-sm">
                            @if($conv->type === 'seller-admin' && $conv->seller)
                                {{ $conv->seller->user->name ?? $conv->seller->store_name }}
                            @else
                                Guest inquiry
                            @endif
                        </span>
                        @if($conv->unread_count > 0)
                            <span class="bg-indigo-600 text-white text-xs rounded-full px-2 py-0.5">{{ $conv->unread_count }}</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ $conv->type === 'guest' ? 'Guest' : 'Seller' }}</div>
                </button>
            @empty
                <div class="p-4 text-center text-gray-500 text-sm">No conversations yet.</div>
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
                    @if($conv->type === 'seller-admin' && $conv->seller)
                        {{ $conv->seller->user->name ?? $conv->seller->store_name }}
                    @else
                        Guest inquiry
                    @endif
                </span>
                <button type="button" wire:click="closeThread" class="text-gray-500 hover:text-gray-700">Close</button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                @foreach($conv->messages as $msg)
                    <div class="flex {{ $msg->sender_type === 'admin' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-lg px-3 py-2 {{ $msg->sender_type === 'admin' ? 'bg-indigo-100' : 'bg-gray-100' }}">
                            <div class="text-xs text-gray-500 mb-1">{{ $msg->sender_type }} · {{ $msg->created_at?->format('M d, H:i') }}</div>
                            <div class="text-sm whitespace-pre-wrap">{{ $msg->body }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($conv->type === 'seller-admin')
                <div class="p-3 border-t">
                    <textarea wire:model="replyBody" rows="2" class="w-full rounded border-gray-300 text-sm" placeholder="Reply..."></textarea>
                    <button type="button" wire:click="sendReply" class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700">Send</button>
                </div>
            @else
                <div class="p-3 border-t text-sm text-gray-500">Guest conversations are read-only. No reply.</div>
            @endif
        @else
            <div class="flex-1 flex items-center justify-center text-gray-500">Select a conversation</div>
        @endif
    </div>
</div>
