@extends('layouts.app')
@section('title', __('Transfer: ').$transfer->transfer_number)
@section('page-title', __('Transfer: ').$transfer->transfer_number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('transfers.index') }}">{{ __('Transfers') }}</a> / <span>{{ $transfer->transfer_number }}</span>
@endsection

@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="glass-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">{{ __('Transfer Route & Status') }}</h3>
                @if($transfer->status === 'completed')
                <span class="badge badge-success">{{ __('Completed & Received') }}</span>
                @elseif($transfer->status === 'in_transit')
                <span class="badge badge-warning">{{ __('In-Transit') }}</span>
                @else
                <span class="badge badge-neutral">{{ __('Pending Dispatch') }}</span>
                @endif
            </div>
            <div class="card-body">
                <div class="transfer-flow-visual" style="display: flex; align-items: center; justify-content: space-around; background: rgba(0,0,0,0.1); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div class="text-center">
                        <small class="text-muted">{{ __('Origin Warehouse') }}</small>
                        <h4 style="margin: 0.25rem 0;">{{ $transfer->fromWarehouse?->name }}</h4>
                        <code>{{ $transfer->fromWarehouse?->code }}</code>
                    </div>
                    <div style="font-size: 1.5rem;">&rarr;</div>
                    <div class="text-center">
                        <small class="text-muted">{{ __('Destination Warehouse') }}</small>
                        <h4 style="margin: 0.25rem 0;">{{ $transfer->toWarehouse?->name }}</h4>
                        <code>{{ $transfer->toWarehouse?->code }}</code>
                    </div>
                </div>

                <h4 class="mb-2" style="font-size: 1rem;">{{ __('Items in Transfer') }}</h4>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th>{{ __('Quantity Dispatched') }}</th>
                                <th>{{ __('Quantity Received') }}</th>
                                <th>{{ __('Est. Value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transfer->items as $item)
                            <tr>
                                <td class="font-medium">{{ $item->item?->name }}</td>
                                <td><span class="badge badge-info">{{ $item->quantity }}</span></td>
                                <td><span class="badge badge-success">{{ $item->received_quantity }}</span></td>
                                <td>{{ number_format($item->quantity * $item->unit_cost, 2) }} ر.س</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions and Audit Log -->
    <div class="col-md-4">
        <div class="glass-card mb-4">
            <div class="card-header">
                <h3 class="card-title">{{ __('Actions & Controls') }}</h3>
            </div>
            <div class="card-body">
                @if($transfer->status === 'pending')
                <form method="POST" action="{{ route('transfers.dispatch', $transfer) }}">
                    @csrf
                    <p class="text-muted" style="font-size:0.9rem;">{{ __('Click below once items are physically loaded onto vehicle.') }}</p>
                    <button type="submit" class="btn btn-warning w-100 mb-2">{{ __('Dispatch Stock (Mark In-Transit)') }}</button>
                </form>
                @elseif($transfer->status === 'in_transit')
                <form method="POST" action="{{ route('transfers.receive', $transfer) }}">
                    @csrf
                    <p class="text-muted" style="font-size:0.9rem;">{{ __('Click below once items arrive and are verified at destination warehouse.') }}</p>
                    <button type="submit" class="btn btn-success w-100 mb-2">{{ __('Confirm Receipt & Reconcile') }}</button>
                </form>
                @else
                <div class="alert alert-success" style="margin:0;">
                    {{ __('Stock transfer completed and ledger balances updated.') }}
                </div>
                @endif
            </div>
        </div>

        <div class="glass-card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Audit Trail') }}</h3>
            </div>
            <div class="card-body" style="font-size: 0.9rem;">
                <div class="mb-2">
                    <strong>{{ __('Requested By:') }}</strong> {{ $transfer->requester?->name }} ({{ $transfer->created_at->format('Y-m-d H:i') }})
                </div>
                @if($transfer->dispatched_at)
                <div class="mb-2">
                    <strong>{{ __('Dispatched By:') }}</strong> {{ $transfer->dispatcher?->name }} ({{ $transfer->dispatched_at->format('Y-m-d H:i') }})
                </div>
                @endif
                @if($transfer->received_at)
                <div class="mb-2">
                    <strong>{{ __('Received By:') }}</strong> {{ $transfer->receiver?->name }} ({{ $transfer->received_at->format('Y-m-d H:i') }})
                </div>
                @endif
                @if($transfer->notes)
                <div class="mt-3 text-muted">
                    <em>"{{ $transfer->notes }}"</em>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
