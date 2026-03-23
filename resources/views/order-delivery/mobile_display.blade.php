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

        /* ONLY show the most recent item (first in list) */
        #mobileHistoryList .history-item-minimal:not(:first-of-type) {
            display: none !important;
        }

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
    </style>
</head>

<body>

    <div class="header">
        <div class="order-id">ORDER #{{ $order->order_id }}</div>
        <div class="text-secondary small fw-bold">MOBILE SCANNER</div>
    </div>

    <!-- Result Display Area -->
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
        <input type="text" id="eanInput" class="scanner-input" placeholder="SCAN EANCODE" inputmode="numeric" autocomplete="off">
        <button class="scan-btn" onclick="processScan()">SUBMIT</button>
    </div>

    <!-- Hidden elements required by scan_system.js to avoid errors -->
    <div id="scanHistory" style="display:none;"></div>

    <!-- Scripts -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        window.ScanConfig = {
            orderId: "{{ $order->order_id }}",
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            routes: {
                scan: "{{ route('order-delivery.scan') }}",
                delete: "{{ route('order-delivery.delete-scan') }}",
                update: "{{ route('order-delivery.update-units') }}",
                matchOrder: "{{ route('order-delivery.match-order') }}",
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
    </script>
    <script src="{{ asset('js/scan_system.js') }}"></script>

    <!-- Auto-focus keep-alive -->
    <script>
        // Force focus back to input if lost, unless user is selecting text (Disabled for mobile specifically to prevent keyboard popup)
        const input = document.getElementById('eanInput');
        document.addEventListener('click', function(e) {
            // Only auto-focus on desktop devices (width >= 768px)
            if (window.innerWidth >= 768 && e.target.tagName !== 'INPUT' && !e.target.closest('button')) {
                input.focus();
            }
        });

        // Prevent zoom on double tap
        document.addEventListener('dblclick', function(event) {
            event.preventDefault();
        }, {
            passive: false
        });
    </script>

</body>

</html>