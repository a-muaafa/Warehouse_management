@extends('layouts.print')

@section('content')
<div class="print-page-wrapper">
    <!-- Print toolbar -->
    <div class="print-controls no-print">
        <button onclick="window.print()" class="print-btn">{{ __('طباعة الملصقات') }} ({{ $copies }})</button>
        <div style="display:flex; gap:8px; align-items:center;">
            <label style="font-size:13px; font-weight:600;">{{ __('عدد النسخ:') }}</label>
            <input type="number" value="{{ $copies }}" min="1" max="50" style="width:60px; padding:6px; border:1px solid #ccc; border-radius:4px;" onchange="window.showWmsLoader('جاري إعادة توليد الملصقات...'); window.location.href='?copies='+this.value+'&template={{ $template }}'">
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <label style="font-size:13px; font-weight:600;">{{ __('القالب:') }}</label>
            <select style="padding:6px; border:1px solid #ccc; border-radius:4px;" onchange="window.showWmsLoader('جاري تغيير القالب وتحديث المعاينة...'); window.location.href='?copies={{ $copies }}&template='+this.value">
                <option value="shelf_label" {{ $template === 'shelf_label' ? 'selected' : '' }}>{{ __('ملصق الرف الحراري (100x50mm)') }}</option>
                <option value="product_tag" {{ $template === 'product_tag' ? 'selected' : '' }}>{{ __('بطاقة المنتج (50x30mm)') }}</option>
            </select>
        </div>
        <a href="{{ route('worker.labels.search') }}" class="back-btn">← {{ __('العودة للبحث') }}</a>
    </div>

    <!-- Printable Sheet -->
    <div class="labels-sheet-grid">
        @for($i = 0; $i < $copies; $i++)
            @if($template === 'shelf_label')
            <!-- Shelf Label Template with Barcode & QR -->
            <div class="label-shelf">
                <div class="label-shelf-header">
                    <div>
                        <div class="label-title">{{ $item->name }}</div>
                        <div style="font-size:7pt; color:#666;">رمز: {{ $item->sku ?: $item->barcode }} | التصنيف: {{ $item->category?->name ?: 'عام' }}</div>
                    </div>
                    <div class="label-price-box">
                        <div style="font-size:6.5pt; color:#666; text-transform:uppercase;">{{ __('Price') }}</div>
                        <div class="label-price-val">{{ number_format($item->selling_price, 2) }} ر.س</div>
                    </div>
                </div>

                <div class="label-shelf-body">
                    <div class="label-barcode-area">
                        <svg class="barcode-svg-target" data-barcode="{{ $item->barcode }}"></svg>
                        <div style="font-family:monospace; font-size:7pt; font-weight:bold;">{{ $item->barcode }}</div>
                    </div>
                    <div class="label-qr-area" id="qr-target-{{ $i }}"></div>
                </div>

                <div class="label-shelf-footer">
                    <span><strong>{{ __('Loc:') }}</strong> {{ $item->default_location ?: 'المستودع الرئيسي' }}</span>
                    <span><strong>{{ __('Unit:') }}</strong> {{ $item->primaryUnit?->symbol ?: $item->unit }}</span>
                    <span><strong>نظام المستودعات</strong></span>
                </div>
            </div>
            @else
            <!-- Product Compact Tag (50x30mm) -->
            <div class="label-tag">
                <div class="label-name">{{ $item->name }}</div>
                <div class="label-barcode">
                    <svg class="barcode-svg-target" data-barcode="{{ $item->barcode }}"></svg>
                </div>
                <div class="label-code">{{ $item->barcode }}</div>
                <div style="font-size:5.5pt; color:#444;">{{ number_format($item->selling_price, 2) }} ر.س | {{ $item->default_location ?: 'المستودع الرئيسي' }}</div>
            </div>
            @endif
        @endfor
    </div>
</div>

{{-- Barcode & QR code client libraries --}}
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Render all barcodes
        document.querySelectorAll('.barcode-svg-target').forEach(el => {
            JsBarcode(el, el.getAttribute('data-barcode'), {
                format: 'CODE128',
                width: 1.5,
                height: 35,
                displayValue: false,
                margin: 0,
                background: '#ffffff',
                lineColor: '#000000',
            });
        });

        // Render QR codes if shelf label
        @if($template === 'shelf_label')
        @for($i = 0; $i < $copies; $i++)
        new QRCode(document.getElementById("qr-target-{{ $i }}"), {
            text: "{{ $item->barcode }}",
            width: 58,
            height: 58,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.M
        });
        @endfor
        @endif
    });
</script>
@endsection
