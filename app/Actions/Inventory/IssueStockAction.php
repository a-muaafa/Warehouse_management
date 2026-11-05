<?php

namespace App\Actions\Inventory;

use App\Data\IssueStockData;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MovementDocument;
use App\Models\Transaction;
use App\Models\TransactionBatchItem;
use App\Models\User;
use App\Services\InventoryValuationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class IssueStockAction
{
    public function __construct(
        private InventoryValuationService $valuation,
    ) {}

    public function execute(IssueStockData $data, ?User $actor = null): Transaction
    {
        $qty = (float) $data->quantity;

        $transaction = DB::transaction(function () use ($data, $actor, $qty): Transaction {
            /** @var Item $lockedItem */
            $lockedItem = Item::where('id', $data->itemId)->lockForUpdate()->firstOrFail();
            $qtyBefore = (float) $lockedItem->quantity;

            if ($qtyBefore < $qty) {
                throw new \DomainException(
                    __('الكمية المتوفرة بالمخزون غير كافية. المتوفر: :qty', ['qty' => $lockedItem->quantity])
                );
            }

            $allocations = $this->valuation->allocateOutboundStock(
                $lockedItem,
                $qty,
                $data->warehouseId
            );

            $lockedItem->decrement('quantity', $qty);

            $batchId = count($allocations) === 1 ? $allocations[0]['batch_id'] : null;

            $unitPrice = $data->unitPrice !== null && $data->unitPrice > 0
                ? $data->unitPrice
                : ((float) $lockedItem->selling_price > 0
                    ? (float) $lockedItem->selling_price
                    : (float) (collect($allocations)->avg('unit_cost') ?? 0));

            $transaction = Transaction::create([
                'item_id' => $lockedItem->id,
                'user_id' => $actor?->id,
                'warehouse_id' => $data->warehouseId,
                'item_batch_id' => $batchId,
                'customer_id' => $data->customerId,
                'unit_id' => $data->unitId ?? $lockedItem->unit_id,
                'type' => 'out',
                'movement_reason' => $data->reason ?: 'sale_delivery',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_amount' => round($qty * $unitPrice, 2),
                'reference_doc' => $data->referenceDoc,
                'idempotency_key' => $data->idempotencyKey,
                'notes' => $data->notes,
                'status' => 'completed',
            ]);

            $runningBefore = $qtyBefore;
            foreach ($allocations as $alloc) {
                TransactionBatchItem::create([
                    'transaction_id' => $transaction->id,
                    'item_batch_id' => $alloc['batch_id'],
                    'quantity' => $alloc['quantity'],
                    'unit_cost' => $alloc['unit_cost'],
                    'total_cost' => $alloc['total_cost'],
                ]);

                $runningAfter = round($runningBefore - (float) $alloc['quantity'], 4);
                InventoryMovement::record([
                    'reference_number' => $transaction->reference_doc ?: ('TX-'.$transaction->id),
                    'movement_type' => 'issue',
                    'source_type' => Transaction::class,
                    'source_id' => $transaction->id,
                    'item_id' => $lockedItem->id,
                    'warehouse_id' => $data->warehouseId,
                    'item_batch_id' => $alloc['batch_id'],
                    'quantity_before' => $runningBefore,
                    'quantity_change' => -(float) $alloc['quantity'],
                    'quantity_after' => $runningAfter,
                    'unit_cost' => $alloc['unit_cost'],
                    'total_cost' => $alloc['total_cost'],
                    'performed_by' => $actor?->id,
                    'notes' => $data->notes ?? "Outbound issue from batch {$alloc['batch_number']}",
                ]);
                $runningBefore = $runningAfter;
            }

            if (! empty($data->referenceDoc)) {
                MovementDocument::create([
                    'transaction_id' => $transaction->id,
                    'document_type' => $data->documentType ?? 'delivery_note',
                    'document_number' => $data->referenceDoc,
                    'reference_party' => $data->customerId ? 'العميل #'.$data->customerId : null,
                    'notes' => $data->notes,
                ]);
            }

            $transaction->setRelation('item', $lockedItem->fresh());

            return $transaction;
        });

        Cache::forget('dashboard_kpis');

        return $transaction;
    }
}
