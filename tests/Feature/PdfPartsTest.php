<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PdfParts;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Large PDFs are split into parts so each request finishes within the server's time limit
 */
class PdfPartsTest extends TestCase
{
    public function test_parts_slice_the_items_and_describe_themselves(): void
    {
        $items = collect(range(1, 250));

        $first = PdfParts::make(Request::create('/x'), $items, 100, 'planters');
        $this->assertSame(3, $first->parts());
        $this->assertSame(range(1, 100), $first->items()->all());
        $this->assertSame('Part 1 of 3 · planters 1–100 of 250', $first->label());
        $this->assertFalse($first->isLast());

        $last = PdfParts::make(Request::create('/x', 'GET', ['part' => 3]), $items, 100, 'planters');
        $this->assertSame(range(201, 250), $last->items()->all());
        $this->assertSame(200, $last->offset());
        $this->assertTrue($last->isLast());
        $this->assertSame('voucher-part3of3.pdf', $last->filename('voucher'));

        // An out-of-range part falls back to the nearest one
        $this->assertSame(3, PdfParts::make(Request::create('/x', 'GET', ['part' => 99]), $items, 100)->part());

        $info = PdfParts::make(Request::create('/x'), $items, 100, 'planters')->info()->getData(true);
        $this->assertSame(3, $info['parts']);
        $this->assertSame('Part 3 · planters 201–250 of 250', $info['labels'][2]);
    }

    public function test_small_documents_are_not_split(): void
    {
        $parts = PdfParts::make(Request::create('/x'), collect(range(1, 40)), 100);
        $this->assertSame(1, $parts->parts());
        $this->assertSame('', $parts->label());
        $this->assertSame('voucher.pdf', $parts->filename('voucher'));
        $this->assertSame(1, PdfParts::make(Request::create('/x'), collect(), 100)->parts());
    }

    public function test_count_only_parts_for_lists_too_big_to_load(): void
    {
        $parts = PdfParts::forCount(Request::create('/x', 'GET', ['part' => 2]), 1700, 800, 'logs');
        $this->assertSame(3, $parts->parts());
        $this->assertSame(800, $parts->offset());
        $this->assertSame(800, $parts->size());
        $this->assertSame('Part 2 of 3 · logs 801–1,600 of 1,700', $parts->label());
    }

    public function test_preview_url_patterns_match_the_split_routes(): void
    {
        $patterns = collect(PdfParts::pathPatterns());
        $matches = fn ($path) => $patterns->contains(fn ($p) => preg_match('#' . $p . '#', $path));

        $this->assertTrue($matches('/voucher/pdf-preview'));
        $this->assertTrue($matches('/hauling-liquidity/12/pdf'));
        $this->assertTrue($matches('/certification-report/pdf'));
        $this->assertFalse($matches('/hauling-liquidity/entries/12/pdf'), 'A single slip is never split');
        $this->assertFalse($matches('/cash-advances/3/receipt-pdf'));
    }

    public function test_registry_export_is_split_and_covers_every_row(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\SystemLockMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('quedans', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->string('qdn_no')->nullable();
            $table->string('tin_no')->nullable();
            $table->decimal('total_liens', 12, 3)->default(0);
            $table->decimal('sugar_lkg', 12, 3)->default(0);
            $table->decimal('labor_lkg', 12, 3)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        foreach (array_chunk(range(1, 1700), 500) as $chunk) {
            DB::table('quedans')->insert(array_map(fn ($i) => [
                'crop_year' => '20262027', 'week_no' => '3', 'planter_code' => (string) $i, 'planter_name' => sprintf('PLANTER %04d', $i),
            ], $chunk));
        }
        $this->actingAs(new User(['fname' => 'Admin', 'role' => 'Administrator']));

        $info = $this->getJson(route('quedan-registry.export', ['crop_year' => '20262027', 'pdf_parts' => 1]))->assertOk()->json();
        $this->assertSame(3, $info['parts']);
        $this->assertSame(1700, $info['total']);

        $seen = collect();
        foreach ([1, 2, 3] as $part) {
            $captured = null;
            view()->composer('pdf.ledger', function ($v) use (&$captured) { $captured = $v->getData(); });
            $this->get(route('quedan-registry.export', ['crop_year' => '20262027', 'part' => $part, 'preview' => 1]))->assertOk();
            $this->assertSame(1700, $captured['totalRows']);
            $this->assertStringStartsWith('Part ' . $part . ' of 3', $captured['partLabel']);
            $seen = $seen->concat($captured['rows']->pluck('planter_code'));
        }
        // Every row printed exactly once across the parts
        $this->assertSame(1700, $seen->count());
        $this->assertSame(1700, $seen->unique()->count());
    }
}
