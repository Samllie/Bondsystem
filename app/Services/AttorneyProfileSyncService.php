<?php

namespace App\Services;

use App\Models\Maintenance\Notary;
use App\Models\Maintenance\Signatory;
use App\Models\User;

class AttorneyProfileSyncService
{
    /**
     * Keep TIN in sync across linked signatory and notary records for the same user account.
     */
    public function syncTinForLinkedAccount(Signatory|Notary $record, string $tin): void
    {
        if ($record->user_id === null) {
            return;
        }

        $user = User::query()->find($record->user_id);

        if ($user === null) {
            return;
        }

        if ($record instanceof Signatory) {
            Notary::query()
                ->where('user_id', $user->id)
                ->update(['tin' => $tin]);

            return;
        }

        Signatory::query()
            ->where('user_id', $user->id)
            ->update(['tin' => $tin]);
    }

    /**
     * Keep name in sync across linked user, signatory, and notary records.
     */
    public function syncNameForLinkedAccount(Signatory|Notary $record, string $name): void
    {
        if ($record->user_id === null) {
            return;
        }

        $user = User::query()->find($record->user_id);

        if ($user === null) {
            return;
        }

        if ($user->name !== $name) {
            $user->update(['name' => $name]);
        }

        if ($record instanceof Signatory) {
            Notary::query()
                ->where('user_id', $user->id)
                ->update(['name' => $name]);

            return;
        }

        Signatory::query()
            ->where('user_id', $user->id)
            ->update(['name' => $name]);
    }
}
