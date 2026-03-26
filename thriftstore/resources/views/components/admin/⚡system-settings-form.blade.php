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
        $this->validate(['logo' => 'required|image|max:1024']);
        SystemSetting::set_file('logo_path', $this->logo, 'site');
        $this->reset('logo');
        $this->dispatch('saved');
    }

    public function saveBackground(): void
    {
        $this->validate(['background' => 'required|image|max:1024']);
        SystemSetting::set_file('background_path', $this->background, 'site');
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
        $this->validate(['gcashQr' => 'required|image|max:1024']);
        SystemSetting::set_file('gcash_qr_path', $this->gcashQr, 'site');
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
        \Illuminate\Support\Facades\Log::info('Saving maintenance settings: ' . ($this->maintenance_mode ? 'ON' : 'OFF'));
        
        SystemSetting::set('maintenance_mode', $this->maintenance_mode ? '1' : '0');
        SystemSetting::set('maintenance_message', $this->maintenance_message);
        
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Maintenance mode updated successfully.'
        ]);

        $this->dispatch('saved');
    }
};
?>

<div class="space-y-6 max-w-2xl">
    <style>
        .set-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; padding: 20px 24px; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
        .set-card-title { font-size: 1rem; font-weight: 800; color: #0F3D22; margin-bottom: 4px; }
        .set-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; display: block; margin-bottom: 6px; }
        .set-input, .set-textarea { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.8125rem; color: #424242; transition: all 0.15s; width: 100%; }
        .set-input:focus, .set-textarea:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
        .set-btn { padding: 8px 18px; border-radius: 50px; font-size: 0.8125rem; font-weight: 700; background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #fff; border: none; cursor: pointer; transition: all 0.15s; }
        .set-btn:hover { box-shadow: 0 4px 14px rgba(15,61,34,0.2); }
        .set-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .set-hint { font-size: 0.75rem; color: #9E9E9E; font-style: italic; margin-bottom: 12px; }
        .set-divider { border: none; border-top: 1px solid #D4E8DA; margin: 16px 0; }
        .set-checkbox { accent-color: #1B7A37; width: 16px; height: 16px; cursor: pointer; }
    </style>

    <div class="set-card">
        <div class="set-card-title">Platform Logo</div>
        <p class="set-hint">Shown in the navigation bar and browser tab.</p>
        @if($logo)
            <p class="set-hint" style="color:#2D9F4E;">New file selected — click Save to apply.</p>
        @else
            @if($currentLogo = \App\Models\SystemSetting::get_url('logo_path'))
                <img src="{{ $currentLogo }}" alt="Logo" class="h-16 object-contain mb-3" style="border-radius:8px;border:1px solid #D4E8DA;">
            @endif
        @endif
        <input type="file" wire:model="logo" accept="image/*" class="block text-sm" style="color:#757575;">
        <button type="button" wire:click="saveLogo" wire:loading.attr="disabled" class="set-btn mt-3">Save logo</button>
    </div>

    <div class="set-card">
        <div class="set-card-title">Background Image / Theme</div>
        <p class="set-hint">Used on the homepage and login pages.</p>
        @if($background)
            <p class="set-hint" style="color:#2D9F4E;">New file selected — click Save to apply.</p>
        @else
            @if($currentBg = \App\Models\SystemSetting::get_url('background_path'))
                <img src="{{ $currentBg }}" alt="Background" class="max-h-32 object-cover rounded mb-3" style="border-radius:12px;border:1px solid #D4E8DA;">
            @endif
        @endif
        <input type="file" wire:model="background" accept="image/*" class="block text-sm" style="color:#757575;">
        <button type="button" wire:click="saveBackground" wire:loading.attr="disabled" class="set-btn mt-3">Save background</button>
    </div>

    <div class="set-card">
        <div class="set-card-title">GCash Settings</div>
        <p class="set-hint">Used by sellers to submit subscription fee payments.</p>
        <div class="space-y-4">
            <div>
                <label class="set-label">GCash Number</label>
                <input type="text" wire:model="gcash_number" class="set-input" style="max-width:280px;" placeholder="e.g. 09XXXXXXXXX">
                <button type="button" wire:click="saveGcash" class="set-btn mt-3">Save number</button>
            </div>
            <hr class="set-divider">
            <div>
                <label class="set-label">GCash QR Image</label>
                @if($currentQr = \App\Models\SystemSetting::get_url('gcash_qr_path'))
                    <img src="{{ $currentQr }}" alt="GCash QR" class="w-32 h-32 object-contain mt-1 mb-2" style="border-radius:12px;border:1.5px solid #D4E8DA;">
                @endif
                @if($gcashQr)
                    <p class="set-hint" style="color:#2D9F4E;">New QR selected — click Save.</p>
                @endif
                <input type="file" wire:model="gcashQr" accept="image/*" class="block text-sm" style="color:#757575;">
                <button type="button" wire:click="saveGcashQr" wire:loading.attr="disabled" class="set-btn mt-3">
                    <span wire:loading.remove wire:target="saveGcashQr">Save QR</span>
                    <span wire:loading wire:target="saveGcashQr">Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- A4 v1.4 — Editable legal pages --}}
    <div class="set-card">
        <div class="set-card-title">Legal Pages</div>
        <p class="set-hint">Content shown on the public Privacy, Terms, and Cookie Settings pages.</p>
        <div class="space-y-4">
            <div>
                <label class="set-label">Privacy Policy</label>
                <textarea wire:model.defer="page_privacy_policy" rows="6" class="set-textarea" placeholder="Privacy policy content..."></textarea>
            </div>
            <div>
                <label class="set-label">Terms of Service</label>
                <textarea wire:model.defer="page_terms_of_service" rows="6" class="set-textarea" placeholder="Terms of service content..."></textarea>
            </div>
            <div>
                <label class="set-label">Cookie Settings</label>
                <textarea wire:model.defer="page_cookie_settings" rows="4" class="set-textarea" placeholder="Cookie settings content..."></textarea>
            </div>
            <button type="button" wire:click="saveLegalPages" class="set-btn">Save legal pages</button>
        </div>
    </div>

    {{-- A4 v1.4 — Maintenance mode --}}
    <div class="set-card" style="border-color:#FFCDD2;background:#FFFBFB;" x-data="{ saved: false }" x-on:saved.window="saved = true; setTimeout(() => saved = false, 2000)">
        <div class="set-card-title" style="color:#C0392B;">Maintenance Mode</div>
        <div x-show="saved" x-cloak class="mb-3 p-2 bg-green-100 text-green-700 text-xs font-bold rounded-lg border border-green-200">
            ✓ Maintenance settings saved.
        </div>
        <p class="set-hint">When enabled, all non-admin users see the message below.</p>
        <div class="space-y-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model="maintenance_mode" class="set-checkbox">
                <span style="font-size:0.875rem;color:#424242;font-weight:600;">Maintenance mode enabled</span>
            </label>
            <div>
                <label class="set-label">Message shown to users</label>
                <textarea wire:model="maintenance_message" rows="3" class="set-input" style="border-color:#FFCDD2;" placeholder="We are currently under maintenance..."></textarea>
            </div>
            <button type="button" wire:click="saveMaintenance" wire:loading.attr="disabled" class="set-btn w-full" style="background:linear-gradient(135deg,#A02622,#C0392B);">
                <span wire:loading.remove wire:target="saveMaintenance">Save maintenance settings</span>
                <span wire:loading wire:target="saveMaintenance">Saving settings...</span>
            </button>
        </div>
    </div>

</div>
