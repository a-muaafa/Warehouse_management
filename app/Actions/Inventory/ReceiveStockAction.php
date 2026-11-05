<?php

namespace App\Actions\Inventory;

use App\Data\ReceiveStockData;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\MovementDocument;
use App\Models\Transaction;
use App\Models\TransactionBatchItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class ReceiveStockAction
{
    public function execute(ReceiveStockData $data, ?User $actor = null): Transaction
    {
        $qty = (float) $data->quantity;

        $transaction = DB::transaction(function () use ($data, $actor, $qty): Transaction {
            /** @var Item $lockedItem */
            $lockedItem = Item::where('id', $data->itemId)->lockForUpdate()->firstOrFail();
            $qtyBefore = (float) $lockedItem->quantity;

            $unitPrice = $data->unitPrice !== null && $data->unitPrice > 0
                ? $data->unitPrice
                : (float) $lockedItem->purchase_price;

            $batchNumber = ! empty($data->batchNumber)
                ? $data->batchNumber
                : 'LOT-'.date('Ymd').'-'.strtoupper(uniqid());

            $batch = ItemBatch::create([
                'item_id' => $lockedItem->id,
                'warehouse_id' => $data->warehouseId,
                'supplier_id' => $data->supplierId,
                'batch_number' => $batchNumber,
                'purchase_cost' => $unitPrice,
                'quantity_received' => $qty,
                'quantity_remaining' => $qty,
                'manufacture_date' => $data->manufactureDate,
                'expiry_date' => $data->expiryDate,
                'received_at' => now(),
                'status' => 'active',
            ]);

            $lockedItem->increment('quantity', $qty);

            if ($unitPrice > 0) {
                $lockedItem->purchase_price = $unitPrice;
                $lockedItem->save();
            }

            $transaction = Transaction::create([
                'item_id' => $lockedItem->id,
                'user_id' => $actor?->id,
                'warehouse_id' => $data->warehouseId,
                'item_batch_id' => $batch->id,
                'supplier_id' => $data->supplierId,
                'unit_id' => $data->unitId ?? $lockedItem->unit_id,
                'type' => 'in',
                'movement_reason' => $data->reason ?: 'purchase',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_amount' => round($qty * $unitPrice, 2),
                'reference_doc' => $data->referenceDoc,
                'idempotency_key' => $data->idempotencyKey,
                'notes' => $data->notes,
                'status' => 'completed',
            ]);

            $batch->update(['transaction_id' => $transaction->id]);

            TransactionBatchItem::create([
                'transaction_id' => $transaction->id,
                'item_batch_id' => $batch->id,
                'quantity' => $qty,
                'unit_cost' => $unitPrice,
                'total_cost' => round($qty * $unitPrice, 4),
            ]);

            InventoryMovement::record([
                'reference_number' => $transaction->reference_doc ?: ('TX-'.$transaction->id),
                'movement_type' => 'receipt',
                'source_type' => Transaction::class,
                'source_id' => $transaction->id,
                'item_id' => $lockedItem->id,
                'warehouse_id' => $data->warehouseId,
                'item_batch_id' => $batch->id,
                'quantity_before' => $qtyBefore,
                'quantity_change' => $qty,
                'quantity_after' => round($qtyBefore + $qty, 4),
                'unit_cost' => $unitPrice,
                'total_cost' => round($qty * $unitPrice, 4),
                'performed_by' => $actor?->id,
                'notes' => $data->notes ?? 'Inbound receipt',
            ]);

            if (! empty($data->referenceDoc)) {
                MovementDocument::create([
                    'transaction_id' => $transaction->id,
                    'document_type' => $data->documentType ?? 'delivery_note',
                    'document_number' => $data->referenceDoc,
                    'reference_party' => $data->supplierId ? 'المورد #'.$data->supplierId : null,
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

