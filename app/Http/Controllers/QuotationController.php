<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    private function prices(): array
    {
        return [
            'base' => (int) config('quotation.base_one_time'),
            'subscription' => (int) config('quotation.subscription_monthly'),
            'hosting' => (int) config('quotation.hosting_monthly'),
            'discount' => (int) config('quotation.annual_subscription_discount_percent'),
            'member_once' => (int) config('quotation.member_portal_one_time'),
            'member_monthly' => (int) config('quotation.member_portal_monthly'),
            'sms_once' => (int) config('quotation.sms_one_time'),
            'sms_monthly' => (int) config('quotation.sms_monthly'),
            'sms_credits' => (int) config('quotation.sms_monthly_credits'),
        ];
    }

    public function index()
    {
        return view('quotation', [
            'prices' => $this->prices(),
            'details' => config('quotation_details'),
        ]);
    }

    public function download(Request $request)
    {
        $prices = $this->prices();
        $member = $request->boolean('member');
        $sms = $request->boolean('sms');
        $annual = $request->query('billing') === 'annual';

        $oneTime = $prices['base']
            + ($member ? $prices['member_once'] : 0)
            + ($sms ? $prices['sms_once'] : 0);
        $addonsMonthly = ($member ? $prices['member_monthly'] : 0)
            + ($sms ? $prices['sms_monthly'] : 0);
        $annualSubscription = (int) round($prices['subscription'] * 12 * (100 - $prices['discount']) / 100);
        $monthly = $prices['hosting'] + $addonsMonthly + ($annual ? 0 : $prices['subscription']);
        $yearOne = $oneTime + ($annual ? $annualSubscription : 0) + $monthly * 12;

        return Pdf::loadView('pdf.quotation', [
            'prices' => $prices,
            'details' => config('quotation_details'),
            'member' => $member,
            'sms' => $sms,
            'annual' => $annual,
            'oneTime' => $oneTime,
            'annualSubscription' => $annualSubscription,
            'monthly' => $monthly,
            'yearOne' => $yearOne,
        ])->setPaper('a4')->download('KUSUG-PA-System-Quotation.pdf');
    }
}
