@extends('layouts.app')
@section('title', __('Audit: ').$audit->audit_number)
@section('page-title', __('Audit: ').$audit->audit_number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('audits.index') }}">{{ __('Audits') }}</a> / <span>{{ $audit->audit_number }}</span>
@endsection

@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="glass-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title">{{ $audit->title }}</h3>
                    <small class="text-muted">{{ __('Warehouse:') }} {{ $audit->warehouse?->name ? __($audit->warehouse->name) : __('All Facilities') }} | {{ __('Scope:') }} {{ match($audit->type) { 'cycle_count' => __('Cycle Count (Continuous sampling)'), 'full_count' => __('Periodic Full Inventory Count'), 'spot_check' => __('Spot Check / High-Value Audit'), default => __(ucfirst(str_replace('_', ' ', $audit->type))) } }}</small>
                </div>
                @if($audit->status === 'reconciled')
                <span class="badge badge-success">{{ __('Reconciled') }}</span>
                @else
                <span class="badge badge-warning">{{ __('In Progress') }}</span>
                @endif
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('audits.counts', $audit) }}">
                    @csrf
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('Book Qty') }}</th>
                                    <th style="width: 140px;">{{ __('Physical Count') }}</th>
                                    <th>{{ __('Discrepancy') }}</th>
                                    <th>{{ __('Value Variance') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($audit->items as $index => $itemRecord)
                                <tr>
                                    <td class="font-medium">
                                        {{ $itemRecord->item?->name }}
                                        <input type="hidden" name="counts[{{ $index }}][id]" value="{{ $itemRecord->id }}">
                                    </td>
                                    <td>{{ $itemRecord->book_quantity }}</td>
                                    <td>
                                        @if($audit->status !== 'reconciled')
                                        <input type="number" name="counts[{{ $index }}][counted_quantity]" class="form-control form-control-sm" value="{{ $itemRecord->counted_quantity }}" min="0">
                                        @else
                                        <strong>{{ $itemRecord->counted_quantity }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        @if($itemRecord->discrepancy_quantity > 0)
                                        <span class="text-success font-bold">+{{ $itemRecord->discrepancy_quantity }}</span>
                                        @elseif($itemRecord->discrepancy_quantity < 0)
                                        <span class="text-danger font-bold">{{ $itemRecord->discrepancy_quantity }}</span>
                                        @else
                                        <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ number_format($itemRecord->discrepancy_value, 2) }} ر.س
                                    </td>
                                    <td>
                                        @if($itemRecord->status === 'surplus')
                                        <span class="badge badge-success">{{ __('Surplus') }}</span>
                                        @elseif($itemRecord->status === 'shortage')
                                        <span class="badge badge-danger">{{ __('Shortage') }}</span>
                                        @else
                                        <span class="badge badge-neutral">{{ __('Matched') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($audit->status !== 'reconciled')
                    <div class="mt-3">
                        <button type="submit" class="btn btn-secondary">{{ __('Save Physical Counts') }}</button>
                    </div>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <!-- Summary & Reconcile Panel -->
    <div class="col-md-4">
        <div class="glass-card mb-4">
            <div class="card-header">
                <h3 class="card-title">{{ __('Reconciliation Actions') }}</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted" style="font-size:0.85rem;">{{ __('Total Net Variance Value') }}</div>
                    <div class="stat-value {{ $audit->total_discrepancy_value < 0 ? 'text-danger' : 'text-success' }}" style="font-size: 1.6rem;">
                        {{ number_format($audit->total_discrepancy_value, 2) }} ر.س
                    </div>
                </div>

                @if($audit->status !== 'reconciled')
                <form method="POST" action="{{ route('audits.reconcile', $audit) }}" onsubmit="return confirm('{{ __('Are you sure you want to reconcile? This will automatically generate ledger balance adjustment transactions.') }}')">
                    @csrf
                    <p class="text-muted" style="font-size: 0.85rem;">
                        {{ __('Reconciliation will adjust book quantities to match physical counts and generate balancing adjustment transactions.') }}
                    </p>
                    <button type="submit" class="btn btn-success w-100">
                        {{ __('Approve & Reconcile Stock') }}
                    </button>
                </form>
                @else
                <div class="alert alert-success" style="margin: 0;">
                    {{ __('This audit session has been reconciled.') }}<br>
                    <small>{{ __('Reconciled on:') }} {{ $audit->reconciled_at?->format('Y-m-d H:i') }}</small>
                </div>
                @endif
            </div>
        </div>

        <div class="glass-card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Session Meta') }}</h3>
            </div>
            <div class="card-body" style="font-size: 0.9rem;">
                <div class="mb-2"><strong>{{ __('Audited By:') }}</strong> {{ $audit->auditor?->name }}</div>
                @if($audit->approver)
                <div class="mb-2"><strong>{{ __('Approved By:') }}</strong> {{ $audit->approver?->name }}</div>
                @endif
                <div><strong>{{ __('Started At:') }}</strong> {{ $audit->created_at->format('Y-m-d H:i') }}</div>
                @if($audit->notes)
                <div class="mt-3 text-muted">
                    <em>"{{ $audit->notes }}"</em>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
