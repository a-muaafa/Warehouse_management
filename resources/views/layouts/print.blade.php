<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('طباعة الملصقات الحرارية') }} — {{ __('نظام إدارة المستودعات') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}">
</head>
<body>
    @yield('content')

    <!-- Global WMS Loading Overlay with Lottie animation -->
    <div id="wms-loading-overlay" class="wms-loading-overlay">
        <div class="wms-loading-content">
            <div id="wms-lottie-container" class="wms-lottie-container"></div>
            <div class="wms-loading-text">{{ __('جاري معالجة وتوليد الملصقات، يرجى الانتظار...') }}</div>
            <div class="wms-loading-subtext">{{ __('المنظومة الذكية لإدارة المستودعات والمخزون') }}</div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
    <script src="{{ asset('js/main.js') }}"></script>
</body>
</html>
