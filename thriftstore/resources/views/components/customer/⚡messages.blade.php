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

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <h3 class="text-lg font-medium text-gray-900">Messages with sellers</h3>
        <p class="mt-1 text-sm text-gray-500">
            Ask questions about your orders directly with sellers.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="bg-white rounded-lg shadow p-4 sm:p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-gray-900">Conversations</h4>
            </div>

            <?php $conversations = $this->conversations; ?>

            <?php if($conversations->isEmpty()): ?>
                <div class="text-sm text-gray-500">
                    No conversations yet. Start one from your orders below.
                </div>
            <?php else: ?>
                <ul class="divide-y divide-gray-200 text-sm">
                    <?php foreach($conversations as $conv): ?>
                        <li>
                            <button type="button"
                                    wire:click="openConversation(<?= $conv->id ?>)"
                                    class="w-full text-left px-3 py-2 hover:bg-gray-50 <?= $activeConversationId === $conv->id ? 'bg-gray-50' : '' ?>">
                                <div class="font-medium text-gray-900">
                                    <?= e($conv->seller->store_name ?? 'Seller #'.$conv->seller_id) ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Updated <?= optional($conv->updated_at)->diffForHumans() ?>
                                </div>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="pt-4 border-t">
                <h5 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">
                    Start new conversation
                </h5>
                <?php $sellers = $this->sellersFromOrders; ?>
                <?php if($sellers->isEmpty()): ?>
                    <div class="text-xs text-gray-500">
                        You need at least one order before messaging sellers.
                    </div>
                <?php else: ?>
                    <ul class="space-y-1 text-xs">
                        <?php foreach($sellers as $seller): ?>
                            <li>
                                <button type="button"
                                        wire:click="startWithSeller(<?= $seller->id ?>)"
                                        class="text-indigo-600 hover:text-indigo-800">
                                    <?= e($seller->store_name) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-lg shadow flex flex-col">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <div class="text-sm font-semibold text-gray-900">
                    Conversation
                </div>
            </div>
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3 text-sm">
                <?php $messages = $this->messages; ?>
                <?php if($activeConversationId && $messages->isNotEmpty()): ?>
                    <?php $customer = $this->customer; ?>
                    <?php foreach($messages as $msg): ?>
                        <?php $isMe = $msg->sender_type === 'customer' && $msg->sender_id === $customer->id; ?>
                        <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?>">
                            <div class="max-w-xs sm:max-w-sm rounded-lg px-3 py-2 text-xs sm:text-sm
                                        <?= $isMe ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-900' ?>">
                                <div><?= e($msg->body) ?></div>
                                <div class="mt-1 text-[10px] opacity-75">
                                    <?= optional($msg->created_at)->format('Y-m-d H:i') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif($activeConversationId): ?>
                    <div class="text-sm text-gray-500">
                        No messages yet. Say hello!
                    </div>
                <?php else: ?>
                    <div class="text-sm text-gray-500">
                        Select a conversation or start a new one from the left.
                    </div>
                <?php endif; ?>
            </div>
            <form wire:submit.prevent="send" class="border-t px-4 py-3 flex gap-2">
                <input type="text" wire:model.defer="body"
                       class="flex-1 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="Type a message...">
                <button type="submit"
                        class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-indigo-600 rounded-md text-xs font-semibold text-white uppercase tracking-widest shadow-sm hover:bg-indigo-500">
                    Send
                </button>
            </form>
        </div>
    </div>
</div>

