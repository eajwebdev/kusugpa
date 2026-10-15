<?php

/*
|--------------------------------------------------------------------------
| Demo Mode
|--------------------------------------------------------------------------
|
| When DEMO=true in .env, the login page shows one-click sign-in buttons for
| the demo accounts below (created by Database\Seeders\UserSeeder).
| NEVER enable this in production.
|
*/

return [

    'enabled' => (bool) env('DEMO', false),

    // Password shared by all demo accounts.
    'password' => 'password',

    // 'permissions' => '*' means every permission, otherwise a list of slugs.
    // Administrator / super_admin always have full access regardless of this list.
    'accounts' => [
        [
            'username'    => 'admin',
            'fname'       => 'Demo',
            'mname'       => null,
            'lname'       => 'Administrator',
            'role'        => 'Administrator',
            'permissions' => '*',
        ],
        [
            'username'    => 'superadmin',
            'fname'       => 'Demo',
            'mname'       => null,
            'lname'       => 'Super Admin',
            'role'        => 'super_admin',
            'permissions' => '*',
        ],
        [
            'username'    => 'manager',
            'fname'       => 'Demo',
            'mname'       => null,
            'lname'       => 'Manager',
            'role'        => 'manager',
            'permissions' => [
                'manage-crop-weeks', 'manage-prices', 'set-quedan-price', 'set-molasses-price',
                'set-crop-year', 'set-week-number', 'view-reports', 'print-vouchers',
                'view-loans', 'approve-loans', 'view-cash-advances', 'approve-cash-advances',
                'view-audit-logs', 'manage-planter-profiles', 'view-planter-profiles',
                'view-consolidated-report',
            ],
        ],
        [
            'username'    => 'loanofficer',
            'fname'       => 'Demo',
            'mname'       => null,
            'lname'       => 'Loan Officer',
            'role'        => 'loan_officer',
            'permissions' => [
                'view-loans', 'create-loans', 'process-loan-payments',
                'view-cash-advances', 'create-cash-advances', 'process-cash-advance-payments',
                'view-planter-profiles', 'view-reports',
            ],
        ],
        [
            'username'    => 'user',
            'fname'       => 'Demo',
            'mname'       => null,
            'lname'       => 'User',
            'role'        => 'User',
            'permissions' => [
                'upload-summary', 'upload-trucking', 'upload-mudpress', 'upload-fci', 'upload-fuel',
                'upload-rentals', 'upload-underload', 'upload-transloading', 'upload-consolidated',
                'upload-quedan', 'upload-molasses', 'view-reports', 'view-consolidated-report',
                'view-planter-profiles',
            ],
        ],
        [
            'username'    => 'viewer',
            'fname'       => 'Demo',
            'mname'       => null,
            'lname'       => 'Viewer',
            'role'        => 'Viewer',
            'permissions' => [
                'view-reports', 'view-loans', 'view-cash-advances',
                'view-planter-profiles', 'view-consolidated-report',
            ],
        ],
    ],

];
