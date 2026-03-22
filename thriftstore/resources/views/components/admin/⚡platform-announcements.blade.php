<?php

use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $title = '';
    public string $body = '';
    public bool $is_active = true;
    public string $expires_at = '';

    public ?int $editingId = null;
    public string $edit_title = '';
    public string $edit_body = '';
    public bool $edit_is_active = true;
    public string $edit_expires_at = '';

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'expires_at' => ['nullable', 'date'],
        ]);

        Announcement::query()->create([
            'created_by' => Auth::guard('admin')->id(),
            'target_role' => 'platform',
            'title' => $this->title,
            'body' => $this->body,
            'is_active' => $this->is_active,
            'expires_at' => $this->expires_at !== '' ? $this->expires_at : null,
        ]);

        $this->reset(['title', 'body', 'expires_at']);
        $this->is_active = true;
        $this->dispatch('saved');
    }

    public function edit(int $id): void
    {
        $a = Announcement::query()->where('target_role', 'platform')->findOrFail($id);
        $this->editingId = $id;
        $this->edit_title = $a->title;
        $this->edit_body = $a->body;
        $this->edit_is_active = (bool) $a->is_active;
        $this->edit_expires_at = $a->expires_at ? $a->expires_at->format('Y-m-d\TH:i') : '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset(['edit_title', 'edit_body', 'edit_is_active', 'edit_expires_at']);
    }

    public function update(): void
    {
        $this->validate([
            'edit_title' => ['required', 'string', 'max:255'],
            'edit_body' => ['required', 'string', 'max:5000'],
            'edit_expires_at' => ['nullable', 'date'],
        ]);

        $a = Announcement::query()->where('target_role', 'platform')->findOrFail($this->editingId);
        $a->update([
            'title' => $this->edit_title,
            'body' => $this->edit_body,
            'is_active' => $this->edit_is_active,
            'expires_at' => $this->edit_expires_at !== '' ? $this->edit_expires_at : null,
        ]);
        $this->cancelEdit();
        $this->dispatch('saved');
    }

    public function delete(int $id): void
    {
        Announcement::query()->where('target_role', 'platform')->where('id', $id)->delete();
        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
        $this->dispatch('saved');
    }

    public function getPlatformAnnouncementsProperty()
    {
        return Announcement::query()
            ->where('target_role', 'platform')
            ->orderByDesc('created_at')
            ->get();
    }
};
?>

<div class="set-card">
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <div class="set-card-title">Platform Announcements</div>
            <p class="set-hint" style="margin-bottom:0;">Shown to all users on the homepage. Auto-hides after expiry.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3">
        <div>
            <label class="set-label">Title</label>
            <input type="text" wire:model.defer="title" class="set-input" placeholder="e.g. Holiday schedule" />
            @error('title') <div class="text-xs mt-1" style="color:#C0392B;">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="set-label">Message</label>
            <textarea wire:model.defer="body" rows="3" class="set-textarea" placeholder="Announcement text..."></textarea>
            @error('body') <div class="text-xs mt-1" style="color:#C0392B;">{{ $message }}</div> @enderror
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.defer="is_active" class="set-checkbox">
                <span style="font-size:0.875rem;color:#424242;">Active (show on homepage)</span>
            </label>
            <div>
                <label class="set-label">Expires at (optional)</label>
                <input type="datetime-local" wire:model.defer="expires_at" class="set-input" style="width:auto;">
            </div>
        </div>
        <div>
            <button type="button" wire:click="save" wire:loading.attr="disabled" class="set-btn">
                Add announcement
            </button>
        </div>
    </div>

    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #D4E8DA;">
        <div style="font-size:0.6875rem;font-weight:700;color:#9E9E9E;text-transform:uppercase;letter-spacing:0.05em;font-style:italic;margin-bottom:10px;">Current Announcements</div>
        <div class="space-y-2">
            @forelse($this->platformAnnouncements as $a)
                <div style="padding:12px 14px;background:#F5FBF7;border-radius:12px;border:1px solid #D4E8DA;">
                    @if($editingId === $a->id)
                        <div class="space-y-2">
                            <input type="text" wire:model.defer="edit_title" class="set-input">
                            <textarea wire:model.defer="edit_body" rows="2" class="set-textarea"></textarea>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.defer="edit_is_active" class="set-checkbox">
                                <span style="font-size:0.8125rem;color:#424242;">Active</span>
                            </label>
                            <input type="datetime-local" wire:model.defer="edit_expires_at" class="set-input" style="width:auto;">
                            <div class="flex gap-2 mt-2">
                                <button type="button" wire:click="update" class="set-btn" style="font-size:0.75rem;padding:6px 14px;">Save</button>
                                <button type="button" wire:click="cancelEdit" style="font-size:0.75rem;padding:6px 14px;border-radius:50px;border:1.5px solid #D4E8DA;background:#fff;color:#424242;cursor:pointer;">Cancel</button>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div style="font-weight:700;color:#0F3D22;font-size:0.9rem;">{{ $a->title }}</div>
                                <div style="font-size:0.75rem;color:#757575;margin-top:2px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">{{ $a->body }}</div>
                                <div style="font-size:0.6875rem;color:#9E9E9E;font-style:italic;margin-top:4px;">
                                    <span style="background:{{ $a->is_active ? '#E8F5E9' : '#FFEBEE' }};color:{{ $a->is_active ? '#1B7A37' : '#C0392B' }};padding:2px 8px;border-radius:50px;font-weight:600;font-style:normal;">{{ $a->is_active ? 'Active' : 'Inactive' }}</span>
                                    @if($a->expires_at) &nbsp;· Expires {{ $a->expires_at->format('M d, Y H:i') }} @endif
                                </div>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button type="button" wire:click="edit({{ $a->id }})" style="font-size:0.75rem;font-weight:600;color:#2D9F4E;background:none;border:none;cursor:pointer;padding:0;">Edit</button>
                                <button type="button" wire:click="delete({{ $a->id }})" wire:confirm="Delete this announcement?"
                                        style="font-size:0.75rem;font-weight:600;color:#C0392B;background:none;border:none;cursor:pointer;padding:0;">Delete</button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div style="font-size:0.875rem;color:#9E9E9E;font-style:italic;">No platform announcements yet.</div>
            @endforelse
        </div>
    </div>
</div>
