<?php

namespace App\Data;

use App\Http\Requests\IssueStockRequest;

final readonly class IssueStockData
{
    public function __construct(
        public int $itemId,
        public ?int $warehouseId,
        public string $quantity,
        public string $reason = 'sale_delivery',
        public ?float $unitPrice = null,
        public ?int $customerId = null,
        public ?int $unitId = null,
        public ?string $referenceDoc = null,
        public ?string $documentType = null,
        public ?string $idempotencyKey = null,
        public ?string $notes = null,
    ) {}

    public static function fromRequest(IssueStockRequest $request): self
    {
        $v = $request->validated();

        return new self(
            itemId: (int) $v['item_id'],
            warehouseId: isset($v['warehouse_id']) ? (int) $v['warehouse_id'] : null,
            quantity: (string) $v['quantity'],
            reason: (string) ($v['movement_reason'] ?? 'sale_delivery'),
            unitPrice: isset($v['unit_price']) ? (float) $v['unit_price'] : null,
            customerId: isset($v['customer_id']) ? (int) $v['customer_id'] : null,
            unitId: isset($v['unit_id']) ? (int) $v['unit_id'] : null,
            referenceDoc: $v['reference_doc'] ?? null,
            documentType: $v['document_type'] ?? null,
            idempotencyKey: $v['idempotency_key'] ?? null,
            notes: $v['notes'] ?? null,
        );
    }
}

