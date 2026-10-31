<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Services\InventoryValuationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryAuditController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InventoryAudit::class);

        $user = $request->user();

        $query = InventoryAudit::with(['warehouse', 'department', 'auditor', 'approver'])
            ->withCount('items');

        if (! $user->isSuperAdmin()) {
            $accessibleIds = $user->accessibleWarehouseIds();
            $query->where(function ($q) use ($accessibleIds, $user) {
                if (! empty($accessibleIds)) {
                    $q->whereIn('warehouse_id', $accessibleIds)
                        ->orWhereNull('warehouse_id');
                }
                if ($user->department_id) {
                    $q->where('department_id', $user->department_id);
                }
            });
        }

        $audits = $query->latest()->paginate(15);

        return view('audits.index', compact('audits'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', InventoryAudit::class);

        $user = $request->user();

        $warehousesQuery = Warehouse::where('is_active', true);
        if (! $user->isSuperAdmin()) {
            $warehousesQuery->whereIn('id', $user->accessibleWarehouseIds());
        }
        $warehouses = $warehousesQuery->get();

        $departments = $user->isSuperAdmin()
            ? Department::all()
            : Department::where('id', $user->department_id)->get();

        return view('audits.create', compact('warehouses', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', InventoryAudit::class);

        $user = $request->user();

        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'type'          => ['required', 'in:cycle_count,periodic_full,spot_check'],
            'warehouse_id'  => ['nullable', 'integer', 'exists:warehouses,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        if (! empty($validated['warehouse_id']) && ! $user->canAccessWarehouse((int) $validated['warehouse_id'])) {
            abort(403, __('غير مصرح لك بإجراء جرد في مستودع لا يقع ضمن صلاحياتك.'));
        }

        // Non-admin users are always scoped to their own department
        if (! $user->isSuperAdmin() && $user->department_id) {
            $validated['department_id'] = $user->department_id;
        }

        $auditNumber = 'AUD-'.date('Ymd').'-'.strtoupper(uniqid());

        $audit = DB::transaction(function () use ($validated, $auditNumber, $request) {
            $audit = InventoryAudit::create([
                'audit_number' => $auditNumber,
                'title'        => $validated['title'],
                'type'         => $validated['type'],
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'department_id'=> $validated['department_id'] ?? null,
                'status'       => 'in_progress',
                'audited_by'   => $request->user()->id,
                'started_at'   => now(),
                'notes'        => $validated['notes'] ?? null,
            ]);

            // Scope audit items to the selected warehouse and/or department.
            $itemsQuery = Item::query();

            if (! empty($validated['department_id'])) {
                $itemsQuery->where('department_id', $validated['department_id']);
            }

            if (! empty($validated['warehouse_id'])) {
                $itemsQuery->whereHas(
                    'batches',
                    fn ($q) => $q->where('warehouse_id', $validated['warehouse_id'])
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0)
                );
            }

            $items = $itemsQuery->get();

            foreach ($items as $item) {
                if (! empty($validated['warehouse_id'])) {
                    $bookQty = (float) ItemBatch::where('item_id', $item->id)
                        ->where('warehouse_id', $validated['warehouse_id'])
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0)
                        ->sum('quantity_remaining');
                } else {
                    $bookQty = (float) $item->quantity;
                }

                InventoryAuditItem::create([
                    'inventory_audit_id'  => $audit->id,
                    'item_id'             => $item->id,
                    'book_quantity'        => $bookQty,
                    'counted_quantity'     => $bookQty,
                    'discrepancy_quantity' => 0,
                    'unit_cost'            => (float) ($item->purchase_price ?: 0),
                    'discrepancy_value'    => 0,
                    'status'               => 'matched',
                ]);
            }

            return $audit;
        });

        ActivityLog::log('بدء جرد ميداني', "تم بدء عملية الجرد رقم ({$audit->audit_number}): {$audit->title}", $audit->department_id);

        return redirect()->route('audits.show', $audit)
            ->with('success', __('تم إنشاء جرد ميداني جديد بنجاح.'));
    }

    public function show(Request $request, InventoryAudit $audit): View
    {
        Gate::authorize('view', $audit);

        $audit->load(['warehouse', 'department', 'auditor', 'approver', 'items.item.primaryUnit']);

        return view('audits.show', compact('audit'));
    }

    public function updateCounts(Request $request, InventoryAudit $audit): RedirectResponse
    {
        Gate::authorize('count', $audit);

        $validated = $request->validate([
            'counts'                    => ['required', 'array'],
            'counts.*.id'               => ['required', 'integer', 'exists:inventory_audit_items,id'],
            'counts.*.counted_quantity' => ['required', 'numeric', 'min:0'],
            'counts.*.notes'            => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $audit) {
            $submittedIds = array_column($validated['counts'], 'id');

            $validIds = InventoryAuditItem::where('inventory_audit_id', $audit->id)
                ->whereIn('id', $submittedIds)
                ->pluck('id')
                ->all();

            $invalidIds = array_diff($submittedIds, $validIds);
            if (! empty($invalidIds)) {
                throw new \DomainException('One or more count IDs do not belong to this audit.');
            }

            foreach ($validated['counts'] as $countData) {
                $auditItem = InventoryAuditItem::findOrFail($countData['id']);
                $book = (float) $auditItem->book_quantity;
                $counted = (float) $countData['counted_quantity'];
                $diff = $counted - $book;
                $diffValue = round($diff * (float) $auditItem->unit_cost, 2);

                $status = match (true) {
                    $diff > 0  => 'surplus',
                    $diff < 0  => 'shortage',
                    default    => 'matched',
                };

                $auditItem->update([
                    'counted_quantity'     => $counted,
                    'discrepancy_quantity' => $diff,
                    'discrepancy_value'    => $diffValue,
                    'status'               => $status,
                    'reconciliation_notes' => $countData['notes'] ?? null,
                ]);
            }
        });

        ActivityLog::log('تحديث نتائج الجرد', "تم تحديث أرقام الكميات الميدانية للجرد رقم: {$audit->audit_number}", $audit->department_id);

        return back()->with('success', __('تم تحديث نتائج الحصر والجرد الفعلي بنجاح.'));
    }

    public function reconcile(Request $request, InventoryAudit $audit): RedirectResponse
    {
        Gate::authorize('approve', $audit);

        try {
            DB::transaction(function () use ($request, $audit) {
                $locked = InventoryAudit::where('id', $audit->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'reconciled') {
                    throw new \DomainException(__('هذا الجرد تمت تسويته بالفعل (تعارض متزامن).'));
                }

                $locked->update([
                    'status'        => 'reconciled',
                    'approved_by'   => $request->user()->id,
                    'reconciled_at' => now(),
                ]);

                foreach ($locked->items as $itemRecord) {
                    $diff = (float) $itemRecord->discrepancy_quantity;
                    if ($diff === 0.0) {
                        continue;
                    }

                    $item = Item::where('id', $itemRecord->item_id)->lockForUpdate()->firstOrFail();

                    if ($locked->warehouse_id) {
                        if ($diff > 0) {
                            ItemBatch::create([
                                'item_id'           => $item->id,
                                'warehouse_id'      => $locked->warehouse_id,
                                'batch_number'      => 'ADJ-'.$locked->audit_number.'-'.strtoupper(substr(uniqid(), -4)),
                                'purchase_cost'     => (float) $itemRecord->unit_cost,
                                'quantity_received' => $diff,
                                'quantity_remaining'=> $diff,
                                'status'            => 'active',
                            ]);
                            $item->increment('quantity', $diff);
                        } else {
                            $shortageQty = abs($diff);
                            try {
                                $this->valuationService->allocateOutboundStock($item, $shortageQty, $locked->warehouse_id);
                                $item->decrement('quantity', $shortageQty);
                            } catch (\DomainException) {
                                $batches = ItemBatch::where('item_id', $item->id)
                                    ->where('warehouse_id', $locked->warehouse_id)
                                    ->where('status', 'active')
                                    ->where('quantity_remaining', '>', 0)
                                    ->get();
                                $deducted = 0;
                                foreach ($batches as $b) {
                                    $deducted += (float) $b->quantity_remaining;
                                    $b->update(['quantity_remaining' => 0, 'status' => 'depleted']);
                                }
                                $item->decrement('quantity', $deducted);
                            }
                        }
                    } else {
                        if ($diff > 0) {
                            ItemBatch::create([
                                'item_id'           => $item->id,
                                'warehouse_id'      => null,
                                'batch_number'      => 'ADJ-'.$locked->audit_number.'-'.strtoupper(substr(uniqid(), -4)),
                                'purchase_cost'     => (float) $itemRecord->unit_cost,
                                'quantity_received' => $diff,
                                'quantity_remaining'=> $diff,
                                'status'            => 'active',
                            ]);
                            $item->increment('quantity', $diff);
                        } else {
                            $shortageQty = abs($diff);
                            try {
                                $this->valuationService->allocateOutboundStock($item, $shortageQty, null);
                                $item->decrement('quantity', $shortageQty);
                            } catch (\DomainException) {
                                $batches = ItemBatch::where('item_id', $item->id)
                                    ->where('status', 'active')
                                    ->where('quantity_remaining', '>', 0)
                                    ->get();
                                $deducted = 0;
                                foreach ($batches as $b) {
                                    $deducted += (float) $b->quantity_remaining;
                                    $b->update(['quantity_remaining' => 0, 'status' => 'depleted']);
                                }
                                $item->decrement('quantity', $deducted);
                            }
                        }
                    }

                    Transaction::create([
                        'item_id'         => $item->id,
                        'user_id'         => $request->user()->id,
                        'warehouse_id'    => $locked->warehouse_id,
                        'type'            => $diff > 0 ? 'in' : 'out',
                        'movement_reason' => 'audit_adjustment',
                        'quantity'        => abs($diff),
                        'unit_price'      => (float) $itemRecord->unit_cost,
                        'total_amount'    => abs((float) $itemRecord->discrepancy_value),
                        'reference_doc'   => $locked->audit_number,
                        'notes'           => __('تسوية جرد ميداني: ').($diff > 0 ? __('فائض (+) ') : __('عجز (-) ')).abs($diff).__(' وحدة'),
                        'approved_by'     => $request->user()->id,
                        'status'          => 'completed',
                    ]);
                }
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::log('تسوية الجرد الميداني', "تم اعتماد وتسوية نتائج الجرد رقم ({$audit->audit_number})", $audit->department_id);

        return redirect()->route('audits.show', $audit)
            ->with('success', __('تمت تسوية الفروقات وتعديل أرصدة المخزون بنجاح.'));
    }
}
