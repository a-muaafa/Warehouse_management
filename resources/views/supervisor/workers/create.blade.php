@extends('layouts.app')
@section('title', __('إضافة عامل جديد'))
@section('page-title', __('إضافة عامل جديد وتحديد الصلاحيات'))
@section('content')
<div class="card card--form" style="max-width: 700px; margin: 0 auto;">
    <div class="card-body">
        <form method="POST" action="{{ route('supervisor.workers.store') }}">
            @csrf
            
            <div class="form-group">
                <label class="form-label">{{ __('الاسم الكامل للعامل') }} *</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-input {{ $errors->has('name') ? 'is-invalid' : '' }}" required autofocus placeholder="مثال: محمود سالم">
                @error('name')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('اسم المستخدم للدخول (Username)') }} *</label>
                <input type="text" name="username" value="{{ old('username') }}" class="form-input {{ $errors->has('username') ? 'is-invalid' : '' }}" required placeholder="مثال: worker_food1">
                @error('username')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('البريد الإلكتروني (اختياري)') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}" placeholder="worker@wms.com">
                @error('email')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('كلمة المرور') }} *</label>
                <input type="password" name="password" class="form-input {{ $errors->has('password') ? 'is-invalid' : '' }}" required placeholder="••••••••">
                @error('password')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group" style="background: rgba(255,255,255,0.03); padding: 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
                <label class="form-label" style="font-weight: 700; font-size: 1rem; color: #cbd5e1; margin-bottom: 12px;">{{ __('تحديد صلاحية ونوع العامل داخل القسم:') }} *</label>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input type="radio" name="worker_type" value="intake" {{ old('worker_type', 'intake') === 'intake' ? 'checked' : '' }} style="margin-top: 4px;">
                        <div>
                            <strong style="color: #3b82f6;">عامل إدخال وتوثيق (Intake)</strong>
                            <div style="font-size: 0.85rem; color: #94a3b8;">صلاحية الإضافة (Create) لعمليات الوارد والمنصرف فقط، ولا يمكنه التعديل أو الحذف بعد حفظ العملية.</div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input type="radio" name="worker_type" value="auditor" {{ old('worker_type') === 'auditor' ? 'checked' : '' }} style="margin-top: 4px;">
                        <div>
                            <strong style="color: #a855f7;">عامل الجرد والفرز (Auditor)</strong>
                            <div style="font-size: 0.85rem; color: #94a3b8;">صلاحية القراءة للأرصدة، طباعة ملصقات الباركود الحرارية، وتنفيذ الجرد الميداني لتسوية الكميات.</div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input type="radio" name="worker_type" value="view_only" {{ old('worker_type') === 'view_only' ? 'checked' : '' }} style="margin-top: 4px;">
                        <div>
                            <strong style="color: #eab308;">عامل مشاهد (View Only)</strong>
                            <div style="font-size: 0.85rem; color: #94a3b8;">صلاحية قراءة مقتصرة لمتابعة نواقص المخزون وتجهيز الطلبيات دون إمكانية التعديل على البيانات.</div>
                        </div>
                    </label>
                </div>
                @error('worker_type')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}>
                    <span>{{ __('تفعيل حساب العامل فوراً') }}</span>
                </label>
            </div>

            <div class="form-actions" style="margin-top: 24px;">
                <a href="{{ route('supervisor.workers.index') }}" class="btn btn-ghost">{{ __('إلغاء') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('حفظ وتفعيل العامل') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
