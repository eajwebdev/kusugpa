<?php

/*
|--------------------------------------------------------------------------
| KUSUG-PA organization details
|--------------------------------------------------------------------------
|
| Printed on official forms such as the Cash Advance Receipt and Sugar
| Assignment. Change them here instead of editing the PDF templates.
|
*/

return [

    'name' => 'KABANKALAN UNITED SUGAR-PLANTERS ASSOCIATION, INC.',

    'short_name' => 'KUSUG-PA',

    'address' => 'Door #3 NZ Arcade, Repullo Street, Kabankalan City',

    'telephone' => '471-0021',

    // Sugar mill the planters deliver cane to and that releases their quedans
    'mill' => 'URC-SONEDCO',

    // Check Voucher printed on the pre-printed Metrobank voucher form, landscape, printed at actual size.
    // Paper: 11 in (27.94 cm) across x 23 cm down, with a 1/2 in (1.27 cm) margin left and right (the tractor-feed
    // strips). The margin does not move the values: every value is placed from the paper's edge.
    // When a printer feeds the form a little off, move everything by these millimetres (+ right / + down).
    'voucher_form' => [
        'paper_width_cm' => 27.94,
        'paper_height_cm' => 23,
        'margin_x_cm' => 1.27,
        'offset_x_mm' => (float) env('VOUCHER_OFFSET_X_MM', 0),
        'offset_y_mm' => (float) env('VOUCHER_OFFSET_Y_MM', 0),
    ],

];
