@extends('layouts.app')
@section('title', __('Edit Customer'))
@section('page-title', __('Edit Customer'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('customers.index') }}">{{ __('Customers') }}</a> / <span>{{ __('Edit') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Edit Customer Account') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Contact / Client Name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $customer->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Company / Organization') }}</label>
                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $customer->company_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Tax Registration #') }}</label>
                    <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number', $customer->tax_number) }}">
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Address / Delivery Site') }}</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $customer->address) }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Update Customer') }}</button>
                <a href="{{ route('customers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
