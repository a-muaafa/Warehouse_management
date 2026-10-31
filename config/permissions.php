<?php

/*
|--------------------------------------------------------------------------
| Role → Permission Matrix
|--------------------------------------------------------------------------
|
| Every server-side authorization decision resolves to one of these
| permission strings (see App\Policies\* and AppServiceProvider). The UI
| may hide buttons using the same abilities, but hiding is never the
| security boundary — every request is re-checked by a policy or gate.
|
| super_admin bypasses this matrix via Gate::before().
|
| Warehouse-level isolation is applied *in addition* to these permissions:
| a user only operates on warehouses assigned to them (warehouse_user pivot).
|
*/

return [

    'roles' => [

        'super_admin' => ['*'],

        'warehouse_manager' => [
            'warehouses.view', 'warehouses.update',
            'items.view', 'items.manage',
            'stock.receive', 'stock.issue',
            'transactions.view',
            'transfers.view', 'transfers.create', 'transfers.approve', 'transfers.dispatch', 'transfers.receive', 'transfers.resolve',
            'audits.view', 'audits.create', 'audits.count', 'audits.approve',
            'batches.view', 'batches.quarantine',
            'master_data.view', 'master_data.manage',
            'users.manage',
            'reports.view', 'reports.export',
            'activity_logs.view',
            'labels.print',
        ],

        'inventory_manager' => [
            'warehouses.view',
            'items.view', 'items.manage',
            'stock.receive', 'stock.issue',
            'transactions.view',
            'transfers.view', 'transfers.create', 'transfers.dispatch', 'transfers.receive',
            'audits.view', 'audits.create', 'audits.count', 'audits.approve',
            'batches.view', 'batches.quarantine',
            'master_data.view', 'master_data.manage',
            'users.manage',
            'reports.view', 'reports.export',
            'labels.print',
        ],

        'storekeeper' => [
            'warehouses.view',
            'items.view',
            'stock.receive', 'stock.issue',
            'transactions.view',
            'transfers.view', 'transfers.dispatch', 'transfers.receive',
            'batches.view',
            'master_data.view',
            'labels.print',
        ],

        'stock_counter' => [
            'warehouses.view',
            'items.view',
            'audits.view', 'audits.count',
            'batches.view',
            'labels.print',
        ],

        'auditor' => [
            'warehouses.view',
            'items.view',
            'transactions.view',
            'transfers.view',
            'audits.view',
            'batches.view',
            'master_data.view',
            'reports.view', 'reports.export',
            'activity_logs.view',
        ],

        'viewer' => [
            'warehouses.view',
            'items.view',
            'transactions.view',
            'transfers.view',
            'audits.view',
            'batches.view',
            'master_data.view',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Roles a given role is allowed to create / edit / delete
    |--------------------------------------------------------------------------
    |
    | Prevents privilege escalation: a manager can never create or modify an
    | account whose role is equal to or higher than their own.
    |
    */

    'manageable_roles' => [
        'super_admin' => ['super_admin', 'warehouse_manager', 'inventory_manager', 'storekeeper', 'stock_counter', 'auditor', 'viewer'],
        'warehouse_manager' => ['inventory_manager', 'storekeeper', 'stock_counter', 'viewer'],
        'inventory_manager' => ['storekeeper', 'stock_counter', 'viewer'],
    ],

];
