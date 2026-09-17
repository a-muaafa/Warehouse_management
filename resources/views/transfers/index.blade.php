@extends('layouts.app')
@section('title', __('Inter-Warehouse Stock Transfers'))
@section('page-title', __('Inter-Warehouse Stock Transfers'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Transfers') }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('transfers.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('New Transfer Request') }}
    </a>
@endsection

@section('content')
<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Transfer #') }}</th>
                        <th>{{ __('Origin Warehouse') }}</th>
                        <th>{{ __('Destination Warehouse') }}</th>
                        <th>{{ __('Items Count') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Requested By') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $tr)
                    <tr>
                        <td>
                            <strong>{{ $tr->transfer_number }}</strong>
                        </td>
                        <td>{{ $tr->fromWarehouse?->name }}</td>
                        <td>{{ $tr->toWarehouse?->name }}</td>
                        <td>
                            <span class="badge badge-info">{{ $tr->items->count() }} {{ __('items') }}</span>
                        </td>
                        <td>
                            @if($tr->status === 'completed')
                            <span class="badge badge-success">{{ __('Completed') }}</span>
                            @elseif($tr->status === 'in_transit')
                            <span class="badge badge-warning">{{ __('In-Transit') }}</span>
                            @elseif($tr->status === 'pending')
                            <span class="badge badge-neutral">{{ __('Pending Dispatch') }}</span>
                            @else
                            <span class="badge badge-danger">{{ ucfirst($tr->status) }}</span>
                            @endif
                        </td>
                        <td>{{ $tr->requester?->name }}</td>
                        <td>{{ $tr->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <a href="{{ route('transfers.show', $tr) }}" class="btn btn-secondary btn-sm">{{ __('View Details') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="empty-row">{{ __('No stock transfers recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transfers->hasPages())
        <div class="p-3">
            {{ $transfers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
