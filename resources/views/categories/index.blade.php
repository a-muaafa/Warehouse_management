@extends('layouts.app')
@section('title', __('Item Categories'))
@section('page-title', __('Item Categories'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Categories') }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('categories.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('Add Category') }}
    </a>
@endsection

@section('content')
<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Category Name') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Parent Category') }}</th>
                        <th>{{ __('Items Count') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td class="font-medium">{{ $category->name }}</td>
                        <td><code>{{ $category->code }}</code></td>
                        <td>{{ $category->parent?->name ?: '-' }}</td>
                        <td>
                            <span class="badge badge-info">{{ $category->items_count }} {{ __('items') }}</span>
                        </td>
                        <td>{{ $category->description ?: '-' }}</td>
                        <td>
                            <a href="{{ route('categories.edit', $category) }}" class="btn btn-secondary btn-sm">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="empty-row">{{ __('No categories created.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
        <div class="p-3">
            {{ $categories->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
