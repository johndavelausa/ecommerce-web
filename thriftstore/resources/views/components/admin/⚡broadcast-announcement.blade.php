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
    public string $target_role = 'seller';

    public function send(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'target_role' => ['required', 'in:seller,platform,all'],
        ]);

        $admin = Auth::guard('admin')->user();

        $announcement = Announcement::query()->create([
            'created_by' => $admin?->id,
            'target_role' => $this->target_role,
            'title' => $this->title,
            'body' => $this->body,
            'is_active' => true,
        ]);

        // Broadcast notifications to targeted users
        if ($this->target_role === 'seller') {
            // Target sellers only
            User::query()
                ->whereHas('seller')
                ->select(['id'])
                ->orderBy('id')
                ->chunkById(500, function ($users) use ($announcement) {
                    Notification::send($users, new BroadcastAnnouncement($announcement));
                });
        } else {
            // Target ALL users (for 'all' or 'platform' broadcasts)
            User::query()
                ->select(['id'])
                ->orderBy('id')
                ->chunkById(500, function ($users) use ($announcement) {
                    Notification::send($users, new BroadcastAnnouncement($announcement));
                });
        }

        $this->reset(['title', 'body', 'target_role']);
        $this->target_role = 'seller';
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

<div class="set-card" style="border-color:#D4E8DA;">
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <div class="set-card-title">Broadcast Announcement</div>
            <p class="set-hint" style="margin-bottom:0;">Sent to all sellers — shows in their notification bell.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="set-label">Title</label>
                <input type="text" wire:model.defer="title" class="set-input" placeholder="e.g. System update" />
                @error('title') <div class="text-xs mt-1" style="color:#C0392B;">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="set-label">Send to</label>
                <select wire:model.defer="target_role" class="set-input">
                    <option value="seller">Sellers only (Dash + Bell)</option>
                    <option value="platform">All users (Home Banner)</option>
                    <option value="all">Everyone (Home + Dash + Bell)</option>
                </select>
            </div>
        </div>
        <div>
            <label class="set-label">Message</label>
            <textarea wire:model.defer="body" rows="4" class="set-textarea" placeholder="Write the announcement here..."></textarea>
            @error('body') <div class="text-xs mt-1" style="color:#C0392B;">{{ $message }}</div> @enderror
        </div>
        <div class="flex items-center gap-3">
            <button type="button" wire:click="send" wire:loading.attr="disabled" class="set-btn">
                <span wire:loading.remove wire:target="send">Send Broadcast</span>
                <span wire:loading wire:target="send">Sending…</span>
            </button>
        </div>
    </div>

    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #D4E8DA;">
        <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:10px;">Recent Broadcasts</div>
        <div class="space-y-2">
            @forelse($this->recent as $a)
                <div style="padding:12px 14px;background:#F5FBF7;border-radius:12px;border:1px solid #D4E8DA;display:flex;align-items:start;justify-content:space-between;gap:12px;">
                    <div class="min-w-0">
                        <div style="font-weight:700;color:#0F3D22;font-size:0.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $a->title }}</div>
                        <div style="font-size:0.75rem;color:#757575;margin-top:2px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">{{ $a->body }}</div>
                    </div>
                    <div style="font-size:0.6875rem;color:#9E9E9E;font-style:italic;white-space:nowrap;flex-shrink:0;">
                        {{ $a->created_at?->format('M d, Y g:i A') }}
                    </div>
                </div>
            @empty
                <div style="font-size:0.875rem;color:#9E9E9E;font-style:italic;">No announcements yet.</div>
            @endforelse
        </div>
    </div>
</div>

