<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light only">
    <title>Reception Hub #{{ $order->order_id }}</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- QuaggaJS -->
    <!-- html2pdf.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            color-scheme: light;
            --primary: #2563eb;
            --secondary: #64748b;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
        }

        body {
            background-color: var(--background);
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            overflow-x: hidden;
            font-size: 0.9rem;
        }

        .reception-hub {
            padding: 5px;
            max-width: 100%;
            margin: 0;
            overflow-x: hidden;
        }

        .hub-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding: 6px 12px;
        }

        .hub-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .session-info {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--secondary);
            display: flex;
            gap: 10px;
        }

        * {
            box-sizing: border-box;
        }

        body,
        html {
            overflow-x: hidden;
            width: 100%;
            position: relative;
        }

        /* Main Grid */
        .hub-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 10px;
        }

        /* Scanner Column */
        .scanner-panel {
            position: sticky;
            top: 10px;
        }

        .reception-card {
            padding: 15px;
            margin-bottom: 15px;
        }

        .scanner-input {
            height: 45px;
            flex: 1;
            width: auto;
            min-width: 0;
            border: 2px solid var(--primary);
            border-radius: 8px 0 0 8px;
            padding: 0 15px;
            font-size: 1.2rem;
            font-weight: 800;
            text-align: center;
            background: #f8fafc;
            color: #1e293b;
            transition: all 0.2s;
        }

        .scanner-input:focus {
            outline: none;
            background: #fff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .scanner-input::placeholder {
            color: #cbd5e1;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .scan-submit-btn {
            height: 45px;
            border-radius: 0 8px 8px 0;
            padding: 0 10px;
            font-weight: 800;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
            box-shadow: none !important;
            flex-grow: 0;
            min-width: 80px;
        }

        /* Sidebar Inputs */
        .scanner-panel .form-control {
            min-height: 45px;
            font-size: 0.95rem;
            border-radius: 6px;
        }

        .scanner-panel label {
            font-size: 0.75rem;
            margin-bottom: 5px !important;
        }

        /* Hide Number Spinners */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        /* Product Monitor */
        #productMonitor {
            background: var(--surface);
            border-radius: 8px;
            padding: 10px;
            border: 2px solid var(--border);
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .monitor-label {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--secondary);
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .monitor-name {
            font-size: 1rem;
            font-weight: 800;
            display: block;
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .state-badge {
            font-size: 0.7rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 5px;
        }

        .state-UNDER {
            background: #eff6ff;
            color: var(--primary);
            border: 1px solid #bfdbfe;
        }

        .state-COMPLETE {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .state-OVER {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            animation: shake 0.5s;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-4px);
            }

            75% {
                transform: translateX(4px);
            }
        }

        .grid-label {
            font-size: 0.6rem;
            font-weight: 700;
            color: var(--secondary);
            text-transform: uppercase;
        }

        .metric-wrapper {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }

        .metric-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 2px 8px;
            text-align: center;
            min-width: 60px;
        }

        .metric-val {
            display: block;
            font-size: 0.85rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 1px;
        }

        .metric-label {
            display: block;
            font-size: 0.55rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .metric-remaining {
            border-color: #3b82f630;
            background: #3b82f605;
        }

        .metric-remaining .metric-val {
            color: #3b82f6;
        }

        /* History Column */
        .history-card {
            border-radius: 8px;
            overflow: hidden;
        }

        .table-modern {
            margin: 0;
            font-size: 0.85rem;
        }

        .table-modern thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            background: #f8fafc;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 8px 10px;
            border-bottom: 1px solid var(--border);
        }

        .table-modern tbody td {
            padding: 2px 5px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .qty-pill {
            background: #f1f5f9;
            padding: 2px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
        }

        .qty-btn {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: none;
            background: white;
            font-weight: 800;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-val {
            font-weight: 700;
            padding: 0 6px;
            min-width: 25px;
            text-align: center;
            font-size: 0.8rem;
        }

        .premium-swal-popup {
            border-radius: 1rem !important;
            padding: 1.5rem !important;
            width: auto !important;
            min-width: 320px !important;
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.2) !important;
        }

        .premium-swal-title {
            font-family: 'Inter', sans-serif !important;
            font-weight: 800 !important;
            color: #1e293b !important;
            font-size: 1.1rem !important;
            margin: 0 !important;
            padding-top: 5px !important;
        }

        .premium-swal-content {
            font-family: 'Inter', sans-serif !important;
            font-weight: 500 !important;
            color: #64748b !important;
            font-size: 0.8rem !important;
            margin: 5px 0 10px 0 !important;
        }

        /* Target SweetAlert2 Icon for TOASTS only to avoid breaking large modals */
        .premium-swal-toast {
            border-radius: 0.5rem !important;
            padding: 0.5rem 1rem !important;
            width: auto !important;
            min-width: 150px !important;
            box-shadow: 0 5px 15px -5px rgba(0, 0, 0, 0.1) !important;
            display: flex !important;
            align-items: center !important;
        }

        .premium-swal-toast .swal2-title {
            font-size: 0.9rem !important;
            margin: 0 !important;
            font-weight: 800 !important;
        }

        .premium-swal-toast .swal2-icon {
            width: 1.5em !important;
            height: 1.5em !important;
            margin: 0 0.5em 0 0 !important;
            border-width: 2px !important;
        }

        .premium-swal-toast .swal2-icon .swal2-icon-content {
            font-size: 1rem !important;
        }

        .premium-swal-confirm {
            border-radius: 50px !important;
            padding: 8px 20px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            font-size: 0.8rem !important;
        }

        .premium-swal-cancel {
            border-radius: 50px !important;
            padding: 8px 20px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            font-size: 0.8rem !important;
            background-color: #f1f5f9 !important;
            color: #475569 !important;
        }

        /* Classic Premium Success UI */
        .classic-premium-popup {
            border-radius: 2rem !important;
            padding: 2.5rem !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            background: #ffffff !important;
        }

        .classic-premium-title {
            font-family: 'Inter', sans-serif !important;
            font-weight: 900 !important;
            color: #0f172a !important;
            font-size: 1.5rem !important;
            letter-spacing: -0.02em !important;
            margin-bottom: 0.5rem !important;
            text-transform: uppercase !important;
        }

        .classic-premium-content {
            font-family: 'Inter', sans-serif !important;
            font-weight: 500 !important;
            color: #475569 !important;
            font-size: 1.1rem !important;
            line-height: 1.6 !important;
        }

        .classic-premium-confirm {
            background: #22c55e !important;
            border-radius: 12px !important;
            padding: 14px 40px !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.3) !important;
            transition: all 0.2s ease !important;
            margin-top: 1rem !important;
        }

        .classic-premium-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 20px 25px -5px rgba(34, 197, 94, 0.4) !important;
        }

        /* Large Success Icon Refinement - Simplified to avoid clipping */
        .classic-premium-icon {
            border: 4px solid #dcfce7 !important;
            margin: 0 auto 1.5em auto !important;
            transform: scale(1.2) !important;
            /* Elegant scaling without clipping */
        }

        .price-input-container {
            position: relative;
            margin: 1rem 0;
            background: #f8fafc;
            border-radius: 0.8rem;
            padding: 0.8rem;
            border: 2px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .price-input-container:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            background: white;
        }


        .latest-scan-container {
            margin-bottom: 10px;
            overflow: hidden;
        }

        .latest-scan-header {
            background: #eff6ff;
            color: #2563eb;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 5px 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .latest-scan-row {
            border-left: 4px solid #2563eb;
        }

        .latest-scan-label {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            padding: 4px 10px;
            background: #eff6ff;
            border-bottom: 1px solid #dbeafe;
            letter-spacing: 0.05em;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 1.5cm;
            }

            .navbar,
            .reception-hub,
            .swal2-container,
            .card,
            .btn,
            .btn-outline-secondary,
            .scanner-panel,
            .logs-panel {
                display: none !important;
            }

            body {
                background-color: white !important;
                padding: 0 !important;
                margin: 0 !important;
                font-family: 'Inter', -apple-system, sans-serif !important;
            }

            #print-area {
                display: block !important;
                visibility: visible !important;
                width: 100%;
                color: #000;
            }

            .report-header {
                border-bottom: 2px solid #2563eb;
                padding-bottom: 15px;
                margin-bottom: 20px;
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
            }

            .report-title {
                font-size: 24px;
                font-weight: 800;
                color: #2563eb;
                margin: 0;
            }

            .report-meta {
                font-size: 12px;
                color: #64748b;
                text-align: right;
            }

            .report-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }

            .report-table th {
                background: #f8fafc;
                border-bottom: 2px solid #e2e8f0;
                padding: 10px;
                text-align: left;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #475569;
            }

            .report-table td {
                padding: 10px;
                border-bottom: 1px solid #f1f5f9;
                font-size: 11px;
                vertical-align: top;
            }

            .status-tag {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 4px;
                font-weight: 700;
                font-size: 10px;
            }

            .status-tag-COMPLETE {
                background: #dcfce7;
                color: #166534;
            }

            .status-tag-UNDER {
                background: #eff6ff;
                color: #1e40af;
            }

            .status-tag-OVER {
                background: #fee2e2;
                color: #991b1b;
            }

            .report-summary {
                margin-top: 30px;
                display: flex;
                justify-content: flex-end;
            }

            .summary-box {
                background: #f1f5f9;
                padding: 15px;
                border-radius: 8px;
                min-width: 250px;
            }

            .summary-line {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                font-size: 12px;
            }

        }


        /* Camera Visibility Logic - HIDDEN GLOBALLY AS REQUESTED */
        #camBtn {
            display: none !important;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .hub-layout {
                grid-template-columns: 1fr;
            }

            .scanner-panel {
                position: static;
                margin-bottom: 15px;
                display: flex;
                flex-direction: column;
            }

            /* Reorder Session Closure as per proper responsive flow */
            #sessionClosureCard {
                order: -1;
                margin-bottom: 10px;
                margin-top: 0 !important;
            }

            .hub-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 8px 10px;
                gap: 5px;
            }

            .session-info {
                flex-direction: column;
                gap: 2px;
                font-size: 0.65rem;
            }

            .btn {
                min-height: 40px;
            }

            .hub-title {
                font-size: 1.1rem;
                word-break: break-word;
            }

            .form-control-sm {
                min-height: 38px;
                font-size: 14px;
            }

            /* Hide desktop table on small mobile, use scrollable for tablets */
            @media (max-width: 576px) {
                .table-responsive {
                    display: none !important;
                }

                #mobileHistoryList {
                    display: block !important;
                }
            }

            .reception-card,
            .latest-scan-container,
            .history-card {
                max-width: 100% !important;
                overflow-x: hidden;
            }
        }

        /* Camera Styles */
        #camera-container {
            display: none;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #000;
            margin-top: 8px;
        }

        #interactive.viewport canvas,
        video {
            width: 100%;
            height: auto;
        }

        /* New Minimal History Item Styles */
        .history-item-minimal {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 8px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .history-item-minimal:last-child {
            border-bottom: none;
        }

        .item-name-minimal {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.25;
            display: block;
        }

        .item-meta-minimal {
            font-size: 0.7rem;
            font-weight: 500;
            color: #94a3b8;
        }

        .qty-pill-minimal {
            background: #f8fafc;
            padding: 1px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            border: 1px solid #e2e8f0;
        }

        .qty-btn-minimal {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: none;
            background: white;
            font-weight: 800;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .qty-val-minimal {
            font-weight: 700;
            padding: 0 5px;
            min-width: 16px;
            text-align: center;
            font-size: 0.8rem;
            color: #1e293b;
        }

        .status-badge-mobile {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 4px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .rest-label-mobile {
            font-size: 0.65rem;
            font-weight: 700;
            color: #64748b;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .col-time {
            width: 70px;
        }

        .col-product {
            width: auto;
        }

        .col-ordered {
            width: 75px;
            text-align: center;
        }

        .col-scanned {
            width: 75px;
            text-align: center;
        }

        .col-rest {
            width: 75px;
            text-align: center;
        }

        .col-qty {
            width: 50px;
            text-align: center;
        }

        .col-status {
            width: 90px;
            text-align: center;
        }

        .col-action {
            width: 140px;
            text-align: center;
        }

        /* EAN Selection Styles */
        .selectable-ean {
            user-select: text !important;
            -webkit-user-select: text !important;
            -moz-user-select: text !important;
            -ms-user-select: text !important;
            cursor: text;
            padding: 0 2px;
            border-radius: 2px;
            transition: background-color 0.2s;
        }

        .selectable-ean:hover {
            background-color: #fef08a;
            /* Subtle yellow highlight on hover to show it's selectable */
        }
    </style>
</head>

<body>
    @include('layouts.nav_bar')
    <div id="print-area" style="display: none;"></div>

    <style>
        /* Default: Hide mobile list on desktop */
        #mobileHistoryList {
            display: none;
        }
    </style>

    <div class="reception-hub">
        <div class="hub-header">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('order-delivery.index') }}" class="btn btn-outline-secondary btn-sm rounded-circle p-2">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                </a>

                <h1 class="hub-title">Registry Reception Hub</h1>
            </div>
            <div class="session-info align-items-center">
                <button onclick="downloadPDF()" class="btn btn-primary btn-sm fw-800 me-2 d-inline-flex align-items-center gap-1">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    PRINT REPORT
                </button>
                <span>ORDER ID: <strong>#{{ $order->order_id }}</strong></span>
                <span>DATE: <strong>{{ \Carbon\Carbon::parse($order->order_date)->format('d.m.Y') }}</strong></span>
                <span>STATUS: <span class="badge bg-primary px-2">{{ $order->status }}</span></span>
            </div>
        </div>

        <div class="hub-layout">
            <!-- Left: Scanner & Monitor -->
            <div class="scanner-panel">
                <div id="scannerInputCard" class="reception-card text-center position-relative">
                    <div class="d-flex align-items-center mb-2">
                        <input type="text" id="eanInput" class="scanner-input" placeholder="SCAN EANCODE" inputmode="numeric" autofocus autocomplete="off">
                        <button onclick="processScan()" class="btn btn-primary scan-submit-btn">SUBMIT</button>
                    </div>

                    <!-- Camera Button -->
                    <button onclick="toggleCamera()" class="btn btn-secondary w-100 fw-bold" id="camBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                        START CAMERA
                    </button>

                    <!-- Camera Container -->
                    <div id="camera-container">
                        <div id="interactive" class="viewport" style="width: 100%; height: 250px;"></div>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; height: 2px; background: red; box-shadow: 0 0 4px red;"></div>
                        <button onclick="stopCamera()" class="btn btn-sm btn-danger" style="position: absolute; top: 10px; right: 10px; z-index: 10;">CLOSE</button>
                    </div>
                </div>

                <!-- Sidebar space now cleaner without monitor -->

                <!-- Session Closure Controls -->
                <div id="sessionClosureCard" class="reception-card mt-3">
                    <div class="monitor-label mb-3">Session Closure</div>
                    <div class="mb-3">
                        <label class="monitor-label d-block mb-1">Planned Delivery</label>
                        <input type="date" id="plannedDelivery" class="form-control border-2 fw-800" value="{{ $order->planned_delivery && $order->planned_delivery != '0001-01-01' ? $order->planned_delivery : date('Y-m-d') }}">
                    </div>
                    <div class="mb-3">
                        <label class="monitor-label d-block mb-1">Staff Name</label>
                        <input type="text" id="staffName" class="form-control border-2 fw-800" value="{{ $order->staff }}" placeholder="Required">
                    </div>
                    <div class="mb-3">
                        <label class="monitor-label d-block mb-1">Internal Note</label>
                        <textarea id="orderNote" class="form-control border-2 fw-800" rows="3" placeholder="Optional notes...">{{ $order->note }}</textarea>
                    </div>
                    <button id="closeOrderBtn" class="btn btn-primary w-100 mt-2 fw-800 py-2 rounded-3">FINISH & ARCHIVE SESSION</button>
                </div>
            </div>

            <!-- Right: Activity Feed -->
            <div class="logs-panel">
                <div class="history-card">
                    <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-800 text-secondary text-uppercase m-0" style="letter-spacing: 0.05em; font-size: 0.75rem;">Live Recognition Log</h6>
                        <div class="d-flex gap-3">
                            <a href="javascript:void(0)" class="text-decoration-none fw-800 text-primary filter-link active" onclick="applyFilter('scanned')" data-filter="scanned" style="font-size: 0.75rem;">SCANNED</a>
                            <a href="javascript:void(0)" class="text-decoration-none fw-700 text-secondary filter-link" onclick="applyFilter('order')" data-filter="order" style="font-size: 0.75rem;">ORDER</a>
                            <a href="javascript:void(0)" class="text-decoration-none fw-700 text-secondary filter-link" onclick="applyFilter('error')" data-filter="error" style="font-size: 0.75rem;">ERROR</a>
                        </div>
                    </div>

                    <div id="mobileHistoryList" class="d-block d-sm-none">
                        @foreach($recentScans as $index => $scan)
                        @php
                        $ordered = $scan->ordered_total;
                        $scannedSoFar = $scan->scanned_total;
                        $diff = $scannedSoFar - $ordered;
                        $remaining = max(0, $ordered - $scannedSoFar);

                        $statusText = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');
                        $statusColor = $diff == 0 ? '#22c55e' : ($diff > 0 ? '#ef4444' : '#2563eb');
                        $errorStatus = abs($diff) > 0 ? 'error' : 'ok';
                        $isVirtual = isset($scan->is_virtual) && $scan->is_virtual;
                        @endphp
                        <div class="history-item-minimal {{ $isVirtual ? 'opacity-75' : 'scan-row-' . $scan->id }} @if($index === 0 && !$isVirtual) latest-scan-row @endif"
                            id="mobile-{{ $isVirtual ? 'virtual-' . $index : 'scan-' . $scan->id }}"
                            data-scan-status="{{ $isVirtual ? 'missing' : 'scanned' }}"
                            data-error-status="{{ $errorStatus }}"
                            data-ean="{{ $scan->ean_code }}">

                            <div class="item-data" style="flex-grow: 1;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="item-name-minimal">{{ $scan->product_name }}</span>
                                </div>
                                <div class="item-meta-minimal mb-1">
                                    {{ $scan->scan_date_time ? \Carbon\Carbon::parse($scan->scan_date_time)->format('H:i:s') : '--:--:--' }} |
                                    <span class="selectable-ean">{{ $scan->ean_code }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                                    <span class="status-badge-mobile" style="color: {{ $statusColor }}; background: {{ $statusColor }}15;">{{ $statusText }}</span>
                                    <span class="rest-label-mobile">REST: <span class="row-remaining-val" style="color: {{ $remaining > 0 ? '#2563eb' : '#64748b' }}">{{ $remaining }}</span></span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <div class="qty-pill-minimal">
                                    @if(!$isVirtual)
                                    <button class="qty-btn-minimal" onclick="updateUnits({{ $scan->id }}, -1)">−</button>
                                    <span class="qty-val-minimal mobile-qty-val-{{ $scan->id }}">{{ $scan->units }}</span>
                                    <button class="qty-btn-minimal text-success" onclick="updateUnits({{ $scan->id }}, 1)">+</button>
                                    @else
                                    <span class="text-muted small">0</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="table-responsive moderno-scroll d-none d-sm-block" style="max-height: calc(100vh - 180px); overflow-y: scroll;">
                        <!-- LAST SCANNED SECTION (Above Header) -->
                        @php $latestScan = $recentScans->where('id', '>', 0)->first(); @endphp
                        <div id="latestScanSection" style="{{ $latestScan ? '' : 'display: none;' }}">
                            <div class="latest-scan-label" style="background: #eff6ff; color: #2563eb; font-weight: 800; font-size: 0.7rem; padding: 4px 12px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                LAST SCANNED
                            </div>
                            <table class="table table-modern m-0" style="table-layout: fixed; width: 100%;">
                                <colgroup>
                                    <col class="col-time">
                                    <col class="col-product">
                                    <col class="col-ordered">
                                    <col class="col-scanned">
                                    <col class="col-rest">
                                    <col class="col-qty">
                                    <col class="col-status">
                                    <col class="col-action">
                                </colgroup>
                                <tbody id="latestScanRow">
                                    @if($latestScan)
                                    @php
                                    $ordered = $latestScan->ordered_total;
                                    $scannedSoFar = $latestScan->scanned_total;
                                    $diff = $scannedSoFar - $ordered;
                                    $remaining = max(0, $ordered - $scannedSoFar);
                                    $statusText = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');
                                    $statusColor = $diff == 0 ? '#22c55e' : ($diff > 0 ? '#ef4444' : '#2563eb');
                                    @endphp
                                    <tr id="scan-{{ $latestScan->id }}" class="history-row latest-scan-row scan-row-{{ $latestScan->id }}" data-ean="{{ $latestScan->ean_code }}">
                                        <td class="text-muted fw-600 col-time" style="font-size: 0.8rem;">
                                            {{ $latestScan->scan_date_time ? \Carbon\Carbon::parse($latestScan->scan_date_time)->format('H:i:s') : '--:--:--' }}
                                        </td>
                                        <td class="col-product">
                                            <div class="fw-800 text-dark">{{ $latestScan->product_name }}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                EAN: <span class="selectable-ean">{{ $latestScan->ean_code }}</span>
                                                @if($latestScan->sku) / VareNr.: {{ $latestScan->sku }} @endif
                                            </div>
                                        </td>
                                        <td class="row-ordered-val col-ordered fw-600 text-secondary" data-ean="{{ $latestScan->ean_code }}">{{ $ordered }}</td>
                                        <td class="row-scanned-val col-scanned fw-700 text-dark" data-ean="{{ $latestScan->ean_code }}">{{ $scannedSoFar }}</td>
                                        <td class="row-remaining-val col-rest fw-700 text-success" data-ean="{{ $latestScan->ean_code }}">{{ $remaining }}</td>
                                        <td class="col-qty">
                                            <span class="badge bg-light text-secondary border-0 fw-700 p-1" style="font-size: 0.75rem;">{{ $latestScan->units }}</span>
                                        </td>
                                        <td class="col-status">
                                            <span class="status-badge-modern" style="background: {{ $statusColor }}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800;">
                                                {{ $statusText }}
                                            </span>
                                        </td>
                                        <td class="col-action">
                                            <div class="d-flex justify-content-center gap-1">
                                                <div class="qty-pill">
                                                    <button class="qty-btn" onclick="updateUnits({{ $latestScan->id }}, -1)">-</button>
                                                    <input type="number" inputmode="numeric" class="qty-input qty-input-{{ $latestScan->id }}" id="qty-input-{{ $latestScan->id }}" value="{{ $latestScan->units }}" onchange="updateUnitsExact({{ $latestScan->id }}, this.value)" style="width: 30px; height: 20px; text-align: center; font-weight: 700; font-size: 0.8rem; border: none; background: transparent; padding: 0; color: #1e293b; outline: none;">
                                                    <button class="qty-btn text-success" onclick="updateUnits({{ $latestScan->id }}, 1)">+</button>
                                                </div>
                                                <button class="btn btn-link text-danger p-0" onclick="deleteScan({{ $latestScan->id }})">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <table class="table table-modern" style="table-layout: fixed; width: 100%;">
                            <colgroup>
                                <col class="col-time">
                                <col class="col-product">
                                <col class="col-ordered">
                                <col class="col-scanned">
                                <col class="col-rest">
                                <col class="col-qty">
                                <col class="col-status">
                                <col class="col-action">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="col-time">TIME</th>
                                    <th class="col-product">PRODUCT DETAILS</th>
                                    <th class="col-ordered">ORDERED</th>
                                    <th class="col-scanned">SCANNED</th>
                                    <th class="col-rest">REST</th>
                                    <th class="col-qty">#</th>
                                    <th class="col-status">STATUS</th>
                                    <th class="col-action">ACTION</th>
                                </tr>
                            </thead>
                            <tbody id="scanHistory">
                                @forelse($recentScans as $index => $scan)
                                @if($latestScan && $scan->id === $latestScan->id) @continue @endif
                                @php
                                $ordered = $scan->ordered_total;
                                $scannedSoFar = $scan->scanned_total;
                                $diff = $scannedSoFar - $ordered;
                                $remaining = max(0, $ordered - $scannedSoFar);

                                $statusText = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');
                                $statusColor = $diff == 0 ? '#22c55e' : ($diff > 0 ? '#ef4444' : '#2563eb');
                                $errorStatus = abs($diff) > 0 ? 'error' : 'ok';
                                $isVirtual = isset($scan->is_virtual) && $scan->is_virtual;
                                @endphp

                                <tr id="{{ $isVirtual ? 'virtual-' . $index : 'scan-' . $scan->id }}"
                                    class="history-row {{ $isVirtual ? 'opacity-75' : 'scan-row-' . $scan->id }} @if($index === 0 && !$isVirtual) latest-scan-row @endif"
                                    data-scan-status="{{ $isVirtual ? 'missing' : 'scanned' }}"
                                    data-error-status="{{ $errorStatus }}"
                                    data-ean="{{ $scan->ean_code }}">

                                    <td class="text-muted fw-600 col-time" style="font-size: 0.8rem;">
                                        {{ $scan->scan_date_time ? \Carbon\Carbon::parse($scan->scan_date_time)->format('H:i:s') : '--:--:--' }}
                                    </td>
                                    <td class="col-product">
                                        <div class="fw-800 text-dark">
                                            {{ $scan->product_name }}
                                        </div>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            EAN: <span class="selectable-ean">{{ $scan->ean_code }}</span>
                                            @if($scan->sku) / VareNr.: {{ $scan->sku }} @endif
                                            @if($scan->order_id2)
                                            <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.65rem;" title="Original Order List">{{ $scan->order_id2 }}</span>
                                            @endif
                                        </div>
                                        @if(($scan->your_reference && $scan->your_reference !== '') || ($scan->ordered_by && $scan->ordered_by !== ''))
                                        <div class="text-secondary" style="font-size: 0.7rem; font-weight: 500;">
                                            @if($scan->your_reference) Ref: <span class="text-dark fw-600">{{ $scan->your_reference }}</span> @endif
                                            @if($scan->ordered_by) | By: <span class="text-dark fw-600">{{ $scan->ordered_by }}</span> @endif
                                        </div>
                                        @endif
                                    </td>
                                    <td class="row-ordered-val col-ordered fw-600 text-secondary" data-ean="{{ $scan->ean_code }}">{{ $ordered }}</td>
                                    <td class="row-scanned-val col-scanned fw-700 text-dark" data-ean="{{ $scan->ean_code }}">{{ $scannedSoFar }}</td>
                                    <td class="row-remaining-val col-rest fw-700 text-success" data-ean="{{ $scan->ean_code }}">{{ $remaining }}</td>
                                    <td class="col-qty">
                                        <span class="badge bg-light text-secondary border-0 fw-700 p-1" style="font-size: 0.75rem;">{{ $scan->units }}</span>
                                    </td>
                                    <td class="col-status">
                                        <span class="status-badge-modern" style="background: {{ $statusColor }}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800;">
                                            {{ $statusText }}
                                        </span>
                                    </td>
                                    <td class="col-action">
                                        @if(!$isVirtual)
                                        <div class="d-flex justify-content-center gap-1">
                                            <div class="qty-pill">
                                                <button class="qty-btn" onclick="updateUnits({{ $scan->id }}, -1)">-</button>
                                                <input type="number" inputmode="numeric" class="qty-input qty-input-{{ $scan->id }}" id="qty-input-{{ $scan->id }}" value="{{ $scan->units }}" onchange="updateUnitsExact({{ $scan->id }}, this.value)" style="width: 30px; height: 20px; text-align: center; font-weight: 700; font-size: 0.8rem; border: none; background: transparent; padding: 0; color: #1e293b; outline: none; -moz-appearance: textfield;">
                                                <button class="qty-btn text-success" onclick="updateUnits({{ $scan->id }}, 1)">+</button>
                                            </div>
                                            <button class="btn btn-link text-danger p-0" onclick="deleteScan({{ $scan->id }})">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" />
                                                </svg>
                                            </button>
                                        </div>
                                        @else
                                        <span class="text-muted small">NOT SCANNED</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">No scans registered yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Report & Alert Libraries -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
            <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

            <script>
                window.ScanConfig = {
                    orderId: "{{ $order->order_id }}",
                    csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    routes: {
                        scan: "{{ route('order-delivery.scan') }}",
                        delete: "{{ route('order-delivery.delete-scan') }}",
                        update: "{{ route('order-delivery.update-units') }}",
                        updateExact: "{{ route('order-delivery.update-exact') }}",
                        matchOrder: "{{ route('order-delivery.match-order') }}",
                        sync: "/order-delivery/sync/",
                        close: "{{ route('order-delivery.close') }}"
                    },
                    pusher: {
                        key: "{{ config('broadcasting.connections.pusher.key') }}",
                        cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}",
                        channel: "{{ config('app.env') }}.order.{{ $order->order_id }}",
                        env: "{{ config('app.env') }}"
                    }
                };

                // Initialize last scan ID
                currentLastScanId = @json($latestScanId);
            </script>
            <script src="{{ asset('js/scan_system.js') }}"></script>
</body>

</html>