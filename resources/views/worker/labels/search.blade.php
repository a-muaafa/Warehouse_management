@extends('layouts.app')
@section('title', __('Print Barcode & QR Labels'))
@section('page-title', __('Print Barcode & QR Labels'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Print Labels') }}</span>
@endsection

@section('content')
<div class="filter-bar mb-4">
    <form method="GET" action="{{ route('worker.labels.search') }}" class="filter-form d-flex gap-2 flex-wrap">
        <div class="search-wrapper flex-grow-1">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="search" value="{{ request('search') }}" class="form-input search-input" placeholder="{{ __('Search by item name, SKU, or barcode...') }}" autofocus>
        </div>
        <select name="category_id" class="form-select" style="max-width: 220px;" onchange="this.form.submit()">
            <option value="">{{ __('All Categories') }}</option>
            @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
        @if(request()->hasAny(['search', 'category_id']))
        <a href="{{ route('worker.labels.search') }}" class="btn btn-secondary">{{ __('Reset') }}</a>
        @endif
    </form>
</div>

<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Item & Category') }}</th>
                        <th>{{ __('Barcode & SKU') }}</th>
                        <th>{{ __('Current Stock') }}</th>
                        <th>{{ __('Unit Price') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td class="font-medium">
                            <div style="font-weight:600;">{{ $item->name }}</div>
                            <small class="text-muted">{{ $item->category?->name ?: __('General') }}</small>
                        </td>
                        <td>
                            <code class="barcode-text" style="display:block;">{{ $item->barcode }}</code>
                            @if($item->sku)<small class="text-muted">رمز الصنف: {{ $item->sku }}</small>@endif
                        </td>
                        <td>
                            <span class="badge {{ $item->isLowStock() ? 'badge-danger' : 'badge-success' }}">
                                {{ $item->quantity }} {{ $item->primaryUnit?->symbol ?: __($item->unit) }}
                            </span>
                        </td>
                        <td>{{ number_format($item->selling_price, 2) }} ر.س</td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('worker.labels.print', ['item' => $item->id, 'template' => 'shelf_label']) }}" class="btn btn-primary btn-sm" target="_blank" title="{{ __('Print Shelf Label') }}">
                                    {{ __('Shelf Label') }}
                                </a>
                                <a href="{{ route('worker.labels.print', ['item' => $item->id, 'template' => 'product_tag']) }}" class="btn btn-secondary btn-sm" target="_blank" title="{{ __('Print Product Tag') }}">
                                    {{ __('Product Tag') }}
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="empty-row">{{ __('No items found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items instanceof \Illuminate\Pagination\LengthAwarePaginator && $items->hasPages())
        <div class="p-3">
            {{ $items->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
