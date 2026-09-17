@extends('layouts.app')
@section('title', __('Batches & Expiration Tracking'))
@section('page-title', __('Batches & Expiration Tracking'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Batches') }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('batches.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('Register Batch / Lot') }}
    </a>
@endsection

@section('content')
<!-- KPI Filter Tabs -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="{{ route('batches.index', ['filter' => 'all']) }}" class="stat-card" style="text-decoration:none; display:block; border-bottom: 3px solid {{ $filter === 'all' ? 'var(--color-primary)' : 'transparent' }};">
            <div class="stat-label">{{ __('Active Batches') }}</div>
            <div class="stat-value">{{ $counts['active'] }}</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('batches.index', ['filter' => 'expiring_soon']) }}" class="stat-card" style="text-decoration:none; display:block; border-bottom: 3px solid {{ $filter === 'expiring_soon' ? '#f59e0b' : 'transparent' }};">
            <div class="stat-label">{{ __('Expiring Soon (< 30 days)') }}</div>
            <div class="stat-value text-warning">{{ $counts['expiring_soon'] }}</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('batches.index', ['filter' => 'expired']) }}" class="stat-card" style="text-decoration:none; display:block; border-bottom: 3px solid {{ $filter === 'expired' ? '#ef4444' : 'transparent' }};">
            <div class="stat-label">{{ __('Expired Batches') }}</div>
            <div class="stat-value text-danger">{{ $counts['expired'] }}</div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('batches.index', ['filter' => 'quarantined']) }}" class="stat-card" style="text-decoration:none; display:block; border-bottom: 3px solid {{ $filter === 'quarantined' ? '#8b5cf6' : 'transparent' }};">
            <div class="stat-label">{{ __('Quarantined / Hold') }}</div>
            <div class="stat-value text-purple">{{ $counts['quarantined'] }}</div>
        </a>
    </div>
</div>

<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Batch / Lot Number') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Quantity Remaining') }}</th>
                        <th>{{ __('Unit Cost') }}</th>
                        <th>{{ __('Expiry Date') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                    <tr>
                        <td class="font-medium">{{ $batch->item?->name }}</td>
                        <td><code>{{ $batch->batch_number }}</code></td>
                        <td>{{ $batch->warehouse?->name ?: __('Main Warehouse') }}</td>
                        <td>
                            <span class="badge badge-info">{{ $batch->quantity_remaining }} {{ $batch->item?->primaryUnit?->symbol ?: $batch->item?->unit }}</span>
                        </td>
                        <td>{{ number_format($batch->purchase_cost, 2) }} ر.س</td>
                        <td>
                            @if($batch->isExpired())
                            <span class="badge badge-danger">{{ $batch->expiry_date?->format('Y-m-d') }} ({{ __('منتهي الصلاحية') }})</span>
                            @elseif($batch->isExpiringSoon(30))
                            <span class="badge badge-warning">{{ $batch->expiry_date?->format('Y-m-d') }} ({{ __('قريب الانتهاء') }})</span>
                            @else
                            {{ $batch->expiry_date?->format('Y-m-d') ?: '-' }}
                            @endif
                        </td>
                        <td><code>{{ $batch->storage_location ?: '-' }}</code></td>
                        <td>
                            @if($batch->status === 'quarantined')
                            <span class="badge badge-danger">{{ __('موقوف / حجر صحي') }}</span>
                            @elseif($batch->status === 'active')
                            <span class="badge badge-success">{{ __('نشط ومتاح') }}</span>
                            @else
                            <span class="badge badge-neutral">{{ ucfirst($batch->status) }}</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('batches.quarantine', $batch) }}" style="display:inline;">
                                @csrf
                                @if($batch->status === 'quarantined')
                                <button type="submit" class="btn btn-success btn-sm">{{ __('فك الحظر والتفعيل') }}</button>
                                @else
                                <button type="submit" class="btn btn-warning btn-sm" title="{{ __('تحويل للحجر الصحي') }}">{{ __('حظر وتحفظ') }}</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="empty-row">{{ __('No batches matching current filter.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($batches->hasPages())
        <div class="p-3">
            {{ $batches->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
