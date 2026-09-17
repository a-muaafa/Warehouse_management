@extends('layouts.app')
@section('title', __('Start New Inventory Audit'))
@section('page-title', __('Start New Inventory Audit'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('audits.index') }}">{{ __('Audits') }}</a> / <span>{{ __('New Audit') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Initiate Inventory Stock-Taking Session') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('audits.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">{{ __('Audit Session Title') }} *</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', __('Monthly Cycle Count — ').date('F Y')) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Audit Type') }} *</label>
                    <select name="type" class="form-select" required>
                        <option value="cycle_count">{{ __('Cycle Count (Continuous sampling)') }}</option>
                        <option value="periodic_full">{{ __('Periodic Full Inventory Count') }}</option>
                        <option value="spot_check">{{ __('Spot Check / High-Value Audit') }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Warehouse Scope') }}</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">{{ __('All Warehouses / General') }}</option>
                        @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Audit Scope Notes & Guidelines') }}</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="{{ __('e.g. Verify shelf counts for electronics aisle A & B') }}"></textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Start Count Session') }}</button>
                <a href="{{ route('audits.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
