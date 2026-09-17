<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabelController extends Controller
{
    public function print(Request $request, Item $item): View
    {
        $item->load(['primaryUnit', 'category', 'department', 'batches' => fn ($q) => $q->active()]);

        $copies = max(1, min((int) $request->input('copies', 1), 50));
        $template = $request->input('template', 'shelf_label'); // shelf_label, product_tag, qr_compact

        return view('worker.labels.print', compact('item', 'copies', 'template'));
    }

    public function search(Request $request): View
    {
        $user = $request->user();
        $items = Item::with(['primaryUnit', 'category'])
            ->when($user->isSupervisor() && ! $user->isAdmin(), fn ($q) => $q->where('department_id', $user->department_id))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('barcode', 'like', '%'.$request->search.'%')
                    ->orWhere('sku', 'like', '%'.$request->search.'%');
            }))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->orderBy('name')
            ->paginate(20);

        $categories = ItemCategory::all();

        return view('worker.labels.search', compact('items', 'categories'));
    }
}
