<?php

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\BroadcastAnnouncement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $title = '';
    public string $body = '';

    public function send(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $admin = Auth::guard('admin')->user();

        $announcement = Announcement::query()->create([
            'created_by' => $admin?->id,
            'target_role' => 'seller',
            'title' => $this->title,
            'body' => $this->body,
        ]);

        // Broadcast to all sellers (users that have a seller profile)
        User::query()
            ->whereHas('seller')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($announcement) {
                Notification::send($users, new BroadcastAnnouncement($announcement));
            });

        $this->reset(['title', 'body']);
        $this->dispatch('saved');
    }

    #[Computed]
    public function recent()
    {
        return Announcement::query()
            ->where('target_role', 'seller')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }
};
?>

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between">
        <h3 class="font-medium text-gray-900">Broadcast Announcement (to all Sellers)</h3>
        <span class="text-xs text-gray-500">Shows in sellers’ notification bell</span>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3">
        <div>
            <label class="block text-sm text-gray-600">Title</label>
            <input type="text" wire:model.defer="title" class="mt-1 rounded border-gray-300 w-full" placeholder="e.g. Subscription fee update" />
            @error('title') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-sm text-gray-600">Message</label>
            <textarea wire:model.defer="body" rows="4" class="mt-1 rounded border-gray-300 w-full" placeholder="Write the announcement here..."></textarea>
            @error('body') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="send" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-500">
                Send to all sellers
            </button>
            <span wire:loading class="text-sm text-gray-500">Sending...</span>
        </div>
    </div>

    <div class="mt-6 border-t pt-4">
        <h4 class="text-sm font-medium text-gray-700">Recent announcements</h4>
        <div class="mt-2 space-y-2">
            @forelse($this->recent as $a)
                <div class="p-3 bg-gray-50 rounded border">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-800 truncate">{{ $a->title }}</div>
                            <div class="text-xs text-gray-600 line-clamp-2">{{ $a->body }}</div>
                        </div>
                        <div class="text-[11px] text-gray-500 whitespace-nowrap">
                            {{ $a->created_at?->format('M d, Y g:i A') }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-500">No announcements yet.</div>
            @endforelse
        </div>
    </div>
</div>

