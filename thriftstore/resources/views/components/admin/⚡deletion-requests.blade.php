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

<style>
    .del-table-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .del-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .del-table th { padding: 9px 16px; text-align: left; font-size: 0.6875rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; background: #F5FBF7; border-bottom: 1px solid #D4E8DA; }
    .del-table td { padding: 10px 16px; color: #424242; border-bottom: 1px solid #F0F7F2; }
    .del-table tr:last-child td { border-bottom: none; }
    .del-table tr:hover td { background: #F5FBF7; }
    .del-action-approve { font-size: 0.8125rem; font-weight: 600; color: #C0392B; background: none; border: none; cursor: pointer; padding: 0; }
    .del-action-approve:hover { text-decoration: underline; color: #A02622; }
    .del-action-deny { font-size: 0.8125rem; font-weight: 600; color: #757575; background: none; border: none; cursor: pointer; padding: 0; margin-left: 12px; }
    .del-action-deny:hover { text-decoration: underline; color: #424242; }
</style>

<div class="space-y-4">
    <div class="del-table-card">
        <table class="del-table">
            <thead>
                <tr>
                    <th>Requested</th>
                    <th>User</th>
                    <th>Email</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->pendingRequests() as $req)
                    <tr>
                        <td style="color:#9E9E9E;font-style:italic;font-size:0.8125rem;white-space:nowrap;">{{ $req->created_at->format('M j, Y H:i') }}</td>
                        <td style="color:#0F3D22;font-weight:600;">{{ $req->user->name ?? '—' }}</td>
                        <td style="color:#757575;font-style:italic;">{{ $req->user->email ?? '—' }}</td>
                        <td style="text-align:right;">
                            <button type="button" wire:click="approve({{ $req->id }})"
                                    wire:confirm="Approve this deletion? The user account will be permanently deleted."
                                    class="del-action-approve">
                                Approve
                            </button>
                            <button type="button" wire:click="deny({{ $req->id }})"
                                    wire:confirm="Deny this deletion request? The user will be notified."
                                    class="del-action-deny">
                                Deny
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;padding:32px 16px;color:#9E9E9E;font-style:italic;">No pending deletion requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
