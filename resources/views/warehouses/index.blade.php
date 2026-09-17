@extends('layouts.app')
@section('title', __('Multi-Warehouse Management'))
@section('page-title', __('Multi-Warehouse Management'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Warehouses') }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('warehouses.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('Add Warehouse') }}
    </a>
@endsection

@section('content')
<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Warehouse Name & Code') }}</th>
                        <th>{{ __('Location / Address') }}</th>
                        <th>{{ __('Capacity') }}</th>
                        <th>{{ __('Active Batches') }}</th>
                        <th>{{ __('Total Inventory Valuation') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($warehouses as $wh)
                    <tr>
                        <td>
                            <div style="font-weight:700;">{{ $wh->name }}</div>
                            <code>{{ $wh->code }}</code>
                        </td>
                        <td>{{ $wh->location_address ?: '-' }}</td>
                        <td>{{ number_format($wh->capacity_sqm) }} م²</td>
                        <td>
                            <span class="badge badge-info">{{ $wh->active_batches_count }} {{ __('batches') }}</span>
                        </td>
                        <td>
                            <strong>{{ number_format($wh->total_valuation, 2) }} ر.س</strong>
                        </td>
                        <td>
                            @if($wh->is_active)
                            <span class="badge badge-success">{{ __('Active') }}</span>
                            @else
                            <span class="badge badge-danger">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('warehouses.show', $wh) }}" class="btn btn-secondary btn-sm">{{ __('Overview') }}</a>
                                <a href="{{ route('warehouses.edit', $wh) }}" class="btn btn-neutral btn-sm">{{ __('Edit') }}</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-row">{{ __('No warehouses configured yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($warehouses->hasPages())
        <div class="p-3">
            {{ $warehouses->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
