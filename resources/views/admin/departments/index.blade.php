@extends('layouts.app')
@section('title', __('Departments'))
@section('page-title', __('Departments'))
@section('topbar-actions')
<a href="{{ route('admin.departments.create') }}" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    {{ __('Add Department') }}
</a>
@endsection
@section('content')
<div class="card">
    <div class="card-body p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Workers') }}</th>
                    <th>{{ __('Items') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $dept)
                <tr>
                    <td class="font-medium">{{ $dept->name }}</td>
                    <td class="text-muted">{{ Str::limit($dept->description, 60) ?? '—' }}</td>
                    <td>{{ $dept->users_count }}</td>
                    <td>{{ $dept->items_count }}</td>
                    <td class="text-sm text-muted">{{ $dept->created_at->format('Y-m-d') }}</td>
                    <td>
                        <div class="action-btns">
                            <a href="{{ route('admin.departments.edit', $dept) }}" class="btn btn-ghost btn-sm">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.departments.destroy', $dept) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="empty-row">{{ __('No records found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($departments->hasPages())
    <div class="card-footer">{{ $departments->links() }}</div>
    @endif
</div>
@endsection
