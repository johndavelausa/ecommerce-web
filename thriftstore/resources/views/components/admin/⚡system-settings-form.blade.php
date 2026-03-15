<?php

use App\Models\SystemSetting;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $logo;
    public $background;
    public string $gcash_number = '';
    public $gcashQr;

    /** A4 v1.4 — Editable legal pages */
    public string $page_privacy_policy = '';
    public string $page_terms_of_service = '';
    public string $page_cookie_settings = '';

    /** A4 v1.4 — Maintenance mode */
    public bool $maintenance_mode = false;
    public string $maintenance_message = '';

    public function mount(): void
    {
        $this->gcash_number = (string) SystemSetting::get('gcash_number', '');
        $this->page_privacy_policy = (string) SystemSetting::get('page_privacy_policy', '');
        $this->page_terms_of_service = (string) SystemSetting::get('page_terms_of_service', '');
        $this->page_cookie_settings = (string) SystemSetting::get('page_cookie_settings', '');
        $this->maintenance_mode = (bool) (int) SystemSetting::get('maintenance_mode', 0);
        $this->maintenance_message = (string) SystemSetting::get('maintenance_message', 'We are currently under maintenance. Please check back soon.');
    }

    public function saveLogo(): void
    {
        $this->validate(['logo' => 'required|image|max:2048']);
        $path = $this->logo->store('settings', 'public');
        SystemSetting::set('logo_path', $path);
        $this->reset('logo');
        $this->dispatch('saved');
    }

    public function saveBackground(): void
    {
        $this->validate(['background' => 'required|image|max:5120']);
        $path = $this->background->store('settings', 'public');
        SystemSetting::set('background_path', $path);
        $this->reset('background');
        $this->dispatch('saved');
    }

    public function saveGcash(): void
    {
        $this->validate(['gcash_number' => 'nullable|string|max:50']);
        SystemSetting::set('gcash_number', $this->gcash_number);
        $this->dispatch('saved');
    }

    public function saveGcashQr(): void
    {
        $this->validate(['gcashQr' => 'required|image|max:2048']);
        $path = $this->gcashQr->store('settings', 'public');
        SystemSetting::set('gcash_qr_path', $path);
        $this->reset('gcashQr');
        $this->dispatch('saved');
    }

    public function saveLegalPages(): void
    {
        SystemSetting::set('page_privacy_policy', $this->page_privacy_policy);
        SystemSetting::set('page_terms_of_service', $this->page_terms_of_service);
        SystemSetting::set('page_cookie_settings', $this->page_cookie_settings);
        $this->dispatch('saved');
    }

    public function saveMaintenance(): void
    {
        SystemSetting::set('maintenance_mode', $this->maintenance_mode ? '1' : '0');
        SystemSetting::set('maintenance_message', $this->maintenance_message);
        $this->dispatch('saved');
    }
};
?>

<div class="space-y-8 max-w-2xl">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-medium text-gray-900 mb-4">Platform logo</h3>
        @if($logo)
            <p class="text-sm text-gray-500 mb-2">New file selected. Click Save to apply.</p>
        @else
            @if($currentLogo = \App\Models\SystemSetting::get('logo_path'))
                <img src="{{ asset('storage/' . $currentLogo) }}" alt="Logo" class="h-16 object-contain mb-2">
            @endif
        @endif
        <input type="file" wire:model="logo" accept="image/*" class="block text-sm text-gray-500">
        <button type="button" wire:click="saveLogo" wire:loading.attr="disabled" class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded text-sm">Save logo</button>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-medium text-gray-900 mb-4">Background image / theme</h3>
        @if($background)
            <p class="text-sm text-gray-500 mb-2">New file selected. Click Save to apply.</p>
        @else
            @if($currentBg = \App\Models\SystemSetting::get('background_path'))
                <img src="{{ asset('storage/' . $currentBg) }}" alt="Background" class="max-h-32 object-cover rounded mb-2">
            @endif
        @endif
        <input type="file" wire:model="background" accept="image/*" class="block text-sm text-gray-500">
        <button type="button" wire:click="saveBackground" wire:loading.attr="disabled" class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded text-sm">Save background</button>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-medium text-gray-900 mb-4">GCash (for seller fees)</h3>
        <div class="space-y-2">
            <label class="block text-sm text-gray-600">GCash number</label>
            <input type="text" wire:model="gcash_number" class="rounded border-gray-300 w-full max-w-xs">
            <button type="button" wire:click="saveGcash" class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded text-sm">Save number</button>
        </div>
        <div class="mt-4">
            <label class="block text-sm text-gray-600">GCash QR image</label>
            @if($currentQr = \App\Models\SystemSetting::get('gcash_qr_path'))
                <img src="{{ asset('storage/' . $currentQr) }}" alt="GCash QR" class="w-32 h-32 object-contain border rounded mt-1">
            @endif
            @if($gcashQr)
                <p class="text-sm text-gray-500 mt-1">New QR selected. Click Save.</p>
            @endif
            <input type="file" wire:model="gcashQr" accept="image/*" class="block text-sm text-gray-500 mt-2">
            <button type="button" wire:click="saveGcashQr" wire:loading.attr="disabled" class="mt-2 px-3 py-1 bg-indigo-600 text-white rounded text-sm">Save QR</button>
        </div>
    </div>

    {{-- A4 v1.4 — Editable legal pages --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-medium text-gray-900 mb-4">Legal pages (editable content)</h3>
        <p class="text-xs text-gray-500 mb-4">Content is shown on the public Privacy, Terms, and Cookie Settings pages. No code changes needed.</p>
        <div class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600">Privacy Policy</label>
                <textarea wire:model.defer="page_privacy_policy" rows="6" class="mt-1 rounded border-gray-300 w-full text-sm" placeholder="Privacy policy content..."></textarea>
            </div>
            <div>
                <label class="block text-sm text-gray-600">Terms of Service</label>
                <textarea wire:model.defer="page_terms_of_service" rows="6" class="mt-1 rounded border-gray-300 w-full text-sm" placeholder="Terms of service content..."></textarea>
            </div>
            <div>
                <label class="block text-sm text-gray-600">Cookie Settings</label>
                <textarea wire:model.defer="page_cookie_settings" rows="4" class="mt-1 rounded border-gray-300 w-full text-sm" placeholder="Cookie settings content..."></textarea>
            </div>
            <button type="button" wire:click="saveLegalPages" class="px-4 py-2 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-500">Save legal pages</button>
        </div>
    </div>

    {{-- A4 v1.4 — Maintenance mode --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-medium text-gray-900 mb-4">Maintenance mode</h3>
        <p class="text-xs text-gray-500 mb-4">When enabled, all non-admin users see the message below. Admins can still access the dashboard.</p>
        <div class="space-y-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model.defer="maintenance_mode" class="rounded border-gray-300 text-indigo-600">
                <span class="text-sm text-gray-700">Maintenance mode enabled</span>
            </label>
            <div>
                <label class="block text-sm text-gray-600">Message shown to users</label>
                <textarea wire:model.defer="maintenance_message" rows="3" class="mt-1 rounded border-gray-300 w-full text-sm" placeholder="We are currently under maintenance..."></textarea>
            </div>
            <button type="button" wire:click="saveMaintenance" class="px-4 py-2 bg-indigo-600 text-white rounded text-sm hover:bg-indigo-500">Save maintenance settings</button>
        </div>
    </div>
</div>
