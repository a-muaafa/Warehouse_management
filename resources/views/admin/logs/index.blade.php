@extends('layouts.app')

@section('title', __('سجلات الحركة والنظام (Logs)'))

@section('content')
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                {{ __('سجلات النظام وحركات المستخدمين المركزية (Activity Logs)') }}
            </h2>
            <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 0.875rem;">
                {{ __('تتبع عمليات الإدخال والصرف، الدخول والخروج، وإدارة المستخدمين لكل الأقسام') }}
            </p>
        </div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('admin.logs.index') }}" style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; background: rgba(255,255,255,0.03); padding: 14px; border-radius: 8px;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" name="search" value="{{ request('search') }}" class="form-input" placeholder="{{ __('بحث في الإجراء أو الوصف أو المستخدم...') }}">
            </div>

            <div style="width: 200px;">
                <select name="department_id" class="form-input">
                    <option value="">{{ __('جميع الأقسام') }}</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="width: 200px;">
                <select name="user_id" class="form-input">
                    <option value="">{{ __('جميع المستخدمين') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->username }})
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                {{ __('تصفية') }}
            </button>
            <a href="{{ route('admin.logs.index') }}" class="btn btn-secondary">
                {{ __('إعادة ضبط') }}
            </a>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('التاريخ والوقت') }}</th>
                        <th>{{ __('المستخدم') }}</th>
                        <th>{{ __('القسم') }}</th>
                        <th>{{ __('نوع الإجراء') }}</th>
                        <th>{{ __('تفاصيل الحركة') }}</th>
                        <th>{{ __('عنوان IP') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td style="white-space: nowrap; font-size: 0.85rem;">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td>
                                <strong>{{ $log->user_name ?? ($log->user?->name ?? __('نظام')) }}</strong>
                                @if($log->user)
                                    <br><small style="color: #94a3b8; font-family: monospace;">{{ $log->user->username }}</small>
                                @endif
                            </td>
                            <td>
                                @if($log->department)
                                    <span class="badge badge-info">{{ $log->department->name }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('مركزي / أدمن') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-primary">{{ $log->action }}</span>
                            </td>
                            <td>{{ $log->description }}</td>
                            <td style="font-family: monospace; font-size: 0.85rem; color: #94a3b8;">
                                {{ $log->ip_address ?? '127.0.0.1' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 30px; color: #94a3b8;">
                                {{ __('لا توجد سجلات حركة مطابقة حتى الآن.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 16px;">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
