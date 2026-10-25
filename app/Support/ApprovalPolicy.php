<?php

namespace App\Support;

use App\Models\User;

/**
 * Who approves a cash advance or loan, by amount (cash advance amount / loan principal):
 *  - below ₱50,000: approved as soon as it is created; staff who handle the module may also
 *    approve, reject or activate it
 *  - ₱50,000 or more: waits as pending for a Manager or Administrator
 */
class ApprovalPolicy
{
    public const MANAGER_THRESHOLD = 50000;

    public static function needsManager($amount): bool
    {
        return round((float) $amount, 2) >= self::MANAGER_THRESHOLD;
    }

    public static function isManagerOrAdmin(User $user): bool
    {
        return in_array($user->role, ['manager', 'Administrator', 'super_admin'], true);
    }

    /**
     * @param  bool  $isModuleStaff  the user can approve or process in the module (cash advances / loans)
     */
    public static function canDecide(User $user, $amount, bool $isModuleStaff): bool
    {
        if (self::isManagerOrAdmin($user)) {
            return true;
        }

        return !self::needsManager($amount) && $isModuleStaff;
    }

    public static function thresholdLabel(): string
    {
        return '₱' . number_format(self::MANAGER_THRESHOLD);
    }
}
