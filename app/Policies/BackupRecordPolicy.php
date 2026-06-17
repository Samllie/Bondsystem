<?php

namespace App\Policies;

use App\Models\BackupRecord;
use App\Models\User;

class BackupRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('backups.manage');
    }

    public function view(User $user, BackupRecord $backupRecord): bool
    {
        return $user->hasPermission('backups.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('backups.manage');
    }

    public function delete(User $user, BackupRecord $backupRecord): bool
    {
        return $user->hasPermission('backups.manage');
    }

    public function download(User $user, BackupRecord $backupRecord): bool
    {
        return $user->hasPermission('backups.manage');
    }

    public function verify(User $user, BackupRecord $backupRecord): bool
    {
        return $user->hasPermission('backups.manage');
    }
}
