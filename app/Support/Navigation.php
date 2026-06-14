<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    public static function items(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $items = [];

        if ($user->hasPermission('dashboard.view')) {
            $items[] = [
                'type' => 'link',
                'name' => 'Dashboard',
                'href' => route('dashboard'),
                'icon' => 'dashboard',
                'routes' => ['dashboard'],
            ];
        }

        if ($user->hasPermission('bond-requests.view')) {
            $items[] = [
                'type' => 'link',
                'name' => 'Bond Requests',
                'href' => route('bond-requests.index'),
                'icon' => 'document',
                'routes' => [
                    'bond-requests.index',
                    'bond-requests.create',
                    'bond-requests.show',
                    'bond-requests.edit',
                ],
            ];
        }

        if ($user->hasPermission('bond-requests.view')) {
            $items[] = [
                'type' => 'link',
                'name' => 'Certifications',
                'href' => route('certifications.index'),
                'icon' => 'certificate',
                'routes' => ['certifications.index'],
            ];
        }

        if ($user->hasPermission('obligees.view')) {
            $items[] = [
                'type' => 'link',
                'name' => 'Obligees',
                'href' => route('obligees.index'),
                'icon' => 'building',
                'routes' => ['obligees.index', 'obligees.create', 'obligees.show', 'obligees.edit'],
            ];
        }

        if ($user->hasPermission('principals.view')) {
            $items[] = [
                'type' => 'link',
                'name' => 'Principals',
                'href' => route('principals.index'),
                'icon' => 'users',
                'routes' => ['principals.index', 'principals.create', 'principals.show', 'principals.edit'],
            ];
        }

        if ($user->hasPermission('users.view')) {
            $items[] = [
                'type' => 'link',
                'name' => 'Users',
                'href' => route('users.index'),
                'icon' => 'users',
                'routes' => ['users.index', 'users.create'],
            ];
        }

        if ($user->hasPermission('audit-logs.view')) {
            $items[] = [
                'type' => 'link',
                'name' => 'Audit Logs',
                'href' => route('audit-logs.index'),
                'icon' => 'history',
                'routes' => ['audit-logs.index'],
            ];
        }

        $maintenanceChildren = [];

        if ($user->hasPermission('maintenance.view')) {
            $maintenanceChildren = [
                self::maintenanceChild('Bond Types', 'maintenance.bond-types'),
                self::maintenanceChild('Signatories', 'maintenance.signatories'),
                self::maintenanceChild('Notary', 'maintenance.notaries'),
                [
                    'name' => 'Certification',
                    'href' => route('maintenance.certifications.index'),
                    'routes' => ['maintenance.certifications.index'],
                ],
                self::maintenanceChild('CTC', 'maintenance.ctcs'),
                self::maintenanceChild('Branches', 'maintenance.branches'),
            ];
        }

        if ($user->hasPermission('certificate-templates.view')) {
            $maintenanceChildren[] = [
                'name' => 'Certificate Templates',
                'href' => route('certificate-templates.index'),
                'routes' => ['certificate-templates.index'],
            ];
        }

        if ($maintenanceChildren !== []) {
            $items[] = [
                'type' => 'group',
                'name' => 'Maintenance',
                'icon' => 'cog',
                'children' => $maintenanceChildren,
            ];
        }

        $paymentChildren = [];

        if ($user->hasPermission('transactions.view')) {
            $paymentChildren[] = [
                'name' => 'Transactions',
                'href' => route('payments.transactions.index'),
                'icon' => 'list',
                'routes' => ['payments.transactions.index'],
            ];
        }

        if ($user->hasPermission('payment-histories.view')) {
            $paymentChildren[] = [
                'name' => 'Payment Histories',
                'href' => route('payments.histories.index'),
                'icon' => 'history',
                'routes' => ['payments.histories.index'],
            ];
        }

        if ($user->hasPermission('deposits.view')) {
            $paymentChildren[] = [
                'name' => 'Deposit Submissions',
                'href' => route('payments.deposits.index'),
                'icon' => 'inbox',
                'routes' => ['payments.deposits.index', 'payments.deposits.show', 'payments.deposits.approve', 'payments.deposits.reject'],
            ];
        }

        if ($user->hasPermission('deposits.create')) {
            $depositHref = $user->hasPermission('deposits.view')
                ? route('payments.deposits.create')
                : route('payments.deposits.index');

            $paymentChildren[] = [
                'name' => 'Deposit',
                'href' => $depositHref,
                'icon' => 'money',
                'routes' => $user->hasPermission('deposits.view')
                    ? ['payments.deposits.create']
                    : ['payments.deposits.index', 'payments.deposits.create', 'payments.deposits.show'],
            ];
        }

        if (! empty($paymentChildren)) {
            $items[] = [
                'type' => 'section',
                'name' => 'Payments',
                'icon' => 'credit-card',
                'children' => $paymentChildren,
            ];
        }

        return $items;
    }

    /**
     * @return array{name: string, href: string, routes: array<int, string>}
     */
    private static function maintenanceChild(string $name, string $routePrefix): array
    {
        return [
            'name' => $name,
            'href' => route("{$routePrefix}.index"),
            'routes' => [
                "{$routePrefix}.index",
                "{$routePrefix}.create",
                "{$routePrefix}.edit",
            ],
        ];
    }
}
