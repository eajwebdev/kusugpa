<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Splits a large PDF into parts that each render well within the server's time limit
 * (dompdf slows down sharply as one document grows). The PDF preview asks a supporting
 * route for its parts first (?pdf_parts=1 → JSON) and then loads ?part=N.
 */
class PdfParts
{
    /** PDF routes that support parts; the preview dialog checks a document's URL against these */
    public const ROUTES = [
        'voucher.pdf',
        'certification-report.pdf',
        'quedan-registry.export',
        'molasses-registry.export',
        'trucking-allowance-report.export',
        'consolidated-report.export',
        'summary.previewPDF',
        'summary.downloadPDF',
        'audit-logs.export',
        'proceeds-sheet-report.pdf',
        'proceeds-deductions-report.pdf',
        'hauling-liquidity.week-pdf',
        'planter-profiles.export',
    ];

    private function __construct(
        private ?Collection $items,
        private int $total,
        private int $size,
        private int $part,
        private string $unit,
    ) {
    }

    /**
     * @param  int  $size  items per part, sized from timings on real data (about 15s each locally)
     * @param  string  $unit  what is being counted, e.g. "planters", "rows"
     */
    public static function make(Request $request, Collection $items, int $size, string $unit = 'rows'): self
    {
        $items = $items->values();

        return self::build($request, $items, $items->count(), $size, $unit);
    }

    /**
     * For lists too big to load whole: only the count is known, and the caller fetches
     * offset() / size() rows of the requested part itself
     */
    public static function forCount(Request $request, int $total, int $size, string $unit = 'rows'): self
    {
        return self::build($request, null, $total, $size, $unit);
    }

    private static function build(Request $request, ?Collection $items, int $total, int $size, string $unit): self
    {
        // Safety margin for slower servers; each part is sized to finish well before this
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $size = max(1, $size);
        $parts = max(1, (int) ceil($total / $size));
        $part = min(max(1, (int) $request->get('part', 1)), $parts);

        return new self($items, $total, $size, $part, $unit);
    }

    public function size(): int
    {
        return $this->size;
    }

    public function wantsInfo(Request $request): bool
    {
        return $request->boolean('pdf_parts');
    }

    /** What the preview needs to offer the parts */
    public function info(): JsonResponse
    {
        $total = $this->total;
        $labels = collect(range(1, $this->parts()))->map(function ($p) use ($total) {
            [$from, $to] = $this->range($p);
            return 'Part ' . $p . ' · ' . $this->unit . ' ' . number_format($from) . '–' . number_format($to) . ' of ' . number_format($total);
        });

        return response()->json([
            'parts' => $this->parts(),
            'total' => $total,
            'size' => $this->size,
            'unit' => $this->unit,
            'labels' => $labels,
        ]);
    }

    public function parts(): int
    {
        return max(1, (int) ceil($this->total / $this->size));
    }

    public function part(): int
    {
        return $this->part;
    }

    public function isLast(): bool
    {
        return $this->part === $this->parts();
    }

    /** Items of the requested part */
    public function items(): Collection
    {
        return ($this->items ?? collect())->slice(($this->part - 1) * $this->size, $this->size)->values();
    }

    /** Index of this part's first item in the whole list (for continuing page numbers) */
    public function offset(): int
    {
        return ($this->part - 1) * $this->size;
    }

    /** "Part 2 of 5 · planters 81–160 of 390", or '' when the document is not split */
    public function label(): string
    {
        if ($this->parts() === 1) {
            return '';
        }
        [$from, $to] = $this->range($this->part);

        return 'Part ' . $this->part . ' of ' . $this->parts() . ' · ' . $this->unit . ' '
            . number_format($from) . '–' . number_format($to) . ' of ' . number_format($this->total);
    }

    public function filename(string $base, string $extension = 'pdf'): string
    {
        return $base . ($this->parts() > 1 ? '-part' . $this->part . 'of' . $this->parts() : '') . '.' . $extension;
    }

    /** URL path patterns of the supporting routes, for the preview dialog */
    public static function pathPatterns(): array
    {
        return collect(self::ROUTES)
            ->map(fn ($name) => Route::getRoutes()->getByName($name)?->uri())
            ->filter()
            ->map(fn ($uri) => '/' . preg_replace('/\\\{[^}]+\\\}/', '[^/]+', preg_quote(trim($uri, '/'), '#')) . '$')
            ->values()
            ->all();
    }

    private function range(int $part): array
    {
        $from = ($part - 1) * $this->size + 1;
        $to = min($part * $this->size, $this->total);

        return [$from, $to];
    }
}
