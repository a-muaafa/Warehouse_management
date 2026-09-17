@extends('layouts.app')
@section('title', __('تعديل بيانات رئيس القسم'))
@section('page-title', __('تعديل بيانات رئيس القسم') . ' — ' . $supervisor->name)
@section('content')
<div class="card card--form" style="max-width: 650px; margin: 0 auto;">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.supervisors.update', $supervisor) }}">
            @csrf @method('PUT')

            <div class="form-group">
                <label class="form-label">{{ __('الاسم الكامل لرئيس القسم') }} *</label>
                <input type="text" name="name" value="{{ old('name', $supervisor->name) }}" class="form-input {{ $errors->has('name') ? 'is-invalid' : '' }}" required autofocus>
                @error('name')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('اسم المستخدم للدخول (Username)') }} *</label>
                <input type="text" name="username" value="{{ old('username', $supervisor->username) }}" class="form-input {{ $errors->has('username') ? 'is-invalid' : '' }}" required>
                @error('username')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('البريد الإلكتروني (اختياري)') }}</label>
                <input type="email" name="email" value="{{ old('email', $supervisor->email) }}" class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}">
                @error('email')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('كلمة المرور الجديدة') }} <span class="text-muted">({{ __('اتركها فارغة إذا لم تكن ترغب بالتغيير') }})</span></label>
                <input type="password" name="password" class="form-input {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="••••••••">
                @error('password')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('تعيين القسم الخاص به (أغذية أو أدوية)') }} *</label>
                <select name="department_id" class="form-select {{ $errors->has('department_id') ? 'is-invalid' : '' }}" required>
                    <option value="">{{ __('اختر القسم المناسب...') }}</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" {{ old('department_id', $supervisor->department_id) == $dept->id ? 'selected' : '' }}>
                        {{ $dept->name }}
                    </option>
                    @endforeach
                </select>
                @error('department_id')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $supervisor->is_active) ? 'checked' : '' }}>
                    <span>{{ __('تفعيل حساب رئيس القسم') }}</span>
                </label>
            </div>

            <div class="form-actions" style="margin-top: 24px;">
                <a href="{{ route('admin.supervisors.index') }}" class="btn btn-ghost">{{ __('إلغاء') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('تحديث البيانات والتخصيص') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
