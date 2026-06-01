<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DepositService
{
    public function approve(Deposit $deposit, User $approver): Transaction
    {
        return DB::transaction(function () use ($deposit, $approver) {
            $lockedDeposit = Deposit::query()
                ->whereKey($deposit->id)
                ->where('status', DepositStatus::Pending)
                ->lockForUpdate()
                ->firstOrFail();

            $user = User::query()
                ->whereKey($lockedDeposit->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (float) $user->balance;
            $amount = (float) $lockedDeposit->amount;
            $balanceAfter = $balanceBefore + $amount;

            $user->update([
                'balance' => number_format($balanceAfter, 2, '.', ''),
            ]);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::Credit->value,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => $lockedDeposit->reference_number,
                'description' => "Deposit approved — Ref: {$lockedDeposit->reference_number}",
                'subject_type' => Deposit::class,
                'subject_id' => $lockedDeposit->id,
            ]);

            $lockedDeposit->update([
                'status' => DepositStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $transaction->fresh();
        });
    }

    public function reject(Deposit $deposit, User $approver, ?string $remarks = null): void
    {
        if ($deposit->status !== DepositStatus::Pending) {
            throw new InvalidArgumentException('Only pending deposits can be rejected.');
        }

        $deposit->update([
            'status' => DepositStatus::Rejected,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'remarks' => $remarks,
        ]);
    }
}
