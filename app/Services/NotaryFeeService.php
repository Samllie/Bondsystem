<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\BondRequest;
use App\Models\Maintenance\Branch;
use App\Models\PaymentHistory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NotaryFeeService
{
    /**
     * Deduct the notary fee when a notary is selected and no fee has been charged yet.
     *
     * @throws InvalidArgumentException when the branch has no notary price configured.
     * @throws InvalidArgumentException when the branch fund has insufficient balance.
     */
    public function chargeWhenNotarySelected(User $user, BondRequest $bondRequest): ?Transaction
    {
        if ($bondRequest->notary_id === null || $this->hasBeenCharged($bondRequest)) {
            return null;
        }

        return $this->charge($user, $bondRequest);
    }

    public function hasBeenCharged(BondRequest $bondRequest): bool
    {
        return Transaction::query()
            ->where('subject_type', BondRequest::class)
            ->where('subject_id', $bondRequest->id)
            ->where('type', TransactionType::Debit->value)
            ->exists();
    }

    /**
     * Deduct the requester's branch notary fee and record a debit transaction.
     *
     * @throws InvalidArgumentException when the branch has no notary price configured.
     * @throws InvalidArgumentException when the branch fund has insufficient balance.
     */
    public function charge(User $user, BondRequest $bondRequest): Transaction
    {
        return DB::transaction(function () use ($user, $bondRequest) {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->loadMissing('branch');

            if ($lockedUser->branch_id === null) {
                throw new InvalidArgumentException('Requester must belong to a branch.');
            }

            $branch = Branch::query()
                ->whereKey($lockedUser->branch_id)
                ->lockForUpdate()
                ->firstOrFail();

            $fee = $branch->notary_price;

            if ($fee === null || (float) $fee <= 0) {
                throw new InvalidArgumentException('Notary price is not configured for your branch.');
            }

            $fee = (float) $fee;
            $balanceBefore = (float) $branch->balance;

            if ($balanceBefore < $fee) {
                throw new InvalidArgumentException(
                    'Insufficient branch fund to cover the notary fee of PHP '.number_format($fee, 2).'.',
                );
            }

            $balanceAfter = $balanceBefore - $fee;

            $branch->update([
                'balance' => number_format($balanceAfter, 2, '.', ''),
            ]);

            $reference = $bondRequest->bond_number
                ?? $bondRequest->car
                ?? "BR-{$bondRequest->id}";

            $transaction = Transaction::create([
                'user_id' => $lockedUser->id,
                'branch_id' => $branch->id,
                'type' => TransactionType::Debit->value,
                'amount' => $fee,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => $reference,
                'description' => "Notary fee — {$branch->name} — Bond request {$reference}",
                'subject_type' => BondRequest::class,
                'subject_id' => $bondRequest->id,
            ]);

            PaymentHistory::updateOrCreate(
                ['bond_request_id' => $bondRequest->id],
                [
                    'user_id' => $lockedUser->id,
                    'amount' => $fee,
                    'description' => "Document fee — {$reference}",
                    'paid_at' => now(),
                ],
            );

            return $transaction;
        });
    }

    /**
     * Resolve the notary fee for a user based on their branch.
     */
    public function feeFor(User $user): ?float
    {
        $user->loadMissing('branch');
        $fee = $user->branch?->notary_price;

        if ($fee === null || (float) $fee <= 0) {
            return null;
        }

        return (float) $fee;
    }
}
