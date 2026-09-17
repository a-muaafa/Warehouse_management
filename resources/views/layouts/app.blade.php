<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', __('Dashboard')) — {{ __('نظام إدارة المستودعات') }}</title>
    
    {{-- WMS 3D Cube Favicon --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    {{-- Instant Theme Initialization to prevent flicker --}}
    <script>
        (function() {
            const theme = localStorage.getItem('wms_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@300;400:500:600:700&family=Tajawal:wght@400:500:700:800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css?v=' . filemtime(public_path('css/style.css'))) }}">
    @stack('styles')
    
    {{-- Mobile & Zoom layout helpers --}}
    <style>
        html, body { overflow-x: hidden; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
                <div class="brand-info">
                    <span class="sidebar-brand-text">{{ __('نظام إدارة المستودعات') }}</span>
                    <span class="sidebar-brand-subtext">{{ __('المنظومة المتكاملة') }}</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <span class="nav-section-label">{{ __('Core Operations') }}</span>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>{{ __('Dashboard') }}</span>
                    </a>
                    <a href="{{ route('scanner.index') }}" class="nav-link {{ request()->routeIs('scanner.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
                        <span>{{ __('Mobile Scanner') }}</span>
                        <span class="nav-tag">{{ __('Live') }}</span>
                    </a>
                    <a href="{{ route('transactions.index') }}" class="nav-link {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/></svg>
                        <span>{{ __('Inbound & Outbound') }}</span>
                    </a>
                </div>

                <div class="nav-section">
                    <span class="nav-section-label">{{ __('Inventory & Logistics') }}</span>
                    <a href="{{ route('supervisor.items.index') }}" class="nav-link {{ request()->routeIs('supervisor.items.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
                        <span>{{ __('Items & Products') }}</span>
                    </a>
                    <a href="{{ route('batches.index') }}" class="nav-link {{ request()->routeIs('batches.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                        <span>{{ __('Batches & Expiry') }}</span>
                    </a>
                    <a href="{{ route('warehouses.index') }}" class="nav-link {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
                        <span>{{ __('Warehouses') }}</span>
                    </a>
                    <a href="{{ route('transfers.index') }}" class="nav-link {{ request()->routeIs('transfers.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
                        <span>{{ __('Stock Transfers') }}</span>
                    </a>
                    <a href="{{ route('audits.index') }}" class="nav-link {{ request()->routeIs('audits.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                        <span>{{ __('Audits & Reconcile') }}</span>
                    </a>
                    <a href="{{ route('worker.labels.search') }}" class="nav-link {{ request()->routeIs('worker.labels.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/><path d="M15 3v18"/></svg>
                        <span>{{ __('Print Barcode Labels') }}</span>
                    </a>
                </div>

                <div class="nav-section">
                    <span class="nav-section-label">{{ __('Partners & Taxonomy') }}</span>
                    <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        <span>{{ __('Suppliers') }}</span>
                    </a>
                    <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        <span>{{ __('Customers') }}</span>
                    </a>
                    <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                        <span>{{ __('Categories') }}</span>
                    </a>
                    <a href="{{ route('units.index') }}" class="nav-link {{ request()->routeIs('units.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>{{ __('Units of Measure') }}</span>
                    </a>
                </div>

                <div class="nav-section">
                    <span class="nav-section-label">{{ __('الإدارة والتقارير') }}</span>
                    <a href="{{ route('supervisor.reports.index') }}" class="nav-link {{ request()->routeIs('supervisor.reports.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>
                        <span>{{ __('التقارير التحليلية') }}</span>
                    </a>
                    
                    @if(auth()->user()?->canManageWorkers())
                    <a href="{{ route('supervisor.workers.index') }}" class="nav-link {{ request()->routeIs('supervisor.workers.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        <span>{{ __('إدارة العمال والصلاحيات') }}</span>
                    </a>
                    @endif

                    @if(auth()->user()?->isAdmin())
                    <a href="{{ route('admin.departments.index') }}" class="nav-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 21v-7a2 2 0 012-2h12a2 2 0 012 2v7"/><path d="M9 18h6"/><path d="M10 9h4"/></svg>
                        <span>{{ __('إدارة الأقسام') }}</span>
                    </a>
                    <a href="{{ route('admin.supervisors.index') }}" class="nav-link {{ request()->routeIs('admin.supervisors.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg>
                        <span>{{ __('إدارة رؤساء الأقسام') }}</span>
                    </a>
                    <a href="{{ route('admin.logs.index') }}" class="nav-link {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <span>{{ __('سجلات الحركة والنظام (Logs)') }}</span>
                    </a>
                    @endif
                </div>
            </nav>

            <div class="sidebar-footer">
                @auth
                <div class="sidebar-user">
                    <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}</div>
                    <div class="user-info">
                        <div class="user-name">{{ auth()->user()->name }}</div>
                        <div class="user-role badge-{{ auth()->user()->role }}">
                            {{ auth()->user()->role_display_name }}
                            @if(auth()->user()->isWorker())
                                - {{ auth()->user()->worker_type_display_name }}
                            @endif
                            @if(auth()->user()->department)
                                ({{ auth()->user()->department->name }})
                            @endif
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn" title="{{ __('تسجيل الخروج') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </form>
                @endauth
            </div>
        </aside>

        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="topbar-title">
                    <h1 class="page-title">@yield('page-title', __('Dashboard'))</h1>
                    @hasSection('breadcrumb')
                    <nav class="breadcrumb">@yield('breadcrumb')</nav>
                    @endif
                </div>
                
                <div class="topbar-actions">
                    <!-- Theme & Language Switchers -->
                    <div class="nav-controls">
                        <!-- Quick Scanner Launch Button -->
                        <a href="{{ route('scanner.index') }}" class="control-btn scanner-btn" title="{{ __('Mobile Scanner') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
                            <span>{{ __('Scanner') }}</span>
                        </a>

                        <!-- Prominent Theme Toggle Button -->
                        <button class="control-btn theme-toggle-btn" title="{{ __('تغيير مظهر النظام') }}">
                            <svg class="theme-icon-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                            <svg class="theme-icon-light" style="display:none; width:16px; height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                            <span class="theme-text" data-text-dark="{{ __('وضع داكن') }}" data-text-light="{{ __('وضع مضيء') }}">{{ __('المظهر') }}</span>
                        </button>
                    </div>

                    @yield('topbar-actions')
                </div>
            </header>

            <div class="content-area">
                @if(session('success'))
                <div class="alert alert-success" id="flash-message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
                    {{ __(session('success')) }}
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-error" id="flash-message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    {{ __(session('error')) }}
                    <button class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <!-- Global WMS Loading Overlay with Lottie animation -->
    <div id="wms-loading-overlay" class="wms-loading-overlay">
        <div class="wms-loading-content">
            <div id="wms-lottie-container" class="wms-lottie-container"></div>
            <div class="wms-loading-text">{{ __('جاري تنفيذ العملية، يرجى الانتظار...') }}</div>
            <div class="wms-loading-subtext">{{ __('المنظومة الذكية لإدارة المستودعات والمخزون') }}</div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
    <script src="{{ asset('js/main.js?v=' . filemtime(public_path('js/main.js'))) }}"></script>
    @stack('scripts')
    
    {{-- Hard refresh helper for cache issues --}}
    <script>
        (function() {
            // Force reload if cached version detected
            if (performance.navigation.type === 1 || performance.getEntriesByType("navigation")[0]?.type === "reload") {
                // Normal reload
                return;
            }
            // Check if CSS loaded properly
            var styles = document.styleSheets;
            var cssLoaded = false;
            for (var i = 0; i < styles.length; i++) {
                try {
                    if (styles[i].href && styles[i].href.includes('style.css')) {
                        cssLoaded = true;
                        break;
                    }
                } catch (e) {}
            }
        })();
        
        // Keyboard shortcut: Ctrl+Shift+R for hard refresh hint
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'R') {
                console.log('Hard refresh triggered');
            }
        });
    </script>
</body>
</html>
