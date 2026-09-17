@extends('layouts.app')
@section('title', __('Edit Unit of Measure'))
@section('page-title', __('Edit Unit of Measure'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('units.index') }}">{{ __('Units') }}</a> / <span>{{ __('Edit') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Edit Measurement Unit') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('units.update', $unit) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Unit Name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $unit->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Unit Code') }} *</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code', $unit->code) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Display Symbol') }} *</label>
                    <input type="text" name="symbol" class="form-control" value="{{ old('symbol', $unit->symbol) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Base Unit') }}</label>
                    <select name="base_unit_id" class="form-select">
                        <option value="">{{ __('None (This is a Base Unit)') }}</option>
                        @foreach($baseUnits as $base)
                        <option value="{{ $base->id }}" {{ $unit->base_unit_id == $base->id ? 'selected' : '' }}>{{ $base->name }} ({{ $base->symbol }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Conversion Factor') }} *</label>
                    <input type="number" step="0.0001" name="conversion_factor" class="form-control" value="{{ old('conversion_factor', $unit->conversion_factor) }}" required>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Update Unit') }}</button>
                <a href="{{ route('units.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
