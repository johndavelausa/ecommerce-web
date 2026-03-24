<x-app-layout>
    @php
        $content = (string) \App\Models\SystemSetting::get('page_terms_of_service', '');
    @endphp
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Terms of Service</h1>
                @if($content !== '')
                    <div class="prose prose-sm max-w-none text-gray-700">{!! nl2br(e($content)) !!}</div>
                @else
                    <p class="text-gray-500">No content has been set for this page yet. Please check back later.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
