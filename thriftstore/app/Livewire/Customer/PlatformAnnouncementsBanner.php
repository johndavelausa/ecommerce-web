<?php

namespace App\Livewire\Customer;

use App\Models\Announcement;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PlatformAnnouncementsBanner extends Component
{
    #[Computed]
    public function announcements()
    {
        return Announcement::query()
            ->whereIn('target_role', ['platform', 'all'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return <<<'HTML'
        <div>
            @if($this->announcements->isNotEmpty())
                <div class="mb-8 space-y-4">
                    @foreach($this->announcements as $announcement)
                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#0F3D22] to-[#1a5c35] p-6 text-white shadow-xl shadow-[#0F3D22]/10 transition-all hover:shadow-2xl hover:shadow-[#0F3D22]/20">
                            {{-- Decorative Background Element --}}
                            <div class="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/5 blur-3xl"></div>
                            <div class="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-[#F9C74F]/5 blur-2xl"></div>
                            
                            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
                                <div class="flex-1">
                                    <div class="mb-2 flex items-center gap-3">
                                        <span class="inline-flex items-center rounded-full bg-[#F9C74F]/20 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#F9C74F] ring-1 ring-inset ring-[#F9C74F]/30">
                                            Announcement
                                        </span>
                                        <span class="text-xs font-medium text-white/50">
                                            {{ $announcement->created_at->diffForHumans() }}
                                        </span>
                                    </div>
                                    <h3 class="text-xl font-black tracking-tight text-white md:text-2xl">
                                        {{ $announcement->title }}
                                    </h3>
                                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-white/80 md:text-base">
                                        {{ $announcement->body }}
                                    </p>
                                </div>
                                
                                <div class="shrink-0">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                                        <svg class="h-6 w-6 text-[#F9C74F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        HTML;
    }
}
