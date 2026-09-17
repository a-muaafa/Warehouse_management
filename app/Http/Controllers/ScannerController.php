<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScannerController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();

        return view('scanner.index', compact('warehouses', 'suppliers', 'customers'));
    }

    /**
     * Fast Barcode / QR lookup via AJAX
     */
    public function lookup(Request $request): JsonResponse
    {
        $code = trim($request->input('code', ''));

        if (empty($code)) {
            return response()->json(['success' => false, 'message' => __('Please provide a barcode or QR code.')], 422);
        }

        $item = Item::with(['category', 'primaryUnit', 'department', 'batches' => fn ($q) => $q->active()])
            ->where('barcode', $code)
            ->orWhere('sku', $code)
            ->first();

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => __('Item not found for code: :code', ['code' => $code]),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'sku' => $item->sku,
                'unit' => $item->primaryUnit ? $item->primaryUnit->symbol : $item->unit,
                'quantity' => $item->quantity,
                'purchase_price' => $item->purchase_price,
                'selling_price' => $item->selling_price,
                'reorder_point' => $item->reorder_point,
                'department' => $item->department ? $item->department->name : '-',
                'category' => $item->category ? $item->category->name : '-',
                'batches' => $item->batches->map(fn ($b) => [
                    'batch_number' => $b->batch_number,
                    'quantity_remaining' => $b->quantity_remaining,
                    'expiry_date' => $b->expiry_date ? $b->expiry_date->format('Y-m-d') : '-',
                    'storage_location' => $b->storage_location ?: '-',
                ]),
            ],
        ]);
    }
}
