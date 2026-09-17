@extends('layouts.app')

@section('title', __('Mobile & Handheld Scanner'))
@section('page-title', __('Mobile & Handheld Scanner'))

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a> / <span>{{ __('Scanner') }}</span>
@endsection

@section('content')
<div class="scanner-container">
    <!-- Scanner Header & Mode Toggle -->
    <div class="glass-card mb-4">
        <div class="scanner-controls-header">
            <div class="scanner-badge-status">
                <span class="status-indicator active"></span>
                <span>{{ __('Scanner Mode:') }} <strong id="scannerModeText">{{ __('Live Camera + Hardware Gun') }}</strong></span>
            </div>
            <div class="scanner-actions-btn-group">
                <button type="button" id="btnToggleCamera" class="btn btn-primary btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-inline-end:4px;"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <span id="cameraBtnText">{{ __('Start Camera') }}</span>
                </button>
                <button type="button" id="btnAudioToggle" class="btn btn-secondary btn-sm" title="{{ __('Sound Feedback') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-inline-end:4px;"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                    <span>{{ __('Sound: ON') }}</span>
                </button>
            </div>
        </div>
    </div>

    <div class="scanner-grid">
        <!-- Left: Interactive Scanner & Hardware Input -->
        <div class="scanner-viewport-col">
            <div class="glass-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;margin-inline-end:6px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="7" y1="12" x2="17" y2="12"/></svg>
                        {{ __('Scan Input Zone') }}
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Manual / Gun Barcode Input Field -->
                    <div class="form-group mb-3">
                        <label class="form-label">{{ __('Barcode / QR Code / SKU (Auto-focused for Scanner Guns)') }}</label>
                        <div class="input-with-action">
                            <input type="text" id="barcodeManualInput" class="form-control form-control-lg" placeholder="{{ __('Scan with barcode gun or type code...') }}" autofocus autocomplete="off">
                            <button type="button" id="btnLookupManual" class="btn btn-primary">
                                {{ __('Lookup') }}
                            </button>
                        </div>
                        <small class="form-hint">{{ __('Hardware barcode scanner guns automatically submit when scanned.') }}</small>
                    </div>

                    <!-- Camera Video Stream Area -->
                    <div id="cameraScannerWrapper" style="display:none; position:relative; border-radius:12px; overflow:hidden; background:#000; border:2px dashed var(--color-border); margin-top:1rem;">
                        <div id="interactiveReader" style="width:100%; min-height:280px;"></div>
                        <div class="scanner-target-crosshair"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Scanned Item Details & Fast Inbound/Outbound Actions -->
        <div class="scanner-results-col">
            <div class="glass-card" id="lookupResultCard" style="display:none;">
                <div class="card-header" style="border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem;">
                    <div>
                        <span class="badge badge-success" id="resItemCategory">-</span>
                        <h2 class="card-title mt-1" id="resItemName" style="font-size: 1.3rem;">-</h2>
                    </div>
                    <span class="badge badge-neutral" id="resItemBarcode">-</span>
                </div>
                <div class="card-body">
                    <div class="item-stats-summary-grid mb-3">
                        <div class="stat-mini-box">
                            <span class="stat-label">{{ __('Current Balance') }}</span>
                            <span class="stat-value" id="resItemQty">-</span>
                            <span class="stat-unit" id="resItemUnit">-</span>
                        </div>
                        <div class="stat-mini-box">
                            <span class="stat-label">{{ __('Unit Cost') }}</span>
                            <span class="stat-value" id="resItemCost">-</span>
                        </div>
                        <div class="stat-mini-box">
                            <span class="stat-label">{{ __('Selling Price') }}</span>
                            <span class="stat-value" id="resItemPrice">-</span>
                        </div>
                        <div class="stat-mini-box">
                            <span class="stat-label">{{ __('Department') }}</span>
                            <span class="stat-value" id="resItemDepartment">-</span>
                        </div>
                    </div>

                    <!-- Active Lots / Batches breakdown -->
                    <div class="batches-section mb-4">
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem; color: var(--color-text-muted);">{{ __('Active Batches & Expiry Dates') }}</h4>
                        <div class="table-responsive">
                            <table class="data-table" style="font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th>{{ __('Batch #') }}</th>
                                        <th>{{ __('Qty') }}</th>
                                        <th>{{ __('Expiry Date') }}</th>
                                        <th>{{ __('Location') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="resItemBatchesBody">
                                    <tr><td colspan="4" class="text-center text-muted">{{ __('No active batches') }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Instant Quick Transaction Form -->
                    <form method="POST" action="{{ route('transactions.store') }}" id="quickTransactionForm" class="quick-tx-form">
                        @csrf
                        <input type="hidden" name="item_id" id="formItemId">
                        
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Operation Type') }}</label>
                                <select name="type" id="quickTxType" class="form-select form-control-sm">
                                    <option value="in">{{ __('Stock In (Receipt)') }}</option>
                                    <option value="out">{{ __('Stock Out (Issue/Dispatch)') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Quantity') }}</label>
                                <input type="number" name="quantity" id="quickTxQty" class="form-control form-control-sm" value="1" min="1" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Warehouse') }}</label>
                                <select name="warehouse_id" class="form-select form-control-sm">
                                    <option value="">{{ __('Select Warehouse') }}</option>
                                    @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Ref Doc # (PO / DN)') }}</label>
                                <input type="text" name="reference_doc" class="form-control form-control-sm" placeholder="PO-12345">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success flex-grow-1">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-inline-end:4px;"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ __('Submit Transaction') }}
                            </button>
                            <a href="#" id="btnPrintLabelLink" class="btn btn-secondary" target="_blank" title="{{ __('Print Label') }}" style="display:inline-flex; align-items:center; gap:0.35rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                                <span>{{ __('Print Label') }}</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Empty Placeholder Card -->
            <div class="glass-card text-center py-5" id="lookupPlaceholderCard">
                <div style="margin-bottom: 1rem; opacity: 0.6; display: flex; justify-content: center;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </div>
                <h3 class="text-muted">{{ __('Ready to Scan') }}</h3>
                <p class="text-muted" style="max-width: 320px; margin: 0 auto; font-size: 0.9rem;">
                    {{ __('Scan any item barcode or QR code with your handheld scanner or start the device camera to view details and process instant stock movements.') }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Load HTML5-QRCode Library from CDN for camera scanning --}}
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    let html5QrCode = null;
    let cameraRunning = false;
    let soundEnabled = true;

    // Audio beeper using Web Audio API
    function playBeep(success = true) {
        if (!soundEnabled) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = success ? 880 : 330; // A5 or E4
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + (success ? 0.12 : 0.25));
        } catch (e) {
            console.log('Audio not supported', e);
        }
    }

    document.getElementById('btnAudioToggle').addEventListener('click', function() {
        soundEnabled = !soundEnabled;
        this.innerHTML = soundEnabled ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-inline-end:4px;"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg> <span>{{ __("Sound: ON") }}</span>' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-inline-end:4px;"><line x1="1" y1="1" x2="23" y2="23"></line><path d="M9 9v6a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"></path></svg> <span>{{ __("Sound: OFF") }}</span>';
    });

    // Lookup Item function
    function lookupCode(code) {
        if (!code || code.trim() === '') return;
        
        fetch('{{ route("scanner.lookup") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ code: code.trim() })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.item) {
                playBeep(true);
                renderItemResult(data.item);
            } else {
                playBeep(false);
                alert(data.message || '{{ __("Item not found.") }}');
            }
        })
        .catch(err => {
            playBeep(false);
            console.error(err);
        });
    }

    function renderItemResult(item) {
        document.getElementById('lookupPlaceholderCard').style.display = 'none';
        document.getElementById('lookupResultCard').style.display = 'block';

        document.getElementById('formItemId').value = item.id;
        document.getElementById('resItemName').textContent = item.name;
        document.getElementById('resItemCategory').textContent = item.category || '{{ __("Uncategorized") }}';
        document.getElementById('resItemBarcode').textContent = item.barcode;
        document.getElementById('resItemQty').textContent = item.quantity;
        document.getElementById('resItemUnit').textContent = item.unit;
        document.getElementById('resItemCost').textContent = Number(item.purchase_price || 0).toFixed(2) + ' ر.س';
        document.getElementById('resItemPrice').textContent = Number(item.selling_price || 0).toFixed(2) + ' ر.س';
        document.getElementById('resItemDepartment').textContent = item.department;

        // Label Print URL
        document.getElementById('btnPrintLabelLink').href = '/worker/labels/' + item.id + '/print';

        // Batches Table
        const tbody = document.getElementById('resItemBatchesBody');
        tbody.innerHTML = '';
        if (item.batches && item.batches.length > 0) {
            item.batches.forEach(b => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${b.batch_number}</strong></td>
                    <td><span class="badge badge-info">${b.quantity_remaining}</span></td>
                    <td>${b.expiry_date}</td>
                    <td><code>${b.storage_location}</code></td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">{{ __("No active batches found") }}</td></tr>`;
        }
    }

    // Manual input submit
    document.getElementById('btnLookupManual').addEventListener('click', function() {
        const input = document.getElementById('barcodeManualInput');
        lookupCode(input.value);
        input.value = '';
    });

    document.getElementById('barcodeManualInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookupCode(this.value);
            this.value = '';
        }
    });

    // Camera Toggle
    document.getElementById('btnToggleCamera').addEventListener('click', function() {
        const wrapper = document.getElementById('cameraScannerWrapper');
        const btnText = document.getElementById('cameraBtnText');

        if (!cameraRunning) {
            wrapper.style.display = 'block';
            html5QrCode = new Html5Qrcode("interactiveReader");
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 15, qrbox: { width: 250, height: 180 } },
                (decodedText) => {
                    lookupCode(decodedText);
                },
                (errorMessage) => {
                    // Scanning stream
                }
            ).then(() => {
                cameraRunning = true;
                btnText.textContent = '{{ __("Stop Camera") }}';
            }).catch(err => {
                alert('{{ __("Unable to access camera:") }} ' + err);
            });
        } else {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                wrapper.style.display = 'none';
                cameraRunning = false;
                btnText.textContent = '{{ __("Start Camera") }}';
            });
        }
    });
</script>
@endpush
