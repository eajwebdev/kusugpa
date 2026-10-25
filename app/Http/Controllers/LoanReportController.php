<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Models\CropYear;
use App\Models\WeekNo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class LoanReportController extends Controller
{
    public function index()
    {
        $cropYears = CropYear::pluck('crop_year');
        $weeks = WeekNo::byCropYear();
        $activeLoans = Loan::where('status', 'active')->count();
        $completedLoans = Loan::where('status', 'completed')->count();
        $pendingLoans = Loan::where('status', 'pending')->count();
        $totalAmount = Loan::whereIn('status', ['active', 'approved'])->sum('principal_amount');
        $totalCollected = LoanAmortization::where('status', 'paid')->sum('amount_paid');

        return view('loans.reports', compact(
            'cropYears', 'weeks', 'activeLoans', 'completedLoans',
            'pendingLoans', 'totalAmount', 'totalCollected'
        ));
    }

    /**
     * Generate Amortization Schedule PDF for a specific loan
     */
    public function amortizationPDF(Loan $loan)
    {
        $loan->load(['loanType', 'amortizations' => function($q) {
            $q->orderBy('payment_number');
        }, 'approvedByUser']);

        $pdf = Pdf::loadView('pdf.loan-amortization', [
            'loan' => $loan,
            'generatedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('amortization-schedule-' . $loan->loan_number . '.pdf');
    }

    /**
     * Generate Loan Details PDF
     */
    public function detailsPDF(Loan $loan)
    {
        $loan->load(['loanType', 'amortizations' => function($q) {
            $q->orderBy('payment_number');
        }, 'approvedByUser', 'createdByUser']);

        $totalPaid = $loan->amortizations->where('status', 'paid')->sum('amount_paid');
        $remainingBalance = $loan->total_amount - $totalPaid;

        $pdf = Pdf::loadView('pdf.loan-details', [
            'loan' => $loan,
            'totalPaid' => $totalPaid,
            'remainingBalance' => $remainingBalance,
            'generatedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('loan-details-' . $loan->loan_number . '.pdf');
    }

    /**
     * Generate Statement of Account (SOA) PDF
     */
    public function soaPDF(Loan $loan)
    {
        $loan->load(['loanType', 'amortizations' => function($q) {
            $q->orderBy('payment_number');
        }]);

        $totalPaid = $loan->amortizations->where('status', 'paid')->sum('amount_paid');
        $totalDue = $loan->total_amount;
        $balance = $totalDue - $totalPaid;
        $overduePayments = $loan->amortizations->where('status', 'overdue');

        $pdf = Pdf::loadView('pdf.loan-soa', [
            'loan' => $loan,
            'totalPaid' => $totalPaid,
            'totalDue' => $totalDue,
            'balance' => $balance,
            'overduePayments' => $overduePayments,
            'generatedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('statement-of-account-' . $loan->loan_number . '.pdf');
    }

    /**
     * Generate Monthly Loan Report PDF
     */
    public function monthlyReportPDF(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'week_from' => 'nullable|integer|min:0',
            'week_to' => 'nullable|integer|min:0',
        ]);
        $month = $request->get('month') ?: now()->format('Y-m');
        $cropYear = $request->get('crop_year');

        $startDate = Carbon::parse($month . '-01');
        $endDate = $startDate->copy()->endOfMonth();
        $periodLabel = $startDate->format('F Y');

        // A crop year week range takes the place of the month: from the first week's start to the last week's end
        if ($cropYear && $request->filled('week_from') && $request->filled('week_to')) {
            $from = min((int) $request->week_from, (int) $request->week_to);
            $to = max((int) $request->week_from, (int) $request->week_to);
            $weeks = WeekNo::where('crop_year', $cropYear)
                ->whereRaw('CAST(week_no AS UNSIGNED) BETWEEN ? AND ?', [$from, $to])
                ->get(['week_start_date', 'week_end_date']);
            if ($weeks->isNotEmpty()) {
                $startDate = Carbon::parse($weeks->min('week_start_date'))->startOfDay();
                $endDate = Carbon::parse($weeks->max('week_end_date'))->endOfDay();
                $periodLabel = 'Crop Year ' . $cropYear . ' · Week ' . ($from === $to ? $from : $from . '–' . $to)
                    . ' (' . $startDate->format('M d') . ' – ' . $endDate->format('M d, Y') . ')';
            }
        }

        $query = Loan::with(['loanType', 'amortizations' => function($q) use ($startDate, $endDate) {
            $q->whereBetween('due_date', [$startDate, $endDate]);
        }]);

        if ($cropYear) {
            $query->where('crop_year', $cropYear);
        }

        $loans = $query->whereIn('status', ['active', 'approved'])->get();

        $newLoans = Loan::whereBetween('application_date', [$startDate, $endDate])->count();
        $approvedLoans = Loan::whereBetween('approved_date', [$startDate, $endDate])->count();
        $collections = LoanAmortization::whereBetween('paid_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('amount_paid');

        $pdf = Pdf::loadView('pdf.loan-monthly-report', [
            'loans' => $loans,
            'month' => $periodLabel,
            'newLoans' => $newLoans,
            'approvedLoans' => $approvedLoans,
            'collections' => $collections,
            'generatedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('monthly-loan-report-' . $startDate->format('Y-m') . '.pdf');
    }

    /**
     * Generate Active Loans Report PDF
     */
    public function activeLoansPDF(Request $request)
    {
        $cropYear = $request->get('crop_year');

        $query = Loan::with(['loanType', 'amortizations'])->where('status', 'active');

        if ($cropYear) {
            $query->where('crop_year', $cropYear);
        }

        $loans = $query->get();
        $totalPrincipal = $loans->sum('principal_amount');
        $totalBalance = $loans->sum('balance');
        $totalMonthlyCollection = $loans->sum('monthly_amortization');

        $pdf = Pdf::loadView('pdf.loan-active-report', [
            'loans' => $loans,
            'totalPrincipal' => $totalPrincipal,
            'totalBalance' => $totalBalance,
            'totalMonthlyCollection' => $totalMonthlyCollection,
            'cropYear' => $cropYear,
            'generatedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('active-loans-report.pdf');
    }
}