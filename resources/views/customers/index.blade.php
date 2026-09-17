@extends('layouts.app')
@section('title', __('Customer Management'))
@section('page-title', __('Customer Management'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Customers') }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('customers.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('Add Customer') }}
    </a>
@endsection

@section('content')
<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Customer Name') }}</th>
                        <th>{{ __('Company / Organization') }}</th>
                        <th>{{ __('Email & Phone') }}</th>
                        <th>{{ __('Tax #') }}</th>
                        <th>{{ __('Outbound Orders') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td class="font-medium">{{ $customer->name }}</td>
                        <td>{{ $customer->company_name ?: '-' }}</td>
                        <td>
                            <div>{{ $customer->email ?: '-' }}</div>
                            <small class="text-muted">{{ $customer->phone }}</small>
                        </td>
                        <td><code>{{ $customer->tax_number ?: '-' }}</code></td>
                        <td>
                            <span class="badge badge-info">{{ $customer->transactions_count }} {{ __('orders') }}</span>
                        </td>
                        <td>
                            @if($customer->is_active)
                            <span class="badge badge-success">{{ __('Active') }}</span>
                            @else
                            <span class="badge badge-danger">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-secondary btn-sm">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-row">{{ __('No customers registered.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($customers->hasPages())
        <div class="p-3">
            {{ $customers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
