<?php

namespace App\Enums;

enum RoleSlug: string
{
    case SuperAdmin = 'super-admin';
    case Requester = 'requester';
    case Encoder = 'encoder';
    case Approver = 'approver';
    case Notary = 'notary';
}
