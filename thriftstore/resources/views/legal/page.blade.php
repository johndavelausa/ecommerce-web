<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $title }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-6 prose prose-sm max-w-none">
                @if($content !== '')
                    {!! nl2br(e($content)) !!}
                @else
                    <p class="text-gray-500">No content has been set for this page yet.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
