<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="color-scheme" content="light only">
    <title>Order Delivery Management</title>
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
            font-family: var(--font-family);
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
    </style>
</head>

<body>
    @include('layouts.nav_bar')

    <div class="container-fluid mt-5 mb-5 px-4">
        <div class="modern-card">
            <div class="card-header bg-transparent border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold" style="color: #2563eb;">ORDER DELIVERY MANAGEMENT</h5>
                <div class="header-actions d-flex gap-2">
                    <a href="{{ route('order-delivery.mobile-global') }}" target="_blank" class="btn btn-modern-secondary btn-sm d-flex align-items-center gap-2 px-3" title="Open Mobile Scanner">
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
                <!-- Mobile Quick Sync Button -->
                <div class="d-md-none px-3 pt-3">
                    <a href="{{ route('order-delivery.mobile-global') }}" class="btn btn-primary w-100 py-3 shadow d-flex align-items-center justify-content-center gap-2 mb-4" style="border-radius: 12px; font-weight: 800; background: #2563eb; border: none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                            <path d="M12 18h.01"></path>
                        </svg>
                        OPEN SYNCED DESKTOP SESSION
                    </a>
                </div>

                <div class="mb-4 px-3">
                    <form action="{{ route('order-delivery.index') }}" method="GET">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <input type="text" name="search" id="searchInput" class="form-control form-control-modern" placeholder="Search by Order ID, EAN, Name, Varenummer..." value="{{ request('search') }}" style="border-radius: 8px; padding: 10px;" autofocus>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-modern-primary btn-sm">Search</button>
                                <a href="{{ route('order-delivery.index') }}" class="btn btn-modern-secondary btn-sm d-flex align-items-center justify-content-center">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="collapse mb-4" id="uploadCollapse">
                    <div class="card card-body bg-light border-0 mb-3" style="border-radius: 12px;">
                        <form action="{{ route('order-delivery.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row align-items-center">
                                <div class="col-md-9">
                                    <label for="order_file" class="form-label font-weight-bold mb-0">Select Supplier Order File (CSV/TXT)</label>
                                    <input type="file" name="order_file" class="form-control form-control-modern mt-2" id="order_file" required>
                                </div>
                                <div class="col-md-3 text-end">
                                    <button type="submit" class="btn btn-modern-primary w-100 mt-4">Upload File</button>
                                </div>
                            </div>
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
                    @if(isset($items))
                    {{-- Product Search Results View --}}
                    <table class="table table-modern table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="pe-4">DATE</th>
                                <th>ORDER ID</th>
                                <th class="ps-4">PRODUCT DETAILS</th>
                                <th class="text-center">ORDERED</th>
                                <th class="text-center">SCANNED</th>
                                <th class="text-center">REST</th>
                                <th class="text-center">#</th>
                                <th class="text-end">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            <tr>
                                <td data-label="Date" class="pe-4">{{ $item->record_date ? \Carbon\Carbon::parse($item->record_date)->format('d.m.Y') : '-' }}</td>
                                <td data-label="Order ID">
                                    <a href="{{ route('order-delivery.show', $item->order_id) }}" class="fw-bold text-decoration-none">#{{ $item->order_id }}</a>
                                </td>
                                <td data-label="Product Details" class="ps-4">
                                    <div class="fw-bold text-dark">{{ $item->item_name }}</div>
                                    @if($item->show_volume)
                                    <span class="badge bg-light text-dark border p-1" style="font-weight: 800; font-size: 0.7rem;">
                                        @if($item->packaging_quantity > 1) {{ str_replace('.', ',', (float)$item->packaging_quantity) }} @endif
                                        {{ $item->packaging_unit }}
                                    </span>
                                    @endif
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        EAN: {{ $item->ean_display }} / VareNr.: {{ $item->sku }}
                                    </div>
                                </td>
                                <td data-label="Ordered" class="text-center text-secondary fw-bold">{{ (int)$item->quantity }}</td>
                                <td data-label="Scanned" class="text-center text-dark fw-bold">{{ $item->scanned_total }}</td>
                                <td data-label="Rest" class="text-center text-success fw-bold">{{ $item->remaining }}</td>
                                <td data-label="#" class="text-center">
                                    <span class="badge bg-light text-secondary border fw-bold">{{ $item->scanned_total }}</span>
                                </td>
                                <td data-label="Status" class="text-end">
                                    <span class="badge px-2 py-1" style="background-color: {{ $item->status_color }}; min-width: 80px;">
                                        {{ $item->status_text }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    No products found matching "{{ $search }}". 
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @else
                    {{-- Default Orders List View --}}
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
                                    <a href="{{ route('order-delivery.show', $order->order_id) }}" class="fw-bold text-decoration-none">#{{ $order->order_id }}</a>
                                    <span style="display:none">{{ $order->searchable_eans }}</span>
                                </td>
                                <td data-label="Order Date">{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d.m.Y') : '-' }}</td>
                                <td data-label="Items (Rows)">{{ $order->total_unique_items }}</td>
                                <td data-label="Total Qty">{{ (int)$order->total_quantity }}</td>
                                <td data-label="Planned Delivery">{{ $order->planned_delivery ? \Carbon\Carbon::parse($order->planned_delivery)->format('d.m.Y') : '-' }}</td>
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
                                <td data-label="Handling Date">{{ $order->delivery_handling_date ? \Carbon\Carbon::parse($order->delivery_handling_date)->format('d.m.Y H:i') : '-' }}</td>
                                 <td class="text-end" data-label="Action">
                                    <div class="d-flex justify-content-end align-items-center w-100 gap-2">
                                        @if($order->completed)
                                            <a href="{{ route('order-delivery.show', $order->order_id) }}" class="btn btn-sm btn-modern-primary mobile-action-btn" style="flex: 1; min-width: 80px;">
                                                OPEN
                                            </a>
                                        @else
                                            <a href="{{ route('order-delivery.show', $order->order_id) }}" class="btn btn-sm btn-modern-primary mobile-action-btn" style="flex: 1; min-width: 80px;">
                                                OPEN
                                            </a>
                                            <button onclick="openAppendModal('{{ $order->order_id }}')" class="btn btn-sm d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px; border-radius: 8px; background-color: #f97316; color: white; border: none; font-weight: bold; font-size: 18px; min-width: 32px;" title="Append items to this order">
                                                +
                                            </button>
                                        @endif
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
                    @endif
                </div>
            </div>
        </div>

        <!-- Append List Modal -->
        <div class="modal fade" id="appendListModal" tabindex="-1" aria-labelledby="appendListModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
                <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                    <div class="modal-header border-bottom-0 pb-2">
                        <h6 class="modal-title fw-bold" id="appendListModalLabel" style="color: #f97316;">Append List to Order <span id="appendTargetOrderText"></span></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-0 pb-3 px-3">
                        <p class="text-muted mb-3" style="font-size: 0.8rem; line-height: 1.3;">Upload a CSV file to add items. They will keep their original list number internally.</p>
                        <form action="{{ route('order-delivery.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="target_order_id" id="target_order_id_input">
                            <div class="mb-3">
                                <label for="append_order_file" class="form-label fw-bold" style="font-size: 0.85rem;">Select CSV</label>
                                <input type="file" name="order_file" class="form-control form-control-sm" id="append_order_file" required>
                            </div>
                            <button type="submit" class="btn text-white w-100" style="background-color: #f97316; font-weight: 600; padding: 8px; border-radius: 8px;">Upload & Append</button>
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
                this.form.submit();
            }
        });

        function openAppendModal(orderId) {
            document.getElementById('target_order_id_input').value = orderId;
            document.getElementById('appendTargetOrderText').innerText = '#' + orderId;
            var myModal = new bootstrap.Modal(document.getElementById('appendListModal'));
            myModal.show();
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
            channel.bind('scan.updated', function(data) {
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