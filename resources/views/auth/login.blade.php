<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('تسجيل الدخول') }} — {{ __('نظام إدارة المستودعات') }}</title>
    
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
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <style>
        .demo-credentials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .demo-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 10px 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: right;
        }
        .demo-card:hover {
            background: rgba(99, 102, 241, 0.12);
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateY(-2px);
        }
        .demo-role {
            font-size: 0.85rem;
            font-weight: 700;
            display: block;
            margin-bottom: 2px;
        }
        .demo-username {
            font-size: 0.8rem;
            color: var(--primary-light, #818cf8);
            direction: ltr;
            display: inline-block;
            font-family: monospace;
        }
        .demo-desc {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 2px;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-container">
        <!-- Top Switchers -->
        <div class="login-top-bar">
            <div class="nav-controls">
                <button class="control-btn theme-toggle-btn" title="{{ __('تغيير مظهر النظام') }}">
                    <svg class="theme-icon-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="theme-icon-light" style="display:none; width:16px; height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    <span class="theme-text" data-text-dark="{{ __('وضع داكن') }}" data-text-light="{{ __('وضع مضيء') }}">{{ __('المظهر') }}</span>
                </button>
            </div>
        </div>

        <div class="login-card" style="max-width: 680px;">
            <div class="login-header">
                <div class="login-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                </div>
                <h1 class="login-title">{{ __('نظام إدارة المستودعات') }}</h1>
                <p class="login-subtitle">{{ __('منظومة إدارة أقسام الأغذية والأدوية والحسابات') }}</p>
            </div>

            <form method="POST" action="{{ route('login.post') }}" class="login-form">
                @csrf

                <div class="form-group">
                    <label for="username" class="form-label">{{ __('اسم المستخدم') }}</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="{{ old('username', 'admin') }}"
                            class="form-input {{ $errors->has('username') ? 'is-invalid' : '' }}"
                            placeholder="admin"
                            autocomplete="username"
                            autofocus
                        >
                    </div>
                    @error('username')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">{{ __('كلمة المرور') }}</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            value="password"
                            class="form-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                            placeholder="••••••••"
                            autocomplete="current-password"
                        >
                    </div>
                    @error('password')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span>{{ __('دخول للنظام') }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12,5 19,12 12,19"/></svg>
                </button>
            </form>

            <div class="login-demo" style="margin-top: 24px; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 16px;">
                <p class="demo-label" style="font-weight: 700; color: #cbd5e1; margin-bottom: 8px;">{{ __('حسابات للتجربة السريعة (اضغط للتعبئة):') }}</p>
                
                <div class="demo-credentials-grid">
                    <div class="demo-card" onclick="fillLogin('admin', 'password')">
                        <span class="demo-role" style="color: #f43f5e;">1. المدير العام (System Admin)</span>
                        <code>اسم المستخدم: <span class="demo-username">admin</span></code>
                        <div class="demo-desc">تحكم كامل، إدارة الرؤساء، سجلات الحركة والتقارير</div>
                    </div>

                    <div class="demo-card" onclick="fillLogin('sup_food', 'password')">
                        <span class="demo-role" style="color: #10b981;">2. رئيس قسم الأغذية</span>
                        <code>اسم المستخدم: <span class="demo-username">sup_food</span></code>
                        <div class="demo-desc">إدارة عمال ومخزون الأغذية والـ FIFO والمرتجعات</div>
                    </div>

                    <div class="demo-card" onclick="fillLogin('sup_pharma', 'password')">
                        <span class="demo-role" style="color: #06b6d4;">3. رئيس قسم الأدوية</span>
                        <code>اسم المستخدم: <span class="demo-username">sup_pharma</span></code>
                        <div class="demo-desc">إدارة عمال ومخزون الصيدلية والتجغيلات وتاريخ الصلاحية</div>
                    </div>

                    <div class="demo-card" onclick="fillLogin('worker_entry', 'password')">
                        <span class="demo-role" style="color: #3b82f6;">4. عامل إدخال وتوثيق (أغذية)</span>
                        <code>اسم المستخدم: <span class="demo-username">worker_entry</span></code>
                        <div class="demo-desc">إضافة الوارد والمنصرف فقط بدون إمكانية التعديل/الحذف</div>
                    </div>

                    <div class="demo-card" onclick="fillLogin('worker_audit', 'password')">
                        <span class="demo-role" style="color: #a855f7;">5. عامل الجرد والفرز (أدوية)</span>
                        <code>اسم المستخدم: <span class="demo-username">worker_audit</span></code>
                        <div class="demo-desc">قراءة الأرصدة، طباعة الملصقات الحرارية، وتأكيد الجرد</div>
                    </div>

                    <div class="demo-card" onclick="fillLogin('worker_view', 'password')">
                        <span class="demo-role" style="color: #eab308;">6. عامل مشاهد (عرض فقط)</span>
                        <code>اسم المستخدم: <span class="demo-username">worker_view</span></code>
                        <div class="demo-desc">متابعة النواقص والطلبيات للعرض فقط دون أي تعديل</div>
                    </div>
                </div>
                <p style="text-align: center; margin-top: 12px; font-size: 0.8rem; color: #94a3b8;">
                    {{ __('كلمة المرور الموحدة لجميع الحسابات:') }} <code>password</code>
                </p>
            </div>
        </div>
    <!-- Global WMS Loading Overlay with Lottie animation -->
    <div id="wms-loading-overlay" class="wms-loading-overlay">
        <div class="wms-loading-content">
            <div id="wms-lottie-container" class="wms-lottie-container"></div>
            <div class="wms-loading-text">{{ __('جاري التحقق وتسجيل الدخول...') }}</div>
            <div class="wms-loading-subtext">{{ __('المنظومة الذكية لإدارة المستودعات والمخزون') }}</div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
    <script src="{{ asset('js/main.js') }}"></script>
    <script>
        function fillLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
