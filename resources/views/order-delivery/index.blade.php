<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="color-scheme" content="light only">
    <title>ORDER DELIVERY MANAGEMENT · Delivery</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Modern UI Styles -->
    <link rel="stylesheet" href="{{asset('assets/css/modern-ui.css')}}?v=1">
    <style>
        body,
        html {
            background-color: var(--bg-body);
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            max-width: 100%;
        }

        /* Ensure title wraps on small screens */
        .card-title {
            white-space: normal;
            word-wrap: break-word;
            font-size: 1.1rem;
            /* Slightly smaller for mobile safety */
        }

        .table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            font-size: 13px;
        }

        .table td {
            font-size: 13px;
            vertical-align: middle;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .container-fluid {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }

            /* Header Adjustments */
            .card-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }

            .card-header .header-actions {
                width: 100%;
            }

            .card-header .header-actions .btn {
                flex: 1;
                padding: 10px;
                font-size: 0.9rem;
            }

            /* Search Area */
            .row.g-2 {
                flex-direction: column;
            }

            .col-md-4.d-flex {
                width: 100%;
                justify-content: space-between;
                /* Spread buttons */
            }

            .col-md-4.d-flex button {
                flex: 1;
                /* Equal width buttons */
            }

            /* Table to Card Transformation */
            .table-responsive {
                overflow: visible !important;
                /* Allow cards to show properly */
            }

            .table thead {
                display: none;
                /* Hide header */
            }

            .table tbody tr {
                display: block;
                margin-bottom: 20px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                /* More premium shadow */
                padding: 15px;
                transition: transform 0.2s ease;
            }

            /* Disable hover background on mobile/touch */
            @media (hover: hover) {
                .table-hover tbody tr:hover {
                    background-color: #f8fafc;
                }
            }

            /* Responsive touch feedback */
            .table tbody tr:active {
                transform: scale(0.98);
            }

            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #f1f5f9;
                text-align: right;
            }

            .table tbody td:last-child {
                border-bottom: none;
                justify-content: flex-end;
                /* Align to right for better thumb reach */
                padding-top: 15px;
                gap: 12px;
            }

            .table tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                font-size: 0.75rem;
                color: #64748b;
                text-transform: uppercase;
                margin-right: 15px;
                text-align: left;
            }

            .table tbody td span {
                text-align: right;
                font-size: 0.95rem;
            }

            /* Action buttons refinement */
            .mobile-action-btn {
                flex: 1;
                max-width: 150px;
                border-radius: 10px;
                padding: 10px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
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
    </style>
</head>

<body>
    @include('layouts.nav_bar')

    <div class="container-fluid mt-5 mb-5 px-4">
        <div class="modern-card">
            <div class="card-header bg-transparent border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold" style="color: #2563eb;">ORDER DELIVERY MANAGEMENT</h5>
                <div class="header-actions d-flex gap-2 flex-wrap">
                    <a href="{{ route('order-delivery.mobile-global') }}" target="_blank" class="btn btn-modern-secondary btn-sm d-flex align-items-center justify-content-center" title="Open Mobile Scanner">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                            <path d="M12 18h.01"></path>
                        </svg>
                    </a>
                    <button class="btn btn-modern-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#uploadCollapse" aria-expanded="false" aria-controls="uploadCollapse">
                        + IMPORT
                    </button>
                </div>
            </div>
            <div class="card-body p-0">

                <div class="mb-4">
                    <p class="small fw-semibold text-secondary mb-2 d-none" id="ordersMatchSummary"></p>
                    <div class="d-flex gap-2 align-items-center flex-wrap flex-md-nowrap">
                        <div style="flex-grow: 1;">
                            <label for="searchInput" class="visually-hidden">Search orders by product or order number</label>
                            <input type="text" id="searchInput" class="form-control form-control-modern" 
                                placeholder="Search Order ID, Product Name, EAN, VareNr." 
                                style="border-radius: 12px; padding: 12px 18px; border: 1.5px solid #bfdbfe; font-weight: 500; font-size: 1rem; color: #1e293b; height: 52px; width: 100%;" 
                                autofocus>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-modern-primary px-4" onclick="filterTable()" 
                                style="background-color: #5c5be5; border-radius: 12px; font-weight: 800; height: 52px; white-space: nowrap; font-size: 0.95rem;">Search</button>
                            <button class="btn btn-modern-secondary px-4" onclick="resetTable()" 
                                style="background-color: #ffffff; color: #1e293b; border: 1.5px solid #e2e8f0; border-radius: 12px; font-weight: 800; height: 52px; white-space: nowrap; font-size: 0.95rem;">Clear</button>
                        </div>
                    </div>
                </div>

                <div class="collapse mb-4" id="uploadCollapse">
                    <div class="card card-body bg-light border-0 mb-3" style="border-radius: 12px;">
                        <form action="{{ route('order-delivery.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-600 extra-small text-muted mb-1 text-uppercase">Select File</label>
                                    <input type="file" name="order_file" class="form-control form-control-modern" id="order_file" accept=".csv, .txt, .xls, .xlsx" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-modern-primary w-100 mt-3">Upload File</button>
                        </form>
                    </div>
                </div>

                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                <div class="table-responsive moderno-scroll">
                    <table class="table table-modern table-hover align-middle" id="ordersTable">
                        <thead class="table-light">
                            <tr>
                                <th>ORDER ID</th>
                                <th>ORDER DATE</th>
                                <th>Unique Items</th>
                                <th>TOTAL QTY</th>
                                <th>PLANNED DELIVERY</th>
                                <th>STATUS</th>
                                <th>STAFF</th>
                                <th>SESSION NOTES</th>
                                <th>HANDLING DATE</th>
                                <th class="text-end">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr>
                                <td data-label="Order ID">
                                    <span class="fw-bold">#{{ $order->order_id }}</span>
                                    <span style="display:none" class="product-search-index">{{ $order->order_id }} {{ $order->searchable_products }}</span>
                                </td>
                                <td data-label="Order Date">{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d.m.Y') : '-' }}</td>
                                <td data-label="Items (Rows)">{{ $order->total_unique_items }}</td>
                                <td data-label="Total Qty">{{ (int)$order->total_quantity }}</td>
                                 <td data-label="Planned Delivery">
                                    @php
                                        $plannedDeliveryText = '-';
                                        if (!empty($order->planned_delivery)) {
                                            try {
                                                $plannedDeliveryDate = \Carbon\Carbon::parse($order->planned_delivery);
                                                if ($plannedDeliveryDate->year > 1900) {
                                                    $plannedDeliveryText = $plannedDeliveryDate->format('d.m.Y');
                                                }
                                            } catch (\Exception $e) {
                                                $plannedDeliveryText = '-';
                                            }
                                        }
                                    @endphp
                                    {{ $plannedDeliveryText }}
                                </td>
                                <td data-label="Status">
                                    @php
                                    $badgeClass = 'bg-secondary';
                                    if($order->status == 'Completed') $badgeClass = 'bg-success';
                                    elseif($order->status == 'Started') $badgeClass = 'bg-primary';
                                    elseif($order->status == 'Done with ERR') $badgeClass = 'bg-danger';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2 py-1">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td data-label="Staff">{{ $order->staff ?: '-' }}</td>
                                <td data-label="Notes"><small class="text-muted">{{ $order->note ?: '-' }}</small></td>
                                <td data-label="Handling Date">{{ $order->delivery_handling_date ? \Carbon\Carbon::parse($order->delivery_handling_date)->format('d.m.Y') : '-' }}</td>
                                <td class="text-end" data-label="Action">
                                    <div class="d-flex justify-content-end gap-2 align-items-center w-100">
                                        <a href="{{ route('order-delivery.show', $order->order_id) }}" data-order-hub-url="{{ route('order-delivery.show', $order->order_id) }}" class="btn btn-sm btn-modern-primary mobile-action-btn" style="width: 85px; font-weight: 700;">
                                            Open
                                        </a>
                                        <div style="width: 50px; display: flex; justify-content: flex-end;">
                                            @if($order->status !== 'Completed')
                                            <button type="button" class="btn btn-sm text-white shadow-sm mobile-action-btn d-flex align-items-center justify-content-center" style="background-color: #f97316; border-radius: 8px; font-weight: 800; font-size: 1.2rem; width: 45px; height: 32px; padding: 0;" onclick="openAppendModal('{{ $order->order_id }}')" title="Append List to this Order">
                                                +
                                            </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    No order deliveries found. Start by importing a supplier file.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Append List Modal -->
        <div class="modal fade" id="appendListModal" tabindex="-1" aria-labelledby="appendListModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                <div class="modal-content" style="border-radius: 24px; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.15); padding: 1.2rem;">
                    <div class="modal-header border-0 pb-1 d-flex justify-content-between align-items-start" style="padding: 1rem 1.5rem 0.5rem 1.5rem;">
                        <h4 class="modal-title fw-bold" id="appendListModalLabel" style="color: #f97316; font-size: 1.4rem; letter-spacing: -0.5px;">
                            Append List to Order <span id="appendTargetOrderText"></span>
                        </h4>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" style="font-size: 0.9rem; margin-top: 5px; opacity: 0.6; transition: opacity 0.2s;"></button>
                    </div>
                    <div class="modal-body" style="padding: 0.5rem 1.5rem 1.5rem 1.5rem;">
                        <p class="text-muted mb-4" style="font-size: 1rem; line-height: 1.4; opacity: 0.8;">
                            Upload a CSV file to add items. They will keep their original list number internally.
                        </p>
                        <form action="{{ route('order-delivery.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="target_order_id" id="target_order_id_input">
                            
                            <div class="mb-4">
                                <label for="append_order_file" class="form-label fw-bold mb-2" style="color: #1e293b; font-size: 1.05rem;">
                                    Select Input File (Excel/CSV)
                                </label>
                                <input type="file" name="order_file" class="form-control" id="append_order_file" 
                                       style="border-radius: 10px; border: 1.5px solid #e2e8f0; padding: 0.6rem; font-size: 0.95rem; transition: border-color 0.2s;" 
                                       accept=".csv, .txt, .xls, .xlsx" required>
                            </div>

                            <button type="submit" class="btn text-white w-100" 
                                    style="background-color: #f97316; font-weight: 800; font-size: 1.3rem; padding: 14px; border-radius: 14px; box-shadow: 0 6px 15px rgba(249, 115, 22, 0.25);">
                                Upload & Append
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script>
        document.getElementById("searchInput").addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                filterTable();

                // UX Optimization for scanners: 
                // If there's only one visible row after scanning, maybe auto-click "Open"?
                // Let's check visible rows.
                setTimeout(() => {
                    const table = document.getElementById("ordersTable");
                    const rows = Array.from(table.querySelectorAll("tbody tr")).filter(r => r.style.display !== "none");
                    if (rows.length === 1) {
                        const openBtn = rows[0].querySelector("a[data-order-hub-url]");
                        if (openBtn) {
                            const base = openBtn.getAttribute("data-order-hub-url");
                            const q = (document.getElementById("searchInput").value || "").trim();
                            if (base) openBtn.setAttribute("href", buildOrderHubHref(base, q));
                            openBtn.click();
                        }
                    }
                }, 100);
            }
        });

        function orderSearchTokens(filterUpper) {
            if (!filterUpper) return [];
            return filterUpper.split(/\s+/).map(function (t) { return t.trim(); }).filter(Boolean);
        }

        function orderRowMatchesSearch(productIndexTextUpper, filterUpper) {
            const tokens = orderSearchTokens(filterUpper);
            if (!tokens.length) return true;
            return tokens.every(function (t) { return productIndexTextUpper.indexOf(t) !== -1; });
        }

        function filterTable() {
            const input = document.getElementById("searchInput");
            const filter = (input.value || "").trim().toUpperCase();
            const table = document.getElementById("ordersTable");
            const rows = table.querySelectorAll("tbody tr");
            const summary = document.getElementById("ordersMatchSummary");

            let visible = 0;
            rows.forEach((row) => {
                const searchIndexElement = row.querySelector(".product-search-index");
                if (!searchIndexElement) {
                    row.style.display = filter ? "none" : "";
                    return;
                }
                const productIndexText = (searchIndexElement.textContent || "").toUpperCase();
                const show = orderRowMatchesSearch(productIndexText, filter);
                row.style.display = show ? "" : "none";
                if (show) visible++;
            });

            if (summary) {
                if (!filter) {
                    summary.classList.add("d-none");
                    summary.textContent = "";
                } else {
                    summary.classList.remove("d-none");
                    summary.textContent = visible === 0
                        ? "No delivery orders contain that product or ID (all words must match somewhere on the order)."
                        : "Showing " + visible + " delivery order" + (visible === 1 ? "" : "s") + " that include a matching line.";
                }
            }
        }

        function buildOrderHubHref(baseUrl, queryTrimmed) {
            try {
                const u = new URL(baseUrl, window.location.origin);
                if (queryTrimmed) {
                    u.searchParams.set("highlight", queryTrimmed);
                } else {
                    u.search = "";
                }
                return u.pathname + u.search + u.hash;
            } catch (err) {
                if (!queryTrimmed) return baseUrl;
                var join = baseUrl.indexOf("?") === -1 ? "?" : "&";
                return baseUrl + join + "highlight=" + encodeURIComponent(queryTrimmed);
            }
        }

        document.getElementById("ordersTable").addEventListener("mousedown", function (e) {
            const link = e.target.closest("a[data-order-hub-url]");
            if (!link) return;
            var base = link.getAttribute("data-order-hub-url");
            if (!base) return;
            var q = (document.getElementById("searchInput").value || "").trim();
            link.setAttribute("href", buildOrderHubHref(base, q));
        });

        (function () {
            let debounceId = null;
            const searchInput = document.getElementById("searchInput");
            if (searchInput) {
                searchInput.addEventListener("input", function () {
                    clearTimeout(debounceId);
                    debounceId = setTimeout(filterTable, 200);
                });
            }
        })();

        function resetTable() {
            document.getElementById("searchInput").value = "";
            filterTable();
            document.getElementById("searchInput").focus();
        }

        function openAppendModal(orderId) {
            document.getElementById('target_order_id_input').value = orderId;
            document.getElementById('appendTargetOrderText').innerText = '#' + orderId;
            var myModal = new bootstrap.Modal(document.getElementById('appendListModal'));
            myModal.show();
        }

        async function reopenReportList(orderId) {
            if (typeof Swal === 'undefined') {
                const response = prompt("Enter Password to Reopen (3535):");
                if (response === '3535') {
                    submitReopen(orderId, response);
                }
                return;
            }

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
                submitReopen(orderId, password);
            }
        }

        async function submitReopen(orderId, password) {
            try {
                const response = await fetch("{{ route('order-delivery.reopen') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        password: password
                    })
                });

                const result = await response.json();
                if (result.success) {
                    if (typeof Swal !== 'undefined') {
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
                        alert('Order reopened successfully');
                        window.location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', result.message || 'Failed to reopen report', 'error');
                    } else {
                        alert(result.message || 'Failed to reopen report');
                    }
                }
            } catch (error) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Something went wrong', 'error');
                } else {
                    alert('Something went wrong');
                }
            }
        }
    </script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(config('broadcasting.connections.pusher.key'))
            var pusher = new Pusher("{{ config('broadcasting.connections.pusher.key') }}", {
                cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}"
            });

            var channel = pusher.subscribe("{{ config('app.env') }}.order.global");
            channel.bind('scan-event', function(data) {
                if (data && data.action === 'reload_list') {
                    var hasModal = document.querySelectorAll('.modal.show').length > 0;
                    var isTyping = document.activeElement && document.activeElement.tagName === 'INPUT' && document.activeElement.id === 'searchInput' && document.activeElement.value !== '';

                    if (!hasModal && !isTyping) {
                        setTimeout(() => window.location.reload(), 1000);
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Order List Updated',
                            text: 'Refresh the page to see new imports.',
                            icon: 'info',
                            toast: true,
                            position: 'top-end',
                            timer: 4000,
                            showConfirmButton: false
                        });
                    }
                }
            });
            @endif
        });
    </script>
</body>

</html>