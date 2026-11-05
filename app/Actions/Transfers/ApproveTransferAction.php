<?php

namespace App\Actions\Transfers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WarehouseTransfer;
use Illuminate\Support\Facades\DB;

final readonly class ApproveTransferAction
{
    public function execute(WarehouseTransfer $transfer, User $approver): WarehouseTransfer
    {
        return DB::transaction(function () use ($transfer, $approver): WarehouseTransfer {
            $locked = WarehouseTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['draft', 'pending'], true)) {
                throw new \DomainException(__('لا يمكن اعتماد التحويل في حالته الحالية.'));
            }

            $oldStatus = $locked->status;

            $locked->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            ActivityLog::log(
                'اعتماد تحويل',
                "تم اعتماد التحويل رقم: {$locked->transfer_number}",
                null,
                $locked,
                ['status' => $oldStatus],
                ['status' => 'approved']
            );

            return $locked;
        });
    }
}
