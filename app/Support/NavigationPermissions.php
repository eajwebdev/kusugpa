<?php

namespace App\Support;

use App\Models\User;

class NavigationPermissions
{
    // Each parent grants every child in its dropdown. Individual children never grant siblings.
    public const GROUPS = [
        'quedan-molasses' => [
            'all' => 'access-all-quedan-molasses',
            'items' => ['view-quedan-molasses-registry', 'buy-quedan', 'buy-molasses'],
        ],
        'uploads' => [
            'all' => 'upload-all',
            'items' => ['upload-quedan', 'upload-molasses', 'upload-consolidated', 'upload-summary',
                'upload-trucking', 'upload-fci', 'upload-fuel', 'upload-rentals', 'upload-underload',
                'upload-transloading', 'upload-mudpress', 'upload-tls'],
        ],
        'updates' => [
            'all' => 'set-all-weekly-updates',
            'items' => ['set-quedan-price', 'set-molasses-price', 'set-crop-year', 'set-week-number'],
        ],
        'reports' => [
            'all' => 'view-reports',
            'items' => ['view-consolidated-report', 'view-summary-report', 'view-trucking-allowance-report',
                'view-certification-report', 'view-tls-report', 'view-proceeds-sheet-report'],
        ],
        'loans' => [
            'all' => 'access-all-loans',
            'items' => ['view-loans', 'view-loan-reports', 'manage-loan-settings', 'create-loans',
                'approve-loans', 'process-loan-payments'],
        ],
        'cash-advances' => [
            'all' => 'access-all-cash-advances',
            'items' => ['view-cash-advances', 'manage-cash-advance-settings', 'create-cash-advances',
                'approve-cash-advances', 'process-cash-advance-payments'],
        ],
    ];

    public static function parentFor(string $slug): ?string
    {
        foreach (self::GROUPS as $group) {
            if (in_array($slug, $group['items'], true)) {
                return $group['all'];
            }
        }

        return null;
    }

    public static function canSeeGroup(User $user, string $group): bool
    {
        $config = self::GROUPS[$group];
        return $user->hasPermission($config['all']) || $user->hasAnyPermission($config['items']);
    }

    public static function requiredForRoute(?string $name, ?string $type = null): ?string
    {
        if (!$name) {
            return null;
        }

        if (in_array($name, ['printVoucher', 'voucher.pdf'], true)) {
            return 'print-vouchers';
        }

        if (in_array($name, ['prices.registry', 'quedan-molasses-registry', 'registry.data', 'registry.filters',
            'quedan-registry.export', 'molasses-registry.export', 'registry.quedan.delete-all',
            'registry.quedan.delete-by-week', 'registry.quedan.delete-selected', 'registry.molasses.delete-all',
            'registry.molasses.delete-by-week', 'registry.molasses.delete-selected'], true)) {
            return 'view-quedan-molasses-registry';
        }
        if (str_starts_with($name, 'quedan-buy') || $name === 'quedans.bulk-update') {
            return 'buy-quedan';
        }
        if (str_starts_with($name, 'molasses-buy') || $name === 'molasses.bulk-update') {
            return 'buy-molasses';
        }

        if (str_starts_with($name, 'summaryReport') || str_starts_with($name, 'summary.')) {
            return 'view-summary-report';
        }

        if ($name === 'updates.addQuedanPrice') return 'set-quedan-price';
        if ($name === 'updates.addMolassesPrice') return 'set-molasses-price';
        if ($name === 'updates.addCropYear') return 'set-crop-year';
        if ($name === 'updates.addWeekNumber') return 'set-week-number';
        if (in_array($name, ['upload.csv', 'upload.template'], true)) {
            return $type ? 'upload-' . $type : null;
        }
        foreach (['consolidated-report' => 'view-consolidated-report',
            'trucking-allowance-report' => 'view-trucking-allowance-report',
            'certification-report' => 'view-certification-report',
            'tls-report' => 'view-tls-report',
            'proceeds-sheet-report' => 'view-proceeds-sheet-report'] as $prefix => $slug) {
            if (str_starts_with($name, $prefix)) {
                return $slug;
            }
        }

        if (in_array($name, ['loans.reports', 'loans.monthly-report-pdf', 'loans.active-loans-pdf',
            'loans.amortization-pdf', 'loans.details-pdf', 'loans.soa-pdf'], true)) {
            return 'view-loan-reports';
        }
        if (str_starts_with($name, 'loans.settings') || str_starts_with($name, 'loans.types.')) {
            return 'manage-loan-settings';
        }
        if (in_array($name, ['loans.index', 'loans.show', 'loans.attachments.index',
            'loans.attachment.view', 'loans.attachment.download'], true)) {
            return 'view-loans';
        }
        if (in_array($name, ['loans.create', 'loans.store', 'loans.attachments.store',
            'loans.attachments.destroy', 'loans.schedule.update', 'loans.destroy'], true)) {
            return 'create-loans';
        }
        if (in_array($name, ['loans.approve', 'loans.activate', 'loans.reject'], true)) {
            return 'approve-loans';
        }
        if (in_array($name, ['loans.payments.store', 'loans.payments.destroy'], true)) {
            return 'process-loan-payments';
        }
        if (str_starts_with($name, 'cash-advances.settings')) {
            return 'manage-cash-advance-settings';
        }
        if (in_array($name, ['cash-advances.index', 'cash-advances.show', 'cash-advances.receipt.pdf'], true)) {
            return 'view-cash-advances';
        }
        if (in_array($name, ['cash-advances.create', 'cash-advances.store', 'cash-advances.schedule.update',
            'cash-advances.destroy', 'cash-advances.receipt.update'], true)) {
            return 'create-cash-advances';
        }
        if (in_array($name, ['cash-advances.approve', 'cash-advances.activate', 'cash-advances.reject'], true)) {
            return 'approve-cash-advances';
        }
        if (in_array($name, ['cash-advances.payment', 'cash-advances.weekly-payments.store',
            'cash-advances.weekly-payments.destroy'], true)) {
            return 'process-cash-advance-payments';
        }

        return null;
    }
}
