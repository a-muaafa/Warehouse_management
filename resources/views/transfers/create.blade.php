@extends('layouts.app')
@section('title', __('New Inter-Warehouse Stock Transfer'))
@section('page-title', __('New Inter-Warehouse Stock Transfer'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('transfers.index') }}">{{ __('Transfers') }}</a> / <span>{{ __('New Transfer') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Internal Stock Transfer Request') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('transfers.store') }}" id="transferForm">
            @csrf
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Origin / Source Warehouse') }} *</label>
                    <select name="from_warehouse_id" class="form-select" required>
                        <option value="">{{ __('Select Origin Warehouse') }}</option>
                        @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Destination Warehouse') }} *</label>
                    <select name="to_warehouse_id" class="form-select" required>
                        <option value="">{{ __('Select Destination Warehouse') }}</option>
                        @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Transfer Notes / Internal Memo') }}</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="{{ __('e.g. Stock balancing for upcoming promotion') }}"></textarea>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 style="font-size: 1rem; font-weight: 700;">{{ __('Items to Transfer') }}</h4>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnAddRow">+ {{ __('Add Item Row') }}</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table" id="transferItemsTable">
                        <thead>
                            <tr>
                                <th style="width: 60%;">{{ __('Select Item') }}</th>
                                <th style="width: 30%;">{{ __('Quantity') }}</th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody id="transferItemsBody">
                            <tr>
                                <td>
                                    <select name="items[0][item_id]" class="form-select" required>
                                        <option value="">{{ __('Choose item...') }}</option>
                                        @foreach($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }} ({{ __('Stock:') }} {{ $item->quantity }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[0][quantity]" class="form-control" min="1" value="1" required>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm btn-remove-row">×</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Submit Transfer Request') }}</button>
                <a href="{{ route('transfers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let rowIndex = 1;
    document.getElementById('btnAddRow').addEventListener('click', function() {
        const tbody = document.getElementById('transferItemsBody');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="items[${rowIndex}][item_id]" class="form-select" required>
                    <option value="">{{ __("Choose item...") }}</option>
                    @foreach($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }} ({{ __("Stock:") }} {{ $item->quantity }})</option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="number" name="items[${rowIndex}][quantity]" class="form-control" min="1" value="1" required>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm btn-remove-row">×</button>
            </td>
        `;
        tbody.appendChild(tr);
        rowIndex++;
    });

    document.getElementById('transferItemsBody').addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-row')) {
            if (document.querySelectorAll('#transferItemsBody tr').length > 1) {
                e.target.closest('tr').remove();
            }
        }
    });
</script>
@endpush
