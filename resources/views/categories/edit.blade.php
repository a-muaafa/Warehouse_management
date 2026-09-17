@extends('layouts.app')
@section('title', __('Edit Item Category'))
@section('page-title', __('Edit Item Category'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <a href="{{ route('categories.index') }}">{{ __('Categories') }}</a> / <span>{{ __('Edit') }}</span>
@endsection

@section('content')
<div class="glass-card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">{{ __('Edit Category Information') }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('categories.update', $category) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Category Name') }} *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Category Code') }} *</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code', $category->code) }}" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Parent Category') }}</label>
                    <select name="parent_id" class="form-select">
                        <option value="">{{ __('None (Top Level Category)') }}</option>
                        @foreach($parentCategories as $parent)
                        <option value="{{ $parent->id }}" {{ $category->parent_id == $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">{{ __('Description') }}</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $category->description) }}</textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Update Category') }}</button>
                <a href="{{ route('categories.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
