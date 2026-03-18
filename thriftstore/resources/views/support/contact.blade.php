<x-app-layout>
    <div class="max-w-2xl mx-auto py-12">
        <h1 class="text-2xl font-bold mb-6">Contact Support</h1>
        <p class="mb-4">If you have questions or need help, please fill out the form below and our team will get back to you.</p>
        <form method="POST" action="#" class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input type="text" id="name" name="name" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input type="email" id="email" name="email" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label for="message" class="block text-sm font-medium">Message</label>
                <textarea id="message" name="message" class="w-full border rounded px-3 py-2" rows="5" required></textarea>
            </div>
            <button type="submit" class="bg-green-700 text-white px-4 py-2 rounded">Send</button>
        </form>
    </div>
</x-app-layout>
