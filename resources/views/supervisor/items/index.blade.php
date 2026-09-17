@extends('layouts.app')
@section('title', __('Inventory Items & Product Catalog'))
@section('page-title', __('Inventory Items & Product Catalog'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Items') }}</span>
@endsection

@section('topbar-actions')
<a href="{{ route('supervisor.items.create') }}" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    {{ __('Add New Item') }}
</a>
@endsection

@section('content')
<div class="filter-bar mb-4">
    <form method="GET" action="{{ route('supervisor.items.index') }}" class="filter-form d-flex gap-2 flex-wrap">
        <div class="search-wrapper flex-grow-1">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="search" value="{{ request('search') }}" class="form-input search-input" placeholder="{{ __('Search by item name, SKU, or barcode...') }}">
        </div>

        <select name="category_id" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('All Categories') }}</option>
            @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>

        <label class="form-check" style="margin: auto 0;">
            <input type="checkbox" name="alert" value="1" {{ request('alert') ? 'checked' : '' }} onchange="this.form.submit()">
            <span class="form-check-label">{{ __('Low Stock / Reorder Needed') }}</span>
        </label>

        <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
        @if(request()->hasAny(['search', 'category_id', 'alert']))
        <a href="{{ route('supervisor.items.index') }}" class="btn btn-secondary">{{ __('Reset') }}</a>
        @endif
    </form>
</div>

<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Item Name & Category') }}</th>
                        <th>{{ __('Barcode & SKU') }}</th>
                        <th>{{ __('Unit') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Purchase Price') }}</th>
                        <th>{{ __('Selling Price') }}</th>
                        <th>{{ __('Total Valuation') }}</th>
                        <th>{{ __('Valuation Method') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr class="{{ $item->isLowStock() ? 'row-alert' : '' }}">
                        <td class="font-medium">
                            <div style="font-weight:600;">{{ $item->name }}</div>
                            <small class="text-muted">{{ $item->category?->name ?: __('General') }}</small>
                        </td>
                        <td>
                            <code class="barcode-text">{{ $item->barcode }}</code>
                            @if($item->sku)<div style="font-size:0.75rem; color:#888;">رمز الصنف: {{ $item->sku }}</div>@endif
                        </td>
                        <td>{{ $item->primaryUnit?->symbol ?: __($item->unit) }}</td>
                        <td class="font-medium {{ $item->isLowStock() ? 'text-danger' : '' }}">
                            <strong>{{ $item->quantity }}</strong>
                            <div style="font-size:0.75rem; color:#888;">{{ __('Min:') }} {{ $item->min_alert_level }} | {{ __('Reorder:') }} {{ $item->reorder_point }}</div>
                        </td>
                        <td>{{ number_format($item->purchase_price, 2) }} ر.س</td>
                        <td>{{ number_format($item->selling_price, 2) }} ر.س</td>
                        <td><strong>{{ number_format($item->total_valuation, 2) }} ر.س</strong></td>
                        <td>
                            <span class="badge badge-neutral">{{ strtoupper($item->valuation_method ?: 'FIFO') === 'LIFO' ? __('LIFO (Last-In, First-Out)') : (strtoupper($item->valuation_method ?: 'FIFO') === 'AVG' ? __('Weighted Average Cost') : __('FIFO (First-In, First-Out)')) }}</span>
                        </td>
                        <td>
                            @if($item->isLowStock())
                            <span class="badge badge-danger">{{ __('Low Stock') }}</span>
                            @elseif($item->needsReorder())
                            <span class="badge badge-warning">{{ __('Reorder Point') }}</span>
                            @else
                            <span class="badge badge-success">{{ __('In Stock') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="{{ route('worker.labels.print', $item) }}" class="btn btn-secondary btn-sm" target="_blank" title="{{ __('Print Label') }}" style="display:inline-flex; align-items:center;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                        <rect x="6" y="14" width="12" height="8"></rect>
                                    </svg>
                                </a>
                                <a href="{{ route('supervisor.items.edit', $item) }}" class="btn btn-neutral btn-sm">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('supervisor.items.destroy', $item) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this item?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="empty-row">{{ __('No items found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
        <div class="p-3">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
