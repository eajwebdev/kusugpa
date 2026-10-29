<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * dompdf never fetches URLs and only reads files from the font and image folders
 */
class PdfSandboxTest extends TestCase
{
    private function render(string $body): string
    {
        $fonts = view('pdf.partials.formal-font')->render();

        return Pdf::loadHTML('<html><head><style>' . $fonts . ' body { font-family: Tinos; }</style></head><body>' . $body . '</body></html>')->output();
    }

    public function test_report_font_and_logo_still_render(): void
    {
        $this->assertFalse(config('dompdf.options.enable_remote'));

        $pdf = $this->render('<p>Received ₱1,000.00</p><img src="' . public_path('img/logo-512.png') . '">');

        $this->assertStringContainsString('Tinos', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }

    public function test_files_outside_the_sandbox_are_not_read(): void
    {
        $outside = storage_path('app/pdf-sandbox-probe.png');
        copy(public_path('img/logo-512.png'), $outside);

        try {
            $pdf = $this->render('<img src="' . $outside . '">');
        } finally {
            @unlink($outside);
        }

        $this->assertStringNotContainsString('/Subtype /Image', $pdf);
    }
}
