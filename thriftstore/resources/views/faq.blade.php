<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Frequently Asked Questions') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 mb-4">
                    Answers to common questions about using Ukay Hub.
                </p>

                <div class="space-y-4 text-sm text-gray-800">
                    <div class="border border-gray-200 rounded-lg p-3">
                        <h2 class="font-semibold text-gray-900">
                            How do I buy an item?
                        </h2>
                        <p class="mt-1 text-gray-600">
                            Browse the catalog on the home page, add items to your cart, then go to the checkout page and confirm your
                            shipping address. Orders are placed per seller and paid via Cash on Delivery (COD).
                        </p>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3">
                        <h2 class="font-semibold text-gray-900">
                            Do I need an account to place an order?
                        </h2>
                        <p class="mt-1 text-gray-600">
                            Yes. You can create a free customer account to track your orders, manage wishlists, and message sellers.
                        </p>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3">
                        <h2 class="font-semibold text-gray-900">
                            How do I contact a seller about my order?
                        </h2>
                        <p class="mt-1 text-gray-600">
                            After your order is placed, go to <span class="font-medium">My Orders</span> and use the
                            <span class="font-medium">Return / issue</span> button or open the
                            <span class="font-medium">Messages</span> section to chat with the seller.
                        </p>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3">
                        <h2 class="font-semibold text-gray-900">
                            What should I do if an item is damaged or not as described?
                        </h2>
                        <p class="mt-1 text-gray-600">
                            Go to <span class="font-medium">My Orders</span>, find the delivered order, and click
                            <span class="font-medium">Return / issue</span> to send details and photos to the seller so they can assist you.
                        </p>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3">
                        <h2 class="font-semibold text-gray-900">
                            How do sellers get paid?
                        </h2>
                        <p class="mt-1 text-gray-600">
                            Sellers configure their GCash details in their store settings. Customers pay the seller directly via COD / GCash
                            using the payment details shown on the checkout page.
                        </p>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3">
                        <h2 class="font-semibold text-gray-900">
                            I have another question that is not listed here.
                        </h2>
                        <p class="mt-1 text-gray-600">
                            You can send us a message anytime through the
                            <a href="{{ route('contact') }}" class="text-indigo-600 hover:text-indigo-800 underline">Contact page</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

