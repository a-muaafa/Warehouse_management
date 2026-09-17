@extends('layouts.app')
@section('title', __('Supplier & Vendor Management'))
@section('page-title', __('Supplier & Vendor Management'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Suppliers') }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('Add Supplier') }}
    </a>
@endsection

@section('content')
<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Company / Supplier Name') }}</th>
                        <th>{{ __('Contact Person') }}</th>
                        <th>{{ __('Email & Phone') }}</th>
                        <th>{{ __('Tax #') }}</th>
                        <th>{{ __('Supplied Batches') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                    <tr>
                        <td class="font-medium">{{ $supplier->company_name }}</td>
                        <td>{{ $supplier->contact_person ?: '-' }}</td>
                        <td>
                            <div>{{ $supplier->email ?: '-' }}</div>
                            <small class="text-muted">{{ $supplier->phone }}</small>
                        </td>
                        <td><code>{{ $supplier->tax_number ?: '-' }}</code></td>
                        <td>
                            <span class="badge badge-info">{{ $supplier->batches_count }} {{ __('batches') }}</span>
                        </td>
                        <td>
                            @if($supplier->is_active)
                            <span class="badge badge-success">{{ __('Active') }}</span>
                            @else
                            <span class="badge badge-danger">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-secondary btn-sm">{{ __('Edit') }}</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-row">{{ __('No suppliers registered.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($suppliers->hasPages())
        <div class="p-3">
            {{ $suppliers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
