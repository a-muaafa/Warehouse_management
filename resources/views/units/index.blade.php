@extends('layouts.app')
@section('title', __('Units of Measurement'))
@section('page-title', __('Units of Measurement'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Units') }}</span>
@endsection

@section('topbar-actions')
    <a href="{{ route('units.create') }}" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-inline-end:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ __('Add Unit of Measure') }}
    </a>
@endsection

@section('content')
<div class="card glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Unit Name') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Symbol') }}</th>
                        <th>{{ __('Base Unit Relationship') }}</th>
                        <th>{{ __('Associated Items') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($units as $unit)
                    <tr>
                        <td class="font-medium">{{ $unit->name }}</td>
                        <td><code>{{ $unit->code }}</code></td>
                        <td><strong>{{ $unit->symbol }}</strong></td>
                        <td>
                            @if($unit->baseUnit)
                            <span>1 {{ $unit->symbol }} = {{ (float)$unit->conversion_factor }} × {{ $unit->baseUnit->symbol }}</span>
                            @else
                            <span class="badge badge-info">{{ __('Base Unit') }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-neutral">{{ $unit->items_count }} {{ __('items') }}</span>
                        </td>
                        <td>
                            @if($unit->is_active)
                            <span class="badge badge-success">{{ __('Active') }}</span>
                            @else
                            <span class="badge badge-danger">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('units.edit', $unit) }}" class="btn btn-secondary btn-sm">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-row">{{ __('No units configured.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($units->hasPages())
        <div class="p-3">
            {{ $units->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
