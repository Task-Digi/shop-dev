@extends('layouts.app')

@section('content')

<head>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JavaScript -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
@php
    $activeSearch = trim((string) ($search ?? request('search', '')));
    $daysLabel = ((string) $days === '0' || $days == 0) ? 'All time' : 'Last ' . $days . ' sales days';
    $reportBaseParams = array_filter([
        'days' => $days,
        'per_page' => request('per_page'),
        'search' => $activeSearch !== '' ? $activeSearch : null,
    ], fn ($v) => $v !== null && $v !== '');
    $customerSearchClearUrl = route('report-customer', array_merge(
        ['customerId' => 'all'],
        array_filter(
            ['days' => $days, 'per_page' => request('per_page')],
            fn ($v) => $v !== null && $v !== ''
        )
    ));
    $routeCustomerId = $customerId ?? request()->route('customerId');
@endphp

@include('layouts.nav_bar')
<div class="card customer-report-page">
    <div class="card-body customer-report-head">
        <h4 class="customer-report-title mb-3">Customer-Report View</h4>

        <div class="customer-report-toolbar">
            <form method="GET" action="{{ route('report-customer', ['customerId' => 'CustomerName']) }}" id="customerReportFilters" class="customer-report-filters">
                <input type="hidden" name="per_page" value="{{ request('per_page', 25) }}">

                <div class="customer-report-filters-row">
                    <label for="customerSearch" class="form-label customer-report-label">Search customer</label>
                    <div class="input-group customer-search-group">
                        <input type="search" name="search" id="customerSearch" class="form-control"
                            value="{{ $activeSearch }}" placeholder="Name or customer ID…" autocomplete="off">
                        <button type="submit" class="btn btn-primary" id="customerSearchButton">Search</button>
                        <button type="button" id="customerSearchClear" data-clear-url="{{ $customerSearchClearUrl }}"
                            class="btn btn-outline-secondary {{ $activeSearch === '' ? 'd-none' : '' }}">Clear</button>
                    </div>
                </div>

                <div class="customer-report-filters-row">
                    <label for="days" class="form-label customer-report-label">Period</label>
                    <select name="days" id="days" class="form-select form-select-sm customer-days-select"
                        onchange="document.getElementById('customerReportFilters').requestSubmit()">
                        <option value="7" {{ (string) $days === '7' ? 'selected' : '' }}>Last 7 sales days</option>
                        <option value="28" {{ (string) $days === '28' ? 'selected' : '' }}>Last 28 sales days</option>
                        <option value="56" {{ (string) $days === '56' ? 'selected' : '' }}>Last 56 sales days</option>
                        <option value="0" {{ (string) $days === '0' || $days == 0 ? 'selected' : '' }}>All time</option>
                    </select>
                </div>
            </form>

            <div class="customer-report-actions">
                <a href="{{ route('report.customers.export', array_filter([
                    'days' => $days,
                    'search' => $activeSearch !== '' ? $activeSearch : null,
                    'customer_id' => !in_array($routeCustomerId, ['all', 'CustomerName'], true) ? $routeCustomerId : null,
                ], fn ($value) => $value !== null && $value !== '')) }}" class="btn btn-outline-success">
                    Export CSV
                </a>
                <a href="{{ route('report-customer', array_merge(['customerId' => 'all'], $reportBaseParams)) }}"
                    class="btn btn-outline-primary {{ ($routeCustomerId === 'all' || $routeCustomerId === 'CustomerName') && $activeSearch === '' ? 'active' : '' }}">
                    All Customers
                </a>
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#customerSalesGraph" aria-expanded="false" aria-controls="customerSalesGraph">
                    Sales graph
                </button>
            </div>
        </div>

        @if($activeSearch !== '')
            <p class="customer-report-meta mb-0 mt-2">Results for <strong>{{ $activeSearch }}</strong> · {{ $daysLabel }}</p>
        @else
            <p class="customer-report-meta text-muted mb-0 mt-2">{{ $daysLabel }}</p>
        @endif

        <div class="collapse mt-3" id="customerSalesGraph">
            <div class="card card-body customer-graph-card">
                <canvas id="salesChart" width="400" height="100"></canvas>
                <div class="pagination-controls d-flex align-items-center gap-2 mt-2">
                    <button type="button" id="prevPage" class="btn btn-sm btn-outline-secondary" disabled>Previous</button>
                    <span id="pageInfo" class="small text-muted"></span>
                    <button type="button" id="nextPage" class="btn btn-sm btn-outline-secondary">Next</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-none customer-report-table-card">
        <div class="card-body pt-0">
            <div id="customerReportStatus" class="alert py-2" role="status" style="display:none;"></div>
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 customer-table-tools">
                <span class="small text-muted">Click a row to expand sales by date.</span>
                <div class="d-flex align-items-center gap-2">
                    <label for="perPageSelect" class="mb-0 small text-muted">Rows per page</label>
                    <select id="perPageSelect" class="form-select form-select-sm" style="width:auto; min-width:4.5rem;">
                    @foreach ([10, 25, 50, 100, 200] as $size)
                        <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
                </div>
            </div>
            <div class="table-responsive">
                <table id="Reporttable" class="table table-sm table-hover customer-report-table">
                    <thead>
                        <tr>
                            <th class="col-customer">Customer Name</th>
                            <th class="col-date">Sales.Date</th>
                            <th class="col-location">Sales.Location</th>
                            <th class="col-total-orders text-right">Total Orders</th>
                            <th class="col-num text-right">No.Orders</th>
                            <th class="col-num text-right">No.Sold</th>
                            <th class="col-price text-right">Price</th>
                            <th class="col-sum text-right">Sum.Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($salesData as $sale)
                        <tr class="mobile-row" data-customer-id="{{ $sale->customer_id }}" data-location="{{ $sale->location }}">
                            <td class="col-customer">{{ $sale->customer_name }} @if(($sale->KS_exists ?? 0) == 1)
                                <span class="badge badge-pill badge-danger">KS</span>
                                @endif
                            </td>
                            <td class="col-date"></td>
                            <td class="col-location">{{ $sale->location }}</td>
                            <td class="col-total-orders text-right">{{ number_format($sale->total_products_sold, 0, '.', ',') }}</td>
                            <td class="col-num text-right">{{ number_format($sale->order_id_count, 0, '.', ',') }}</td>
                            <td class="col-num text-right">{{ number_format($sale->total_products_sold, 0, '.', ',') }}</td>
                            <td class="col-price text-right">{{ $sale->unit_price_avg !== null ? number_format($sale->unit_price_avg, 2, '.', ',') : '0.00' }}</td>
                            <td class="col-sum text-right">{{ number_format($sale->total_sales, 2, '.', ',') }}</td>
                        </tr>
                        <tr class="hidden-row2" style="display: none;color:green">
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <strong>No customers found.</strong><br>
                                Try another search or period.
                            </td>
                        </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

            <x-report-pagination :paginator="$salesData" item-label="customers" aria-label="Customer report pages" />
        </div>
    </div>
</div>
{{-- <script>
        document.getElementById('days').addEventListener('change', function() {
            document.getElementById('searchInput').value =
            "{{ request()->input('search') }}"; // Keep search term on days change
document.getElementById('searchForm').submit();
});

// Clear search only on "All Customers" button click
document.querySelector('.btn-all-customers').addEventListener('click', function() {
document.getElementById('searchInput').value = ''; // Clear search input
});
</script> --}}


<script type="application/json" id="customerChartData">{!! json_encode($salesData1, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script>
    // "Rows per page" selector — reload the current URL with the new size
    // while preserving the existing search/days query params.
    (function() {
        var sel = document.getElementById('perPageSelect');
        if (!sel) return;
        sel.addEventListener('change', function() {
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', sel.value);
            url.searchParams.delete('page'); // jump back to first page on size change
            window.location.href = url.toString();
        });
    })();

    (function() {
        var input = document.getElementById('customerSearch');
        var clearBtn = document.getElementById('customerSearchClear');
        if (!input || !clearBtn) return;

        var clearUrl = clearBtn.dataset.clearUrl;

        function toggleClearVisibility() {
            clearBtn.classList.toggle('d-none', input.value.trim() === '');
        }

        input.addEventListener('input', toggleClearVisibility);

        clearBtn.addEventListener('click', function() {
            input.value = '';
            input.focus();
            toggleClearVisibility();
            window.location.href = clearUrl;
        });
    })();

    document.getElementById('customerReportFilters').addEventListener('submit', function() {
        var button = document.getElementById('customerSearchButton');
        if (button) {
            button.disabled = true;
            button.textContent = 'Loading...';
        }
    });
</script>
<script>
    var ajaxInProgress = false;
    // Use jQuery instead of $ to avoid conflicts with other libraries
    // jQuery(document).ready(function($) {

    //     function calculateRollingAverage(data, windowSize) {
    //         let rollingAverages = [];
    //         for (let i = 0; i < data.length; i++) {
    //             if (i < windowSize - 1) {
    //                 rollingAverages.push(null); // Not enough data to calculate rolling average
    //             } else {
    //                 let windowData = data.slice(i - windowSize + 1, i + 1);
    //                 let sum = windowData.reduce((acc, val) => acc + val, 0);
    //                 rollingAverages.push(sum / windowSize);
    //             }
    //         }
    //         return rollingAverages;
    //     }

    //     // Calculate trendline based on linear regression
    //     function calculateTrendline(data) {
    //         const n = data.length;
    //         const sumX = data.reduce((acc, val, idx) => acc + idx, 0);
    //         const sumY = data.reduce((acc, val) => acc + val, 0);
    //         const sumXY = data.reduce((acc, val, idx) => acc + idx * val, 0);
    //         const sumX2 = data.reduce((acc, val, idx) => acc + idx * idx, 0);

    //         const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
    //         const intercept = (sumY - slope * sumX) / n;

    //         return data.map((val, idx) => slope * idx + intercept);
    //     }

    //     // Extracting customer names and their total sales
    //     // Original data from backend
    //     const salesData1 = {!! json_encode($salesData1) !!};
    //     const labels = salesData1.map(item => item.customer_name); // Customer names for x-axis
    //     const sales = salesData1.map(item => Number(item.total_sales)); // Total sales for each customer

    //     // Calculate rolling average and trendline based on customer sales data
    //     const rollingAverage = calculateRollingAverage(sales, 3); // Adjust window size if needed
    //     const trendline = calculateTrendline(sales);

    //     const ctx = document.getElementById('salesChart').getContext('2d');
    //     new Chart(ctx, {
    //         type: 'bar',
    //         data: {
    //             labels: labels,
    //             datasets: [{
    //                     label: 'Total Sales',
    //                     data: sales,
    //                     backgroundColor: 'rgba(75, 192, 192, 0.2)',
    //                     borderColor: 'rgba(75, 192, 192, 1)',
    //                     borderWidth: 1
    //                 },
    //                 {
    //                     label: 'Rolling Average',
    //                     data: rollingAverage,
    //                     backgroundColor: 'rgba(255, 159, 64, 0.2)',
    //                     borderColor: 'rgba(255, 159, 64, 1)',
    //                     borderWidth: 1,
    //                     type: 'line', // Line chart for the rolling average
    //                     borderDash: [5, 5]
    //                 },
    //                 {
    //                     label: 'Trendline',
    //                     data: trendline,
    //                     backgroundColor: 'rgba(153, 102, 255, 0.2)',
    //                     borderColor: 'rgba(153, 102, 255, 1)',
    //                     borderWidth: 1,
    //                     type: 'line', // Line chart for the trendline
    //                     borderDash: [10, 5]
    //                 }
    //             ]
    //         },
    //         options: {
    //             scales: {
    //                 x: {
    //                     title: {
    //                         display: true,
    //                         text: 'Customer Name'
    //                     }
    //                 },
    //                 y: {
    //                     beginAtZero: true,
    //                     title: {
    //                         display: true,
    //                         text: 'Sum of Sales'
    //                     }
    //                 }
    //             }
    //         }
    //     });

    // });
    jQuery(document).ready(function($) {
        let currentPage = 0;
        const itemsPerPage = 10; // Adjust as needed

        // Initial dataset setup
        const salesData1 = JSON.parse(document.getElementById('customerChartData').textContent || '[]');
        const labels = salesData1.map(item => {
            const name = item.customer_name || 'Unknown customer';
            return item.customer_id ? `${name} (${item.customer_id})` : name;
        });
        const sales = salesData1.map(item => Number(item.total_sales)); // Total sales for each customer

        function paginateData(data, page, perPage) {
            const start = page * perPage;
            return data.slice(start, start + perPage);
        }

        function calculateRollingAverage(data, windowSize) {
            let rollingAverages = [];
            for (let i = 0; i < data.length; i++) {
                if (i < windowSize - 1) {
                    rollingAverages.push(null); // Not enough data to calculate rolling average
                } else {
                    let windowData = data.slice(i - windowSize + 1, i + 1);
                    let sum = windowData.reduce((acc, val) => acc + val, 0);
                    rollingAverages.push(sum / windowSize);
                }
            }
            return rollingAverages;
        }

        function calculateTrendline(data) {
            const n = data.length;
            if (n === 0) return [];
            if (n === 1) return [data[0]];
            const sumX = data.reduce((acc, val, idx) => acc + idx, 0);
            const sumY = data.reduce((acc, val) => acc + val, 0);
            const sumXY = data.reduce((acc, val, idx) => acc + idx * val, 0);
            const sumX2 = data.reduce((acc, val, idx) => acc + idx * idx, 0);

            const denominator = n * sumX2 - sumX * sumX;
            if (denominator === 0) return data.slice();
            const slope = (n * sumXY - sumX * sumY) / denominator;
            const intercept = (sumY - slope * sumX) / n;

            return data.map((val, idx) => slope * idx + intercept);
        }

        function updateChart(chart, page) {
            const paginatedLabels = paginateData(labels, page, itemsPerPage);
            const paginatedSales = paginateData(sales, page, itemsPerPage);
            const paginatedRollingAvg = paginateData(calculateRollingAverage(sales, 3), page, itemsPerPage);
            const paginatedTrendline = paginateData(calculateTrendline(sales), page, itemsPerPage);

            chart.data.labels = paginatedLabels;
            chart.data.datasets[0].data = paginatedSales;
            chart.data.datasets[1].data = paginatedRollingAvg;
            chart.data.datasets[2].data = paginatedTrendline;

            chart.update();

            $("#pageInfo").text(`Page ${page + 1} of ${Math.ceil(salesData1.length / itemsPerPage)}`);
            $("#prevPage").prop("disabled", page === 0);
            $("#nextPage").prop("disabled", page >= Math.ceil(salesData1.length / itemsPerPage) - 1);
        }

        if (typeof Chart !== 'undefined' && document.getElementById('salesChart')) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                            label: 'Total Sales',
                            data: [],
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            type: 'line',
                            label: 'Rolling Average',
                            data: [],
                            backgroundColor: 'rgba(255, 159, 64, 0.2)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1,
                            borderDash: [5, 5],
                            fill: true,
                            tension: 0.4
                        },
                        {
                            type: 'line',
                            label: 'Trendline',
                            data: [],
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1,
                            borderDash: [10, 5],
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Customer Name'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sum of Sales'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Total Sales: ${Number(context.raw).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                                }
                            }
                        }
                    }
                }
            });

            // Update chart with initial page
            updateChart(chart, currentPage);

            // Pagination controls
            $('#prevPage').click(function() {
                if (currentPage > 0) {
                    currentPage--;
                    updateChart(chart, currentPage);
                }
            });

            $('#nextPage').click(function() {
                if (currentPage < Math.ceil(salesData1.length / itemsPerPage) - 1) {
                    currentPage++;
                    updateChart(chart, currentPage);
                }
            });
        }
    });
</script>
<script>
    function showCustomerReportStatus(message, type) {
        var status = document.getElementById('customerReportStatus');
        if (!status) return;
        status.className = 'alert py-2 alert-' + (type || 'info');
        status.textContent = message;
        status.style.display = 'block';
    }

    function hideCustomerReportStatus() {
        var status = document.getElementById('customerReportStatus');
        if (status) status.style.display = 'none';
    }

    function escapeCustomerHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $(document).on('click', '.mobile-row', function() {
        if (ajaxInProgress) {
            return;
        };

        ajaxInProgress = true;

        // var date = $(this).data('date');
        // Read attributes as strings so numeric-looking customer IDs are not
        // converted by jQuery (which can remove leading zeroes or lose digits).
        var location = this.getAttribute('data-location');
        var customerId = this.getAttribute('data-customer-id');
        var hiddenRow = $(this).next('.hidden-row2');
        var requestKey = String(customerId) + '|' + String(location);

        // Hide all other hidden rows
        $('.hidden-row2 ').not(hiddenRow).hide().removeClass('loaded');

        var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible2') === requestKey;

        if (!isHiddenRowVisible) {
            hideCustomerReportStatus();
            $('.customer-details-row, .customer-details-lastRow, .hidden-row3').remove();
            hiddenRow.html('<td colspan="8" class="text-center text-muted py-3">Loading customer orders...</td>').show();
            $.ajax({
                url: '/CustomerReport/details',
                method: 'GET',
                data: {
                    // date: date,
                    location: location,
                    customer_id: customerId,
                    days: '{{ $days }}'
                },
                success: function(response) {
                    hiddenRow.siblings('.customer-details-row').each(function() {
                        var $next = $(this).next('tr');
                        if ($next.hasClass('hidden-row3')) {
                            $next.remove();
                        }
                    });
                    hiddenRow.siblings('.customer-details-row').remove();

                    // Append each customer's details as individual rows
                    var orderRowsHtml = '';
                    if (!Array.isArray(response) || response.length === 0) {
                        hiddenRow.html('<td colspan="8" class="text-center text-muted py-3">No orders found for this customer and period.</td>').show();
                        sessionStorage.setItem('hiddenRowVisible2', requestKey);
                        return;
                    }

                    response.forEach(function(customer) {
                        if (!customer) return;

                        function formatDate(dateString) {
                            const date = new Date(dateString);
                            if (isNaN(date))
                                return dateString;
                            const day = String(date.getDate()).padStart(2, '0');
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const year = String(date.getFullYear()).slice(-2);
                            return `${day}.${month}.${year}`;
                        }
                        function formatMoney(n) {
                            if (n === null || n === undefined || n === '') return '0.00';
                            return Number(n).toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                        function orderRefCell(orderid) {
                            var oid = escapeCustomerHtml(orderid);
                            return '<span>Order ' + oid + '</span>';
                        }
                        var safeOrderId = escapeCustomerHtml(customer.orderid);
                        var safeCustomerId = escapeCustomerHtml(customerId);
                        var safeCustomerName = escapeCustomerHtml(customer.customer_name);
                        orderRowsHtml +=
                            '<tr class="customer-details-row alert alert-primary" data-orderid="' +
                            safeOrderId +
                            '" data-customerid="' + safeCustomerId +
                            '" data-name="' + safeCustomerName +
                            '">' +
                            '<td class="col-customer"><h6>' + escapeCustomerHtml(customer.customer_id) + '</h6></td>' +
                            '<td class="col-date"><h6>' + escapeCustomerHtml(formatDate(customer.sales_date)) + '</h6></td>' +
                            '<td class="col-location"><h6>' + escapeCustomerHtml(customer.location) + '</h6></td>' +
                            '<td class="col-total-orders text-right"><h6>' + escapeCustomerHtml(customer.daily_total_orders) + '</h6></td>' +
                            '<td class="col-num text-right"><h6>' + orderRefCell(customer.orderid) + '</h6></td>' +
                            '<td class="col-num text-right"><h6>' + customer.total_products_sold + '</h6></td>' +
                            '<td class="col-price text-right"><h6>' + formatMoney(customer.unit_price) + '</h6></td>' +
                            '<td class="col-sum text-right"><h6>' + formatMoney(customer.total_sales) + '</h6></td>' +
                            '</tr>' +
                            '<tr class="hidden-row3" style="display: none; color: yellow;"></tr>';
                    });
                    hiddenRow.after(orderRowsHtml);

                    // Mark hiddenRow as loaded
                    hiddenRow.empty().addClass('loaded').hide();
                    sessionStorage.setItem('hiddenRowVisible2', requestKey);
                },
                error: function() {
                    hiddenRow.html('<td colspan="8" class="text-center text-danger py-3">Unable to load customer orders. Click the customer row to retry.</td>').show();
                    showCustomerReportStatus('Unable to load customer orders. Please retry.', 'danger');
                    sessionStorage.setItem('hiddenRowVisible2', '');
                },
                complete: function() {
                    ajaxInProgress = false;
                }
            });
        } else {
            $('.customer-details-row, .customer-details-lastRow, .hidden-row3').remove();
            hiddenRow.empty().hide();
            sessionStorage.setItem('hiddenRowVisible2', '');
            ajaxInProgress = false;
        }
    });
    // Click event handler for hidden-row
    $(document).on('click', '.customer-details-row', function() {
        if (ajaxInProgress) {
            return;
        }

        $('.customer-details-lastRow').hide();
        // var date = $(this).data('date');
        var orderid = $(this).data('orderid');
        var customerId = $(this).data('customerid');
        var customerName = $(this).data('name');
        var hiddenRow = $(this).next('.hidden-row3');

        // Hide all other hidden rows
        $('.hidden-row3 ').not(hiddenRow).hide().removeClass('loaded');

        var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible3') == orderid;

        // Check if details for hidden-row3 are already loaded
        if (!isHiddenRowVisible) {
            ajaxInProgress = true;
            hiddenRow.html('<td colspan="8" class="text-center text-muted py-3">Loading order items...</td>').show();
            $.ajax({
                url: '/customer/finaldetails',
                method: 'GET',
                data: {
                    orderid: orderid,
                    customerId: customerId
                },
                success: function(response) {

                    // Remove any existing customer details rows
                    hiddenRow.siblings('.customer-details-lastRow').remove();

                    if (!Array.isArray(response) || response.length === 0) {
                        hiddenRow.html('<td colspan="8" class="text-center text-muted py-3">No products found for this order.</td>').show();
                        sessionStorage.setItem('hiddenRowVisible3', orderid);
                        return;
                    }

                    response.forEach(function(customer) {

                        if (customer) {
                            function formatNumber(number) {
                                var value = Number(number);
                                if (!Number.isFinite(value)) return '-';
                                return value.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                            var customerHtml =
                                '<tr class="customer-details-lastRow alert alert-success">' +
                                '<td class="col-customer"><h6></h6></td>' +
                                '<td class="col-date"><h6></h6></td>' +
                                '<td class="col-location"><h6>Product - ' + escapeCustomerHtml(customer.product_name) + '</h6></td>' +
                                '<td class="col-total-orders"><h6></h6></td>' +
                                '<td class="col-num text-right"><h6>ID - ' + escapeCustomerHtml(customer.product_id) + '</h6></td>' +
                                '<td class="col-num text-right"><h6>' + formatNumber(customer.count) + '</h6></td>' +
                                '<td class="col-price text-right"><h6>' + formatNumber(customer.price) + '</h6></td>' +
                                '<td class="col-sum text-right"><h6>' + formatNumber(customer.total_price) + '</h6></td>' +
                                '</tr>';

                            hiddenRow.after(customerHtml);
                        }
                    });

                    // Mark hiddenRow as loaded
                    hiddenRow.empty().addClass('loaded').hide();
                    sessionStorage.setItem('hiddenRowVisible3', orderid);
                },
                error: function() {
                    hiddenRow.html('<td colspan="8" class="text-center text-danger py-3">Unable to load order items. Click the order row to retry.</td>').show();
                    showCustomerReportStatus('Unable to load order items. Please retry.', 'danger');
                    sessionStorage.setItem('hiddenRowVisible3', '');
                },
                complete: function() {
                    ajaxInProgress = false;
                }
            });
        } else {
            $('.customer-details-lastRow').remove();
            hiddenRow.empty().hide();
            sessionStorage.setItem('hiddenRowVisible3', '');
            ajaxInProgress = false;
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"
    integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous">
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"
    integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous">
</script>

<style>

    .customer-report-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 1rem 1.5rem;
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }

    .customer-report-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 1rem 1.25rem;
        flex: 1 1 320px;
        min-width: 0;
    }

    .customer-report-filters-row {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .customer-report-filters-row:first-child {
        flex: 1 1 280px;
        max-width: 520px;
    }

    .customer-report-label {
        margin: 0;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
    }

    .customer-search-group .form-control {
        min-width: 12rem;
    }

    .customer-days-select {
        min-width: 9rem;
    }

    .customer-report-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .customer-report-actions .btn.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }

    .customer-report-meta {
        font-size: 0.9rem;
    }

    .customer-graph-card {
        border: 1px solid #e2e8f0;
    }

    /* Customer Report table — keep column widths predictable so the headers
       align with the body, and so the right-aligned numeric columns line up. */
    .customer-report-table {
        width: 100%;
        table-layout: fixed;
    }

    .customer-report-table th,
    .customer-report-table td {
        vertical-align: middle;
        padding: 8px 10px;
        word-break: break-word;
    }

    .customer-report-table th.text-right,
    .customer-report-table td.text-right {
        text-align: right;
    }

    .customer-report-table .col-customer     { width: 26%; }
    .customer-report-table .col-date         { width: 11%; }
    .customer-report-table .col-location     { width: 13%; }
    .customer-report-table .col-total-orders { width: 9%; }
    .customer-report-table .col-num          { width: 9%; }
    .customer-report-table .col-price        { width: 9%; }
    .customer-report-table .col-sum          { width: 14%; }

    @media (max-width: 767.98px) {
        .customer-report-table {
            table-layout: auto;
        }
    }

    /* Unified pagination footer — text on the left, buttons on the right,
       same line on desktop, stacks on small screens. */
    .report-pagination-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 1rem;
        padding: 0 4px;
        width: 100%;
    }

    .report-pagination-wrap .results-info {
        color: #64748b;
        font-size: 13px;
    }

    .report-pagination-wrap .pagination {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        padding-left: 0;
        list-style: none;
        margin: 0;
    }

    .report-pagination-wrap .pagination .page-item .page-link,
    .report-pagination-wrap .pagination .page-item span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 34px;
        padding: 0 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        color: #334155;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        line-height: 1;
    }

    .report-pagination-wrap .pagination .page-item.active .page-link,
    .report-pagination-wrap .pagination .page-item.active span {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
        font-weight: 600;
    }

    .report-pagination-wrap .pagination .page-item.disabled .page-link,
    .report-pagination-wrap .pagination .page-item.disabled span {
        color: #94a3b8;
        background: #f8fafc;
        cursor: not-allowed;
    }

    .report-pagination-wrap .pagination .page-item .page-link:hover {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    @media (max-width: 575.98px) {
        .report-pagination-wrap {
            justify-content: center;
            text-align: center;
        }
    }

    /* CSS for KS Button - Default (Inactive) State */
    .ks-button.ks-inactive {
        background-color: #d3d3d3;
        /* Light gray */
        color: #333;
        transition: all 0.3s ease;
    }

    .ks-button.ks-inactive:hover {
        background-color: #b0b0b0;
        /* Darker gray on hover */
    }

    /* CSS for KS Button - Active State */
    .ks-button.ks-active {
        background-color: #038221ff;
        /* Green */
        color: white;
        transition: all 0.3s ease;
    }

    .ks-button.ks-active:hover {
        background-color: #026017ff;
        /* Darker green on hover */
    }
</style>

<script>
    // JavaScript to handle KS button clicks
    document.addEventListener('DOMContentLoaded', function() {
        // Select all KS buttons
        const ksButtons = document.querySelectorAll('.ks-button');

        ksButtons.forEach(button => {
            button.addEventListener('click', function() {
                const clickedButton = this;
                const customerId = this.getAttribute('data-customer-id');
                const customerName = this.getAttribute('data-customer-name');
                const currentStatus = parseInt(this.getAttribute('data-ks-status'));

                // Toggle the status (0 to 1, or 1 to 0)
                const newStatus = currentStatus === 0 ? 1 : 0;
                clickedButton.disabled = true;

                // Send AJAX request to update the database
                fetch('/update-ks-status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        },
                        body: JSON.stringify({
                            customer_id: customerId,
                            KS_exists: newStatus
                        })
                    })
                    .then(response => response.json().then(data => ({ ok: response.ok, data })))
                    .then(result => {
                        const data = result.data;
                        if (!result.ok) throw new Error(data.message || 'Unable to update KS status.');
                        if (data.success) {
                            // Update the button status and color
                            this.setAttribute('data-ks-status', newStatus);
                            this.classList.remove('ks-inactive', 'ks-active');
                            this.classList.add(newStatus === 1 ? 'ks-active' : 'ks-inactive');

                            Swal.fire({
                                icon: 'success',
                                title: 'KS status updated',
                                text: customerName || String(customerId),
                                timer: 1400,
                                showConfirmButton: false
                            });
                        } else {
                            throw new Error(data.message || 'Unable to update KS status.');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Update failed', error.message || 'Unable to update KS status.', 'error');
                    })
                    .finally(() => {
                        clickedButton.disabled = false;
                    });
            });
        });
    });
</script>
@endsection
