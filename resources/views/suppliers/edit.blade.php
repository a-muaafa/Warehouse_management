@extends('layouts.app')
@section('title', __('Edit Supplier'))
@section('page-title', __('Edit Supplier'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('suppliers.index') }}">{{ __('Suppliers') }}</a> / <span>{{ __('Edit') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Edit Supplier Profile') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Company / Vendor Name') }} *</label>
                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $supplier->company_name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Contact Person') }}</label>
                    <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supplier->contact_person) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Tax Registration #') }}</label>
                    <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number', $supplier->tax_number) }}">
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Address') }}</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $supplier->address) }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Update Supplier') }}</button>
                <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
