<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $selectedConversationId = null;
    public string $replyBody = '';

    public function getConversationsProperty()
    {
        $seller = Auth::guard('seller')->user()?->seller;
        if (! $seller) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        return Conversation::query()
            ->where('type', 'seller-customer')
            ->where('seller_id', $seller->id)
            ->with(['customer'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('is_read', false)->where('sender_type', 'customer')])
            ->orderByRaw('(SELECT MAX(created_at) FROM messages WHERE messages.conversation_id = conversations.id) DESC')
            ->paginate(20);
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
            ->where('sender_type', 'customer')
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
            ->where('type', 'seller-customer')
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
        <div class="p-3 border-b font-medium">Customer messages</div>
        <div class="overflow-y-auto flex-1 divide-y">
            @forelse($this->conversations as $conv)
                <button type="button"
                        wire:click="selectConversation({{ $conv->id }})"
                        class="w-full text-left p-3 hover:bg-gray-50 {{ $selectedConversationId === $conv->id ? 'bg-indigo-50' : '' }}">
                    <div class="flex justify-between items-start">
                        <span class="font-medium text-sm">
                            {{ $conv->customer->name ?? 'Customer #'.$conv->customer_id }}
                        </span>
                        @if($conv->unread_count > 0)
                            <span class="bg-indigo-600 text-white text-xs rounded-full px-2 py-0.5">
                                {{ $conv->unread_count }}
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        Updated {{ optional($conv->updated_at)->diffForHumans() }}
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
                    {{ $conv->customer->name ?? 'Customer #'.$conv->customer_id }}
                </span>
                <span class="text-xs text-gray-500">
                    Conversation #{{ $conv->id }}
                </span>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
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
            <div class="p-3 border-t">
                <textarea wire:model="replyBody" rows="2"
                          class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Reply to customer..."></textarea>
                <button type="button"
                        wire:click="sendReply"
                        class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-700">
                    Send
                </button>
            </div>
        @else
            <div class="flex-1 flex items-center justify-center text-gray-500">
                Select a conversation from the left.
            </div>
        @endif
    </div>
</div>

