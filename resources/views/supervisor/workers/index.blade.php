@extends('layouts.app')
@section('title', __('إدارة عمال القسم والصلاحيات'))
@section('page-title', __('إدارة عمال القسم والصلاحيات'))
@section('topbar-actions')
<a href="{{ route('supervisor.workers.create') }}" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    {{ __('إضافة عامل جديد وتحديد صلاحياته') }}
</a>
@endsection
@section('content')
<div class="card">
    <div class="card-header" style="background: rgba(255,255,255,0.02); padding: 16px;">
        <h3 style="margin:0; font-size:1.1rem; font-weight:700;">{{ __('قائمة العمال والتعيينات الخاصة بالقسم') }}</h3>
    </div>
    <div class="card-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('الاسم') }}</th>
                    <th>{{ __('اسم المستخدم (Login)') }}</th>
                    <th>{{ __('نوع الصلاحية / المهام') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th>{{ __('تاريخ الإضافة') }}</th>
                    <th>{{ __('الإجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($workers as $worker)
                <tr>
                    <td class="font-medium">
                        {{ $worker->name }}
                        @if($worker->email)
                            <br><small class="text-muted">{{ $worker->email }}</small>
                        @endif
                    </td>
                    <td><code style="font-family: monospace; font-size: 0.9rem; color: #818cf8;">{{ $worker->username }}</code></td>
                    <td>
                        @if($worker->worker_type === 'intake')
                            <span class="badge badge-primary">{{ $worker->worker_type_display_name }}</span>
                        @elseif($worker->worker_type === 'auditor')
                            <span class="badge badge-info">{{ $worker->worker_type_display_name }}</span>
                        @else
                            <span class="badge badge-warning">{{ $worker->worker_type_display_name }}</span>
                        @endif
                    </td>
                    <td>
                        @if($worker->is_active)
                            <span class="badge badge-success">{{ __('مفعّل') }}</span>
                        @else
                            <span class="badge badge-danger">{{ __('موقوف') }}</span>
                        @endif
                    </td>
                    <td class="text-sm text-muted">{{ $worker->created_at->format('Y-m-d') }}</td>
                    <td>
                        <div class="action-btns">
                            <a href="{{ route('supervisor.workers.edit', $worker) }}" class="btn btn-ghost btn-sm">{{ __('تعديل الصلاحيات') }}</a>
                            <form method="POST" action="{{ route('supervisor.workers.destroy', $worker) }}" onsubmit="return confirm('{{ __('هل أنت تأكد من رغبتك في حذف حساب العامل هذا؟') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">{{ __('حذف') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="empty-row">{{ __('لا يوجد عمال مضافون في هذا القسم بعد.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($workers->hasPages())
    <div class="card-footer">{{ $workers->links() }}</div>
    @endif
</div>
@endsection
