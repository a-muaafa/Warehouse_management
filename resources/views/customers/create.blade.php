@extends('layouts.app')
@section('title', __('Add Customer'))
@section('page-title', __('Add Customer'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('customers.index') }}">{{ __('Customers') }}</a> / <span>{{ __('Add') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Customer Account Details') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Contact / Client Name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Company / Organization') }}</label>
                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Tax Registration #') }}</label>
                    <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number') }}">
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Address / Delivery Site') }}</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Save Customer') }}</button>
                <a href="{{ route('customers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
