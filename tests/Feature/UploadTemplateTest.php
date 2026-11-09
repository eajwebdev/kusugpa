<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UploadTemplateTest extends TestCase
{
    /** @dataProvider templateColumns */
    public function test_each_upload_template_downloads_the_expected_csv_header(string $type, array $columns): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);
        $this->actingAs(new User(['role' => 'Administrator']));

        $response = $this->get(route('upload.template', ['type' => $type]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("kusugpa-{$type}-template.csv", $response->headers->get('content-disposition'));

        ob_start();
        $response->baseResponse->sendContent();
        $csv = ob_get_clean();

        $this->assertSame($columns, str_getcsv(trim($csv)));
    }

    public function test_template_carries_the_picked_crop_year_and_week(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);
        $this->actingAs(new User(['role' => 'Administrator']));

        $response = $this->get(route('upload.template', ['type' => 'fuel', 'crop_year' => '20262027', 'week_no' => '3']));

        $response->assertOk();
        $this->assertStringContainsString('kusugpa-fuel-template-20262027-wk3.csv', $response->headers->get('content-disposition'));

        ob_start();
        $response->baseResponse->sendContent();
        $lines = array_map('str_getcsv', explode("\n", trim(ob_get_clean())));

        $this->assertSame(['crop_year', 'week_no', 'planter_code', 'planter_name', 'liters', 'fuel_amount'], $lines[0]);
        $this->assertSame(['20262027', '3', '', '', '', ''], $lines[1]);

        // A template without crop year / week columns stays header-only
        $response = $this->get(route('upload.template', ['type' => 'tls', 'crop_year' => '20262027', 'week_no' => '3']));
        ob_start();
        $response->baseResponse->sendContent();
        $this->assertCount(1, explode("\n", trim(ob_get_clean())));
    }

    public function templateColumns(): array
    {
        return [
            'summary' => ['summary', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'net_cane', 'net_amount']],
            'trucking' => ['trucking', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'net_cane', 'ta_amount', 'trans_code']],
            'fci' => ['fci', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'fci_amount']],
            'fuel' => ['fuel', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'liters', 'fuel_amount']],
            'rentals' => ['rentals', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'rental_amount']],
            'underload' => ['underload', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'underload_amount']],
            'transloading' => ['transloading', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'transloading_amount']],
            'mudpress' => ['mudpress', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'trans_code', 'charge_code', 'mpress']],
            'consolidated' => ['consolidated', [
                'planter_code', 'assn_code', 'planter_name', 'assn_name', 'ta_wt', 'ta_amount',
                'emi_wt', 'emi_amount', 'pat_wt', 'pat_amount', 'cci_fa_wt', 'cci_fa_amt',
                'cci_fb_wt', 'cci_fb_amt', 'cci_fc_wt', 'cci_fc_amt', 'fuel_issuance_amt',
                'rental_amt', 'underload_amt', 'mudpress_amt', 'adj_amt',
            ]],
            'quedan' => ['quedan', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'qdn_no', 'tin_no', 'total_liens', 'sugar_lkg', 'labor_lkg']],
            'molasses' => ['molasses', ['crop_year', 'week_no', 'planter_code', 'planter_name', 'tin_no', 'mc_no', 'mol_net']],
            'tls' => ['tls', [
                'reference_no', 'planter_code', 'planter_name', 'assn_code', 'assn_name', 'vehicle_type', 'plate_no',
                'tare_weight', 'gross_cane_weight', 'net_cane_wt', 'date_milled', 'trans_code', 'description', 'tdif_rate',
            ]],
        ];
    }
}
