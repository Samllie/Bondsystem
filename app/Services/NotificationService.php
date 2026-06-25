<?php

namespace App\Services;

use App\Enums\RoleSlug;
use App\Models\BondRequest;
use App\Models\Deposit;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class NotificationService
{
    public function bondRequestSubmitted(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('creator');

        $this->notifyUsersWithPermission(
            'bond-requests.approve',
            AppNotification::make(
                type: 'bond_request.submitted',
                title: 'New bond request',
                message: "Bond request {$bondRequest->bond_number} was submitted by {$bondRequest->creator->name}.",
                url: route('bond-requests.show', $bondRequest),
                subjectType: BondRequest::class,
                subjectId: $bondRequest->id,
            ),
            except: $bondRequest->creator,
        );
    }

    public function bondRequestApproved(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('creator');

        $this->notifyUser(
            $bondRequest->creator,
            AppNotification::make(
                type: 'bond_request.approved',
                title: 'Bond request approved',
                message: "Your bond request {$bondRequest->bond_number} has been approved.",
                url: route('bond-requests.show', $bondRequest),
                subjectType: BondRequest::class,
                subjectId: $bondRequest->id,
            ),
        );
    }

    public function bondRequestPendingForChanges(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('creator');

        $this->notifyUser(
            $bondRequest->creator,
            AppNotification::make(
                type: 'bond_request.pending_for_changes',
                title: 'Bond request pending for changes',
                message: "Your bond request {$bondRequest->bond_number} needs some changes. Please review the remarks.",
                url: route('bond-requests.show', $bondRequest),
                subjectType: BondRequest::class,
                subjectId: $bondRequest->id,
            ),
        );
    }

    public function bondRequestResubmitted(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('creator');

        $this->notifyUsersWithPermission(
            'bond-requests.approve',
            AppNotification::make(
                type: 'bond_request.resubmitted',
                title: 'Bond request resubmitted',
                message: "Bond request {$bondRequest->bond_number} was resubmitted by {$bondRequest->creator->name}.",
                url: route('bond-requests.show', $bondRequest),
                subjectType: BondRequest::class,
                subjectId: $bondRequest->id,
            ),
            except: $bondRequest->creator,
        );
    }

    public function bondRequestRejected(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('creator');

        $this->notifyUser(
            $bondRequest->creator,
            AppNotification::make(
                type: 'bond_request.rejected',
                title: 'Bond request rejected',
                message: "Your bond request {$bondRequest->bond_number} has been rejected.",
                url: route('bond-requests.show', $bondRequest),
                subjectType: BondRequest::class,
                subjectId: $bondRequest->id,
            ),
        );
    }

    public function bondRequestNotarized(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('creator');

        $this->notifyUser(
            $bondRequest->creator,
            AppNotification::make(
                type: 'bond_request.notarized',
                title: 'Bond request notarized',
                message: "Your bond request {$bondRequest->bond_number} has been notarized.",
                url: route('bond-requests.show', $bondRequest),
                subjectType: BondRequest::class,
                subjectId: $bondRequest->id,
            ),
        );
    }

    public function bondRequestReturned(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('creator');

        $this->notifyUser(
            $bondRequest->creator,
            AppNotification::make(
                type: 'bond_request.returned',
                title: 'Funds returned',
                message: "The notary fee for bond request {$bondRequest->bond_number} has been returned.",
                url: route('bond-requests.show', $bondRequest),
                subjectType: BondRequest::class,
                subjectId: $bondRequest->id,
            ),
        );
    }

    public function certificateGenerated(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('creator');

        $this->notifyUser(
            $bondRequest->creator,
            AppNotification::make(
                type: 'certificate.generated',
                title: 'Confirmation ready',
                message: "The confirmation for bond request {$bondRequest->bond_number} is ready to view.",
                url: route('bond-requests.show', $bondRequest),
                subjectType: BondRequest::class,
                subjectId: $bondRequest->id,
            ),
        );
    }

    public function depositSubmitted(Deposit $deposit): void
    {
        $deposit->loadMissing('user');

        $this->notifyUsersWithPermission(
            'deposits.approve',
            AppNotification::make(
                type: 'deposit.submitted',
                title: 'New deposit request',
                message: "{$deposit->user->name} submitted a deposit of ₱".number_format((float) $deposit->amount, 2).'.',
                url: route('payments.deposits.show', $deposit),
                subjectType: Deposit::class,
                subjectId: $deposit->id,
            ),
            except: $deposit->user,
        );
    }

    public function depositApproved(Deposit $deposit): void
    {
        $deposit->loadMissing('user');

        $this->notifyUser(
            $deposit->user,
            AppNotification::make(
                type: 'deposit.approved',
                title: 'Deposit approved',
                message: 'Your deposit of ₱'.number_format((float) $deposit->amount, 2).' has been approved.',
                url: route('payments.deposits.show', $deposit),
                subjectType: Deposit::class,
                subjectId: $deposit->id,
            ),
        );
    }

    public function depositRejected(Deposit $deposit): void
    {
        $deposit->loadMissing('user');

        $this->notifyUser(
            $deposit->user,
            AppNotification::make(
                type: 'deposit.rejected',
                title: 'Deposit rejected',
                message: 'Your deposit of ₱'.number_format((float) $deposit->amount, 2).' has been rejected.',
                url: route('payments.deposits.show', $deposit),
                subjectType: Deposit::class,
                subjectId: $deposit->id,
            ),
        );
    }

    public function notaryUsedInCertificate(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('notary.user', 'creator');

        if ($bondRequest->notary?->user) {
            $this->notifyUser(
                $bondRequest->notary->user,
                AppNotification::make(
                    type: 'certificate.notary_used',
                    title: 'Your notary information was used',
                    message: "Your notary credentials were used in confirmation for bond request {$bondRequest->bond_number} by {$bondRequest->creator->name}.",
                    url: route('bond-requests.show', $bondRequest),
                    subjectType: BondRequest::class,
                    subjectId: $bondRequest->id,
                ),
            );
        }
    }

    public function signatureUsedInCertificate(BondRequest $bondRequest): void
    {
        $bondRequest->loadMissing('signatory.user', 'creator');

        if ($bondRequest->signatory?->user && $bondRequest->include_signatory_signature) {
            $this->notifyUser(
                $bondRequest->signatory->user,
                AppNotification::make(
                    type: 'certificate.signature_used',
                    title: 'Your signature was used in a confirmation',
                    message: "Your signature was used in confirmation for bond request {$bondRequest->bond_number} by {$bondRequest->creator->name}.",
                    url: route('bond-requests.show', $bondRequest),
                    subjectType: BondRequest::class,
                    subjectId: $bondRequest->id,
                ),
            );
        }
    }

    public function notifyUser(User $user, AppNotification $notification): void
    {
        if (! $user->is_active) {
            return;
        }

        $user->notify($notification);
    }

    public function notifyUsersWithPermission(string $permission, AppNotification $notification, ?User $except = null): void
    {
        $this->usersWithPermission($permission, $except)->each(
            fn (User $user) => $this->notifyUser($user, $notification),
        );
    }

    /**
     * @return Collection<int, User>|SupportCollection<int, User>
     */
    private function usersWithPermission(string $permission, ?User $except = null): Collection|SupportCollection
    {
        return User::query()
            ->where('is_active', true)
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except->id))
            ->where(function ($query) use ($permission): void {
                $query->whereHas('role.permissions', fn ($q) => $q->where('slug', $permission))
                    ->orWhereHas('role', fn ($q) => $q->where('slug', RoleSlug::SuperAdmin->value));
            })
            ->get();
    }
}
