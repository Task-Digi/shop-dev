<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mobile</title>

    <!-- Fonts & Bootstrap -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #2563eb;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
        }

        body {
            background-color: var(--background);
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            padding: 15px;
            margin: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Minimal Header */
        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .order-id {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .scan-controls {
            margin-top: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: stretch;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--primary);
            background: white;
            flex-shrink: 0;
        }

        .scanner-input {
            flex: 1;
            width: 0;
            min-width: 0;
            height: 60px;
            border: none;
            padding: 0 15px;
            font-size: 1.1rem;
            font-weight: 700;
            text-align: center;
            color: #1e293b;
            background: transparent;
        }

        .scanner-input::placeholder {
            color: #94a3b8;
            opacity: 1;
            font-size: 1rem;
            letter-spacing: 1px;
        }

        .scan-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 20px;
            font-weight: 800;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #mobileHistoryList {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .latest-scanned-label {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #2563eb;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 6px 10px;
            margin-bottom: 10px;
            text-align: left;
        }

        .history-item-minimal:not(:first-child) {
            display: none !important;
        }

        .history-item-minimal {
            background: var(--surface);
            border-radius: 20px;
            padding: 15px;
            box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--border);
            animation: slideDown 0.3s ease-out;
            text-align: center;
        }

        .item-name-minimal {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 5px;
            display: block;
            line-height: 1.2;
        }

        .item-meta-minimal {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 10px;
        }

        .status-badge-mobile {
            font-size: 1rem;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 10px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            margin-top: 10px;
            background: #f1f5f9;
            padding: 8px;
            border-radius: 12px;
        }

        .metric-box {
            text-align: center;
        }

        .metric-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .metric-val {
            font-size: 1.2rem;
            font-weight: 800;
            color: #334155;
        }

        .metric-val.remaining {
            color: var(--primary);
        }

        @keyframes slideDown {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .qty-pill-minimal {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .qty-btn-minimal {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: none;
            background: white;
            font-weight: 800;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e293b;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            touch-action: manipulation;
        }

        .qty-btn-minimal:active {
            background: #f1f5f9;
            transform: translateY(1px);
        }

        .qty-val-minimal {
            font-weight: 800;
            padding: 0 15px;
            min-width: 40px;
            text-align: center;
            font-size: 1.2rem;
            color: #1e293b;
        }

        .btn-delete-action {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #fee2e2;
            color: #ef4444;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-delete-action:active {
            background: #fecaca;
            transform: scale(0.95);
        }

        .small-swal-popup {
            padding: 10px !important;
            border-radius: 12px;
        }

        .report-locked .qty-btn-minimal,
        .report-locked .qty-input-minimal,
        .report-locked .btn-delete-action,
        .report-locked .scan-btn,
        .report-locked .qty-pill-minimal {
            pointer-events: none;
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

@php $isLocked = $order && $order->status === 'Completed'; @endphp
<body class="@if($isLocked) report-locked @endif">

    <div class="header">
        <div class="order-id" id="displayOrderId">
            @if($order)
            ORDER #{{ $order->order_id }}
            @else
            ORDER # ---
            @endif
        </div>
        <div class="text-secondary small fw-bold"></div>
    </div>

    <!-- Result Display Area -->
    <div class="latest-scanned-label" id="mobileLatestSectionLabel">Last Scanned</div>
    <div id="mobileHistoryList">
        @if($latestScan)
        @include('order-delivery.partials.mobile_scan_item', ['scan' => $latestScan])
        @else
        <div class="text-center text-muted mt-5" id="noScansPlaceholder">
            <p>No scans yet.</p>
            <small>Scan an item to begin.</small>
        </div>
        @endif
    </div>

    <!-- Main Input Area (Bottom) -->
    <div class="scan-controls">
        <input type="text" id="eanInput" class="scanner-input" placeholder="{{ $isLocked ? 'REPORT LOCKED' : 'SCAN EANCODE' }}" inputmode="numeric" autocomplete="off" @if($isLocked) disabled @endif>
        <button class="scan-btn" onclick="processScan()" @if($isLocked) disabled @endif>SUBMIT</button>
    </div>

    <div id="scanHistory" style="display:none;"></div>

    <!-- Scripts -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        window.ScanConfig = {
            orderId: "{{ $order ? $order->order_id : '' }}",
            isMobileView: true,
            isGlobal: true,
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            routes: {
                scan: "{{ route('order-delivery.scan') }}",
                delete: "{{ route('order-delivery.delete-scan') }}",
                update: "{{ route('order-delivery.update-units') }}",
                updateExact: "{{ route('order-delivery.update-exact') }}",
                matchOrder: "{{ route('order-delivery.match-order') }}",
                latestScan: "{{ $order ? '/order-delivery/' . $order->order_id . '/latest-scan' : '' }}",
                sync: "/order-delivery/sync/"
            },
            pusher: {
                key: "{{ config('broadcasting.connections.pusher.key') }}",
                cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}",
                channel: "{{ config('app.env') }}.order.global",
                env: "{{ config('app.env') }}"
            }
        };
        window.toggleCamera = function() {};
    </script>
    <script src="{{ asset('js/scan_system.js') }}?v={{ time() }}"></script>

    <script>
        // ─── GUARANTEED REAL-TIME SYNC VIA POLLING ───────────────────────────
        // Polls /order-delivery/global-latest-scan every 2 seconds.
        // When the latest scan changes, the card is instantly re-rendered.
        // This works REGARDLESS of Pusher delivering events correctly.

        let _lastKnownScanId    = null;
        let _lastKnownUpdatedAt = 0;
        let _lastKnownUnits     = null;
        let _lastKnownScanned   = null;
        let _lastKnownRemaining = null;
        let _lastKnownStatus    = null;
        let _pollActive        = true;

        // Nothing pre-loaded — first poll always renders
        @if($latestScan)
        _lastKnownScanId    = {{ $latestScan->id }};
        _lastKnownUpdatedAt = {{ $latestScan->updated_at ? $latestScan->updated_at->valueOf() : 0 }};
        _lastKnownUnits     = {{ $latestScan->units ?? 'null' }};
        _lastKnownScanned   = {{ $latestScan->scanned_total ?? 'null' }};
        @php
            $__gDelivered = isset($latestScan->delivery_target_total) ? (int)$latestScan->delivery_target_total : 0;
            $__gOrdered = isset($latestScan->ordered_total) ? (int)$latestScan->ordered_total : 0;
            $__gTarget = $__gDelivered > 0 ? $__gDelivered : $__gOrdered;
            $__gScanned = isset($latestScan->scanned_total) ? (int)$latestScan->scanned_total : 0;
        @endphp
        _lastKnownRemaining = {{ isset($latestScan->ordered_total, $latestScan->scanned_total) ? max(0, $__gTarget - $__gScanned) : 'null' }};
        _lastKnownStatus    = "{{ isset($latestScan->ordered_total, $latestScan->scanned_total) ? (($__gScanned - $__gTarget) == 0 ? 'COMPLETE' : (($__gScanned - $__gTarget) > 0 ? 'OVER' : 'UNDER')) : '' }}";
        @endif

        function renderMobileCard(data) {
            const list = document.getElementById('mobileHistoryList');
            if (!list) return;

            const statusColor = data.status === 'COMPLETE' ? '#22c55e'
                              : data.status === 'OVER'     ? '#ef4444' : '#2563eb';

            // Update order ID header
            const header = document.getElementById('displayOrderId');
            if (header) header.textContent = 'ORDER #' + data.order_id;

            list.innerHTML = `
                <div class="history-item-minimal" id="mobile-scan-${data.scan_id}"
                     data-ean="${data.ean_code}" data-delivery-target="${data.delivery_target !== undefined ? data.delivery_target : data.ordered}" data-scan-status="scanned">
                    <div class="item-data" style="width:100%;">
                        <div class="d-flex justify-content-end align-items-start w-100 mb-1">
                            <span class="status-badge-mobile"
                                  style="color:white; background:${statusColor}; font-size:0.85rem;">
                                ${data.status}
                            </span>
                        </div>
                        <div class="text-center mb-2">
                            <div style="font-size:1.1rem;" class="fw-800 item-name-minimal">
                                ${data.product_name}
                            </div>
                            <div class="item-meta-minimal mt-1">
                                Varenr : ${data.product_id || '—'}&nbsp;&nbsp;EAN : ${data.ean_code || '—'}
                            </div>
                            ${(data.ean_missing || (data.product_id && data.ean_code && String(data.ean_code) == String(data.product_id))) ? `
                            <div class="ean-missing-wrapper" style="justify-content: center;">
                                <div class="ean-missing-tag" style="background: #fefce8; border: 1.5px solid #facc15; border-radius: 50px; padding: 2px 8px; font-size: 0.65rem; font-weight: 800; color: #a16207; text-transform: uppercase;">
                                    &#9888; EAN MISSING &ndash; Using VareNR
                                </div>
                            </div>
                            ` : ''}
                        </div>
                        <div class="metrics-grid mb-3">
                            <div class="metric-box">
                                <span class="metric-label">Ordered</span>
                                <span class="metric-val row-ordered-val" data-ean="${data.ean_code}">${data.ordered}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">Scanned</span>
                                <span class="metric-val row-scanned-val" data-ean="${data.ean_code}">${data.scanned}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">Rest</span>
                                <span class="metric-val remaining row-remaining-val" data-ean="${data.ean_code}">${data.remaining}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">#</span>
                                <span class="metric-val units-val" id="mq-${data.scan_id}">${data.units}</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center align-items-center gap-4">
                            <button type="button" class="qty-btn-minimal fw-800"
                                    style="color:#2563eb;background:#eff6ff;border-radius:8px;font-size:1rem;padding:0 10px;"
                                    onclick="matchOrderScans(${data.scan_id})">#OK</button>
                            <div class="qty-pill-minimal">
                                <button type="button" class="qty-btn-minimal"
                                        onclick="updateUnits(${data.scan_id}, -1)">−</button>
                                <input type="number" inputmode="numeric" class="qty-input-minimal qty-input-${data.scan_id}"
                                       id="mobile-qty-input-${data.scan_id}"
                                       value="${data.units}"
                                       onchange="updateUnitsExact(${data.scan_id}, this.value)"
                                       style="width:50px;text-align:center;font-weight:800;font-size:1.2rem;border:none;background:transparent;padding:0;margin:0 5px;color:#1e293b;outline:none;">
                                <button type="button" class="qty-btn-minimal text-success"
                                        onclick="updateUnits(${data.scan_id}, 1)">+</button>
                            </div>
                            <button class="btn-delete-action shadow-sm"
                                    onclick="deleteScan(${data.scan_id})">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3
                                             0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>`;
        }

        async function pollGlobalLatest() {
            if (!_pollActive) return;
            try {
                const res  = await fetch('/order-delivery/global-latest-scan', { cache: 'no-store' });
                const data = await res.json();

                if (!data.success || !data.has_scan) return;

                // Handle dynamic locking
                const isLocked = data.order_status === 'Completed';
                if (isLocked) {
                    document.body.classList.add('report-locked');
                    const input = document.getElementById('eanInput');
                    if (input) {
                        input.disabled = true;
                        input.placeholder = 'REPORT LOCKED';
                    }
                    const btn = document.querySelector('.scan-btn');
                    if (btn) btn.disabled = true;
                } else {
                    document.body.classList.remove('report-locked');
                    const input = document.getElementById('eanInput');
                    if (input && input.disabled) {
                        input.disabled = false;
                        input.placeholder = 'SCAN EANCODE';
                    }
                    const btn = document.querySelector('.scan-btn');
                    if (btn && btn.disabled) btn.disabled = false;
                }

                const serverUpdatedAt = data.updated_at || 0;
                const shouldRender = (
                    data.scan_id !== _lastKnownScanId ||
                    serverUpdatedAt !== _lastKnownUpdatedAt ||
                    data.units !== _lastKnownUnits ||
                    data.scanned !== _lastKnownScanned ||
                    data.remaining !== _lastKnownRemaining ||
                    data.status !== _lastKnownStatus
                );

                if (shouldRender) {
                    _lastKnownScanId    = data.scan_id;
                    _lastKnownUpdatedAt = serverUpdatedAt;
                    _lastKnownUnits     = data.units;
                    _lastKnownScanned   = data.scanned;
                    _lastKnownRemaining = data.remaining;
                    _lastKnownStatus    = data.status;
                    renderMobileCard(data);
                }
            } catch (e) {}
        }

        // Start polling immediately and every 2 seconds
        pollGlobalLatest();
        setInterval(pollGlobalLatest, 2000);
    </script>

    <script>
        document.addEventListener('click', function(e) {
            const input = document.getElementById('eanInput');
            if (input && window.innerWidth >= 768 && e.target.tagName !== 'INPUT' && !e.target.closest('button')) {
                input.focus();
            }
        });
        document.addEventListener('dblclick', function(e) { e.preventDefault(); }, { passive: false });
    </script>

</body>

</html>