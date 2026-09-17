<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Transaction;
use App\Models\TransferItem;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseTransferController extends Controller
{
    public function index(): View
    {
        $transfers = WarehouseTransfer::with(['fromWarehouse', 'toWarehouse', 'requester', 'items.item'])
            ->latest()
            ->paginate(15);

        return view('transfers.index', compact('transfers'));
    }

    public function create(): View
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        $items = Item::where('quantity', '>', 0)->with('primaryUnit')->get();

        return view('transfers.create', compact('warehouses', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id|different:to_warehouse_id',
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:1',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $transferNumber = 'TRF-'.strtoupper(uniqid());

            $transfer = WarehouseTransfer::create([
                'transfer_number' => $transferNumber,
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'status' => 'pending',
                'requested_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $item = Item::findOrFail($itemData['item_id']);
                TransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'item_id' => $item->id,
                    'quantity' => $itemData['quantity'],
                    'unit_cost' => $item->purchase_price ?: 0,
                ]);
            }
        });

        return redirect()->route('transfers.index')->with('success', __('Internal stock transfer requested successfully.'));
    }

    public function show(WarehouseTransfer $transfer): View
    {
        $transfer->load(['fromWarehouse', 'toWarehouse', 'requester', 'dispatcher', 'receiver', 'items.item', 'items.batch']);

        return view('transfers.show', compact('transfer'));
    }

    /**
     * Dispatch items from source warehouse
     */
    public function dispatchTransfer(Request $request, WarehouseTransfer $transfer): RedirectResponse
    {
        if ($transfer->status !== 'pending') {
            return back()->with('error', __('Transfer cannot be dispatched in its current state.'));
        }

        DB::transaction(function () use ($request, $transfer) {
            $transfer->update([
                'status' => 'in_transit',
                'dispatched_by' => $request->user()->id,
                'dispatched_at' => now(),
            ]);

            foreach ($transfer->items as $transferItem) {
                // Log outbound transaction from source warehouse
                Transaction::create([
                    'item_id' => $transferItem->item_id,
                    'user_id' => $request->user()->id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'type' => 'out',
                    'movement_reason' => 'transfer_out',
                    'quantity' => $transferItem->quantity,
                    'unit_price' => $transferItem->unit_cost,
                    'total_amount' => $transferItem->quantity * $transferItem->unit_cost,
                    'reference_doc' => $transfer->transfer_number,
                    'notes' => 'Dispatched transfer to '.$transfer->toWarehouse->name,
                    'status' => 'completed',
                ]);
            }
        });

        return back()->with('success', __('Transfer marked as In-Transit.'));
    }

    /**
     * Receive items at destination warehouse
     */
    public function receiveTransfer(Request $request, WarehouseTransfer $transfer): RedirectResponse
    {
        if ($transfer->status !== 'in_transit') {
            return back()->with('error', __('Only in-transit transfers can be received.'));
        }

        DB::transaction(function () use ($request, $transfer) {
            $transfer->update([
                'status' => 'completed',
                'received_by' => $request->user()->id,
                'received_at' => now(),
            ]);

            foreach ($transfer->items as $transferItem) {
                $transferItem->update([
                    'received_quantity' => $transferItem->quantity,
                ]);

                // Create or update destination warehouse batch
                ItemBatch::create([
                    'item_id' => $transferItem->item_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'batch_number' => 'TRF-'.$transfer->transfer_number,
                    'purchase_cost' => $transferItem->unit_cost,
                    'quantity_received' => $transferItem->quantity,
                    'quantity_remaining' => $transferItem->quantity,
                    'status' => 'active',
                ]);

                // Log inbound transaction into destination warehouse
                Transaction::create([
                    'item_id' => $transferItem->item_id,
                    'user_id' => $request->user()->id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'type' => 'in',
                    'movement_reason' => 'transfer_in',
                    'quantity' => $transferItem->quantity,
                    'unit_price' => $transferItem->unit_cost,
                    'total_amount' => $transferItem->quantity * $transferItem->unit_cost,
                    'reference_doc' => $transfer->transfer_number,
                    'notes' => 'Received transfer from '.$transfer->fromWarehouse->name,
                    'status' => 'completed',
                ]);
            }
        });

        return back()->with('success', __('Stock transfer received and reconciled successfully.'));
    }
}
