<?php

namespace App\Support;

class NavigationPermissionsSeeder
{
    public static function permissions(): array
    {
        $groups = [
            'Quedan & Molasses' => [
                ['All Quedan & Molasses', 'access-all-quedan-molasses'],
                ['Registry List', 'view-quedan-molasses-registry'],
                ['Buy Quedan', 'buy-quedan'],
                ['Buy Molasses', 'buy-molasses'],
            ],
            'Weekly Uploads' => [
                ['All Weekly Uploads', 'upload-all'],
            ],
            'Weekly Updates' => [
                ['All Weekly Updates', 'set-all-weekly-updates'],
            ],
            'Reports' => [
                ['Summary Reports', 'view-summary-report'],
                ['Trucking Allowance Report', 'view-trucking-allowance-report'],
                ['Production Certification', 'view-certification-report'],
                ['TLS Reports', 'view-tls-report'],
                ['Proceeds Sheet', 'view-proceeds-sheet-report'],
                ['Hauler TLS Signature', 'view-hauler-signature-report'],
                ['Planter Accnt. Applied', 'view-planter-accounts-report'],
                ['Proceeds & Deductions', 'view-proceeds-deductions-report'],
            ],
            'Loans' => [
                ['All Loans Menu', 'access-all-loans'],
                ['Loan Reports', 'view-loan-reports'],
            ],
            'Cash Advances' => [
                ['All Cash Advances Menu', 'access-all-cash-advances'],
            ],
            'Hauler TLS LIQ' => [
                ['All Hauler TLS LIQ', 'access-all-hauler-tls-liq'],
            ],
            'Fuel & Others Deduction' => [
                ['All Fuel & Others Deduction', 'access-all-planter-deductions'],
            ],
        ];

        $permissions = [];
        foreach ($groups as $group => $items) {
            foreach ($items as [$name, $slug]) {
                $permissions[] = [
                    'name' => $name,
                    'slug' => $slug,
                    'description' => "Access to {$name} in {$group}",
                ];
            }
        }

        return $permissions;
    }
}
