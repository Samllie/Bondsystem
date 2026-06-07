<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\BondRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NotaryFeeService
{
    /**
     * Deduct the requester's branch notary fee and record a debit transaction.
     *
     * @throws InvalidArgumentException when the branch has no notary price configured.
     * @throws InvalidArgumentException when the user has insufficient balance.
     */
    public function charge(User $user, BondRequest $bondRequest): Transaction
    {
        return DB::transaction(function () use ($user, $bondRequest) {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->loadMissing('branch');

            $fee = $lockedUser->branch?->notary_price;

            if ($fee === null || (float) $fee <= 0) {
                throw new InvalidArgumentException('Notary price is not configured for your branch.');
            }

            $fee = (float) $fee;
            $balanceBefore = (float) $lockedUser->balance;

            if ($balanceBefore < $fee) {
                throw new InvalidArgumentException(
                    'Insufficient balance to cover the notary fee of PHP '.number_format($fee, 2).'.',
                );
            }

            $balanceAfter = $balanceBefore - $fee;

            $lockedUser->update([
                'balance' => number_format($balanceAfter, 2, '.', ''),
            ]);

            $reference = $bondRequest->bond_number
                ?? $bondRequest->car
                ?? "BR-{$bondRequest->id}";

            $branchName = $lockedUser->branch?->name ?? 'branch';

            return Transaction::create([
                'user_id' => $lockedUser->id,
                'type' => TransactionType::Debit->value,
                'amount' => $fee,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => $reference,
                'description' => "Notary fee — {$branchName} — Bond request {$reference}",
                'subject_type' => BondRequest::class,
                'subject_id' => $bondRequest->id,
            ]);
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
