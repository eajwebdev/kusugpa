<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The plain table exports (pdf.ledger): every row, printed in parts of 800 rows for large lists
 */
class LedgerPdf
{
    private const ROWS_PER_PART = 800;

    /**
     * @param  array  $columns  [['key' => 'planter_name', 'label' => 'Planter Name', 'decimals' => 2 (optional), 'total' => true (optional: summed under ?show_totals=1)], ...]
     *                         'width' => '20%' (optional) sets the column's share of the page width
     * @param  callable|null  $beforeRender  called with the PdfParts of the requested part (e.g. to write the audit log)
     * @param  string  $orientation  'landscape' or 'portrait' (A4)
     */
    public static function response(Request $request, Collection $rows, string $title, array $columns, string $filenameBase, ?callable $beforeRender = null, string $orientation = 'landscape')
    {
        $parts = PdfParts::make($request, $rows, self::ROWS_PER_PART, 'rows');
        if ($parts->wantsInfo($request)) {
            return $parts->info();
        }
        if ($beforeRender) {
            $beforeRender($parts);
        }

        $pdf = Pdf::loadView('pdf.ledger', [
            'title' => $title,
            'rows' => $parts->items(),
            'totalRows' => $rows->count(),
            'partLabel' => $parts->label(),
            'columns' => $columns,
            'generatedDate' => now()->format('F d, Y H:i:s'),
            // A grand total of every part, so it closes the last part
            'totals' => $request->boolean('show_totals') && $parts->isLast() ? self::totals($rows, $columns) : null,
        ])->setPaper('a4', $orientation);

        $filename = $parts->filename($filenameBase . '-' . now()->format('Y-m-d'));

        return $request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename);
    }

    private static function totals(Collection $rows, array $columns): array
    {
        return collect($columns)
            ->filter(fn ($column) => !empty($column['total']))
            ->mapWithKeys(fn ($column) => [$column['key'] => $rows->sum(fn ($row) => (float) $row->{$column['key']})])
            ->all();
    }
}
