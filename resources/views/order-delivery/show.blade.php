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
            --primary: #007bff;
            --primary-hover: #0069d9;
            --secondary: #64748b;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --background: #f4f6f9;
            --surface: #ffffff;
            --border: #dee2e6;
            --sidebar-tint: #eceff3;
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
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px 16px;
            margin-bottom: 12px;
            padding: 10px 14px;
            background: var(--surface);
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .hub-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .session-info {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--secondary);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 14px;
            justify-content: flex-end;
            max-width: 100%;
        }

        .session-info > span {
            white-space: nowrap;
        }

        .session-info strong {
            color: #212529;
            font-weight: 700;
        }

        .hub-status-pill {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.28rem 0.65rem;
            border-radius: 50rem;
            text-transform: capitalize;
        }

        .hub-back-btn {
            width: 40px;
            height: 40px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-color: var(--border) !important;
            color: #495057;
        }

        .hub-back-btn:hover {
            background: var(--sidebar-tint);
            border-color: #ced4da !important;
            color: var(--primary);
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
            grid-template-columns: 300px 1fr;
            gap: 16px;
            align-items: start;
        }

        /* Scanner Column */
        .scanner-panel {
            position: sticky;
            top: 10px;
        }

        .scanner-panel-surface {
            background: var(--sidebar-tint);
            border-radius: 12px;
            padding: 12px;
            border: 1px solid var(--border);
        }

        .reception-card {
            padding: 14px 16px;
            margin-bottom: 0;
            background: var(--surface);
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .reception-card + .reception-card {
            margin-top: 12px;
        }

        .scanner-section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .scanner-section-title {
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .sync-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.68rem;
            font-weight: 700;
            color: #6c757d;
        }

        #syncDot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
            background: #adb5bd;
            transition: background 0.2s ease;
        }

        .scan-field-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
            text-align: left;
        }

        .session-closure-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .session-closure-title {
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        #sessionClosureCard .card {
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
        }

        #sessionClosureCard .card-body {
            padding: 0 !important;
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
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.12);
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
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--surface);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .log-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px 12px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            background: #fafbfc;
        }

        .log-toolbar-left {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px 16px;
        }

        .log-toolbar-filters {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        #liveHubIndicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.68rem;
            font-weight: 800;
            color: var(--success);
            letter-spacing: 0.04em;
        }

        #liveHubIndicator .live-hub-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.5);
            animation: livePulse 1.8s ease-out infinite;
        }

        @keyframes livePulse {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.45);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(40, 167, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        #liveHubIndicator.hub-live--sync .live-hub-dot {
            animation: none;
            background: #ffc107;
        }

        #liveHubIndicator.hub-live--offline {
            color: #6c757d;
        }

        #liveHubIndicator.hub-live--offline .live-hub-dot {
            animation: none;
            background: #adb5bd;
            box-shadow: none;
        }

        .filter-link {
            font-size: 0.72rem !important;
            letter-spacing: 0.04em;
            padding-bottom: 4px;
            border-bottom: 2px solid transparent;
            color: #6c757d !important;
            transition: color 0.15s ease, border-color 0.15s ease;
        }

        .filter-link:hover {
            color: var(--primary) !important;
        }

        .filter-link.active {
            color: var(--primary) !important;
            font-weight: 800 !important;
            border-bottom-color: var(--primary);
        }

        .table-modern {
            margin: 0;
            font-size: 0.875rem;
        }

        .table-modern thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            box-shadow: 0 1px 0 var(--border);
            background: #f1f3f5;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            color: #495057;
        }

        .table-modern tbody td {
            padding: 10px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .table-modern tbody tr:nth-child(even):not(.latest-scan-row) {
            background: #f8f9fa;
        }

        .table-modern tbody tr.latest-scan-row {
            background: #e7f1ff;
        }

        .table-modern .status-badge-modern {
            border-radius: 50rem !important;
            padding: 0.2rem 0.55rem !important;
            font-size: 0.68rem !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .qty-pill {
            background: #fff;
            padding: 2px 4px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--border);
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        }

        .qty-btn {
            width: 26px;
            height: 26px;
            border-radius: 4px;
            border: none;
            background: #f1f3f5;
            font-weight: 800;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #343a40;
            line-height: 1;
        }

        .qty-btn:hover {
            background: #e2e6ea;
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
            width: 95px;
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

        .clickable-ean {
            cursor: pointer !important;
            text-decoration: none;
            color: var(--primary);
        }

        .clickable-ean:hover {
            background-color: rgba(0, 123, 255, 0.08);
            border-radius: 2px;
        }

        /* EAN Missing Styling matches provided image */
        .ean-missing-wrapper {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }

        .ean-missing-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fefce8;
            border: 1.5px solid #facc15;
            border-radius: 50px;
            padding: 2px 8px;
            font-size: 0.65rem;
            font-weight: 800;
            color: #a16207;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .btn-add-ean {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: white;
            border: 1.5px solid #facc15;
            border-radius: 50px;
            padding: 2px 8px;
            font-size: 0.65rem;
            font-weight: 800;
            color: #a16207;
            text-transform: uppercase;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-add-ean:hover {
            background: #fefce8;
            transform: translateY(-1px);
        }

        @keyframes pulseWarn {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .report-locked .qty-btn,
        .report-locked .qty-btn-minimal,
        .report-locked .qty-input,
        .report-locked .text-danger,
        .report-locked .btn-add-ean {
            pointer-events: none;
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* --- Reopen Modal Premium UI (Based on Screenshots) --- */
        .reopen-swal-popup {
            border-radius: 24px !important;
            padding: 2.5rem 2rem !important;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15) !important;
        }
        .reopen-swal-title {
            color: #1e293b !important;
            font-weight: 800 !important;
            font-size: 1.6rem !important;
            margin-bottom: 0.5rem !important;
        }
        .reopen-swal-text {
            color: #64748b !important;
            font-size: 1.1rem !important;
            font-weight: 500 !important;
        }
        .reopen-confirm-btn {
            background-color: #f59e0b !important; /* Orange */
            border-radius: 50px !important;
            padding: 12px 40px !important;
            font-weight: 800 !important;
            font-size: 0.95rem !important;
            letter-spacing: 0.5px !important;
            text-transform: uppercase !important;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3) !important;
        }
        .reopen-cancel-btn {
            background-color: #f1f5f9 !important; /* Light grayish blue */
            color: #475569 !important;
            border-radius: 50px !important;
            padding: 12px 40px !important;
            font-weight: 800 !important;
            font-size: 0.95rem !important;
            letter-spacing: 0.5px !important;
            text-transform: uppercase !important;
        }
        .reopen-success-confirm-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            border-radius: 12px !important;
            padding: 14px 45px !important;
            font-weight: 800 !important;
            font-size: 1.1rem !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4) !important;
        }
        .reopen-password-input {
            border-radius: 12px !important;
            border: 2px solid #e2e8f0 !important;
            padding: 0.8rem !important;
            font-size: 1rem !important;
            transition: all 0.2s ease !important;
            text-align: center !important;
        }
        .reopen-password-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }

        /* Bootstrap alignment: hub uses classic blue */
        .reception-hub .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .reception-hub .btn-primary:hover:not(:disabled) {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .reception-hub .bg-primary {
            background-color: var(--primary) !important;
        }

        .reception-hub .text-primary {
            color: var(--primary) !important;
        }
    </style>
</head>

<body>
    @include('layouts.nav_bar')
    @php $isLocked = $order->status === 'Completed'; @endphp
    <div id="print-area" style="display: none;"></div>

    <style>
        /* Default: Hide mobile list on desktop */
        #mobileHistoryList {
            display: none;
        }
    </style>

    <div class="reception-hub @if($isLocked) report-locked @endif">
        <div class="hub-header">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('order-delivery.index') }}" class="btn btn-outline-secondary btn-sm rounded-circle hub-back-btn" title="Back to list">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M19 12H5M12 19l-7-7 7-7" />
                    </svg>
                </a>

                <h1 class="hub-title">Registry Reception Hub</h1>
            </div>
            <div class="session-info">
                <button type="button" id="printReportBtn" onclick="downloadPDF()" class="btn btn-primary btn-sm fw-bold text-uppercase d-inline-flex align-items-center gap-2 px-3" style="font-size: 0.72rem; letter-spacing: 0.04em;" title="Print rows matching the current log filter (works on completed reports)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    PRINT REPORT
                </button>
                <span>ORDER ID: <strong>#{{ $order->order_id }}</strong></span>
                <span>ORDER DATE: <strong>{{ \Carbon\Carbon::parse($order->order_date)->format('d.m.Y') }}</strong></span>
                <span id="plannedDeliveryHeader">PLANNED DELIVERY: <strong id="plannedDeliveryHeaderText">
                    @if($order->planned_delivery && $order->planned_delivery != '0001-01-01' && $order->planned_delivery != '0000-00-00')
                        {{ \Carbon\Carbon::parse($order->planned_delivery)->format('d.m.Y') }}
                    @else
                        -
                    @endif
                </strong></span>
                <span>STATUS: <span class="hub-status-pill @if($isLocked) bg-success text-white @else bg-primary text-white @endif">{{ $order->status }}</span></span>
            </div>
        </div>

        @php $productFocusQuery = trim((string) request('highlight', '')); @endphp
        @if($productFocusQuery !== '')
        <div class="alert alert-primary py-2 px-3 mb-2 d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size: 0.84rem; border-radius: 10px;" role="status">
            <span class="me-2">
                <strong>From Orders search:</strong>
                <span class="fw-bold text-dark">{{ $productFocusQuery }}</span>
                <span class="text-secondary">— one line per product (EAN); clear the search box or use Clear to show all scan lines again.</span>
            </span>
            <a href="{{ route('order-delivery.show', $order->order_id) }}" class="btn btn-sm btn-outline-primary fw-bold text-nowrap">Clear</a>
        </div>
        @endif

        <div class="hub-layout">
            <!-- Left: Scanner & Monitor -->
            <div class="scanner-panel">
                <div class="scanner-panel-surface">
                <div id="scannerInputCard" class="reception-card position-relative">
                    <div class="scanner-section-head">
                        <span class="scanner-section-title">Scanner Input</span>
                        <span class="sync-indicator"><span id="syncDot" aria-hidden="true"></span><span id="syncText">…</span></span>
                    </div>
                    <label class="scan-field-label" for="eanInput">SCAN EANCODE</label>
                    <div class="d-flex align-items-stretch">
                        <input type="text" id="eanInput" class="scanner-input" placeholder="{{ $isLocked ? 'REPORT LOCKED' : 'SCAN EANCODE' }}" inputmode="numeric" @if(!$isLocked) autofocus @else disabled @endif autocomplete="off">
                        <button type="button" onclick="processScan()" class="btn btn-primary scan-submit-btn text-uppercase" @if($isLocked) disabled @endif>SUBMIT</button>
                    </div>

                    <!-- Camera Button -->
                    <button onclick="toggleCamera()" class="btn btn-secondary w-100 fw-bold" id="camBtn" @if($isLocked) disabled @endif>
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
                        <button type="button" onclick="stopCamera()" class="btn btn-sm btn-danger" style="position: absolute; top: 10px; right: 10px; z-index: 10;">CLOSE</button>
                    </div>
                </div>

                <!-- Session Closure Controls -->
                <div id="sessionClosureCard" class="reception-card mt-0">
                    <div class="card h-100 border-0 position-relative bg-transparent">
                        <div class="session-closure-head">
                            <span class="session-closure-title">Session Closure</span>
                            <div id="saveIndicator" class="badge bg-success opacity-0" style="transition: opacity 0.3s; font-size: 0.62rem; font-weight: 700;">SAVED</div>
                        </div>
                        <div class="card-body p-0">
                            <div class="mb-3">
                                <label class="monitor-label d-block mb-1">Planned Delivery</label>
                                <input type="date" id="plannedDelivery" class="form-control fw-semibold" value="{{ $order->planned_delivery && $order->planned_delivery != '0001-01-01' ? $order->planned_delivery : date('Y-m-d') }}" @if($isLocked) disabled @endif>
                            </div>
                            <div class="mb-3">
                                <label class="monitor-label d-block mb-1">Staff Name</label>
                                <input type="text" id="staffName" class="form-control fw-semibold" value="{{ $order->staff }}" placeholder="Required" @if($isLocked) disabled @endif>
                            </div>
                            <div class="mb-3">
                                <label class="monitor-label d-block mb-1">Internal Note</label>
                                <textarea id="orderNote" class="form-control fw-semibold" rows="3" placeholder="Optional notes..." @if($isLocked) disabled @endif>{{ $order->note }}</textarea>
                            </div>
                            @if(!$isLocked)
                            <button type="button" id="closeOrderBtn" class="btn btn-primary w-100 mt-1 fw-bold py-2 rounded-3 text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.04em;">FINISH & ARCHIVE SESSION</button>
                            @else
                            <button type="button" onclick="reopenReport()" class="btn btn-warning w-100 mt-1 fw-bold py-2 rounded-3 d-inline-flex align-items-center justify-content-center gap-2 text-uppercase">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                REOPEN REPORT
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
                </div>
            </div>{{-- END scanner-panel --}}

            <!-- Right: Activity Feed -->
            <div class="logs-panel">
                <div class="history-card">
                    <div class="log-toolbar">
                        <div class="log-toolbar-left">
                            <h6 class="fw-bold text-secondary text-uppercase m-0" style="letter-spacing: 0.06em; font-size: 0.72rem;">Live Recognition Log</h6>
                            <div id="liveHubIndicator" class="@if($isLocked) hub-live--offline @endif" data-live-state="{{ $isLocked ? 'offline' : 'sync' }}" role="status" aria-live="polite">
                                <span class="live-hub-dot" aria-hidden="true"></span>
                                <span id="liveHubLabel">@if($isLocked) OFFLINE @else LIVE @endif</span>
                            </div>
                        </div>
                        <div class="log-toolbar-filters">
                            <a href="javascript:void(0)" class="text-decoration-none filter-link active" onclick="applyFilter('scanned')" data-filter="scanned">SCANNED</a>
                            <a href="javascript:void(0)" class="text-decoration-none filter-link" onclick="applyFilter('ordered')" data-filter="ordered">ORDERED</a>
                            <a href="javascript:void(0)" class="text-decoration-none filter-link" onclick="applyFilter('rest')" data-filter="rest">REST</a>
                            <a href="javascript:void(0)" class="text-decoration-none filter-link" onclick="applyFilter('error')" data-filter="error">ERROR</a>
                        </div>
                    </div>

                    <div class="px-3 pt-2 pb-2 d-flex justify-content-end">
                        <div style="position: relative; width: 100%; max-width: 420px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="search" id="productLookupInput" class="form-control" autocomplete="off"
                                placeholder="Search: name, VareNr., or EAN"
                                style="height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.84rem; font-weight: 600; padding: 0 12px 0 36px; background: #ffffff; box-shadow: none;">
                        </div>
                    </div>

                    @php
                        $hubLatestScan = $recentScans->first(function ($s) {
                            return empty($s->is_virtual) && (int)($s->id ?? 0) > 0;
                        });
                    @endphp

                    <div id="mobileHistoryList" class="d-block d-sm-none">
                        @foreach($recentScans as $index => $scan)
                        @php
                        $ordered = (int)$scan->ordered_total;
                        $rawTarget = (int)($scan->delivery_target_total ?? 0);
                        $target = $rawTarget > 0 ? $rawTarget : $ordered;
                        $scannedSoFar = $scan->scanned_total;
                        $diff = $scannedSoFar - $target;
                        $remaining = max(0, $target - $scannedSoFar);

                        $statusText = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');
                        $statusColor = $diff == 0 ? '#22c55e' : ($diff > 0 ? '#ef4444' : '#2563eb');
                        $isVirtual = isset($scan->is_virtual) && $scan->is_virtual;
                        $rowEanMissingMobile = !$isVirtual && !empty($scan->sku) && !empty($scan->ean_code) && strval($scan->ean_code) == strval($scan->sku);
                        @endphp
                        <div class="history-item-minimal {{ $isVirtual ? 'opacity-75' : 'scan-row-' . $scan->id }} @if($hubLatestScan && !$isVirtual && (int)$scan->id === (int)$hubLatestScan->id) latest-scan-row @endif"
                            id="mobile-{{ $isVirtual ? 'virtual-' . $index : 'scan-' . $scan->id }}"
                            data-scan-status="{{ $isVirtual ? 'missing' : 'scanned' }}"
                            data-ean-missing="{{ $rowEanMissingMobile ? '1' : '0' }}"
                            data-ean="{{ $scan->ean_code }}"
                            data-product-id="{{ $scan->sku }}"
                            data-product-name="{{ $scan->product_name }}"
                            data-delivery-target="{{ $target }}"
                            data-sort-key="{{ $scan->id > 0 ? $scan->id : (1000000 - $index) }}">

                            <div class="item-data" style="flex-grow: 1;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="item-name-minimal">{{ $scan->product_name }}</span>
                                </div>
                                <div class="item-meta-minimal mb-1">
                                    {{ $scan->scan_date_time ? \Carbon\Carbon::parse($scan->scan_date_time)->format('d.m.Y') : '--.--.----' }} |
                                    <span class="selectable-ean">{{ $scan->ean_code }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                                    <span class="status-badge-mobile" style="color: {{ $statusColor }}; background: {{ $statusColor }}15;">{{ $statusText }}</span>
                                    <span class="rest-label-mobile">REST: <span class="row-remaining-val" style="color: {{ $remaining > 0 ? '#2563eb' : '#64748b' }}">{{ $remaining }}</span></span>
                                </div>
                                <span class="visually-hidden"><span class="row-ordered-val">{{ $ordered }}</span><span class="row-scanned-val">{{ $scannedSoFar }}</span></span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <div class="qty-pill-minimal">
                                    @if(!$isVirtual)
                                    <button type="button" class="qty-btn-minimal" onclick="updateUnits({{ $scan->id }}, -1)">−</button>
                                    <span class="qty-val-minimal mobile-qty-val-{{ $scan->id }}">{{ $scan->units }}</span>
                                    <button type="button" class="qty-btn-minimal text-success" onclick="updateUnits({{ $scan->id }}, 1)">+</button>
                                    @else
                                    <span class="text-muted small">0</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="table-responsive moderno-scroll d-none d-sm-block" style="max-height: calc(100vh - 180px); overflow-y: scroll;">
                        <div id="latestScanSection" class="latest-scan-container" style="{{ $hubLatestScan ? '' : 'display: none;' }}">
                            <div class="latest-scan-label">LAST SCANNED</div>
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
                                    @if($hubLatestScan)
                                    @php
                                        $scan = $hubLatestScan;
                                        $ordered = (int)$scan->ordered_total;
                                        $rawTarget = (int)($scan->delivery_target_total ?? 0);
                                        $target = $rawTarget > 0 ? $rawTarget : $ordered;
                                        $scannedSoFar = $scan->scanned_total;
                                        $diff = $scannedSoFar - $target;
                                        $remaining = max(0, $target - $scannedSoFar);
                                        $statusText = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');
                                        $statusColor = $diff == 0 ? '#22c55e' : ($diff > 0 ? '#ef4444' : '#2563eb');
                                        $rowEanMissing = !empty($scan->sku) && !empty($scan->ean_code) && strval($scan->ean_code) == strval($scan->sku);
                                    @endphp
                                    <tr id="scan-{{ $scan->id }}" class="history-row latest-scan-row scan-row-{{ $scan->id }}" data-ean="{{ $scan->ean_code }}" data-delivery-target="{{ $target }}" data-scan-status="scanned" data-ean-missing="{{ $rowEanMissing ? 1 : 0 }}" data-product-id="{{ $scan->sku }}" data-product-name="{{ $scan->product_name }}" data-sort-key="{{ $scan->id }}">
                                        <td class="text-muted fw-600 col-time" style="font-size: 0.8rem;">{{ $scan->scan_date_time ? \Carbon\Carbon::parse($scan->scan_date_time)->format('d.m.Y') : '--.--.----' }}</td>
                                        <td class="col-product">
                                            <div class="fw-800 text-dark">{{ $scan->product_name }}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                Varenr : {{ $scan->sku ?: '—' }}&nbsp;&nbsp;EAN :
                                                @if($scan->sku)
                                                <span class="selectable-ean clickable-ean" onclick="addEanCode('{{ $scan->sku }}', '{{ addslashes($scan->product_name) }}', '{{ addslashes($scan->ean_code) }}')">{{ $scan->ean_code }}</span>
                                                @else
                                                <span class="selectable-ean">{{ $scan->ean_code }}</span>
                                                @endif
                                            </div>
                                            @if($rowEanMissing)
                                            <div class="ean-missing-wrapper">
                                                <div class="ean-missing-tag">&#9888; EAN MISSING &ndash; Using VareNr</div>
                                                <button type="button" class="btn-add-ean" data-sku="{{ $scan->sku }}" data-name="{{ $scan->product_name }}" data-ean="{{ $scan->ean_code }}" onclick="addEanCode(this.dataset.sku, this.dataset.name, this.dataset.ean)">+ ADD EAN</button>
                                            </div>
                                            @endif
                                        </td>
                                        <td class="row-ordered-val col-ordered fw-600 text-secondary" data-ean="{{ $scan->ean_code }}">{{ $ordered }}</td>
                                        <td class="row-scanned-val col-scanned fw-700 text-dark" data-ean="{{ $scan->ean_code }}">{{ $scannedSoFar }}</td>
                                        <td class="row-remaining-val col-rest fw-700 {{ $remaining > 0 ? 'text-primary' : 'text-success' }}" data-ean="{{ $scan->ean_code }}">{{ $remaining }}</td>
                                        <td class="col-qty"><span class="badge bg-light text-secondary border-0 fw-700 p-1 units-val" style="font-size: 0.75rem;">{{ $scan->units }}</span></td>
                                        <td class="col-status"><span class="status-badge-modern" style="background: {{ $statusColor }}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800;">{{ $statusText }}</span></td>
                                        <td class="col-action">
                                            <div class="d-flex justify-content-center gap-1">
                                                <div class="qty-pill">
                                                    <button type="button" class="qty-btn" onclick="updateUnits({{ $scan->id }}, -1)">-</button>
                                                    <input type="number" inputmode="numeric" class="qty-input qty-input-{{ $scan->id }}" id="qty-input-{{ $scan->id }}" value="{{ $scan->units }}" onchange="updateUnitsExact({{ $scan->id }}, this.value)" style="width: 30px; height: 20px; text-align: center; font-weight: 700; font-size: 0.8rem; border: none; background: transparent; padding: 0; color: #1e293b; outline: none;">
                                                    <button type="button" class="qty-btn text-success" onclick="updateUnits({{ $scan->id }}, 1)">+</button>
                                                </div>
                                                <button type="button" class="btn btn-link text-danger p-0" onclick="deleteScan({{ $scan->id }})">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" /></svg>
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
                                    <th class="col-time">DATE</th>
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
                                @if($hubLatestScan && (int)$scan->id > 0 && (int)$scan->id === (int)$hubLatestScan->id)
                                    @continue
                                @endif
                                @php
                                $ordered = (int)$scan->ordered_total;
                                $rawTarget = (int)($scan->delivery_target_total ?? 0);
                                $target = $rawTarget > 0 ? $rawTarget : $ordered;
                                $scannedSoFar = $scan->scanned_total;
                                $diff = $scannedSoFar - $target;
                                $remaining = max(0, $target - $scannedSoFar);

                                $statusText = $diff == 0 ? 'COMPLETE' : ($diff > 0 ? 'OVER' : 'UNDER');
                                $statusColor = $diff == 0 ? '#22c55e' : ($diff > 0 ? '#ef4444' : '#2563eb');
                                $isVirtual = isset($scan->is_virtual) && $scan->is_virtual;
                                @endphp

                                @php $rowEanMissing = !$isVirtual && !empty($scan->sku) && !empty($scan->ean_code) && strval($scan->ean_code) == strval($scan->sku); @endphp
                                <tr id="{{ $isVirtual ? 'virtual-' . $index : 'scan-' . $scan->id }}"
                                    class="history-row {{ $isVirtual ? 'opacity-75' : 'scan-row-' . $scan->id }}"
                                    data-scan-status="{{ $isVirtual ? 'missing' : 'scanned' }}"
                                    data-ean="{{ $scan->ean_code }}"
                                    data-delivery-target="{{ $target }}"
                                    data-ean-missing="{{ $rowEanMissing ? 1 : 0 }}"
                                    data-product-id="{{ $scan->sku }}"
                                    data-product-name="{{ $scan->product_name }}"
                                    data-sort-key="{{ $scan->id > 0 ? $scan->id : (1000000 - $index) }}">

                                    <td class="text-muted fw-600 col-time" style="font-size: 0.8rem;">
                                        {{ $scan->scan_date_time ? \Carbon\Carbon::parse($scan->scan_date_time)->format('d.m.Y') : '--.--.----' }}
                                    </td>
                                    <td class="col-product">
                                        <div class="fw-800 text-dark">
                                            {{ $scan->product_name }}
                                        </div>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            Varenr : {{ $scan->sku ?: '—' }}&nbsp;&nbsp;EAN :
                                            @if($scan->sku)
                                            <span class="selectable-ean clickable-ean" onclick="addEanCode('{{ $scan->sku }}', '{{ addslashes($scan->product_name) }}', '{{ addslashes($scan->ean_code) }}')">{{ $scan->ean_code }}</span>
                                            @else
                                            <span class="selectable-ean">{{ $scan->ean_code }}</span>
                                            @endif
                                            @if($scan->order_id2)
                                            <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.65rem;" title="Original Order List">{{ $scan->order_id2 }}</span>
                                            @endif
                                        </div>
                                        @if($scan->ordered_by && $scan->ordered_by !== '')
                                        <div class="text-secondary" style="font-size: 0.7rem; font-weight: 500;">
                                            By: <span class="text-dark fw-600">{{ $scan->ordered_by }}</span>
                                        </div>
                                        @endif
                                        @if($rowEanMissing)
                                        <div class="ean-missing-wrapper">
                                            <div class="ean-missing-tag">
                                                &#9888; EAN MISSING &ndash; Using VareNr
                                            </div>
                                            <button class="btn-add-ean" data-sku="{{ $scan->sku }}" data-name="{{ $scan->product_name }}" data-ean="{{ $scan->ean_code }}" onclick="addEanCode(this.dataset.sku, this.dataset.name, this.dataset.ean)">
                                                + ADD EAN
                                            </button>
                                        </div>
                                        @endif
                                    </td>
                                    <td class="row-ordered-val col-ordered fw-600 text-secondary" data-ean="{{ $scan->ean_code }}">{{ $ordered }}</td>
                                    <td class="row-scanned-val col-scanned fw-700 text-dark" data-ean="{{ $scan->ean_code }}">{{ $scannedSoFar }}</td>
                                    <td class="row-remaining-val col-rest fw-700 {{ $remaining > 0 ? 'text-primary' : 'text-success' }}" data-ean="{{ $scan->ean_code }}">{{ $remaining }}</td>
                                    <td class="col-qty">
                                        <span class="badge bg-light text-secondary border-0 fw-700 p-1 units-val" style="font-size: 0.75rem;">{{ $scan->units }}</span>
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
                                                <button type="button" class="qty-btn" onclick="updateUnits({{ $scan->id }}, -1)">-</button>
                                                <input type="number" inputmode="numeric" class="qty-input qty-input-{{ $scan->id }}" id="qty-input-{{ $scan->id }}" value="{{ $scan->units }}" onchange="updateUnitsExact({{ $scan->id }}, this.value)" style="width: 30px; height: 20px; text-align: center; font-weight: 700; font-size: 0.8rem; border: none; background: transparent; padding: 0; color: #1e293b; outline: none; -moz-appearance: textfield;">
                                                <button type="button" class="qty-btn text-success" onclick="updateUnits({{ $scan->id }}, 1)">+</button>
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
                                <tr class="history-row history-row--empty">
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
                    orderStatus: @json($order->status),
                    lockTimestamp: @json($order->delivery_handling_date ? \Carbon\Carbon::parse($order->delivery_handling_date)->format('d.m.Y H:i') : null),
                    csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    routes: {
                        index: "{{ route('order-delivery.index') }}",
                        scan: "{{ route('order-delivery.scan') }}",
                        delete: "{{ route('order-delivery.delete-scan') }}",
                        update: "{{ route('order-delivery.update-units') }}",
                        updateExact: "{{ route('order-delivery.update-exact') }}",
                        matchOrder: "{{ route('order-delivery.match-order') }}",
                        sync: "/order-delivery/sync/",
                        close: "{{ route('order-delivery.close') }}",
                        reopen: "{{ route('order-delivery.reopen') }}",
                        updateEan: "{{ route('order-delivery.update-ean') }}",
                        updateSession: "{{ route('order-delivery.update-session-info') }}"
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

                async function reopenReport() {
                    const { value: password } = await Swal.fire({
                        title: 'Enter Password to Reopen',
                        input: 'password',
                        inputPlaceholder: 'Enter password',
                        showCancelButton: true,
                        confirmButtonText: 'REOPEN',
                        cancelButtonText: 'CANCEL',
                        confirmButtonColor: '#f59e0b',
                        cancelButtonColor: '#f1f5f9',
                        reverseButtons: true,
                        customClass: {
                            popup: 'reopen-swal-popup',
                            title: 'reopen-swal-title',
                            confirmButton: 'reopen-confirm-btn',
                            cancelButton: 'reopen-cancel-btn',
                            input: 'reopen-password-input'
                        }
                    });

                    if (password) {
                        try {
                            const response = await fetch(window.ScanConfig.routes.reopen, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': window.ScanConfig.csrfToken
                                },
                                body: JSON.stringify({
                                    order_id: window.ScanConfig.orderId,
                                    password: password
                                })
                            });

                            const result = await response.json();
                            if (result.success) {
                                Swal.fire({
                                    title: 'REOPENED!',
                                    text: 'The report is now editable again.',
                                    icon: 'success',
                                    confirmButtonText: 'OK',
                                    customClass: {
                                        popup: 'reopen-swal-popup',
                                        title: 'reopen-swal-title',
                                        htmlContainer: 'reopen-swal-text',
                                        confirmButton: 'reopen-success-confirm-btn'
                                    }
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error', result.message || 'Failed to reopen report', 'error');
                            }
                        } catch (error) {
                            Swal.fire('Error', 'Something went wrong', 'error');
                        }
                    }
                }

                // Live Updates for Session Fields
                document.addEventListener('DOMContentLoaded', function() {
                    const sessionFields = ['plannedDelivery', 'staffName', 'orderNote'];
                    sessionFields.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) {
                            el.addEventListener('change', updateSessionInfo);
                            // Also for text inputs, maybe save on blur to avoid too many requests
                            if (el.tagName === 'INPUT' && el.type === 'text' || el.tagName === 'TEXTAREA') {
                                el.addEventListener('blur', updateSessionInfo);
                            }
                        }
                    });
                });

                async function updateSessionInfo() {
                    const data = {
                        order_id: window.ScanConfig.orderId,
                        planned_delivery: document.getElementById('plannedDelivery').value,
                        staff: document.getElementById('staffName').value,
                        note: document.getElementById('orderNote').value
                    };

                    try {
                        const saveIndicator = document.getElementById('saveIndicator');
                        if (saveIndicator) {
                            saveIndicator.innerText = 'SAVING...';
                            saveIndicator.classList.remove('bg-success');
                            saveIndicator.classList.add('bg-warning');
                            saveIndicator.classList.remove('opacity-0');
                        }

                        const response = await fetch(window.ScanConfig.routes.updateSession, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': window.ScanConfig.csrfToken
                            },
                            body: JSON.stringify(data)
                        });
                        const result = await response.json();
                        if (result.success) {
                            console.log('Session info updated live');
                            
                            // Update the header display for planned delivery
                            document.getElementById('plannedDeliveryHeaderText').innerText = result.planned_delivery_formatted || '-';
                            
                            if (saveIndicator) {
                                saveIndicator.innerText = 'SAVED';
                                saveIndicator.classList.remove('bg-warning');
                                saveIndicator.classList.add('bg-success');
                                setTimeout(() => {
                                    saveIndicator.classList.add('opacity-0');
                                }, 1500);
                            }
                        }
                    } catch (error) {
                        console.error('Failed to update session info', error);
                        const saveIndicator = document.getElementById('saveIndicator');
                        if (saveIndicator) {
                            saveIndicator.innerText = 'ERROR';
                            saveIndicator.classList.add('bg-danger');
                        }
                    }
                }
            </script>
            <script src="{{ asset('js/scan_system.js') }}?v={{ time() }}"></script>
</body>

</html>
</html>


