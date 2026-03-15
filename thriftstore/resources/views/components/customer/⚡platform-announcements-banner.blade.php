<?php

use App\Models\Announcement;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function announcements()
    {
        return Announcement::query()
            ->activePlatform()
            ->orderByDesc('created_at')
            ->get();
    }
};
?>

<div>
    @if($this->announcements->isNotEmpty())
        <div class="mb-6 space-y-3">
            @foreach($this->announcements as $a)
                <div class="rounded-xl border border-[#cfe0d7] bg-[#eef7f2] px-4 py-3 text-sm text-[#214233]">
                    <div class="font-semibold text-[#1f4f3a]">{{ $a->title }}</div>
                    <div class="mt-0.5 text-[#2a5f46]">{{ $a->body }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
