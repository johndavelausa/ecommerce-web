<x-app-layout>
    <div class="flex min-h-screen">
        @include('layouts.admin-sidebar')
        <main class="flex-1 p-6 bg-gray-50">
            <style>
                .set-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; padding: 20px 24px; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
                .set-card-title { font-size: 1rem; font-weight: 800; color: #0F3D22; margin-bottom: 4px; }
                .set-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; display: block; margin-bottom: 6px; }
                .set-input, .set-textarea { border-radius: 12px; border: 1.5px solid #D4E8DA; padding: 8px 12px; font-size: 0.8125rem; color: #424242; transition: all 0.15s; width: 100%; box-sizing: border-box; }
                .set-input:focus, .set-textarea:focus { border-color: #2D9F4E; box-shadow: 0 0 0 3px rgba(45,159,78,0.1); outline: none; }
                .set-btn { padding: 8px 18px; border-radius: 50px; font-size: 0.8125rem; font-weight: 700; background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #fff; border: none; cursor: pointer; transition: all 0.15s; display: inline-block; }
                .set-btn:hover { box-shadow: 0 4px 14px rgba(15,61,34,0.2); }
                .set-btn:disabled { opacity: 0.5; cursor: not-allowed; }
                .set-hint { font-size: 0.75rem; color: #9E9E9E; font-style: italic; margin-bottom: 12px; }
                .set-divider { border: none; border-top: 1px solid #D4E8DA; margin: 16px 0; }
                .set-checkbox { accent-color: #1B7A37; width: 16px; height: 16px; cursor: pointer; }
            </style>
            <div class="max-w-7xl mx-auto">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
                    {{-- Left column: system settings --}}
                    <div>
                        <livewire:admin.system-settings-form />
                    </div>
                    {{-- Right column: announcements --}}
                    <div class="space-y-6">
                        <livewire:admin.platform-announcements />
                        <livewire:admin.broadcast-announcement />
                    </div>
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
