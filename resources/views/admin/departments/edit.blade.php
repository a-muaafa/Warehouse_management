@extends('layouts.app')
@section('title', __('Edit'))
@section('page-title', __('Edit') . ' — ' . $department->name)
@section('content')
<div class="card card--form">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.departments.update', $department) }}">
            @csrf @method('PUT')
            <div class="form-group">
                <label class="form-label">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name', $department->name) }}" class="form-input {{ $errors->has('name') ? 'is-invalid' : '' }}" required autofocus>
                @error('name')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('Description') }}</label>
                <textarea name="description" class="form-textarea {{ $errors->has('description') ? 'is-invalid' : '' }}" rows="4">{{ old('description', $department->description) }}</textarea>
                @error('description')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-actions">
                <a href="{{ route('admin.departments.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
