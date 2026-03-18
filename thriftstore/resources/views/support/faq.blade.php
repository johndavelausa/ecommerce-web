<x-app-layout>
    <div class="max-w-2xl mx-auto py-12">
        <h1 class="text-2xl font-bold mb-6">Frequently Asked Questions</h1>
        <ul class="space-y-4">
            <li>
                <strong>How do I contact support?</strong><br>
                Use the <a href="{{ route('support.contact') }}" class="text-green-700 underline">Contact</a> page to reach us.
            </li>
            <li>
                <strong>Where can I find your privacy policy?</strong><br>
                See our <a href="{{ route('support.privacy') }}" class="text-green-700 underline">Privacy Policy</a> for details.
            </li>
            <li>
                <strong>What are your terms and conditions?</strong><br>
                Read our <a href="{{ route('support.terms') }}" class="text-green-700 underline">Terms & Conditions</a>.
            </li>
            <li>
                <strong>How do you use cookies?</strong><br>
                See our <a href="{{ route('support.cookies') }}" class="text-green-700 underline">Cookies</a> page for info.
            </li>
        </ul>
    </div>
</x-app-layout>
