<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ItemCategory::with('parent')->withCount('items')->latest()->paginate(15);

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        $parentCategories = ItemCategory::all();

        return view('categories.create', compact('parentCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:item_categories,code',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:item_categories,id',
        ]);

        ItemCategory::create($validated);

        return redirect()->route('categories.index')->with('success', __('Item category created successfully.'));
    }

    public function edit(ItemCategory $category): View
    {
        $parentCategories = ItemCategory::where('id', '!=', $category->id)->get();

        return view('categories.edit', compact('category', 'parentCategories'));
    }

    public function update(Request $request, ItemCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:item_categories,code,'.$category->id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:item_categories,id|different:'.$category->id,
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')->with('success', __('Item category updated successfully.'));
    }

    public function destroy(ItemCategory $category): RedirectResponse
    {
        if ($category->items()->exists() || $category->children()->exists()) {
            return back()->with('error', __('Cannot delete category containing items or child categories.'));
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', __('Category deleted successfully.'));
    }
}
