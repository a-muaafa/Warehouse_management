@extends('layouts.app')
@section('title', __('Reports'))
@section('page-title', __('Reports'))
@section('content')

{{-- Summary Cards --}}
<div class="stats-row stats-row--compact">
    <div class="stat-card stat-card--items">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23,6 13.5,15.5 8.5,10.5 1,18"/><polyline points="17,6 23,6 23,12"/></svg></div>
        <div class="stat-content"><div class="stat-value">{{ number_format($summary['total_in']) }}</div><div class="stat-label">{{ __('Total Stock In') }}</div></div>
    </div>
    <div class="stat-card stat-card--supervisors">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23,18 13.5,8.5 8.5,13.5 1,6"/><polyline points="17,18 23,18 23,12"/></svg></div>
        <div class="stat-content"><div class="stat-value">{{ number_format($summary['total_out']) }}</div><div class="stat-label">{{ __('Total Stock Out') }}</div></div>
    </div>
    <div class="stat-card {{ $summary['low_stock_count'] > 0 ? 'stat-card--danger' : 'stat-card--workers' }}">
        <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        <div class="stat-content"><div class="stat-value">{{ $summary['low_stock_count'] }}</div><div class="stat-label">{{ __('Low Stock') }}</div></div>
    </div>
</div>

{{-- Filters --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('supervisor.reports.index') }}" class="filter-form">
        <select name="type" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('All Types') }}</option>
            <option value="in" {{ request('type') === 'in' ? 'selected' : '' }}>{{ __('Stock In') }}</option>
            <option value="out" {{ request('type') === 'out' ? 'selected' : '' }}>{{ __('Stock Out') }}</option>
        </select>
        <select name="item_id" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('All') }} {{ __('Items') }}</option>
            @foreach($items as $item)
            <option value="{{ $item->id }}" {{ request('item_id') == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input" placeholder="{{ __('From Date') }}">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input" placeholder="{{ __('To Date') }}">
        <button type="submit" class="btn btn-ghost">{{ __('Filter') }}</button>
        @if(request()->hasAny(['type','item_id','date_from','date_to']))
        <a href="{{ route('supervisor.reports.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
        @endif
        <a href="{{ route('supervisor.reports.export', request()->query()) }}" class="btn btn-primary" style="margin-inline-start: auto; display: inline-flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span>{{ __('Export to Excel') }}</span>
        </a>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Quantity') }}</th>
                    <th>{{ __('Notes') }}</th>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('Date') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                <tr>
                    <td class="text-muted">{{ $tx->id }}</td>
                    <td class="font-medium">{{ $tx->item->name }}</td>
                    <td><span class="badge {{ $tx->type === 'in' ? 'badge-success' : 'badge-warning' }}">{{ $tx->type === 'in' ? __('IN') : __('OUT') }}</span></td>
                    <td>{{ $tx->quantity }} {{ __($tx->item->unit ?? '') }}</td>
                    <td class="text-muted">{{ $tx->notes ?? '—' }}</td>
                    <td>{{ $tx->user->name }}</td>
                    <td class="text-sm text-muted">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="empty-row">{{ __('No records found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection
