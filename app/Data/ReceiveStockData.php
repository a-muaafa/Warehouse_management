<?php

namespace App\Data;

use App\Http\Requests\ReceiveStockRequest;

final readonly class ReceiveStockData
{
    public function __construct(
        public int $itemId,
        public ?int $warehouseId,
        public string $quantity,
        public string $reason = 'purchase',
        public ?float $unitPrice = null,
        public ?int $supplierId = null,
        public ?int $unitId = null,
        public ?string $batchNumber = null,
        public ?string $manufactureDate = null,
        public ?string $expiryDate = null,
        public ?string $referenceDoc = null,
        public ?string $documentType = null,
        public ?string $idempotencyKey = null,
        public ?string $notes = null,
    ) {}

    public static function fromRequest(ReceiveStockRequest $request): self
    {
        $v = $request->validated();

        return new self(
            itemId: (int) $v['item_id'],
            warehouseId: isset($v['warehouse_id']) ? (int) $v['warehouse_id'] : null,
            quantity: (string) $v['quantity'],
            reason: (string) ($v['movement_reason'] ?? 'purchase'),
            unitPrice: isset($v['unit_price']) ? (float) $v['unit_price'] : null,
            supplierId: isset($v['supplier_id']) ? (int) $v['supplier_id'] : null,
            unitId: isset($v['unit_id']) ? (int) $v['unit_id'] : null,
            batchNumber: $v['batch_number'] ?? null,
            manufactureDate: $v['manufacture_date'] ?? null,
            expiryDate: $v['expiry_date'] ?? null,
            referenceDoc: $v['reference_doc'] ?? null,
            documentType: $v['document_type'] ?? null,
            idempotencyKey: $v['idempotency_key'] ?? null,
            notes: $v['notes'] ?? null,
        );
    }
}
