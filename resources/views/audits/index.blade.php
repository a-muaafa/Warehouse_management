@extends('layouts.app')
@section('title', __('Inventory Audits & Cycle Counts'))
@section('page-title', __('Inventory Audits & Cycle Counts'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Audits') }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('audits.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('Start New Audit Session') }}
    </a>
@endsection

@section('content')
<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Audit #') }}</th>
                        <th>{{ __('Title & Scope') }}</th>
                        <th>{{ __('Warehouse') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Items Counted') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Audited By') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($audits as $audit)
                    <tr>
                        <td><strong>{{ $audit->audit_number }}</strong></td>
                        <td>{{ $audit->title }}</td>
                        <td>{{ $audit->warehouse?->name ?: __('All Warehouses') }}</td>
                        <td>
                            <span class="badge badge-neutral">{{ match($audit->type) { 'cycle_count' => __('Cycle Count (Continuous sampling)'), 'full_count' => __('Periodic Full Inventory Count'), 'spot_check' => __('Spot Check / High-Value Audit'), default => __(ucfirst(str_replace('_', ' ', $audit->type))) } }}</span>
                        </td>
                        <td>{{ $audit->items_count }} {{ __('items') }}</td>
                        <td>
                            @if($audit->status === 'reconciled')
                            <span class="badge badge-success">{{ __('Reconciled & Balanced') }}</span>
                            @elseif($audit->status === 'in_progress')
                            <span class="badge badge-warning">{{ __('In Progress') }}</span>
                            @else
                            <span class="badge badge-info">{{ match($audit->status) { 'draft' => __('Draft'), 'completed' => __('Completed'), default => __(ucfirst($audit->status)) } }}</span>
                            @endif
                        </td>
                        <td>{{ $audit->auditor?->name }}</td>
                        <td>{{ $audit->created_at->format('Y-m-d') }}</td>
                        <td>
                            <a href="{{ route('audits.show', $audit) }}" class="btn btn-secondary btn-sm">{{ __('Open Audit') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="empty-row">{{ __('No audit sessions recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($audits->hasPages())
        <div class="p-3">
            {{ $audits->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
