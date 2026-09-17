<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryAuditController extends Controller
{
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
        if ($user->isViewOnlyWorker()) {
            abort(403, __('حسابك مخصص للعرض فقط، لا يمكن بدء عمليات الجرد الميداني.'));
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
        if ($user->isViewOnlyWorker()) {
            abort(403, __('حسابك مخصص للعرض فقط، لا يمكن بدء عمليات الجرد الميداني.'));
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:cycle_count,periodic_full,spot_check',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'department_id' => 'nullable|exists:departments,id',
            'notes' => 'nullable|string',
        ]);

        if (! $user->isAdmin() && $user->department_id) {
            $validated['department_id'] = $user->department_id;
        }

        $auditNumber = 'AUD-'.date('Ymd').'-'.strtoupper(uniqid());

        $audit = DB::transaction(function () use ($validated, $auditNumber, $request) {
            $audit = InventoryAudit::create([
                'audit_number' => $auditNumber,
                'title' => $validated['title'],
                'type' => $validated['type'],
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'status' => 'in_progress',
                'audited_by' => $request->user()->id,
                'started_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Populate audit items snapshot from existing items
            $itemsQuery = Item::query();
            if (! empty($validated['department_id'])) {
                $itemsQuery->where('department_id', $validated['department_id']);
            }

            $items = $itemsQuery->get();

            foreach ($items as $item) {
                InventoryAuditItem::create([
                    'inventory_audit_id' => $audit->id,
                    'item_id' => $item->id,
                    'book_quantity' => $item->quantity,
                    'counted_quantity' => $item->quantity,
                    'discrepancy_quantity' => 0,
                    'unit_cost' => $item->purchase_price ?: 0,
                    'discrepancy_value' => 0,
                    'status' => 'matched',
                ]);
            }

            return $audit;
        });

        ActivityLog::log('بدء جرد ميداني', "تم بدء عملية الجرد رقم ({$audit->audit_number}): {$audit->title}", $audit->department_id);

        return redirect()->route('audits.show', $audit)->with('success', __('تم إنشاء جرد ميداني جديد بنجاح.'));
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
        if ($user->isViewOnlyWorker()) {
            abort(403, __('حسابك مخصص للعرض فقط، لا يمكن تعديل كميات الجرد.'));
        }

        if (! $user->isAdmin() && $user->department_id && $audit->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بإدخال نتائج جرد لأقسام أخرى.'));
        }

        if ($audit->status === 'reconciled') {
            return back()->with('error', __('لا يمكن تعديل جرد تمت تسويته واعتماده سابقاً.'));
        }

        $validated = $request->validate([
            'counts' => 'required|array',
            'counts.*.id' => 'required|exists:inventory_audit_items,id',
            'counts.*.counted_quantity' => 'required|integer|min:0',
            'counts.*.notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['counts'] as $countData) {
                $auditItem = InventoryAuditItem::findOrFail($countData['id']);
                $book = $auditItem->book_quantity;
                $counted = (int) $countData['counted_quantity'];
                $diff = $counted - $book;
                $diffValue = $diff * (float) $auditItem->unit_cost;

                $status = 'matched';
                if ($diff > 0) {
                    $status = 'surplus';
                } elseif ($diff < 0) {
                    $status = 'shortage';
                }

                $auditItem->update([
                    'counted_quantity' => $counted,
                    'discrepancy_quantity' => $diff,
                    'discrepancy_value' => $diffValue,
                    'status' => $status,
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
        if ($user->isViewOnlyWorker() || $user->isIntakeWorker()) {
            abort(403, __('تسوية نتائج الجرد والاعتماد مقتصرة على عامل الجرد والفرز ورئيس القسم والمدير العام.'));
        }

        if (! $user->isAdmin() && $user->department_id && $audit->department_id !== $user->department_id) {
            abort(403, __('غير مسموح لك بتسوية جرد أقسام أخرى.'));
        }

        if ($audit->status === 'reconciled') {
            return back()->with('error', __('هذا الجرد تمت تسويته بالفعل.'));
        }

        DB::transaction(function () use ($request, $audit) {
            $audit->update([
                'status' => 'reconciled',
                'approved_by' => $request->user()->id,
                'reconciled_at' => now(),
            ]);

            foreach ($audit->items as $itemRecord) {
                $diff = $itemRecord->discrepancy_quantity;
                if ($diff !== 0) {
                    $item = $itemRecord->item;

                    $item->quantity = $itemRecord->counted_quantity;
                    $item->save();

                    Transaction::create([
                        'item_id' => $item->id,
                        'user_id' => $request->user()->id,
                        'warehouse_id' => $audit->warehouse_id,
                        'type' => $diff > 0 ? 'in' : 'out',
                        'movement_reason' => 'audit_adjustment',
                        'quantity' => abs($diff),
                        'unit_price' => $itemRecord->unit_cost,
                        'total_amount' => abs($itemRecord->discrepancy_value),
                        'reference_doc' => $audit->audit_number,
                        'notes' => __('تسوية جرد ميداني: ').($diff > 0 ? __('فائض (+) ') : __('عجز (-) ')).abs($diff).' '.__('وحدة'),
                        'approved_by' => $request->user()->id,
                        'status' => 'completed',
                    ]);
                }
            }
        });

        ActivityLog::log('تسوية الجرد الميداني', "تم اعتماد وتسوية نتائج الجرد رقم ({$audit->audit_number}) وتحديث أرصدة المخزون", $audit->department_id);

        return redirect()->route('audits.show', $audit)->with('success', __('تمت تسوية الفروقات وتعديل أرصدة المخزون بالدفاتر الفيلية بنجاح.'));
    }
}
