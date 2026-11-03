<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('reports.view');

        $user = $request->user();
        $departmentId = $user->department_id;

        $transactions = Transaction::with(['item', 'user', 'warehouse'])
            ->when(! $user->isSuperAdmin() && $departmentId, fn ($q) => $q->whereHas('item', fn ($sq) => $sq->where('department_id', $departmentId)))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('item_id'), fn ($q) => $q->where('item_id', $request->item_id))
            ->latest()
            ->paginate(25);

        $items = Item::when(! $user->isSuperAdmin() && $departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('name')
            ->get();

        $summary = [
            'total_in' => Transaction::when(! $user->isSuperAdmin() && $departmentId, fn ($q) => $q->whereHas('item', fn ($sq) => $sq->where('department_id', $departmentId)))
                ->where('type', 'in')->sum('quantity'),
            'total_out' => Transaction::when(! $user->isSuperAdmin() && $departmentId, fn ($q) => $q->whereHas('item', fn ($sq) => $sq->where('department_id', $departmentId)))
                ->where('type', 'out')->sum('quantity'),
            'low_stock_count' => Item::when(! $user->isSuperAdmin() && $departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->whereColumn('quantity', '<=', 'min_alert_level')->count(),
        ];

        return view('supervisor.reports.index', compact('transactions', 'items', 'summary'));
    }

    public function exportExcel(Request $request)
    {
        Gate::authorize('reports.export');

        $user = $request->user();
        $departmentId = $user->department_id;

        $transactions = Transaction::with(['item', 'user'])
            ->when(! $user->isSuperAdmin() && $departmentId, fn ($q) => $q->whereHas('item', fn ($sq) => $sq->where('department_id', $departmentId)))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('item_id'), fn ($q) => $q->where('item_id', $request->item_id))
            ->latest()
            ->get();

        $fileName = 'inventory_report_'.date('Y-m-d_H-i').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel Arabic compatibility
            fprintf($file, "\xEF\xBB\xBF");

            // CSV Column Header
            fputcsv($file, [
                '#',
                __('Item Name') ?? 'اسم الصنف',
                __('Type') ?? 'نوع الحركة',
                __('Quantity') ?? 'الكمية',
                __('Notes') ?? 'ملاحظات',
                __('User') ?? 'المستخدم',
                __('Date') ?? 'التاريخ',
            ]);

            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->id,
                    $tx->item->name ?? '',
                    $tx->type === 'in' ? (__('Stock In') ?? 'إدخال') : (__('Stock Out') ?? 'صرف'),
                    $tx->quantity.' '.($tx->item->unit ?? ''),
                    $tx->notes ?? '',
                    $tx->user->name ?? '',
                    $tx->created_at ? $tx->created_at->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
