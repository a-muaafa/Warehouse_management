@extends('layouts.app')
@section('title', __('إدارة رؤساء الأقسام'))
@section('page-title', __('إدارة رؤساء الأقسام وتعييناتهم'))
@section('topbar-actions')
<a href="{{ route('admin.supervisors.create') }}" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    {{ __('إضافة رئيس قسم جديد') }}
</a>
@endsection
@section('content')
<div class="card">
    <div class="card-header" style="background: rgba(255,255,255,0.02); padding: 16px;">
        <h3 style="margin:0; font-size:1.1rem; font-weight:700;">{{ __('قائمة رؤساء الأقسام (الأغذية والأدوية)') }}</h3>
    </div>
    <div class="card-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('الاسم') }}</th>
                    <th>{{ __('اسم المستخدم (Login)') }}</th>
                    <th>{{ __('القسم المعيّن عليه') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th>{{ __('تاريخ التعيين') }}</th>
                    <th>{{ __('الإجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supervisors as $supervisor)
                <tr>
                    <td class="font-medium">
                        {{ $supervisor->name }}
                        @if($supervisor->email)
                            <br><small class="text-muted">{{ $supervisor->email }}</small>
                        @endif
                    </td>
                    <td><code style="font-family: monospace; font-size: 0.9rem; color: #818cf8;">{{ $supervisor->username }}</code></td>
                    <td>
                        @if(str_contains($supervisor->department?->name ?? '', 'أدوية'))
                            <span class="badge badge-info">{{ $supervisor->department?->name }}</span>
                        @else
                            <span class="badge badge-success">{{ $supervisor->department?->name ?? 'غير محدد' }}</span>
                        @endif
                    </td>
                    <td>
                        @if($supervisor->is_active)
                            <span class="badge badge-success">{{ __('مفعّل') }}</span>
                        @else
                            <span class="badge badge-danger">{{ __('موقوف') }}</span>
                        @endif
                    </td>
                    <td class="text-sm text-muted">{{ $supervisor->created_at->format('Y-m-d') }}</td>
                    <td>
                        <div class="action-btns">
                            <a href="{{ route('admin.supervisors.edit', $supervisor) }}" class="btn btn-ghost btn-sm">{{ __('تعديل') }}</a>
                            <form method="POST" action="{{ route('admin.supervisors.destroy', $supervisor) }}" onsubmit="return confirm('{{ __('هل أنت تأكد من رغبتك في حذف حساب رئيس القسم هذا؟') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">{{ __('حذف') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="empty-row">{{ __('لا يوجد رؤساء أقسام مضافون بعد.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($supervisors->hasPages())
    <div class="card-footer">{{ $supervisors->links() }}</div>
    @endif
</div>
@endsection
