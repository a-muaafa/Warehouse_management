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
use Illuminate\View\View;

class InventoryAuditController extends Controller
{
    public function __construct(
        protected InventoryValuationService $valuationService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = InventoryAudit::with(['warehouse', 'department', 'auditor', 'approver'])
            ->withCount('items');

        if (! $user->isAdmin() && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        $audits = $query->latest()->paginate(15);

        return view('audits.index', compact('audits'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        if (! $user->canPerformAudit()) {
            abort(403, __('ليس لديك صلاحية لإنشاء عمليات الجرد الميداني.'));
        }

        $warehouses = Warehouse::where('is_active', true)->get();
        $departments = $user->isAdmin()
            ? Department::all()
            : Department::where('id', $user->department_id)->get();

        return view('audits.create', compact('warehouses', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canPerformAudit()) {
            abort(403, __('ليس لديك صلاحية لإنشاء عمليات الجرد الميداني.'));
        }

        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'type'          => ['required', 'in:cycle_count,periodic_full,spot_check'],
            'warehouse_id'  => ['nullable', 'integer', 'exists:warehouses,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        // Non-admin users are always scoped to their own department
        if (! $user->isAdmin() && $user->department_id) {
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
            // If a warehouse is chosen, we use the items that actually have active batches there —
            // NOT all items globally — so the book quantity reflects that warehouse's stock.
            $itemsQuery = Item::query();

            if (! empty($validated['department_id'])) {
                $itemsQuery->where('department_id', $validated['department_id']);
            }

            if (! empty($validated['warehouse_id'])) {
                // Only include items with at least one active batch in this warehouse
                $itemsQuery->whereHas(
                    'batches',
                    fn ($q) => $q->where('warehouse_id', $validated['warehouse_id'])
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0)
                );
            }

            $items = $itemsQuery->get();

            foreach ($items as $item) {
                // Book quantity = sum of active batch quantities for THIS warehouse, not item.quantity globally
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
                    'counted_quantity'     => $bookQty,    // Default: counted = book (auditor will adjust)
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
        $user = $request->user();

        if (! $user->isAdmin() && $user->department_id && $audit->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك باستعراض جرد أقسام أخرى.'));
        }

        $audit->load(['warehouse', 'department', 'auditor', 'approver', 'items.item.primaryUnit']);

        return view('audits.show', compact('audit'));
    }

    public function updateCounts(Request $request, InventoryAudit $audit): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canPerformAudit()) {
            abort(403, __('ليس لديك صلاحية لتعديل نتائج الجرد.'));
        }

        if (! $user->isAdmin() && $user->department_id && $audit->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بإدخال نتائج جرد لأقسام أخرى.'));
        }

        if ($audit->status === 'reconciled') {
            return back()->with('error', __('لا يمكن تعديل جرد تمت تسويته واعتماده سابقاً.'));
        }

        $validated = $request->validate([
            'counts'                    => ['required', 'array'],
            'counts.*.id'               => ['required', 'integer', 'exists:inventory_audit_items,id'],
            'counts.*.counted_quantity' => ['required', 'numeric', 'min:0'],
            'counts.*.notes'            => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $audit) {
            // Collect the IDs being submitted
            $submittedIds = array_column($validated['counts'], 'id');

            // Critical: verify every submitted ID actually belongs to THIS audit, not another one.
            // This prevents a malicious or erroneous request from modifying another audit's data.
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
                $book      = (float) $auditItem->book_quantity;
                $counted   = (float) $countData['counted_quantity'];
                $diff      = $counted - $book;
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
        $user = $request->user();

        if (! $user->canPerformAudit() || $user->isIntakeWorker()) {
            abort(403, __('تسوية نتائج الجرد مقتصرة على عامل الجرد والفرز ورئيس القسم والمدير العام.'));
        }

        if (! $user->isAdmin() && $user->department_id && $audit->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بتسوية جرد أقسام أخرى.'));
        }

        if ($audit->status === 'reconciled') {
            return back()->with('error', __('هذا الجرد تمت تسويته بالفعل.'));
        }

        try {
            DB::transaction(function () use ($request, $audit) {
                // Lock the audit row to prevent concurrent reconciliation
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

                    // IMPORTANT: if this is a warehouse-scoped audit, we must NOT overwrite
                    // the item's global quantity with the single-warehouse counted value.
                    // Instead we apply only the DELTA to both the global quantity and the batches.
                    if ($locked->warehouse_id) {
                        if ($diff > 0) {
                            // Surplus: create a new adjustment batch in this warehouse
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
                            // Shortage: deduct from this warehouse's batches only
                            $shortageQty = abs($diff);
                            // Use allocateOutboundStock to deduct from the correct batches
                            // Suppress DomainException here — if batches ran out, apply what we can
                            try {
                                $this->valuationService->allocateOutboundStock($item, $shortageQty, $locked->warehouse_id);
                                $item->decrement('quantity', $shortageQty);
                            } catch (\DomainException) {
                                // Partial deduction: decrement whatever is available
                                $available = (float) ItemBatch::where('item_id', $item->id)
                                    ->where('warehouse_id', $locked->warehouse_id)
                                    ->where('status', 'active')
                                    ->sum('quantity_remaining');
                                $item->decrement('quantity', min($shortageQty, $available));
                            }
                        }
                    } else {
                        // No warehouse scope: apply delta directly to global quantity
                        // Do NOT set item->quantity = counted_quantity (would clobber other warehouses)
                        if ($diff > 0) {
                            $item->increment('quantity', $diff);
                        } else {
                            $item->decrement('quantity', abs($diff));
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
