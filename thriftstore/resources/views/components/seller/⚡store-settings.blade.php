<?php

use App\Models\Seller;
use App\Notifications\VerifyNewEmailNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

new class extends Component
{
    use \Livewire\WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $contact_number = '';
    public string $address = '';

    public string $store_name = '';
    public string $store_description = '';
    public string $gcash_number = '';

    public $store_banner = null;

    /** B2 v1.4 — Business hours (displayed on public store profile) */
    public string $business_hours = '';

    public bool $is_open = true;

    /** A2 v1.3 — Delivery settings */
    public string $delivery_option = 'flat_rate'; // flat_rate | per_product
    public string $delivery_fee_amount = ''; // used when flat_rate

    public bool $saved = false;
    public bool $emailVerificationSent = false;
    public ?string $pending_email = null;

    public $avatar = null;

    public function mount(): void
    {
        $user = Auth::guard('seller')->user();
        $seller = $user?->seller;

        $this->name = (string) ($user?->name ?? '');
        $this->email = (string) ($user?->email ?? '');
        $this->pending_email = $user && $user->pending_email ? (string) $user->pending_email : null;
        $this->contact_number = (string) ($user?->contact_number ?? '');
        $this->address = (string) ($user?->address ?? '');

        $this->store_name = (string) ($seller?->store_name ?? '');
        $this->store_description = (string) ($seller?->store_description ?? '');
        $this->gcash_number = (string) ($seller?->gcash_number ?? '');
        $this->business_hours = (string) ($seller?->business_hours ?? '');
        $this->is_open = (bool) ($seller?->is_open ?? true);
        $this->delivery_option = (string) ($seller?->delivery_option ?? 'flat_rate');
        if ($this->delivery_option === 'free') {
            $this->delivery_option = 'flat_rate';
        }
        $this->delivery_fee_amount = $seller?->delivery_fee !== null ? (string) $seller->delivery_fee : '';

        $this->avatar = null;
        $this->store_banner = null;
    }

    public function save(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::guard('seller')->user();
        /** @var Seller|null $seller */
        $seller = $user?->seller;

        if (! $user || ! $seller) {
            abort(403);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'delivery_option' => ['required', 'in:flat_rate,per_product'],
            'delivery_fee_amount' => $this->delivery_option === 'flat_rate'
                ? ['required', 'numeric', 'min:0']
                : ['nullable', 'numeric', 'min:0'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'contact_number' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
            'store_name' => ['required', 'string', 'max:255', 'unique:sellers,store_name,'.$seller->id],
            'store_description' => ['nullable', 'string', 'max:5000'],
            'business_hours' => ['nullable', 'string', 'max:1000'],
            'gcash_number' => ['nullable', 'digits:11'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'store_banner' => ['nullable', 'image', 'max:5120'],
        ];
        $this->validate($rules);


        $userData = [
            'name' => $this->name,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
        ];
        if ($this->avatar) {
            $userData['avatar'] = $this->avatar->store('avatars', 'public');
        }
        $user->update($userData);

        // B1 - v1.3: Seller email change requires verification of new email
        $newEmail = strtolower(trim($this->email));
        if ($newEmail !== strtolower((string) $user->email)) {
            $user->pending_email = $newEmail;
            $user->save();
            Notification::route('mail', $newEmail)->notify(new VerifyNewEmailNotification($user->id, $newEmail));
            $this->emailVerificationSent = true;
            $this->pending_email = $newEmail;
        } else {
            $this->emailVerificationSent = false;
        }

        $sellerUpdateData = [
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'gcash_number' => $this->gcash_number,
            'business_hours' => $this->business_hours !== '' ? trim($this->business_hours) : null,
            'is_open' => $this->is_open,
            'delivery_option' => $this->delivery_option,
            'delivery_fee' => $this->delivery_option === 'flat_rate' && $this->delivery_fee_amount !== ''
                ? (float) $this->delivery_fee_amount
                : null,
        ];

        if ($this->store_banner) {
            $sellerUpdateData['banner_path'] = $this->store_banner->store('banners', 'public');
        }

        $seller->update($sellerUpdateData);

        $this->saved = true;
    }
};
?>

@push('styles')
<style>
    /* ── Store Settings — Brand Palette ───────────────────────── */
    .sst-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #D4E8DA;
        box-shadow: 0 2px 12px rgba(15,61,34,0.07);
        overflow: hidden;
        margin-bottom: 24px;
    }
    .sst-card-header {
        background: linear-gradient(135deg, #0F3D22 0%, #1a5c35 100%);
        padding: 18px 24px;
        border-bottom: 2px solid #F9C74F;
    }
    .sst-card-header h3 {
        font-size: 1.0625rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }
    .sst-card-body {
        padding: 24px;
    }
    .sst-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #1B7A37;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .sst-hint {
        font-size: 0.75rem;
        color: #9E9E9E;
        margin-top: 4px;
    }
    .sst-input {
        width: 100%;
        padding: 10px 14px;
        border: 1.5px solid #D4E8DA;
        border-radius: 10px;
        font-size: 0.875rem;
        color: #212121;
        background: #fff;
        transition: all 0.15s ease;
        outline: none;
    }
    .sst-input:focus {
        border-color: #2D9F4E;
        box-shadow: 0 0 0 3px rgba(45,159,78,0.1);
    }
    .sst-input.max-xs {
        max-width: 280px;
    }
    .sst-error {
        font-size: 0.75rem;
        color: #E53935;
        margin-top: 4px;
    }
    .sst-alert-success {
        display: flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
        border: 1px solid #A5D6A7;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 0.875rem;
        color: #1B7A37;
        font-weight: 600;
        margin-bottom: 16px;
    }
    .sst-alert-info {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);
        border: 1px solid #90CAF9;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 0.875rem;
        color: #1565C0;
        margin-bottom: 16px;
    }
    .sst-alert-warn {
        background: #FFF9E3;
        border: 1px solid #F9C74F;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.8125rem;
        color: #B45309;
        margin-top: 4px;
    }
    .sst-avatar-wrap {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: #F5FBF7;
        border: 1.5px dashed #A8D5B5;
        border-radius: 12px;
    }
    .sst-avatar-placeholder {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0F3D22 0%, #2D9F4E 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .sst-avatar-placeholder svg {
        color: rgba(255,255,255,0.7);
    }
    .sst-avatar-img {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #2D9F4E;
        flex-shrink: 0;
    }
    .sst-file-hint {
        font-size: 0.75rem;
        color: #9E9E9E;
        margin-top: 4px;
    }
    .sst-uploading {
        font-size: 0.75rem;
        color: #2D9F4E;
        font-weight: 600;
        margin-top: 4px;
    }
    .sst-btn-save {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        background: linear-gradient(135deg, #2D9F4E 0%, #1B7A37 100%);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 2px 8px rgba(45,159,78,0.25);
    }
    .sst-btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45,159,78,0.35);
    }
    .sst-btn-save:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    .sst-verified-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);
        border: 1px solid #90CAF9;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 700;
        color: #1565C0;
    }
    .sst-divider {
        border: none;
        border-top: 1px solid #D4E8DA;
        margin: 20px 0;
    }
    .sst-section-title {
        font-size: 0.8125rem;
        font-weight: 700;
        color: #0F3D22;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 12px;
    }
    .sst-radio-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .sst-radio-label {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: #F5FBF7;
        border: 1.5px solid #D4E8DA;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.15s;
        font-size: 0.875rem;
        color: #424242;
    }
    .sst-radio-label.is-selected {
        border-color: #2D9F4E;
        background: #EAF7EE;
        color: #1B7A37;
        font-weight: 600;
    }
    .sst-radio-label input[type="radio"] {
        accent-color: #2D9F4E;
        width: 16px;
        height: 16px;
    }
    .sst-toggle-wrap {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        background: #F5FBF7;
        border: 1.5px solid #D4E8DA;
        border-radius: 12px;
    }
    .sst-toggle-wrap input[type="checkbox"] {
        accent-color: #2D9F4E;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .sst-toggle-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #212121;
    }
    .sst-toggle-status {
        font-size: 0.75rem;
        font-weight: 600;
    }
    .sst-toggle-status.open  { color: #2D9F4E; }
    .sst-toggle-status.closed { color: #9E9E9E; }
    .sst-form-row {
        margin-bottom: 18px;
    }
    .sst-form-row:last-child {
        margin-bottom: 0;
    }
</style>
@endpush

<div style="max-width:1200px;">

    {{-- Page header with Save button --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <div>
            <h2 style="font-size:1.25rem;font-weight:700;color:#0F3D22;margin:0;">Store &amp; Profile Settings</h2>
            <p style="font-size:0.8125rem;color:#9E9E9E;margin:4px 0 0;">Manage your account details and store information.</p>
        </div>
        <button type="button" wire:click="save" wire:loading.attr="disabled" class="sst-btn-save">
            <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Save Changes
        </button>
    </div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

    {{-- Profile Card --}}
    <div class="sst-card" style="margin-bottom:0;">
        <div class="sst-card-header">
            <h3>Profile</h3>
        </div>
        <div class="sst-card-body">

            @if($saved)
                <div class="sst-alert-success">
                    <svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Changes saved successfully.
                </div>
            @endif

            @if($emailVerificationSent)
                <div class="sst-alert-info">
                    <svg style="width:18px;height:18px;flex-shrink:0;margin-top:1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>A verification link has been sent to <strong>{{ $email }}</strong>. Click the link to update your login address. Until then, continue using your current email to sign in.</span>
                </div>
            @endif

            {{-- Profile Photo --}}
            <div class="sst-form-row">
                <label class="sst-label">Profile Photo</label>
                <div class="sst-avatar-wrap">
                    @php($user = Auth::guard('seller')->user())
                    @if($user?->avatar)
                        <img src="{{ $user->avatar_url }}" class="sst-avatar-img" alt="Profile photo">
                    @else
                        <div class="sst-avatar-placeholder">
                            <svg style="width:28px;height:28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </div>
                    @endif
                    <div style="flex:1;">
                        <input type="file" wire:model="avatar" accept="image/*" style="font-size:0.875rem;color:#424242;">
                        <div wire:loading wire:target="avatar" class="sst-uploading">Uploading…</div>
                        @error('avatar') <div class="sst-error">{{ $message }}</div> @enderror
                        <p class="sst-file-hint">Max 2 MB · JPG, PNG, GIF</p>
                    </div>
                </div>
            </div>

            {{-- Name --}}
            <div class="sst-form-row">
                <label class="sst-label">Name</label>
                <input type="text" wire:model.defer="name" class="sst-input">
                @error('name') <div class="sst-error">{{ $message }}</div> @enderror
            </div>

            {{-- Email --}}
            <div class="sst-form-row">
                <label class="sst-label">Email (login)</label>
                <input type="email" wire:model.defer="email" class="sst-input" placeholder="your@email.com">
                <p class="sst-hint">Changing your email requires verifying the new address. We'll send a link to the new email.</p>
                @error('email') <div class="sst-error">{{ $message }}</div> @enderror
                @if($pending_email)
                    <div class="sst-alert-warn" style="margin-top:8px;">
                        Pending email change: <strong>{{ $pending_email }}</strong>. Check that inbox for the verification link.
                    </div>
                @endif
            </div>

            {{-- Contact Number --}}
            <div class="sst-form-row">
                <label class="sst-label">Contact Number</label>
                <input type="text" wire:model.defer="contact_number" class="sst-input">
                @error('contact_number') <div class="sst-error">{{ $message }}</div> @enderror
            </div>

            {{-- Address --}}
            <div class="sst-form-row">
                <label class="sst-label">Delivery Address</label>
                <textarea wire:model.defer="address" rows="3" class="sst-input" style="resize:vertical;"></textarea>
                @error('address') <div class="sst-error">{{ $message }}</div> @enderror
            </div>

        </div>
    </div>

    {{-- Store Details Card --}}
    <div class="sst-card" style="margin-bottom:0;">
        <div class="sst-card-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <h3>Store Details</h3>
                @if(Auth::guard('seller')->user()?->seller?->is_verified ?? false)
                    <span class="sst-verified-badge">
                        <svg style="width:12px;height:12px;" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Verified Seller
                    </span>
                @endif
            </div>
        </div>
        <div class="sst-card-body">

            {{-- Store Name --}}
            <div class="sst-form-row">
                <label class="sst-label">Store Name</label>
                <input type="text" wire:model.defer="store_name" class="sst-input">
                @error('store_name') <div class="sst-error">{{ $message }}</div> @enderror
            </div>

            {{-- Store Description --}}
            <div class="sst-form-row">
                <label class="sst-label">Store Description</label>
                <textarea wire:model.defer="store_description" rows="3" class="sst-input" style="resize:vertical;"></textarea>
                @error('store_description') <div class="sst-error">{{ $message }}</div> @enderror
            </div>

            {{-- Business Hours --}}
            <div class="sst-form-row">
                <label class="sst-label">Business Hours</label>
                <textarea wire:model.defer="business_hours" rows="2" class="sst-input" style="resize:vertical;" placeholder="e.g. Mon–Sat 8:00 AM – 5:00 PM, Closed Sunday"></textarea>
                <p class="sst-hint">Shown on your public store page. Optional.</p>
                @error('business_hours') <div class="sst-error">{{ $message }}</div> @enderror
            </div>


            {{-- Store Banner --}}
            <div class="sst-form-row">
                <label class="sst-label">Store Banner Image</label>
                @php($seller = Auth::guard('seller')->user()?->seller)
                @if($seller?->banner_path)
                    <img src="{{ $seller->banner_url }}" alt="Current Banner"
                         class="mb-2 w-full max-h-32 object-cover rounded-xl border border-[#D4E8DA]">
                @endif
                <input type="file" wire:model="store_banner" accept="image/*" style="font-size:0.875rem;color:#424242;">
                <div wire:loading wire:target="store_banner" class="sst-uploading">Uploading…</div>
                @if($store_banner)
                    <img src="{{ $store_banner->temporaryUrl() }}" alt="Banner Preview"
                         class="mt-2 w-full max-h-32 object-cover rounded-xl border border-[#D4E8DA]">
                @endif
                <p class="sst-hint">Max 5 MB · JPG, PNG. Shown on your public store page.</p>
                @error('store_banner') <div class="sst-error">{{ $message }}</div> @enderror
            </div>

            {{-- GCash --}}
            <div class="sst-form-row">
                <label class="sst-label">GCash Number</label>
                <input type="text" wire:model.defer="gcash_number" class="sst-input sst-input--xs" style="max-width:280px;" maxlength="11" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,11)" placeholder="09XXXXXXXXX">
                <p class="sst-hint">Used for customer payments.</p>
                @error('gcash_number') <div class="sst-error">{{ $message }}</div> @enderror
            </div>


            <hr class="sst-divider">

            {{-- Delivery Settings --}}
            <div class="sst-form-row">
                <div class="sst-section-title">Delivery Settings</div>
                <div class="sst-radio-group">
                    <label wire:key="delivery-opt-flat" 
                           wire:click="$set('delivery_option', 'flat_rate')"
                           @class(['sst-radio-label', 'is-selected' => $delivery_option === 'flat_rate'])>
                        <input type="radio" 
                               wire:model.live="delivery_option" 
                               name="seller_delivery_option_group" 
                               value="flat_rate">
                        <div>
                            <div style="font-weight:600;">Flat rate per order</div>
                            <div style="font-size:0.75rem;color:#9E9E9E;">One fixed fee for the entire order</div>
                        </div>
                    </label>
                    <label wire:key="delivery-opt-product" 
                           wire:click="$set('delivery_option', 'per_product')"
                           @class(['sst-radio-label', 'is-selected' => $delivery_option === 'per_product'])>
                        <input type="radio" 
                               wire:model.live="delivery_option" 
                               name="seller_delivery_option_group" 
                               value="per_product">
                        <div>
                            <div style="font-weight:600;">Per product</div>
                            <div style="font-size:0.75rem;color:#9E9E9E;">Set a delivery fee on each product individually</div>
                        </div>
                    </label>
                </div>

                @if($delivery_option === 'flat_rate')
                    <div style="margin-top:12px;">
                        <label class="sst-label">Flat Rate Amount (₱)</label>
                        <input type="number" step="0.01" min="0" wire:model.defer="delivery_fee_amount"
                               class="sst-input" style="max-width:200px;" placeholder="0.00">
                        @error('delivery_fee_amount') <div class="sst-error">{{ $message }}</div> @enderror
                    </div>
                @endif

                @if($delivery_option === 'per_product')
                    <div class="sst-alert-warn" style="margin-top:10px;">
                        Set the delivery fee on each product in your Products page.
                    </div>
                @endif

                @error('delivery_option') <div class="sst-error" style="margin-top:6px;">{{ $message }}</div> @enderror
            </div>

            <hr class="sst-divider">

            {{-- Store Open Toggle --}}
            <div class="sst-form-row">
                <div class="sst-toggle-wrap">
                    <input type="checkbox" wire:model.defer="is_open" id="store_open_toggle">
                    <label for="store_open_toggle" style="cursor:pointer;">
                        <div class="sst-toggle-label">Store is Open</div>
                        <div class="sst-toggle-status {{ $is_open ? 'open' : 'closed' }}">
                            {{ $is_open ? 'Customers can see and order your products.' : 'Store closed — products hidden but not deleted.' }}
                        </div>
                    </label>
                </div>
            </div>

        </div>
    </div>

</div>{{-- end grid --}}
</div>{{-- end max-width wrapper --}}

