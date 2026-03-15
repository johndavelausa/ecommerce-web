<?php

use App\Models\AccountDeletionRequest;
use App\Notifications\DeletionRequestApprovedNotification;
use App\Notifications\DeletionRequestDeniedNotification;
use Illuminate\Support\Facades\Notification;

new class extends \Livewire\Component
{
    public function pendingRequests()
    {
        return AccountDeletionRequest::query()
            ->with('user')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
    }

    public function approve(int $id): void
    {
        $request = AccountDeletionRequest::query()->where('status', 'pending')->findOrFail($id);
        $user = $request->user;
        $email = $user->email;

        $request->update([
            'status' => 'approved',
            'admin_id' => auth('admin')->id(),
            'processed_at' => now(),
        ]);

        $user->delete();

        Notification::route('mail', $email)->notify(new DeletionRequestApprovedNotification());
    }

    public function deny(int $id): void
    {
        $request = AccountDeletionRequest::query()->where('status', 'pending')->findOrFail($id);
        $user = $request->user;

        $request->update([
            'status' => 'denied',
            'admin_id' => auth('admin')->id(),
            'processed_at' => now(),
        ]);

        $user->notify(new DeletionRequestDeniedNotification());
    }
};
?>

<div class="space-y-4">
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Requested</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($this->pendingRequests() as $req)
                    <tr>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $req->created_at->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-2 text-sm">{{ $req->user->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm">{{ $req->user->email ?? '—' }}</td>
                        <td class="px-4 py-2 text-right text-sm space-x-2">
                            <button type="button" wire:click="approve({{ $req->id }})"
                                    wire:confirm="Approve this deletion? The user account will be permanently deleted."
                                    class="text-red-600 hover:text-red-800 font-medium">
                                Approve
                            </button>
                            <button type="button" wire:click="deny({{ $req->id }})"
                                    wire:confirm="Deny this deletion request? The user will be notified."
                                    class="text-gray-600 hover:text-gray-800 font-medium">
                                Deny
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-sm">No pending deletion requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
