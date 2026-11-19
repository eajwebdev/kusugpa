<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\VoucherBatch;
use App\Models\VoucherBatchItem;
use App\Services\PlanterProceeds;
use App\Services\PlanterProfileSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Generated voucher groups on Print Vouchers. Generate saves a group with every planter of the
 * crop year and weeks that is not yet in a group for any of those weeks, so a voucher is never
 * generated twice. Deleting a group lets its planters be generated again.
 */
class VoucherBatchController extends Controller
{
    /** The groups, latest first, of a crop year (or all) */
    public function index(Request $request)
    {
        $batches = VoucherBatch::query()
            ->when($request->filled('crop_year'), fn ($q) => $q->where('crop_year', $request->input('crop_year')))
            ->withCount('items')
            ->withCount(['items as receivers_count' => fn ($q) => $q->whereNotNull('receiver_name')])
            ->withSum('items', 'net_paid')
            ->withSum('items', 'quedan_count')
            ->withSum('items', 'molasses_count')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (VoucherBatch $b) => $this->batchRow($b));

        return response()->json($batches);
    }

    /**
     * Generate a group from the selected crop year, Week Bought and (optionally) planters: each voucher has
     * the quedans and molasses bought in the week, with the trucking allowance of their weeks
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'bought_week' => 'required|integer|min:1',
            'planter_name' => 'nullable|array',
            'planter_name.*' => 'string',
        ]);
        $boughtWeek = (int) $data['bought_week'];
        $from = $to = $boughtWeek;

        // One Generate at a time, so two people generating at once cannot both take the same planter
        return Cache::lock('voucher-batch-generate', 120)->block(60, function () use ($data, $from, $to, $boughtWeek) {
            $records = PlanterProceeds::summaryByBoughtWeek($data['crop_year'], $boughtWeek, $data['planter_name'] ?? null);

            // Planters already in a group of this Week Bought, or in an older group (by week range) covering the week
            $taken = VoucherBatchItem::where('crop_year', $data['crop_year'])
                ->where(fn ($q) => $q->where('bought_week', $boughtWeek)
                    ->orWhere(fn ($old) => $old->whereNull('bought_week')->where('week_from', '<=', $boughtWeek)->where('week_to', '>=', $boughtWeek)))
                ->with('batch:id,created_at')
                ->get()
                ->keyBy(fn ($item) => PlanterProfileSync::key($item->planter_code));

            // One voucher per quedan (and per planter-week of molasses without a quedan); a planter's
            // vouchers of the week are generated together
            $new = [];
            $skipped = [];
            foreach ($records as $record) {
                $code = trim((string) $record['planter_code']);
                $key = PlanterProfileSync::key($code);
                $voucherKey = (string) $record['voucher_key'];
                if ($code === '' || isset($new[$voucherKey])) {
                    continue;
                }
                if ($taken->has($key)) {
                    $skipped[$key] = trim((string) $record['planter_name']) . ' (group of ' . $taken[$key]->batch->created_at->format('M d, Y h:i A') . ')';
                    continue;
                }
                $new[$voucherKey] = [
                    'planter_code' => $code,
                    'planter_name' => trim((string) $record['planter_name']),
                    'voucher_key' => $voucherKey,
                    'quedan_week' => $record['quedan_week'] ?? null,
                    'qdn_no' => mb_substr(implode(', ', array_filter($record['qdn_nos'] ?? [])), 0, 100) ?: null,
                    'quedan_count' => (int) ($record['quedan_count'] ?? 0),
                    'molasses_count' => (int) ($record['molasses_count'] ?? 0),
                    'net_paid' => round(PlanterProceeds::compute($record)['netProceedsPaid'], 2),
                ];
            }
            $skipped = array_values($skipped);

            if (empty($new)) {
                return response()->json([
                    'message' => $records->isEmpty()
                        ? 'No quedans or molasses were bought in Week ' . $boughtWeek . ' of crop year ' . $data['crop_year'] . '.'
                        : 'Every planter of Week Bought ' . $boughtWeek . ' is already in a generated group. Delete that group to generate them again.',
                    'skipped' => count($skipped),
                ], 422);
            }

            $user = auth()->user();
            $batch = DB::transaction(function () use ($data, $from, $to, $boughtWeek, $new, $user) {
                $batch = VoucherBatch::create([
                    'crop_year' => $data['crop_year'],
                    'week_from' => $from,
                    'week_to' => $to,
                    'bought_week' => $boughtWeek,
                    'user_id' => $user?->id,
                    'generated_by' => $user ? trim($user->fname . ' ' . $user->lname) : null,
                ]);
                // Already in planter / week / quedan order
                foreach ($new as $voucher) {
                    $batch->items()->create($voucher + ['crop_year' => $data['crop_year'], 'week_from' => $from, 'week_to' => $to, 'bought_week' => $boughtWeek]);
                }

                return $batch;
            });

            $quedans = array_sum(array_column($new, 'quedan_count'));
            $molasses = array_sum(array_column($new, 'molasses_count'));
            AuditLog::log('create', 'voucher', 'Generated voucher group of ' . count($new) . ' voucher(s)', [
                'batch_id' => $batch->id,
                'crop_year' => $batch->crop_year,
                'bought_week' => $boughtWeek,
                'vouchers' => count($new),
                'quedans' => $quedans,
                'molasses' => $molasses,
                'skipped_already_generated' => count($skipped),
            ]);

            $message = 'Generated ' . count($new) . ' voucher(s): ' . $quedans . ' quedan(s) and ' . $molasses . ' molasses record(s).';
            if ($skipped) {
                $message .= ' ' . count($skipped) . ' planter(s) left out, already in a generated group: '
                    . implode('; ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? '; ...' : '') . '.';
            }

            return response()->json([
                'message' => $message,
                'batch' => $this->batchRow($this->withTotals($batch)),
                'skipped' => count($skipped),
            ], 201);
        });
    }

    /** A group's planters */
    public function show(VoucherBatch $batch)
    {
        return response()->json([
            'batch' => $this->batchRow($this->withTotals($batch)),
            // Planter, then week and generation order: one row per voucher
            'items' => $batch->items()->orderBy('planter_name')->orderBy('quedan_week')->orderBy('id')->get()
                ->map(fn (VoucherBatchItem $i) => [
                    'id' => $i->id,
                    'planter_code' => $i->planter_code,
                    'planter_name' => $i->planter_name,
                    'quedan_week' => $i->quedan_week,
                    'qdn_no' => (string) $i->qdn_no,
                    'quedan_count' => (int) $i->quedan_count,
                    'molasses_count' => (int) $i->molasses_count,
                    'net_paid' => $i->net_paid,
                    'receiver_name' => (string) $i->receiver_name,
                ]),
        ]);
    }

    /** Set (or clear, with a blank name) the person authorized to receive one planter's voucher */
    public function receiver(Request $request, VoucherBatch $batch, VoucherBatchItem $item)
    {
        abort_unless((int) $item->voucher_batch_id === (int) $batch->id, 404);
        $data = $request->validate(['receiver_name' => 'nullable|string|max:255']);

        $name = trim(preg_replace('/\s+/', ' ', (string) ($data['receiver_name'] ?? '')));
        $previous = $item->receiver_name;
        $item->update(['receiver_name' => $name === '' ? null : $name]);

        AuditLog::log('update', 'voucher', $name === ''
            ? 'Removed authorized receiver of voucher: ' . $item->planter_name
            : 'Set authorized receiver of voucher: ' . $item->planter_name . ' -> ' . $name, [
            'batch_id' => $batch->id,
            'crop_year' => $item->crop_year,
            'weeks' => $item->week_from . ' - ' . $item->week_to,
            'planter_code' => $item->planter_code,
            'previous' => $previous,
            'receiver' => $name ?: null,
        ]);

        return response()->json([
            'message' => $name === '' ? 'Authorized receiver removed.' : 'Authorized receiver saved.',
            'receiver_name' => $name,
        ]);
    }

    /** Delete a group; its planters can then be generated again */
    public function destroy(VoucherBatch $batch)
    {
        $count = $batch->items()->count();
        $label = $batch->created_at->format('M d, Y h:i A');
        $batch->delete();

        AuditLog::log('delete', 'voucher', 'Deleted voucher group of ' . $label . ' (' . $count . ' planter(s))', [
            'batch_id' => $batch->id,
            'crop_year' => $batch->crop_year,
            'weeks' => $batch->week_from . ' - ' . $batch->week_to,
            'planters' => $count,
        ]);

        return response()->json(['message' => "Group deleted. Its {$count} planter(s) can be generated again."]);
    }

    /** A group with its voucher, receiver, quedan and molasses counts and net paid */
    private function withTotals(VoucherBatch $batch): VoucherBatch
    {
        return $batch->loadCount(['items', 'items as receivers_count' => fn ($q) => $q->whereNotNull('receiver_name')])
            ->loadSum('items', 'net_paid')
            ->loadSum('items', 'quedan_count')
            ->loadSum('items', 'molasses_count');
    }

    private function batchRow(VoucherBatch $b): array
    {
        return [
            'id' => $b->id,
            'crop_year' => $b->crop_year,
            'week_from' => $b->week_from,
            'week_to' => $b->week_to,
            'bought_week' => $b->bought_week,
            'generated_at' => $b->created_at->format('M d, Y h:i A'),
            'generated_by' => (string) $b->generated_by,
            // One voucher per planter in older groups; one per quedan in Week Bought groups
            'planters' => (int) $b->items_count,
            'vouchers' => (int) $b->items_count,
            'quedans' => (int) ($b->items_sum_quedan_count ?? 0),
            'molasses' => (int) ($b->items_sum_molasses_count ?? 0),
            'receivers' => (int) ($b->receivers_count ?? 0),
            'net_paid' => round((float) ($b->items_sum_net_paid ?? 0), 2),
        ];
    }
}
