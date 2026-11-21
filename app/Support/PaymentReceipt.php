<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Proof of payment for an over-the-counter (cash) repayment of a cash advance or loan: a small receipt
 * (half of a letter page) with the receipt number, planter, account, amount in figures and words and the
 * balance after the payment, signed by the cashier and the planter
 */
class PaymentReceipt
{
    public static function stream(array $data, string $filename)
    {
        $data['paid_date'] = $data['paid_date'] ? Carbon::parse($data['paid_date'])->format('F d, Y') : '';
        $data['amount_words'] = AmountInWords::pesos($data['amount']);
        $data['org'] = config('kusugpa');

        return Pdf::loadView('pdf.payment-receipt', $data)
            // Half of a letter page, landscape: 8.5in x 5.5in
            ->setPaper([0, 0, 612, 396])
            ->stream($filename);
    }
}
