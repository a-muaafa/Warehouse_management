@extends('layouts.app')

@section('title', __('لوحة التحكم التنفيذية للمستودعات'))
@section('page-title', __('لوحة التحكم التنفيذية للمستودعات'))

@section('topbar-actions')
<div class="d-flex gap-2">
    <a href="{{ route('scanner.index') }}" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;margin-inline-end:4px;"><path d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
        {{ __('Open Scanner') }}
    </a>
    <a href="{{ route('transactions.index') }}" class="btn btn-secondary btn-sm">
        + {{ __('Log Movement') }}
    </a>
</div>
@endsection

@section('content')
<div class="dashboard-wrapper">

    {{-- 1. High-Level Executive KPI Row --}}
    <div class="stats-row mb-4">
        <!-- Total Inventory Valuation -->
        <div class="stat-card stat-card--valuation" style="border-inline-start: 4px solid #10b981;">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value text-success">{{ number_format($stats['total_valuation'], 2) }} ر.س</div>
                <div class="stat-label">{{ __('Capital Tied-Up in Stock') }}</div>
            </div>
        </div>

        <!-- Monthly Holding / Carrying Cost -->
        <div class="stat-card stat-card--holding" style="border-inline-start: 4px solid #f59e0b;">
            <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value text-warning">{{ number_format($stats['total_holding_cost'], 2) }} ر.س</div>
                <div class="stat-label">{{ __('Monthly Storage Holding Cost') }}</div>
            </div>
        </div>

        <!-- Expiring Batches Warning -->
        <div class="stat-card stat-card--alert {{ $stats['expiring_batches'] > 0 ? 'stat-card--danger' : '' }}" style="border-inline-start: 4px solid #ef4444;">
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value text-danger">{{ $stats['expiring_batches'] }}</div>
                <div class="stat-label">{{ __('Expiring Soon (< 30d)') }}</div>
            </div>
        </div>

        <!-- Low Stock / Reorder Point -->
        <div class="stat-card" style="border-inline-start: 4px solid #8b5cf6;">
            <div class="stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value text-purple">{{ $stats['reorder_alerts'] }}</div>
                <div class="stat-label">{{ __('Reorder Thresholds Reached') }}</div>
            </div>
        </div>

        <!-- Active Warehouses -->
        <div class="stat-card" style="border-inline-start: 4px solid #3b82f6;">
            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $stats['warehouses'] }}</div>
                <div class="stat-label">{{ __('Active Warehouses') }}</div>
            </div>
        </div>
    </div>

    {{-- 2. Inventory Dynamics: Fast-Moving Turnover vs Stagnant (Dead Stock) --}}
    <div class="row g-4 mb-4">
        <!-- High Turnover (Fast-Moving Items) -->
        <div class="col-md-6">
            <div class="card glass-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        {{ __('الأصناف الأكثر حركة وسرعة دوران (آخر 30 يوم)') }}
                    </h3>
                    <span class="badge badge-success">{{ __('سريعة الدوران') }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('Current Stock') }}</th>
                                    <th>{{ __('Outbound Volume') }}</th>
                                    <th>{{ __('Turnover Velocity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($fastMovingItems as $item)
                                <tr>
                                    <td class="font-medium">{{ __($item->name) }}</td>
                                    <td>{{ $item->quantity }} {{ $item->primaryUnit?->symbol ?: __($item->unit) }}</td>
                                    <td><span class="badge badge-success font-bold">{{ $item->outbound_volume }} {{ __('وحدة') }}</span></td>
                                    <td>
                                        <div class="progress-bar-container" style="width: 80px; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                                            <div style="width: 85%; height: 100%; background: #10b981;"></div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="empty-row">{{ __('No outbound movements recorded.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stagnant (Dead Stock) Items -->
        <div class="col-md-6">
            <div class="card glass-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        {{ __('الأصناف البطيئة والراكدة (مخزون ميت)') }}
                    </h3>
                    <span class="badge badge-warning">{{ __('رأس مال معطل') }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('Dormant Stock') }}</th>
                                    <th>{{ __('Tied-Up Valuation') }}</th>
                                    <th>{{ __('Holding Cost/Mo') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($stagnantItems as $item)
                                <tr>
                                    <td class="font-medium">{{ $item->name }}</td>
                                    <td>{{ $item->quantity }} {{ $item->primaryUnit?->symbol ?: $item->unit }}</td>
                                    <td><strong>{{ number_format($item->total_valuation, 2) }} ر.س</strong></td>
                                    <td class="text-warning">{{ number_format($item->estimated_holding_cost, 2) }} ر.س</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="empty-row">{{ __('No stagnant stock detected.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Expiration Tracking & Low Stock Alerts Center --}}
    <div class="row g-4 mb-4">
        <!-- Expiring Batches -->
        <div class="col-md-6">
            <div class="card glass-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        {{ __('التشغيلات قريبة الانتهاء والمنتهية (تتبع FEFO)') }}
                    </h3>
                    <a href="{{ route('batches.index', ['filter' => 'expiring_soon']) }}" class="btn btn-ghost btn-sm">{{ __('عرض كافة التشغيلات') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Item & Batch #') }}</th>
                                    <th>{{ __('Warehouse') }}</th>
                                    <th>{{ __('Qty Remaining') }}</th>
                                    <th>{{ __('Expiry Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expiringBatches as $batch)
                                <tr>
                                    <td class="font-medium">
                                        {{ $batch->item?->name }}<br>
                                        <code style="font-size:0.75rem;">{{ $batch->batch_number }}</code>
                                    </td>
                                    <td>{{ $batch->warehouse?->name ?: __('Main WH') }}</td>
                                    <td><span class="badge badge-info">{{ $batch->quantity_remaining }}</span></td>
                                    <td>
                                        @if($batch->isExpired())
                                        <span class="badge badge-danger">{{ $batch->expiry_date?->format('Y-m-d') }} ({{ __('منتهي') }})</span>
                                        @else
                                        <span class="badge badge-warning">{{ $batch->expiry_date?->format('Y-m-d') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="empty-row">{{ __('No batches expiring in the next 30 days.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="col-md-6">
            <div class="card glass-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        {{ __('تنبيهات الحد الأدنى ونواقص المخزون') }}
                    </h3>
                    <a href="{{ route('supervisor.items.index', ['alert' => '1']) }}" class="btn btn-ghost btn-sm">{{ __('إدارة الأصناف') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('Department') }}</th>
                                    <th>{{ __('Current Balance') }}</th>
                                    <th>{{ __('Min Level') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lowStockItems as $item)
                                <tr class="row-alert">
                                    <td class="font-medium">{{ $item->name }}</td>
                                    <td>{{ $item->department->name ?? '—' }}</td>
                                    <td><span class="badge badge-danger">{{ $item->quantity }} {{ $item->primaryUnit?->symbol ?: $item->unit }}</span></td>
                                    <td>{{ $item->min_alert_level }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="empty-row">{{ __('All stock levels are above safety thresholds.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Recent Real-Time Transactions Feed --}}
    <div class="card glass-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
                {{ __('سجل حركات المخزون المباشرة') }}
            </h3>
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary btn-sm">{{ __('عرض السجل الكامل') }}</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('Movement Type') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Total Amount') }}</th>
                            <th>{{ __('Warehouse') }}</th>
                            <th>{{ __('Operator') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $tx)
                        <tr>
                            <td class="text-muted text-sm">{{ $tx->created_at->diffForHumans() }}</td>
                            <td class="font-medium">{{ $tx->item->name }}</td>
                            <td>
                                <span class="badge {{ $tx->type === 'in' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $tx->type === 'in' ? __('الوارد (IN)') : __('المنصرف (OUT)') }}
                                </span>
                            </td>
                            <td>{{ $tx->movement_reason_display_name }}</td>
                            <td class="font-medium"><strong>{{ $tx->quantity }}</strong> {{ $tx->item->primaryUnit?->symbol ?: $tx->item->unit }}</td>
                            <td>{{ number_format($tx->total_amount, 2) }} ر.س</td>
                            <td>{{ $tx->warehouse?->name ?: __('Main Warehouse') }}</td>
                            <td>{{ $tx->user->name }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="empty-row">{{ __('No recent transactions logged.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
