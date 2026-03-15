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

<div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between">
        <h3 class="font-medium text-gray-900">Platform announcements (homepage banner)</h3>
        <span class="text-xs text-gray-500">Shown to all users on the homepage. Auto-hides after expiry.</span>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3">
        <div>
            <label class="block text-sm text-gray-600">Title</label>
            <input type="text" wire:model.defer="title" class="mt-1 rounded border-gray-300 w-full" placeholder="e.g. Holiday schedule" />
            @error('title') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-sm text-gray-600">Message</label>
            <textarea wire:model.defer="body" rows="3" class="mt-1 rounded border-gray-300 w-full" placeholder="Announcement text..."></textarea>
            @error('body') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.defer="is_active" class="rounded border-gray-300 text-indigo-600">
                <span class="text-sm text-gray-700">Active (show on homepage)</span>
            </label>
            <div>
                <label class="block text-sm text-gray-600">Expires at (optional)</label>
                <input type="datetime-local" wire:model.defer="expires_at" class="mt-1 rounded border-gray-300 text-sm">
            </div>
        </div>
        <div>
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-500">
                Add announcement
            </button>
        </div>
    </div>

    <div class="mt-6 border-t pt-4">
        <h4 class="text-sm font-medium text-gray-700">Current platform announcements</h4>
        <div class="mt-2 space-y-2">
            @forelse($this->platformAnnouncements as $a)
                <div class="p-3 bg-gray-50 rounded border">
                    @if($editingId === $a->id)
                        <div class="space-y-2">
                            <input type="text" wire:model.defer="edit_title" class="rounded border-gray-300 w-full text-sm">
                            <textarea wire:model.defer="edit_body" rows="2" class="rounded border-gray-300 w-full text-sm"></textarea>
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model.defer="edit_is_active" class="rounded border-gray-300"> Active</label>
                            <input type="datetime-local" wire:model.defer="edit_expires_at" class="rounded border-gray-300 text-sm">
                            <div class="flex gap-2">
                                <button type="button" wire:click="update" class="px-3 py-1 bg-indigo-600 text-white rounded text-xs">Save</button>
                                <button type="button" wire:click="cancelEdit" class="px-3 py-1 border rounded text-xs">Cancel</button>
                            </div>
                        </div>
                    @else
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-800">{{ $a->title }}</div>
                                <div class="text-xs text-gray-600 line-clamp-2">{{ $a->body }}</div>
                                <div class="text-[11px] text-gray-500 mt-1">
                                    {{ $a->is_active ? 'Active' : 'Inactive' }}
                                    @if($a->expires_at) · Expires {{ $a->expires_at->format('M d, Y H:i') }} @endif
                                </div>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <button type="button" wire:click="edit({{ $a->id }})" class="text-xs text-indigo-600 hover:underline">Edit</button>
                                <button type="button" wire:click="delete({{ $a->id }})" wire:confirm="Delete this announcement?"
                                        class="text-xs text-red-600 hover:underline">Delete</button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-sm text-gray-500">No platform announcements yet.</div>
            @endforelse
        </div>
    </div>
</div>
