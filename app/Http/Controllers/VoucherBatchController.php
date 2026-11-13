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
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (VoucherBatch $b) => $this->batchRow($b));

        return response()->json($batches);
    }

    /** Generate a group from the selected crop year, weeks and (optionally) planters */
    public function store(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_from' => 'required|integer|min:1',
            'week_to' => 'required|integer|min:1',
            'planter_name' => 'nullable|array',
            'planter_name.*' => 'string',
        ]);
        $from = min((int) $data['week_from'], (int) $data['week_to']);
        $to = max((int) $data['week_from'], (int) $data['week_to']);

        // One Generate at a time, so two people generating at once cannot both take the same planter
        return Cache::lock('voucher-batch-generate', 120)->block(60, function () use ($data, $from, $to) {
            $records = PlanterProceeds::summary($data['crop_year'], $from, $to, $data['planter_name'] ?? null);

            // Planters already in a group whose weeks overlap these weeks
            $taken = VoucherBatchItem::where('crop_year', $data['crop_year'])
                ->where('week_from', '<=', $to)
                ->where('week_to', '>=', $from)
                ->with('batch:id,created_at')
                ->get()
                ->keyBy(fn ($item) => PlanterProfileSync::key($item->planter_code));

            $new = [];
            $skipped = [];
            foreach ($records as $record) {
                $code = trim((string) $record['planter_code']);
                $key = PlanterProfileSync::key($code);
                if ($code === '' || isset($new[$key])) {
                    continue;
                }
                if ($taken->has($key)) {
                    $skipped[] = trim((string) $record['planter_name']) . ' (group of ' . $taken[$key]->batch->created_at->format('M d, Y h:i A') . ')';
                    continue;
                }
                $new[$key] = [
                    'planter_code' => $code,
                    'planter_name' => trim((string) $record['planter_name']),
                    'net_paid' => round(PlanterProceeds::compute($record)['netProceedsPaid'], 2),
                ];
            }

            if (empty($new)) {
                return response()->json([
                    'message' => $records->isEmpty()
                        ? 'No vouchers for the selected period.'
                        : 'Every planter of the selected period is already in a generated group. Delete that group to generate them again.',
                    'skipped' => count($skipped),
                ], 422);
            }

            $user = auth()->user();
            $batch = DB::transaction(function () use ($data, $from, $to, $new, $user) {
                $batch = VoucherBatch::create([
                    'crop_year' => $data['crop_year'],
                    'week_from' => $from,
                    'week_to' => $to,
                    'user_id' => $user?->id,
                    'generated_by' => $user ? trim($user->fname . ' ' . $user->lname) : null,
                ]);
                foreach (collect($new)->sortBy(fn ($p) => mb_strtoupper($p['planter_name'])) as $planter) {
                    $batch->items()->create($planter + ['crop_year' => $data['crop_year'], 'week_from' => $from, 'week_to' => $to]);
                }

                return $batch;
            });

            AuditLog::log('create', 'voucher', 'Generated voucher group of ' . count($new) . ' planter(s)', [
                'batch_id' => $batch->id,
                'crop_year' => $batch->crop_year,
                'weeks' => $from . ' - ' . $to,
                'planters' => count($new),
                'skipped_already_generated' => count($skipped),
            ]);

            $message = 'Generated ' . count($new) . ' voucher(s).';
            if ($skipped) {
                $message .= ' ' . count($skipped) . ' planter(s) left out, already in a generated group: '
                    . implode('; ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? '; ...' : '') . '.';
            }

            return response()->json([
                'message' => $message,
                'batch' => $this->batchRow($batch->loadCount(['items', 'items as receivers_count' => fn ($q) => $q->whereNotNull('receiver_name')])->loadSum('items', 'net_paid')),
                'skipped' => count($skipped),
            ], 201);
        });
    }

    /** A group's planters */
    public function show(VoucherBatch $batch)
    {
        return response()->json([
            'batch' => $this->batchRow($batch->loadCount(['items', 'items as receivers_count' => fn ($q) => $q->whereNotNull('receiver_name')])->loadSum('items', 'net_paid')),
            'items' => $batch->items()->orderBy('planter_name')->get()
                ->map(fn (VoucherBatchItem $i) => [
                    'id' => $i->id,
                    'planter_code' => $i->planter_code,
                    'planter_name' => $i->planter_name,
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

    private function batchRow(VoucherBatch $b): array
    {
        return [
            'id' => $b->id,
            'crop_year' => $b->crop_year,
            'week_from' => $b->week_from,
            'week_to' => $b->week_to,
            'generated_at' => $b->created_at->format('M d, Y h:i A'),
            'generated_by' => (string) $b->generated_by,
            'planters' => (int) $b->items_count,
            'receivers' => (int) ($b->receivers_count ?? 0),
            'net_paid' => round((float) ($b->items_sum_net_paid ?? 0), 2),
        ];
    }
}
