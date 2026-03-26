<x-app-layout>

    <div class="seller-register-page py-3">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
            <div class="seller-register-frame"
                 x-data="{
                    step: 1,
                    totalSteps: 5,
                          isAdvancing: false,
                    stepError: '',
                          storageKey: 'seller-register-draft-v1',
                          persistedKeys: ['name', 'username', 'email', 'contact_number', 'address', 'store_name', 'store_description', 'gcash_number', 'reference_number'],
                    form: {
                        name: @js(old('name', '')),
                        username: @js(old('username', '')),
                        email: @js(old('email', '')),
                        contact_number: @js(old('contact_number', '')),
                        address: @js(old('address', '')),
                        password: '',
                        password_confirmation: '',
                        store_name: @js(old('store_name', '')),
                        store_description: @js(old('store_description', '')),
                        gcash_number: @js(old('gcash_number', '')),
                        reference_number: @js(old('reference_number', ''))
                    },
                    init() {
                        this.loadDraft();
                        this.normalizeReferenceNumber();
                        this.$watch('step', () => this.saveDraft());
                        this.persistedKeys.forEach((key) => {
                            this.$watch(`form.${key}`, () => this.saveDraft());
                        });
                    },
                    loadDraft() {
                        try {
                            const raw = localStorage.getItem(this.storageKey);
                            if (!raw) return;
                            const draft = JSON.parse(raw);

                            if (draft && draft.form && typeof draft.form === 'object') {
                                this.persistedKeys.forEach((key) => {
                                    if (Object.prototype.hasOwnProperty.call(draft.form, key)) {
                                        this.form[key] = draft.form[key] ?? '';
                                    }
                                });
                            }

                            const draftStep = Number(draft?.step ?? 1);
                            if (draftStep >= 1 && draftStep <= this.totalSteps) {
                                this.step = draftStep;
                            }
                        } catch (e) {
                            localStorage.removeItem(this.storageKey);
                        }
                    },
                    saveDraft() {
                        try {
                            const safeForm = {};
                            this.persistedKeys.forEach((key) => {
                                safeForm[key] = this.form[key] ?? '';
                            });
                            localStorage.setItem(this.storageKey, JSON.stringify({
                                step: this.step,
                                form: safeForm
                            }));
                        } catch (e) {
                            // Ignore storage quota or private mode errors.
                        }
                    },
                    clearDraft() {
                        localStorage.removeItem(this.storageKey);
                    },
                    trim(value) {
                        return (value ?? '').toString().trim();
                    },
                    digitsOnly(value) {
                        return (value ?? '').toString().replace(/\D+/g, '');
                    },
                    formatReferenceNumber(value) {
                        const digits = this.digitsOnly(value).slice(0, 13);
                        return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
                    },
                    formatReferenceNumberInput() {
                        this.form.reference_number = this.formatReferenceNumber(this.form.reference_number);
                    },
                    isEmail(value) {
                        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.trim(value));
                    },
                    validateCurrentStep() {
                        if (this.step === 1) {
                            if (!this.trim(this.form.name)) return 'Please enter your full name.';
                            if (!this.trim(this.form.username)) return 'Please enter your username.';
                            if (!this.trim(this.form.contact_number)) return 'Please enter your contact number.';
                            if (!this.trim(this.form.email)) return 'Please enter your email address.';
                            if (!this.isEmail(this.form.email)) return 'Please enter a valid email address.';
                            if (!this.trim(this.form.address)) return 'Please enter your delivery address.';
                        }

                        if (this.step === 2) {
                            if (!this.trim(this.form.password)) return 'Please create your password.';
                            if (this.trim(this.form.password).length < 8) return 'Password must be at least 8 characters.';
                            if (!this.trim(this.form.password_confirmation)) return 'Please confirm your password.';
                            if (this.form.password !== this.form.password_confirmation) return 'Password confirmation does not match.';
                        }

                        if (this.step === 3) {
                            if (!this.trim(this.form.store_name)) return 'Please enter your store name.';
                            if (!this.trim(this.form.gcash_number)) return 'Please enter your GCash number.';
                        }

                        if (this.step === 4) {
                            if (!this.trim(this.form.reference_number)) return 'Please enter your GCash reference number.';
                            if (this.digitsOnly(this.form.reference_number).length !== 13) {
                                return 'GCash reference number must be exactly 13 digits.';
                            }
                            const receiptInput = document.getElementById('payment_screenshot');
                            if (!receiptInput || !receiptInput.files || receiptInput.files.length === 0) {
                                return 'Please upload your payment screenshot.';
                            }
                        }

                        return '';
                    },
                    async emailAlreadyExists() {
                        const email = this.trim(this.form.email);
                        if (!this.isEmail(email)) {
                            return false;
                        }

                        try {
                            const res = await fetch(`{{ route('seller.register.check-email') }}?email=${encodeURIComponent(email)}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!res.ok) {
                                return false;
                            }

                            const data = await res.json();
                            return !!data.exists;
                        } catch (e) {
                            return false;
                        }
                    },
                    async storeNameAlreadyExists() {
                        const storeName = this.trim(this.form.store_name);
                        if (!storeName) {
                            return false;
                        }

                        try {
                            const res = await fetch(`{{ route('seller.register.check-store-name') }}?store_name=${encodeURIComponent(storeName)}`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!res.ok) {
                                return false;
                            }

                            const data = await res.json();
                            return !!data.exists;
                        } catch (e) {
                            return false;
                        }
                    },
                    goStep(target) {
                        if (target < 1 || target > this.totalSteps) return;
                        this.stepError = '';
                        this.step = target;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    },
                    async nextStep() {
                        if (this.isAdvancing) {
                            return;
                        }

                        this.isAdvancing = true;

                        const error = this.validateCurrentStep();
                        if (error) {
                            this.stepError = error;
                            this.isAdvancing = false;
                            return;
                        }

                        if (this.step === 1) {
                            const exists = await this.emailAlreadyExists();
                            if (exists) {
                                this.stepError = 'This email is already registered. Please use another email.';
                                this.isAdvancing = false;
                                return;
                            }
                        }

                        if (this.step === 3) {
                            const exists = await this.storeNameAlreadyExists();
                            if (exists) {
                                this.stepError = 'This store name already exists. Please choose another store name.';
                                this.isAdvancing = false;
                                return;
                            }
                        }

                        this.goStep(this.step + 1);

                        setTimeout(() => {
                            this.isAdvancing = false;
                        }, 250);
                    },
                    normalizeUsername() {
                        this.form.username = this.trim(this.form.username).replace(/^@+/, '');
                    },
                    normalizeReferenceNumber() {
                        this.form.reference_number = this.formatReferenceNumber(this.form.reference_number);
                    },
                    prevStep() { this.goStep(this.step - 1); },
                    progress() { return Math.round((this.step / this.totalSteps) * 100); }
                 }">
                <div class="seller-register-shell">
                    <aside class="seller-register-sidebar" aria-label="Registration steps">
                        <p class="seller-sidebar-title">Registration</p>
                        <p class="seller-sidebar-subtitle">Step <span x-text="step"></span> of 5</p>

                        <nav class="seller-sidebar-nav" :class="step === 5 ? 'is-review-stage' : ''">
                            <div class="seller-step-link" :class="step === 1 ? 'is-active' : (step > 1 ? 'is-complete' : 'is-future')">
                                <span class="seller-step-icon" aria-hidden="true">
                                    <svg x-show="step <= 1" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    <svg x-show="step > 1" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"></path></svg>
                                </span>
                                <span>Seller Information</span>
                            </div>

                            <div class="seller-step-link" :class="step === 2 ? 'is-active' : (step > 2 ? 'is-complete' : 'is-future')">
                                <span class="seller-step-icon" aria-hidden="true">
                                    <svg x-show="step <= 2" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 5 7v6c0 5 3.5 7.5 7 8 3.5-.5 7-3 7-8V7l-7-4Z"></path><path d="M9.5 12.5 11 14l3.5-3.5"></path></svg>
                                    <svg x-show="step > 2" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"></path></svg>
                                </span>
                                <span>Security</span>
                            </div>

                            <div class="seller-step-link" :class="step === 3 ? 'is-active' : (step > 3 ? 'is-complete' : 'is-future')">
                                <span class="seller-step-icon" aria-hidden="true">
                                    <svg x-show="step <= 3" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h18"></path><path d="M5 10V6h14v4"></path><path d="M6 10v8h12v-8"></path></svg>
                                    <svg x-show="step > 3" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"></path></svg>
                                </span>
                                <span>Shop Information</span>
                            </div>

                            <div class="seller-step-link" :class="step === 4 ? 'is-active' : (step > 4 ? 'is-complete' : 'is-future')">
                                <span class="seller-step-icon" aria-hidden="true">
                                    <svg x-show="step <= 4" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="12" rx="2"></rect><path d="M7 12h6"></path><path d="M17 10v4"></path></svg>
                                    <svg x-show="step > 4" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"></path></svg>
                                </span>
                                <span>Registration Fee</span>
                            </div>

                            <div class="seller-step-link" :class="step === 5 ? 'is-active' : 'is-future'">
                                <span class="seller-step-icon" aria-hidden="true">
                                    <svg x-show="step <= 5" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11.5 11 13.5l4-4"></path><rect x="4" y="4" width="16" height="16" rx="2"></rect></svg>
                                </span>
                                <span>Review</span>
                            </div>
                        </nav>

                    </aside>

                    <div class="seller-register-card">
                        <div class="seller-top-progress-wrap">
                            <div class="seller-top-progress" role="progressbar" :aria-valuenow="progress()" aria-valuemin="0" aria-valuemax="100">
                                <div class="seller-top-progress-value" :style="`width: ${progress()}%`"></div>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('seller.register.store') }}" enctype="multipart/form-data" class="seller-register-form" @submit="clearDraft()">
        @csrf

                        <section x-show="step === 1" x-cloak class="seller-step-section">
                            <div class="seller-step-head">
                                <h3 class="seller-step-title">Seller Information</h3>
                                <p class="seller-step-description">Please provide your personal details to get started with your shop on Ukay Hub.</p>
                            </div>

                            <div class="seller-grid seller-grid-2">
                                <div class="seller-field seller-field-full">
                                    <x-input-label for="name" :value="__('Full Name')" />
                                    <x-text-input id="name" x-model="form.name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" x-bind:required="step === 1" autofocus autocomplete="name" placeholder="Ukay Hub" />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div class="seller-field">
                                    <x-input-label for="username" :value="__('Username')" />
                                    <div class="seller-username-wrap mt-1">
                                        <span class="seller-username-at">@</span>
                                        <x-text-input id="username" x-model="form.username" @blur="normalizeUsername()" class="block w-full seller-username-input" type="text" name="username" :value="old('username')" x-bind:required="step === 1" autocomplete="username" placeholder="ukayhub" />
                                    </div>
                                    <x-input-error :messages="$errors->get('username')" class="mt-2" />
                                </div>

                                <div class="seller-field">
                                    <x-input-label for="contact_number" :value="__('Contact Number')" />
                                    <x-text-input id="contact_number" x-model="form.contact_number" class="block mt-1 w-full" type="text" name="contact_number" :value="old('contact_number')" x-bind:required="step === 1" autocomplete="tel" placeholder="0912 345 6789" />
                                    <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
                                </div>

                                <div class="seller-field seller-field-full">
                                    <x-input-label for="email" :value="__('Email Address')" />
                                    <x-text-input id="email" x-model="form.email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" x-bind:required="step === 1" autocomplete="email" placeholder="ukayhub@gmail.com" />
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>

                                <div class="seller-field seller-field-full">
                                    <x-input-label for="address" :value="__('Delivery Address')" />
                                    <textarea id="address" x-model="form.address" name="address" x-bind:required="step === 1" class="seller-textarea block mt-1 w-full" placeholder="Street Name, Barangay, City, Province, Zip Code">{{ old('address') }}</textarea>
                                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                                </div>
                            </div>

                            <p class="seller-step-error" x-show="stepError" x-text="stepError"></p>

                            <div class="seller-register-actions">
                                <a class="seller-cancel-link" @click="clearDraft()" href="{{ route('seller.login') }}">
                                    <svg class="seller-btn-icon seller-btn-icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                                    <span>{{ __('Cancel') }}</span>
                                </a>
                                <button type="button" class="seller-next-button" @click="nextStep()" :disabled="isAdvancing">
                                    <span>Next Step</span>
                                    <svg class="seller-btn-icon seller-btn-icon-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                                </button>
                            </div>
                        </section>

                        <section x-show="step === 2" x-cloak class="seller-step-section">
                            <div class="seller-step-head">
                                <h3 class="seller-step-title">Security</h3>
                                <p class="seller-step-description">Set up a strong password to protect your shop, customer data, and earnings.</p>
                            </div>

                            <div class="seller-grid seller-grid-2">
                                <div class="seller-field">
                                    <x-input-label for="password" :value="__('Create Password')" />
                                    <x-text-input id="password" x-model="form.password" class="block mt-1 w-full" type="password" name="password" x-bind:required="step === 2" autocomplete="new-password" placeholder="At least 8 characters" />
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                </div>

                                <div class="seller-field">
                                    <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                                    <x-text-input id="password_confirmation" x-model="form.password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" x-bind:required="step === 2" autocomplete="new-password" placeholder="Re-type your password" />
                                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                                </div>
                            </div>

                            <div class="seller-note-box">
                                <p class="seller-note-title">Security Tip</p>
                                <p>A strong password uses a mix of uppercase letters, lowercase letters, numbers, and symbols. Avoid using common words or your shop name.</p>
                            </div>

                            <p class="seller-step-error" x-show="stepError" x-text="stepError"></p>

                            <div class="seller-register-actions">
                                <button type="button" class="seller-cancel-link" @click="prevStep()">
                                    <svg class="seller-btn-icon seller-btn-icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                                    <span>Back</span>
                                </button>
                                <button type="button" class="seller-next-button" @click="nextStep()" :disabled="isAdvancing">
                                    <span>Next Step</span>
                                    <svg class="seller-btn-icon seller-btn-icon-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                                </button>
                            </div>
                        </section>

                        <section x-show="step === 3" x-cloak class="seller-step-section">
                            <div class="seller-step-head">
                                <h3 class="seller-step-title">Shop Information</h3>
                                <p class="seller-step-description">Tell us more about your thrift store to help buyers find you.</p>
                            </div>

                            <div class="seller-grid seller-grid-2">
                                <div class="seller-field seller-field-full">
                                    <x-input-label for="store_name" :value="__('Store Name (unique)')" />
                                    <x-text-input id="store_name" x-model="form.store_name" class="block mt-1 w-full" type="text" name="store_name" :value="old('store_name')" x-bind:required="step === 3" autocomplete="organization" placeholder="e.g. Vintage Finds PH" />
                                    <x-input-error :messages="$errors->get('store_name')" class="mt-2" />
                                </div>

                                <div class="seller-field seller-field-full">
                                    <x-input-label for="store_description" :value="__('Store Description')" />
                                    <textarea id="store_description" x-model="form.store_description" name="store_description" class="seller-textarea block mt-1 w-full" placeholder="Tell customers what makes your shop unique...">{{ old('store_description') }}</textarea>
                                    <x-input-error :messages="$errors->get('store_description')" class="mt-2" />
                                </div>

                                <div class="seller-field seller-field-full">
                                    <x-input-label for="gcash_number" :value="__('Your GCash Number')" />
                                    <x-text-input id="gcash_number" x-model="form.gcash_number" class="block mt-1 w-full" type="text" name="gcash_number" :value="old('gcash_number')" x-bind:required="step === 3" autocomplete="tel" placeholder="09XX XXX XXXX" />
                                    <x-input-error :messages="$errors->get('gcash_number')" class="mt-2" />
                                </div>
                            </div>

                            <div class="seller-note-box">
                                <p>Please ensure your GCash number is correct to avoid delays in receiving your earnings from the hub.</p>
                            </div>

                            <p class="seller-step-error" x-show="stepError" x-text="stepError"></p>

                            <div class="seller-register-actions">
                                <button type="button" class="seller-cancel-link" @click="prevStep()">
                                    <svg class="seller-btn-icon seller-btn-icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                                    <span>Back</span>
                                </button>
                                <button type="button" class="seller-next-button" @click="nextStep()" :disabled="isAdvancing">
                                    <span>Next Step</span>
                                    <svg class="seller-btn-icon seller-btn-icon-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                                </button>
                            </div>
                        </section>

                        <section x-show="step === 4" x-cloak class="seller-step-section">
                            <div class="seller-step-head">
                                <h3 class="seller-step-title">Registration Fee</h3>
                                <p class="seller-step-description">Pay the seller registration fee using admin GCash and provide payment verification details.</p>
                            </div>

                            <div class="seller-admin-payment-card">
                                <div class="seller-admin-details">
                                    <div class="seller-admin-title">Seller registration fee (₱200 via GCash)</div>
                                    <p>GCash Number: <span>{{ \App\Models\SystemSetting::get('gcash_number', 'Not set') }}</span></p>
                                </div>
                                <img class="seller-admin-qr" src="{{ \App\Models\SystemSetting::get_url('gcash_qr_path', asset('storage/defaults/gcash-qr.png')) }}" alt="GCash QR">
                            </div>

                            <div class="seller-grid seller-grid-2">
                                <div class="seller-field">
                                    <x-input-label for="reference_number" :value="__('GCash Reference Number (unique)')" />
                                    <x-text-input id="reference_number" x-model="form.reference_number" @input="formatReferenceNumberInput()" @blur="normalizeReferenceNumber()" class="block mt-1 w-full" type="text" name="reference_number" :value="old('reference_number')" x-bind:required="step === 4" inputmode="numeric" autocomplete="off" maxlength="16" placeholder="1234 1234 1234 1" />
                                    <x-input-error :messages="$errors->get('reference_number')" class="mt-2" />
                                </div>

                                <div class="seller-field seller-field-full">
                                    <x-input-label for="payment_screenshot" :value="__('Upload Payment Screenshot')" />
                                    <input id="payment_screenshot" name="payment_screenshot" type="file" accept="image/*" class="seller-file block mt-1 w-full text-sm" x-bind:required="step === 4" />
                                    <x-input-error :messages="$errors->get('payment_screenshot')" class="mt-2" />
                                </div>
                            </div>

                            <p class="seller-step-error" x-show="stepError" x-text="stepError"></p>

                            <div class="seller-register-actions">
                                <button type="button" class="seller-cancel-link" @click="prevStep()">
                                    <svg class="seller-btn-icon seller-btn-icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                                    <span>Back</span>
                                </button>
                                <button type="button" class="seller-next-button" @click="nextStep()" :disabled="isAdvancing">
                                    <span>Next Step</span>
                                    <svg class="seller-btn-icon seller-btn-icon-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                                </button>
                            </div>
                        </section>

                        <section x-show="step === 5" x-cloak class="seller-step-section">
                            <div class="seller-step-head">
                                <h3 class="seller-step-title">Review Registration</h3>
                                <p class="seller-step-description">Please verify all details before completing your registration. Once submitted, some information may require verification to change.</p>
                            </div>

                            <div class="seller-review-card">
                                <div class="seller-review-section">
                                    <div class="seller-review-section-head">
                                        <div class="seller-review-title-wrap">
                                            <span class="seller-review-title-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                            </span>
                                            <h4>Seller Details</h4>
                                        </div>
                                        <button type="button" class="seller-edit-icon" aria-label="Go to Seller Information" @click="goStep(1)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 21 3.8-.8L19 8l-3-3L3.8 17.2 3 21z"></path><path d="m14.5 6.5 3 3"></path></svg>
                                        </button>
                                    </div>
                                    <div class="seller-review-grid-2">
                                        <div>
                                            <p class="seller-review-label">Full Name</p>
                                            <p class="seller-review-value" x-text="form.name || '-'">-</p>
                                        </div>
                                        <div>
                                            <p class="seller-review-label">Email Address</p>
                                            <p class="seller-review-value" x-text="form.email || '-'">-</p>
                                        </div>
                                        <div>
                                            <p class="seller-review-label">Phone Number</p>
                                            <p class="seller-review-value" x-text="form.contact_number || '-'">-</p>
                                        </div>
                                        <div>
                                            <p class="seller-review-label">Business Address</p>
                                            <p class="seller-review-value" x-text="form.address || '-'">-</p>
                                        </div>
                                        <div>
                                            <p class="seller-review-label">Username</p>
                                            <p class="seller-review-value" x-text="form.username ? ('@' + form.username.replace(/^@/, '')) : '-'">-</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="seller-review-section">
                                    <div class="seller-review-section-head">
                                        <div class="seller-review-title-wrap">
                                            <span class="seller-review-title-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h18"></path><path d="M5 10V6h14v4"></path><path d="M6 10v8h12v-8"></path></svg>
                                            </span>
                                            <h4>Shop Information</h4>
                                        </div>
                                        <button type="button" class="seller-edit-icon" aria-label="Go to Shop Details" @click="goStep(3)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 21 3.8-.8L19 8l-3-3L3.8 17.2 3 21z"></path><path d="m14.5 6.5 3 3"></path></svg>
                                        </button>
                                    </div>
                                    <div class="seller-review-grid-2">
                                        <div>
                                            <p class="seller-review-label">Shop Name</p>
                                            <p class="seller-review-value" x-text="form.store_name || '-'">-</p>
                                        </div>
                                        <div>
                                            <p class="seller-review-label">Instagram Handle</p>
                                            <p class="seller-review-value" x-text="form.username ? ('@' + form.username.replace(/^@/, '')) : '-'">-</p>
                                        </div>
                                    </div>
                                    <div class="seller-review-grid-1">
                                        <p class="seller-review-label">Shop Bio</p>
                                        <p class="seller-review-value" x-text="form.store_description || '-'">-</p>
                                    </div>
                                </div>

                                <div class="seller-review-section">
                                    <div class="seller-review-section-head">
                                        <div class="seller-review-title-wrap">
                                            <span class="seller-review-title-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="12" rx="2"></rect><path d="M7 12h6"></path><path d="M17 10v4"></path></svg>
                                            </span>
                                            <h4>Registration Summary</h4>
                                        </div>
                                        <button type="button" class="seller-edit-icon" aria-label="Go to Payment and Verification" @click="goStep(4)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 21 3.8-.8L19 8l-3-3L3.8 17.2 3 21z"></path><path d="m14.5 6.5 3 3"></path></svg>
                                        </button>
                                    </div>
                                    <div class="seller-review-grid-2">
                                        <div>
                                            <p class="seller-review-label">GCash Reference No.</p>
                                            <p class="seller-review-value" x-text="form.reference_number || '-'">-</p>
                                        </div>
                                        <div>
                                            <p class="seller-review-label">Amount Paid</p>
                                            <p class="seller-review-value">₱200.00</p>
                                        </div>
                                        <div>
                                            <p class="seller-review-label">Application Status</p>
                                            <p class="seller-review-value seller-review-enabled">Ready for Submission</p>
                                        </div>
                                        <div>
                                            <p class="seller-review-label">Payment Proof</p>
                                            <p class="seller-review-value">Attached in Step 4</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="seller-warning-box">
                                By clicking Complete Registration, you agree to the seller terms and privacy policy. Our team will review your application after payment verification.
                            </div>

                            <div class="seller-register-actions">
                                <button type="button" class="seller-cancel-link" @click="prevStep()">
                                    <svg class="seller-btn-icon seller-btn-icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                                    <span>Back to Step 4</span>
                                </button>
                                <x-primary-button type="submit" class="seller-next-button">
                                    {{ __('Complete Registration') }}
                                </x-primary-button>
                            </div>
                        </section>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
