<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'Set Quedan Price',
                'slug' => 'set-quedan-price',
                'description' => 'Can set quedan prices for crop years and weeks',
            ],
            [
                'name' => 'Set Molasses Price',
                'slug' => 'set-molasses-price',
                'description' => 'Can set molasses prices for crop years and weeks',
            ],
            [
                'name' => 'Set Crop Year',
                'slug' => 'set-crop-year',
                'description' => 'Can create and manage crop years',
            ],
            [
                'name' => 'Set Week Number',
                'slug' => 'set-week-number',
                'description' => 'Can create and manage week numbers',
            ],
            [
                'name' => 'Manage Crop & Weeks',
                'slug' => 'manage-crop-weeks',
                'description' => 'Can manage crop years and week numbers',
            ],
            [
                'name' => 'Manage Prices',
                'slug' => 'manage-prices',
                'description' => 'Can manage quedan and molasses prices',
            ],
            [
                'name' => 'Upload Summary',
                'slug' => 'upload-summary',
                'description' => 'Can upload summary CSV files',
            ],
            [
                'name' => 'Upload Trucking Allowance',
                'slug' => 'upload-trucking',
                'description' => 'Can upload trucking allowance CSV files',
            ],
            [
                'name' => 'Upload Mudpress',
                'slug' => 'upload-mudpress',
                'description' => 'Can upload mudpress CSV files',
            ],
                        [
                'name' => 'Upload Fresh Cane Incentive',
                'slug' => 'upload-fci',
                'description' => 'Can upload fresh cane incentive CSV files',
            ],
            [
                'name' => 'Upload Fuel Allowance',
                'slug' => 'upload-fuel',
                'description' => 'Can upload fuel allowance CSV files',
            ],
            [
                'name' => 'Upload Rentals',
                'slug' => 'upload-rentals',
                'description' => 'Can upload rental allowance CSV files',
            ],
            [
                'name' => 'Upload Underload',
                'slug' => 'upload-underload',
                'description' => 'Can upload underload allowance CSV files',
            ],
            [
                'name' => 'Upload Transloading',
                'slug' => 'upload-transloading',
                'description' => 'Can upload transloading allowance CSV files',
            ],
            [
                'name' => 'All Reports',
                'slug' => 'view-reports',
                'description' => 'Can access every item in the Reports menu',
            ],
            [
                'name' => 'Print Vouchers',
                'slug' => 'print-vouchers',
                'description' => 'Can print cheque vouchers',
            ],
            [
                'name' => 'View Loans',
                'slug' => 'view-loans',
                'description' => 'Can view loan records and details',
            ],
            [
                'name' => 'Create Loans',
                'slug' => 'create-loans',
                'description' => 'Can create new loan applications',
            ],
            [
                'name' => 'Approve Loans',
                'slug' => 'approve-loans',
                'description' => 'Can approve or reject loan applications',
            ],
            [
                'name' => 'Process Loan Payments',
                'slug' => 'process-loan-payments',
                'description' => 'Can record loan payments and amortizations',
            ],
            [
                'name' => 'Manage Loan Settings',
                'slug' => 'manage-loan-settings',
                'description' => 'Can configure loan types, interest rates, and settings',
            ],
            [
                'name' => 'View Audit Logs',
                'slug' => 'view-audit-logs',
                'description' => 'Can view system audit logs and activity trail',
            ],
            [
                'name' => 'Manage Planter Profiles',
                'slug' => 'manage-planter-profiles',
                'description' => 'Can create, edit, and manage planter profiles',
            ],
            [
                'name' => 'View Planter Profiles',
                'slug' => 'view-planter-profiles',
                'description' => 'Can view planter profiles and details',
            ],
            [
                'name' => 'Upload Consolidated',
                'slug' => 'upload-consolidated',
                'description' => 'Can upload consolidated summary CSV files',
            ],
            [
                'name' => 'View Consolidated Report',
                'slug' => 'view-consolidated-report',
                'description' => 'Can view consolidated upload summary report',
            ],
            [
                'name' => 'Upload Quedan',
                'slug' => 'upload-quedan',
                'description' => 'Can upload quedan CSV files',
            ],
            [
                'name' => 'Upload Molasses Data',
                'slug' => 'upload-molasses',
                'description' => 'Can upload molasses CSV files',
            ],
            [
                'name' => 'Upload TLS Hauling',
                'slug' => 'upload-tls',
                'description' => 'Can upload weekly TLS hauling CSV files (creates Hauling Liquidity entries)',
            ],
            [
                'name' => 'View Cash Advances',
                'slug' => 'view-cash-advances',
                'description' => 'Can view cash advance records and details',
            ],
            [
                'name' => 'Create Cash Advances',
                'slug' => 'create-cash-advances',
                'description' => 'Can create new cash advance applications',
            ],
            [
                'name' => 'Approve Cash Advances',
                'slug' => 'approve-cash-advances',
                'description' => 'Can approve or reject cash advance applications',
            ],
            [
                'name' => 'Process Cash Advance Payments',
                'slug' => 'process-cash-advance-payments',
                'description' => 'Can record cash advance payments',
            ],
            [
                'name' => 'Manage Cash Advance Settings',
                'slug' => 'manage-cash-advance-settings',
                'description' => 'Can configure cash advance settings',
            ],
            [
                'name' => 'View Hauling Liquidity',
                'slug' => 'view-hauling-liquidity',
                'description' => 'Can view haulers and hauling liquidation entries',
            ],
            [
                'name' => 'Manage Hauling Liquidity',
                'slug' => 'manage-hauling-liquidity',
                'description' => 'Can add haulers and create, edit, and delete hauling liquidation entries',
            ],
            ...\App\Support\NavigationPermissionsSeeder::permissions(),
        ];

        // Use updateOrCreate to avoid duplicate key errors
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']], // Search by slug
                $permission // Values to update or create
            );
        }
        
        // Output success message in console
        $this->command->info('Permissions seeded successfully!');
        $this->command->info('Total permissions processed: ' . count($permissions));
    }
}
