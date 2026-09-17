@extends('layouts.app')
@section('title', $warehouse->name)
@section('page-title', $warehouse->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('warehouses.index') }}">{{ __('Warehouses') }}</a> / <span>{{ $warehouse->code }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-secondary">{{ __('Edit Facility') }}</a>
@endsection

@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">{{ __('Total Valuation') }}</div>
            <div class="stat-value text-success">{{ number_format($warehouse->total_valuation, 2) }} ر.س</div>
            <div class="stat-desc">{{ __('Active Inventory Assets') }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">{{ __('Stock Units') }}</div>
            <div class="stat-value">{{ number_format($warehouse->total_stock_quantity) }}</div>
            <div class="stat-desc">{{ __('Across all active lots') }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">{{ __('Monthly Holding Overhead') }}</div>
            <div class="stat-value text-warning">{{ number_format($costSummary['total_monthly_holding'] ?? 0, 2) }} ر.س</div>
            <div class="stat-desc">{{ __('Space & carrying expenses') }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-label">{{ __('Storage Capacity') }}</div>
            <div class="stat-value">{{ number_format($warehouse->capacity_sqm) }} م²</div>
            <div class="stat-desc">{{ $warehouse->location_address ?: __('Main Facility') }}</div>
        </div>
    </div>
</div>

<div class="card glass-card">
    <div class="card-header">
        <h3 class="card-title">{{ __('Active Inventory Batches in this Warehouse') }}</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Batch / Lot #') }}</th>
                        <th>{{ __('Quantity Remaining') }}</th>
                        <th>{{ __('Unit Cost') }}</th>
                        <th>{{ __('Total Value') }}</th>
                        <th>{{ __('Expiry Date') }}</th>
                        <th>{{ __('Bin / Rack') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($warehouse->batches as $batch)
                    <tr>
                        <td class="font-medium">{{ $batch->item?->name }}</td>
                        <td><code>{{ $batch->batch_number }}</code></td>
                        <td>
                            <span class="badge badge-info">{{ $batch->quantity_remaining }}</span>
                        </td>
                        <td>{{ number_format($batch->purchase_cost, 2) }} ر.س</td>
                        <td><strong>{{ number_format($batch->quantity_remaining * $batch->purchase_cost, 2) }} ر.س</strong></td>
                        <td>
                            @if($batch->isExpired())
                            <span class="badge badge-danger">{{ $batch->expiry_date?->format('Y-m-d') }} ({{ __('Expired') }})</span>
                            @elseif($batch->isExpiringSoon(30))
                            <span class="badge badge-warning">{{ $batch->expiry_date?->format('Y-m-d') }} ({{ __('Expiring Soon') }})</span>
                            @else
                            {{ $batch->expiry_date?->format('Y-m-d') ?: '-' }}
                            @endif
                        </td>
                        <td><code>{{ $batch->storage_location ?: '-' }}</code></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-row">{{ __('No active batches in this warehouse.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
