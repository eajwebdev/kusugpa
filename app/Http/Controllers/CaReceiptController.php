<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CashAdvance;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\LoanSetting;
use App\Services\CashAdvanceSchedule;
use App\Support\AmountInWords;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * CA Receipt, used from Cash Advances > All Cash Advances: prints the "Cash Advance Receipt
 * and Sugar Assignment" with its Cash / Check Voucher, and records the CV No., Check No. and
 * the weeks the advance is deducted (used by Hauling Liquidity's "Cash Adv." deduction).
 */
class CaReceiptController extends Controller
{
    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private function canView(): bool
    {
        if (in_array($this->user()->role, ['Administrator', 'super_admin', 'manager', 'loan_officer'])) {
            return true;
        }
        return $this->user()->hasPermission('view-cash-advances');
    }

    /**
     * Who may edit CV / Check No. and deduction weeks (also used by the All Cash Advances page)
     */
    public static function canManageReceipt(User $user): bool
    {
        if (in_array($user->role, ['Administrator', 'super_admin', 'manager'])) {
            return true;
        }
        return $user->hasAnyPermission(['create-cash-advances', 'process-cash-advance-payments']);
    }

    private function canManage(): bool
    {
        return self::canManageReceipt($this->user());
    }

    /** Receipt signatory slots => Cash Advance Settings key of its default name (null = no default) */
    public const SIGNATORIES = [
        'committee' => 'ca_sig_committee',
        'co_maker' => null,
        'prepared' => 'ca_sig_prepared',
        'approved' => 'ca_sig_approved',
        'received' => null,
    ];

    /** Default signatory names from Cash Advance Settings */
    public static function signatoryDefaults(): array
    {
        $defaults = [];
        foreach (self::SIGNATORIES as $slot => $key) {
            $defaults[$slot] = $key ? (string) LoanSetting::get($key, '') : '';
        }
        return $defaults;
    }

    /** Names printed on the receipt: the cash advance's own, else the settings default */
    public static function signatoriesFor(CashAdvance $ca): array
    {
        $own = $ca->receipt_signatories ?? [];
        $names = [];
        foreach (self::signatoryDefaults() as $slot => $default) {
            $names[$slot] = trim((string) ($own[$slot] ?? '')) ?: $default;
        }
        return $names;
    }

    /**
     * Save CV No., Check No. and deduction weeks of a cash advance
     */
    public function update(Request $request, CashAdvance $cashAdvance)
    {
        abort_unless($this->canManage(), 403, 'Unauthorized');

        $data = $request->validate([
            'cv_no' => 'nullable|string|max:50',
            'check_no' => 'nullable|string|max:50',
            'deductions' => 'array',
            'deductions.*.week_no' => 'required|string|distinct',
            'deductions.*.amount' => 'required|numeric|min:0.01',
            'signatories' => 'array',
            'signatories.*' => 'nullable|string|max:100',
        ], [
            'deductions.*.week_no.distinct' => 'Each week can only be listed once.',
            'deductions.*.amount.min' => 'Each deduction week needs an amount.',
        ]);

        $deductions = collect($data['deductions'] ?? []);

        CashAdvanceSchedule::validate($cashAdvance, $deductions);

        DB::transaction(function () use ($cashAdvance, $data, $deductions) {
            $cashAdvance->update([
                'cv_no' => $data['cv_no'] ?? null,
                'check_no' => $data['check_no'] ?? null,
                'receipt_signatories' => collect(array_keys(self::SIGNATORIES))
                    ->mapWithKeys(fn ($slot) => [$slot => trim((string) ($data['signatories'][$slot] ?? ''))])
                    ->all(),
            ]);
            CashAdvanceSchedule::apply($cashAdvance, $deductions);
        });

        AuditLog::log('update', 'cash_advances', 'Updated receipt details of cash advance #' . $cashAdvance->ca_number, [
            'cv_no' => $cashAdvance->cv_no,
            'check_no' => $cashAdvance->check_no,
            'deduction_weeks' => $deductions->map(fn ($d) => 'Wk ' . $d['week_no'] . ' = ' . $d['amount'])->implode(', '),
            'signatories' => $cashAdvance->receipt_signatories,
        ]);

        $fresh = $cashAdvance->fresh('deductions');

        return response()->json([
            'message' => 'Receipt details saved',
            'cash_advance' => [
                'id' => $fresh->id,
                'cv_no' => $fresh->cv_no,
                'check_no' => $fresh->check_no,
                'receipt_signatories' => $fresh->receipt_signatories,
                'deductions' => $fresh->deductions,
            ],
        ]);
    }

    /**
     * Cash Advance Receipt and Sugar Assignment + Cash / Check Voucher (short bond, portrait)
     */
    public function pdf(Request $request, CashAdvance $cashAdvance)
    {
        abort_unless($this->canView(), 403, 'Unauthorized');

        $cashAdvance->load('deductions', 'planter', 'createdByUser', 'approvedByUser');

        $pdf = Pdf::loadView('pdf.ca-receipt', [
            'ca' => $cashAdvance,
            // Worded like the printed form: "TWENTY THOUSAND Pesos AND 0/100 ONLY"
            'amountInWords' => preg_replace_callback('/ PESOS AND (\d+)\/100/', fn ($m) => ' Pesos AND ' . (int) $m[1] . '/100', AmountInWords::pesos($cashAdvance->amount)),
            'signatories' => self::signatoriesFor($cashAdvance),
            'weeklyInterest' => (float) LoanSetting::get('ca_receipt_weekly_interest', 1),
            'org' => config('kusugpa'),
        ])->setPaper('letter', 'portrait'); // short bond: 8.5" x 11"

        $filename = 'ca-receipt-' . $cashAdvance->ca_number . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
