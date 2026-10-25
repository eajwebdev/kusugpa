<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One batch of a table's rows for the "load more on scroll" tables (kpFeed in layouts/main).
 * Filters are applied by the caller; this adds the sort, the batch window and the total.
 *
 * Response: { data, page, per_page, has_more, total (first batch only), extra }
 */
class TableFeed
{
    public const PER_PAGE = 100;

    /**
     * @param  array  $sortable  sort key from the page => column name, or ['raw' => 'SQL expression']
     * @param  array  $defaultSort  [[column or ['raw' => ...], 'asc'|'desc'], ...] when no sort is chosen
     * @param  callable|null  $map  transforms each row before it is sent
     * @param  callable|null  $extra  fn (query) => array, sent with the first batch (e.g. totals over the filtered rows)
     * @param  callable|null  $mapBatch  fn (Collection $rows) => Collection, for work done once per batch (e.g. lookups by code)
     */
    public static function respond(
        Request $request,
        EloquentBuilder|QueryBuilder $query,
        array $sortable,
        array $defaultSort,
        ?callable $map = null,
        ?callable $extra = null,
        ?callable $mapBatch = null,
    ): JsonResponse {
        $perPage = min(max((int) $request->get('per_page', self::PER_PAGE), 10), 500);
        $page = max(1, (int) $request->get('page', 1));

        // Totals only with the first batch: counting a very large table on every scroll would be wasted work
        $total = $page === 1 ? (clone $query)->reorder()->count() : null;
        $extraData = $page === 1 && $extra ? $extra(clone $query) : [];

        $sort = (string) $request->get('sort', '');
        $direction = strtolower((string) $request->get('dir')) === 'desc' ? 'desc' : 'asc';
        $query->reorder();
        if (isset($sortable[$sort])) {
            self::orderBy($query, $sortable[$sort], $direction);
        } else {
            foreach ($defaultSort as [$column, $dir]) {
                self::orderBy($query, $column, $dir);
            }
        }
        // Same order every time, so batches never repeat or skip a row
        $query->orderBy(($query instanceof EloquentBuilder ? $query->getModel()->getTable() . '.' : '') . 'id', $direction);

        // One extra row tells whether another batch follows
        $rows = $query->skip(($page - 1) * $perPage)->take($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage)->values();
        if ($map) {
            $rows = $rows->map($map)->values();
        }
        if ($mapBatch) {
            $rows = $mapBatch($rows)->values();
        }

        return response()->json([
            'data' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => $hasMore,
            'total' => $total,
            'extra' => $extraData,
        ]);
    }

    private static function orderBy(EloquentBuilder|QueryBuilder $query, string|array $column, string $direction): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        if (is_array($column)) {
            $query->orderByRaw($column['raw'] . ' ' . $direction);
        } else {
            $query->orderBy($column, $direction);
        }
    }
}
