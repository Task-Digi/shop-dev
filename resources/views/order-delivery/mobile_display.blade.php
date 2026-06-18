@php $isLocked = $order->status === 'Completed'; @endphp
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mobile Scan - #{{ $order->order_id }}</title>

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

        /* Input Styling */
        /* Input Styling Fix */
        /* Input Styling Fix */
        .scan-controls {
            /* Removed margin-top: auto to stop pushing to bottom */
            margin-top: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: stretch;
            /* Ensure full height */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--primary);
            background: white;
            flex-shrink: 0;
            /* Don't shrink */
        }

        .scanner-input {
            flex: 1;
            /* Take remaining space */
            width: 0;
            /* Fix flexbox text overflow issue */
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
            /* Prevent text wrap */
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Scan Result Container */
        #mobileHistoryList {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
            /* Removed flex-grow: 1 and justify-content: center to keep it at top */
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

        /* 
           CRITICAL: Only show the FIRST item (latest scan).
           scan_system.js prepends items, so first-child is always the newest.
        */
        .history-item-minimal:not(:first-child) {
            display: none !important;
        }

        /* Item Styling (Re-use similar styles from main view but larger for mobile focus) */
        .history-item-minimal {
            background: var(--surface);
            border-radius: 20px;
            /* Slightly MORE rounded for standalone focus */
            padding: 15px;
            box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.12);
            /* More premium depth */
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
            /* Tight gap for 4 cols */
            margin-top: 10px;
            background: #f1f5f9;
            padding: 8px;
            /* Slightly less padding */
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

        /* Hide unwanted elements generated by JS if any */
        /* Elements are now used */
        /* Action Section Styling */
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

        /* Delete Button */
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

        /* Sweet Alert Small Modal */
        .small-swal-popup {
            padding: 10px !important;
            border-radius: 12px;
        }

        div:where(.swal2-container) h2:where(.swal2-title) {
            padding: .8em 1em 0;
            font-size: 1.25rem;
            margin: 0;
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

@php $isLocked = $order->status === 'Completed'; @endphp
<body class="@if($isLocked) report-locked @endif">

    <div class="header">
        <div class="order-id">ORDER #{{ $order->order_id }}</div>
        <div class="text-secondary small fw-bold">MOBILE SCANNER</div>
    </div>

    <!-- Result Display Area -->
    <div class="latest-scanned-label" id="mobileLatestSectionLabel">Last Scanned</div>
    <div id="mobileHistoryList">
        <!-- JS will inject .history-item-minimal here. CSS hides all but the first. -->
        @if($latestScan)
        @include('order-delivery.partials.mobile_scan_item', ['scan' => $latestScan])
        @else
        <div class="text-center text-muted mt-5">
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

    <!-- Hidden elements required by scan_system.js to avoid errors -->
    <div id="scanHistory" style="display:none;"></div>

    <!-- Scripts -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        window.ScanConfig = {
            orderId: "{{ $order->order_id }}",
            isMobileView: true,
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            routes: {
                scan: "{{ route('order-delivery.scan') }}",
                delete: "{{ route('order-delivery.delete-scan') }}",
                update: "{{ route('order-delivery.update-units') }}",
                updateExact: "{{ route('order-delivery.update-exact') }}",
                matchOrder: "{{ route('order-delivery.match-order') }}",
                latestScan: "/order-delivery/{{ $order->order_id }}/latest-scan",
                sync: "/order-delivery/sync/"
            },
            pusher: {
                key: "{{ config('broadcasting.connections.pusher.key') }}",
                cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}",
                channel: "{{ config('app.env') }}.order.{{ $order->order_id }}",
                env: "{{ config('app.env') }}"
            }
        };

        // Disable Quagga/Camera functions if called by JS (safety placeholder)
        window.toggleCamera = function() {};
        // Prevent zoom on double tap
        document.addEventListener('dblclick', function(event) {
            event.preventDefault();
        }, {
            passive: false
        });
    </script>

    <script src="{{ asset('js/scan_system.js') }}?v={{ time() }}"></script>

    <!-- ─── POLLING SYNC for order-specific mobile view ──────────────────── -->
    <script>
        (function() {
            const orderId = "{{ $order->order_id }}";
            const pollUrl = '/order-delivery/' + orderId + '/latest-scan';

            let _lastScanId   = {{ $latestScan ? $latestScan->id : 'null' }};
            let _lastUnits    = {{ $latestScan ? $latestScan->units : 'null' }};
            let _lastUpdatedAt = {{ $latestScan && $latestScan->updated_at ? $latestScan->updated_at->valueOf() : 0 }};
            let _lastScanned = {{ isset($latestScan->scanned_total) ? $latestScan->scanned_total : 'null' }};
            @php
                $__mobDelivered = isset($latestScan->delivery_target_total) ? (int)$latestScan->delivery_target_total : 0;
                $__mobOrdered = isset($latestScan->ordered_total) ? (int)$latestScan->ordered_total : 0;
                $__mobTarget = $__mobDelivered > 0 ? $__mobDelivered : $__mobOrdered;
                $__mobScanned = isset($latestScan->scanned_total) ? (int)$latestScan->scanned_total : 0;
            @endphp
            let _lastRemaining = {{ isset($latestScan->ordered_total, $latestScan->scanned_total) ? max(0, $__mobTarget - $__mobScanned) : 'null' }};
            let _lastStatus = "{{ isset($latestScan->ordered_total, $latestScan->scanned_total) ? (($__mobScanned - $__mobTarget) == 0 ? 'COMPLETE' : (($__mobScanned - $__mobTarget) > 0 ? 'OVER' : 'UNDER')) : '' }}";

            function buildCard(d) {
                const statusColor = d.status === 'COMPLETE' ? '#22c55e'
                                  : d.status === 'OVER'     ? '#ef4444' : '#2563eb';
                return `<div class="history-item-minimal" id="mobile-scan-${d.scan_id}"
                              data-ean="${d.ean_code}" data-delivery-target="${d.delivery_target !== undefined ? d.delivery_target : d.ordered}" data-scan-status="scanned">
                    <div class="item-data" style="width:100%;">
                        <div class="d-flex justify-content-end align-items-start w-100 mb-1">
                            <span class="status-badge-mobile"
                                  style="color:white;background:${statusColor};font-size:0.85rem;">${d.status}</span>
                        </div>
                        <div class="text-center mb-2">
                            <div style="font-size:1.1rem;" class="fw-800 item-name-minimal">${d.product_name}</div>
                            <div class="item-meta-minimal mt-1">Varenr : ${d.product_id || '—'}&nbsp;&nbsp;EAN : ${d.ean_code || '—'}</div>
                        </div>
                            <div class="metrics-grid mb-3">
                                <div class="metric-box"><span class="metric-label">Ordered</span><span class="metric-val row-ordered-val" data-ean="${d.ean_code}">${d.ordered}</span></div>
                                <div class="metric-box"><span class="metric-label">Scanned</span><span class="metric-val row-scanned-val" data-ean="${d.ean_code}">${d.scanned}</span></div>
                                <div class="metric-box"><span class="metric-label">Rest</span><span class="metric-val remaining row-remaining-val" data-ean="${d.ean_code}">${d.remaining}</span></div>
                                <div class="metric-box"><span class="metric-label">#</span><span class="metric-val units-val" id="mq-${d.scan_id}">${d.units}</span></div>
                            </div>
                            <div class="d-flex justify-content-center align-items-center gap-4">
                                <button type="button" class="qty-btn-minimal fw-800"
                                        style="color:#2563eb;background:#eff6ff;border-radius:8px;font-size:1rem;padding:0 10px;"
                                        onclick="matchOrderScans(${d.scan_id})">#OK</button>
                                <div class="qty-pill-minimal">
                                    <button type="button" class="qty-btn-minimal" onclick="updateUnits(${d.scan_id},-1)">−</button>
                                    <input type="number" inputmode="numeric" class="qty-input-minimal qty-input-${d.scan_id}"
                                           id="mobile-qty-input-${d.scan_id}" value="${d.units}"
                                           onchange="updateUnitsExact(${d.scan_id},this.value)"
                                           style="width:50px;text-align:center;font-weight:800;font-size:1.2rem;border:none;background:transparent;padding:0;margin:0 5px;color:#1e293b;outline:none;">
                                    <button type="button" class="qty-btn-minimal text-success" onclick="updateUnits(${d.scan_id},1)">+</button>
                                </div>
                            <button class="btn-delete-action shadow-sm" onclick="deleteScan(${d.scan_id})">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>`;
            }

            async function pollOrderLatest() {
                try {
                    const res  = await fetch(pollUrl, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) return;

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

                    if (!data.has_scan) {
                        if (_lastScanId !== null) {
                            _lastScanId = null; _lastUnits = null;
                            const list = document.getElementById('mobileHistoryList');
                            if (list) list.innerHTML = '<div class="text-center text-muted mt-5"><p>No scans yet.</p></div>';
                        }
                        return;
                    }

                    const serverUpdatedAt = data.updated_at || 0;
                    const shouldRender = (
                        data.scan_id !== _lastScanId ||
                        data.units !== _lastUnits ||
                        serverUpdatedAt !== _lastUpdatedAt ||
                        data.scanned !== _lastScanned ||
                        data.remaining !== _lastRemaining ||
                        data.status !== _lastStatus
                    );
                    if (shouldRender) {
                        _lastScanId = data.scan_id;
                        _lastUnits = data.units;
                        _lastUpdatedAt = serverUpdatedAt;
                        _lastScanned = data.scanned;
                        _lastRemaining = data.remaining;
                        _lastStatus = data.status;
                        const list = document.getElementById('mobileHistoryList');
                        if (list) list.innerHTML = buildCard(data);
                    }
                } catch(e) { /* network hiccup, try next time */ }
            }

            // Fire immediately then every 2s
            pollOrderLatest();
            setInterval(pollOrderLatest, 2000);
        })();
    </script>

</body>

</html>