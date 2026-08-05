@extends('layouts.app')

@section('content')

<head>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Include jQuery UI (Datepicker) -->
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">

    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>

    <!-- Bootstrap JavaScript -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

</head>

<div class="card">
    @include('layouts.nav_bar')
    <h4>Sales-Report View</h4>
    <div class="card-body" style="">
        {{-- <a href="/Report_view" class="btn btn-primary">Back to Dashboard</a> --}}
        <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#collapseExample2"
            aria-expanded="false" aria-controls="collapseExample2">
            Sales Graph View
        </button>

    </div>
    <div class="row">
        <div class="col-8">

        </div>

    </div>
    <div class="collapse" id="collapseExample2">

        <div class="card card-body sales-report-chart-panel">
            <div class="sales-chart-canvas-wrap">
                <canvas id="salesChart" width="900" height="280"></canvas>
            </div>
            @if ($chartLimited ?? false)
                <p class="text-muted small mb-0 mt-2">
                    For performance, the All Days chart shows the latest 366 calendar days. The table still includes the complete history.
                </p>
            @endif
            <div class="sales-chart-pagination-ui" aria-hidden="true">
                <button type="button" class="sales-chart-page-btn" disabled>Previous</button>
                <span class="sales-chart-page-info">Page 1 of 1</span>
                <button type="button" class="sales-chart-page-btn" disabled>Next</button>
            </div>
        </div>
        <style>
            .sales-report-filter-bar {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.5rem;
            }

            .sales-report-filter-bar .form-control,
            .sales-report-filter-bar select {
                width: auto;
                min-width: 130px;
                margin: 0 !important;
            }

            @media (max-width: 767.98px) {
                .sales-report-filter-bar > * {
                    width: 100% !important;
                }
            }

            .sales-report-chart-panel {
                background: #fff;
                border-radius: 6px;
            }

            .sales-chart-canvas-wrap {
                position: relative;
                height: 300px;
                max-width: 100%;
            }

            .sales-chart-pagination-ui {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-top: 14px;
                padding-top: 4px;
            }

            .sales-chart-page-btn {
                padding: 6px 14px;
                font-size: 13px;
                color: #444;
                background: #e8e8e8;
                border: 1px solid #ccc;
                border-radius: 3px;
                cursor: default;
            }

            .sales-chart-page-btn:disabled {
                opacity: 0.75;
            }

            .sales-chart-page-info {
                font-size: 13px;
                color: #555;
            }
        </style>

    </div>
    <div class="card-header">
        <form method="GET" action="{{ route('report') }}" class="sales-report-filter-bar my-2 my-lg-1" id="salesReportForm">
            <label for="days">Select days:</label>
            <select name="days" id="days" class="form-control" onchange="handleDaysChange()">
                <option value="" disabled {{ is_null($days) ? 'selected' : '' }}>Select days</option>
                <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 days</option>
                <option value="28" {{ $days == 28 ? 'selected' : '' }}>Last 28 days</option>
                <option value="56" {{ $days == 56 ? 'selected' : '' }}>Last 56 days</option>
                <option value="all" {{ $days == 'all' ? 'selected' : '' }}>All Days</option>
            </select>

            <input type="date" name="searchDate" id="searchDate" value="{{ request()->input('searchDate') }}"
                class="form-control mx-2" onchange="handleDateChange()">
            <a href="{{ route('report') }}" class="btn btn-outline-secondary">Clear</a>
        </form>
        <div id="dailySalesLoading" class="alert alert-info py-2 mt-2 mb-0" role="status" style="display:none;">
            Loading sales report...
        </div>
        <small class="text-muted d-block mt-2">
            Active view:
            @if ($searchDate)
                {{ \Carbon\Carbon::parse($searchDate)->format('d.m.Y') }}
            @elseif ($days === 'all')
                All days
            @else
                Last {{ $days }} days
            @endif
        </small>
    </div>



    <!-- Drill-Down Table -->
    <div class="card">
        <div class="card-header">
            @if ($searchDate)
                Transactions for {{ \Carbon\Carbon::parse($searchDate)->format('d.m.Y') }}
            @elseif ($days && $days !== 'all')
                Transactions of Last {{ $days }} Days
            @else
                Transactions (all days)
            @endif
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="Reporttable" class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Sales.Date</th>
                            <th>Sales.Location</th>
                            <th colspan="2">No.Customers</th>
                            <th>No.Orders</th>
                            <th>No.Sold</th>
                            <th style="text-align: right">Price</th>
                            <th style="text-align: right">MPP Sales</th>
                            <th style="text-align: right">Fargerike Sales</th>
                            <th style="text-align: right">Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($salesData as $sale)
                        <tr class="mobile-row" data-date="{{ $sale->date }}">
                            <td>{{ \Carbon\Carbon::parse($sale->date)->format('d.m.y') }}</td>
                            <td>{{ $sale->location }}</td>
                            <td style="max-width: 250px; overflow-wrap: break-word; color: #007bff;">
                                {{ $sale->customer_count }}
                            </td>
                            <td></td>
                            <td style="">{{ number_format($sale->order_id_count, 0, '.', ',') }}
                            </td>
                            <td style="">
                                {{ number_format($sale->product_id_count, 0, '.', ',') }}
                            </td>
                            <td style="text-align: right;">{{ $sale->unit_price_avg !== null ? number_format($sale->unit_price_avg, 2, '.', ',') : '–' }}</td>
                            <td style="text-align: right;">
                                {{ number_format($sale->mpp_sales, 2, '.', ',') }}
                            </td>
                            <td style="text-align: right;">
                                {{ number_format($sale->fargerike_sales, 2, '.', ',') }}
                            </td>
                            <td style="text-align: right;">
                                {{ number_format($sale->total_products_price, 2, '.', ',') }}
                            </td>
                        </tr>


                        <tr class="hidden-row" style="display: none;color:brown" data-date="{{ $sale->date }}">
                        </tr>
                        <tr class="hidden-row1-products" style="display: none;">
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <strong>No sales found.</strong><br>
                                <span>Try another date or clear the current filters.</span>
                            </td>
                        </tr>
                        @endforelse

                    </tbody>
                </table>

                @if ($salesData->onFirstPage() && !$salesData->hasMorePages())
                    @if ($salesData->count() > 0)
                        <small class="text-muted d-block mt-3">
                            Showing all {{ $salesData->count() }} rows
                        </small>
                    @endif
                @else
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
                        <small class="text-muted mb-2">
                            Page {{ $salesData->currentPage() }} ({{ $salesData->count() }} rows shown)
                        </small>
                        <div class="btn-group mb-2" role="navigation" aria-label="Daily Sales pagination">
                            @if ($salesData->onFirstPage())
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Previous</button>
                            @else
                                <a class="btn btn-sm btn-outline-secondary" href="{{ $salesData->previousPageUrl() }}" rel="prev">Previous</a>
                            @endif

                            @if ($salesData->hasMorePages())
                                <a class="btn btn-sm btn-outline-secondary" href="{{ $salesData->nextPageUrl() }}" rel="next">Next</a>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Next</button>
                            @endif
                        </div>
                    </div>
                @endif

                <div id="crmModal" class="modal"
                    style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%);
     background:white; padding:20px; border:1px solid #ccc; z-index:9999;
     width:500px; max-height:200px; overflow-y:auto; border-radius:8px; box-shadow:0 0 15px rgba(0,0,0,0.2);">

                    <h5>Edit CRM ID</h5>
                    <input type="text" id="crmIdInput" class="form-control" placeholder="Enter CRM ID" />
                    <input type="hidden" id="crmCustomerId" />
                    <button id="saveCrmBtn" class="btn btn-success mt-2">Save</button>
                    <button id="cancelCrmBtn" class="btn btn-secondary mt-2">Cancel</button>
                </div>
                <div id="modalOverlay"
                    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:9998;">
                </div>


            </div>
        </div>
    </div>
</div>
<script>
    function handleDaysChange() {
        const daysDropdown = document.getElementById("days");
        const searchDateInput = document.getElementById("searchDate");

        // Clear the date input when a selection is made from 'Select days'
        if (daysDropdown.value) {
            searchDateInput.value = '';
        }

        // Submit the form automatically
        document.getElementById("salesReportForm").requestSubmit();
    }

    function clearDaysDropdown() {
        // When a date is selected, clear the 'Select days' dropdown
        document.getElementById("days").value = '';
    }

    function handleDateChange() {
        clearDaysDropdown();
        document.getElementById('salesReportForm').requestSubmit();
    }

    document.getElementById('salesReportForm').addEventListener('submit', function() {
        document.getElementById('dailySalesLoading').style.display = 'block';
    });
</script>

<script>
    var ajaxInProgress = false;
    //console.log("nanban");
    // Use jQuery instead of $ to avoid conflicts with other libraries
    jQuery(document).ready(function($) {

        // var customerId = {!! json_encode($customerId) !!};
        // if (customerId) {
        //     var route = '/sales-data/' + customerId;
        // } else {
        //     var route = "/sales-data";
        // }

        const chartSeries = @json($chartSeries ?? ['labels' => [], 'values' => []]);
        const chartLabelsRaw = chartSeries.labels || [];
        const sales = (chartSeries.values || []).map(function(v) {
            var n = Number(v);
            return isNaN(n) ? 0 : n;
        });

        /* X-axis: YYYY-MM-DD (matches reference layout) */
        var displayLabels = chartLabelsRaw.map(function(d) {
            return d ? String(d) : '';
        });

        function calculateRollingAverage(data, windowSize) {
            var rollingAverages = [];
            for (var i = 0; i < data.length; i++) {
                if (i < windowSize - 1) {
                    rollingAverages.push(null);
                } else {
                    var windowData = data.slice(i - windowSize + 1, i + 1);
                    var sum = windowData.reduce(function(acc, val) {
                        return acc + val;
                    }, 0);
                    rollingAverages.push(sum / windowSize);
                }
            }
            return rollingAverages;
        }

        /** Linear regression (least squares) on indices 0..n-1 vs daily sales — same units as bars. */
        function calculateTrendline(data) {
            var vals = data.map(function(v) {
                return Number(v);
            });
            var n = vals.length;
            if (n === 0) return [];
            if (n === 1) return [vals[0]];
            var sumX = 0,
                sumY = 0,
                sumXY = 0,
                sumX2 = 0;
            for (var i = 0; i < n; i++) {
                sumX += i;
                sumY += vals[i];
                sumXY += i * vals[i];
                sumX2 += i * i;
            }
            var denom = n * sumX2 - sumX * sumX;
            if (Math.abs(denom) < 1e-12) {
                var meanY = sumY / n;
                return vals.map(function() {
                    return meanY;
                });
            }
            var slope = (n * sumXY - sumX * sumY) / denom;
            var intercept = (sumY - slope * sumX) / n;
            return vals.map(function(_, idx) {
                return slope * idx + intercept;
            });
        }

        var rollingWindow = 7;
        var rollingAverage = calculateRollingAverage(sales, rollingWindow);
        var trendline = calculateTrendline(sales);

        if (typeof Chart !== 'undefined' && document.getElementById('salesChart')) {
            var ctx = document.getElementById('salesChart').getContext('2d');
            var canShowRolling = sales.length >= rollingWindow;
            var canShowTrend = sales.length >= 2;

            var gridColor = 'rgba(0, 0, 0, 0.08)';
            var tickColor = '#555';

            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: displayLabels,
                    datasets: [{
                            label: 'Total Sales',
                            data: sales,
                            backgroundColor: 'rgba(178, 235, 242, 0.92)',
                            borderColor: 'rgba(0, 137, 123, 0.45)',
                            borderWidth: 1,
                            borderRadius: 1,
                            categoryPercentage: 0.92,
                            barPercentage: 0.88,
                            order: 0
                        },
                        {
                            type: 'line',
                            label: '7 Day Rolling Average',
                            data: canShowRolling ? rollingAverage : sales.map(function() {
                                return null;
                            }),
                            backgroundColor: 'rgba(255, 183, 77, 0.18)',
                            borderColor: 'rgba(245, 124, 0, 1)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.2,
                            pointRadius: 3,
                            pointBackgroundColor: 'rgba(245, 124, 0, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 1,
                            pointHitRadius: 10,
                            spanGaps: false,
                            hidden: !canShowRolling,
                            order: 2
                        },
                        {
                            type: 'line',
                            label: 'Trendline',
                            data: canShowTrend ? trendline : sales.map(function() {
                                return null;
                            }),
                            backgroundColor: 'rgba(186, 104, 200, 0.22)',
                            borderColor: 'rgba(123, 31, 162, 0.95)',
                            borderWidth: 2,
                            borderDash: [8, 4],
                            fill: 'origin',
                            tension: 0,
                            pointRadius: 3.5,
                            pointBackgroundColor: 'rgba(123, 31, 162, 0.95)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 1,
                            pointHitRadius: 10,
                            spanGaps: false,
                            hidden: !canShowTrend,
                            order: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 8,
                            right: 8,
                            bottom: 4,
                            left: 4
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'center',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'rect',
                                padding: 18,
                                font: {
                                    size: 12
                                },
                                color: tickColor
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(items) {
                                    if (!items.length) return '';
                                    var idx = items[0].dataIndex;
                                    return chartLabelsRaw[idx] || items[0].label || '';
                                },
                                label: function(ctx) {
                                    var v = ctx.parsed.y;
                                    if (v === null || v === undefined || isNaN(v)) {
                                        return ctx.dataset.label + ': —';
                                    }
                                    return ctx.dataset.label + ': ' + Number(v).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: true,
                                color: gridColor,
                                drawBorder: true,
                                borderColor: 'rgba(0,0,0,0.12)'
                            },
                            ticks: {
                                color: tickColor,
                                font: {
                                    size: 11
                                },
                                maxRotation: 45,
                                minRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 32
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: gridColor,
                                drawBorder: true,
                                borderColor: 'rgba(0,0,0,0.12)'
                            },
                            ticks: {
                                color: tickColor,
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return Number(value).toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        } else {
            console.warn("Chart.js is not loaded, skipping chart initialization.");
        }

        $('.mobile-row').on('click', function() {

            if (ajaxInProgress) {
                return;
            }

            ajaxInProgress = true;

            $('.customer-details-row').remove();
            $('.customer-details-lastRow').remove();
            $('.hidden-row1').remove();

            var date = $(this).data('date');
            var location = $(this).children('td').eq(1).text().trim();
            var drillDownKey = date + '|' + location;
            // console.log(date, "date");
            var hiddenRow = $(this).siblings('.hidden-row1');
            var clickedRow = $(this); // Store reference to the clicked row


            if (!hiddenRow.length) {
                hiddenRow = $('<tr class="hidden-row"></tr>').insertAfter(clickedRow);
            }

            var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible') === drillDownKey;
            var customer_name = @json($customer_name);
            if (!isHiddenRowVisible && !customer_name) {
                console.log(!hiddenRow.hasClass('loaded'), "tttttt")

                $.ajax({
                    url: '/customers/' + date,
                    method: 'GET',
                    data: {
                        location: location
                    },
                    success: function(response) {
                        // Sort response array based on customer_name in ascending order
                        response.sort(function(a, b) {
                            var nameA = (a.customer_name || '')
                                .toUpperCase(); // Handle null values
                            var nameB = (b.customer_name || '')
                                .toUpperCase(); // Handle null values
                            if (nameA < nameB) {
                                return -1;
                            }
                            if (nameA > nameB) {
                                return 1;
                            }
                            return 0;
                        });

                        var customerRowsHtml =
                            ''; // Initialize variable to store HTML for each customer

                        response.forEach(function(name) {

                            // Format total_sales to display only two decimal places
                            var formattedTotalSales = parseFloat(name.total_price)
                                .toFixed(2);
                            var customerHtml =
                                ''; // Initialize variable to store HTML for each customer row
                            // console.log(name, date);

                            function formatNumber(number) {
                                return number.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }

                            if (!name) {
                                // Generate HTML for each cell
                                customerHtml +=
                                    '<td>' + '' + '</td>' +
                                    '<td>' + '' + '</td>' +
                                    '<td>customer not found</td>' +
                                    '<td>' + name.order_count + '</td>' +
                                    '<td>' + name.product_count + '</td>' +
                                    '<td>' + 0 + '</td>' +
                                    '<td>' + formattedTotalSales + '</td>';
                            } else {
                                // Generate HTML for each cell
                                customerHtml +=
                                    '<tr class="hidden-row1" style="background-color: #e9ecef;" data-date="' +
                                    date +
                                    '" data-location="' + name.location +
                                    '" data-customerid="' + name.customer_id +
                                    '">' +
                                    '<td></td><td></td>' +
                                    '<td style="padding: 4px 8px; vertical-align: middle;">' +
                                    '<a href="report/' + name.customer_id +
                                    '" onclick="handleCustomerGraphClick(' +
                                    name.customer_id + ')" target="_blank" style="text-decoration: underline; color: #007bff; font-weight: 500;">' +
                                    name.customer_id + '</a>' +
                                    '</td>' +
                                    '<td class="extract-products-cell" title="Click to view products" style="cursor: pointer; padding: 4px 8px; vertical-align: middle;">' +
                                    '<a href="report/' + name.customer_id +
                                    '" onclick="handleCustomerGraphClick(' +
                                    name.customer_id + ')" target="_blank" style="text-decoration: underline; color: #007bff; font-weight: 500; font-size: 14px;">' +
                                    name.customer_name + '</a>' +

                                    '<i class="fa fa-pencil crm-edit-icon" ' +
                                    'data-customerid="' + name.customer_id + '" ' +
                                    'data-crmid="' + (name.crm_id || '') + '" ' +
                                    'style="margin-left:8px; cursor:pointer; color:#007bff; font-size:12px; vertical-align: middle;" title="Edit CRM ID"></i>' +

                                    '<button class="crm-toggle-btn btn btn-sm" ' +
                                    'data-customerid="' + name.customer_id + '" ' +
                                    'data-crmexists="' + name.crm_exists + '" ' +
                                    'data-crmid="' + (name.crm_id || '') + '" ' +
                                    'style="margin-left:8px; padding: 1px 4px; background-color:' +
                                    (name.crm_id ? '#e60000' : '#ccc') +
                                    '; color:white; border:none; border-radius: 3px; font-size:11px; font-weight: bold;">' +
                                    (name.crm_id ? 'I CRM' : 'CRM') +
                                    '</button>' +
                                    '</td>' +

                                    '<td style="padding: 4px 8px; vertical-align: middle;">' +
                                    formatNumber(name.order_count) + '</td>' +
                                    '<td style="padding: 4px 8px; vertical-align: middle;">' +
                                    formatNumber(name.product_count) +
                                    '</td>' +
                                    '<td style="padding: 4px 8px; text-align: right; vertical-align: middle;">' +
                                    (name.unit_price_avg != null && !isNaN(Number(name.unit_price_avg)) ? formatNumber(Number(name.unit_price_avg)) : '–') + '</td>' +
                                    '<td style="padding: 4px 8px; text-align: right; vertical-align: middle;">' +
                                    formatNumber(name.total_price) + '</td>' +
                                    '</tr>' +
                                    '<tr class="hidden-row2" style="display: none;">' +
                                    '<td colspan="8"></td>' +
                                    '</tr>' +
                                    '<tr class="hidden-row2-products" style="display: none;">' +
                                    '<td colspan="8"></td>' +
                                    '</tr>';
                            }
                            // Append the customer HTML and skip the redundant <tr> wrapper
                            customerRowsHtml += customerHtml;
                        });

                        if (!customerRowsHtml) {
                            customerRowsHtml = '<tr>' +
                                '<td colspan="6"><h6>customer not found</h6></td>' +
                                '</tr>';
                        }

                        // Insert the hidden rows below the clicked row
                        $('.hidden-row').not(hiddenRow).remove();
                        $(customerRowsHtml).insertAfter(
                            clickedRow); // Use the clickedRow reference here

                        // Mark hidden row as loaded and show it
                        hiddenRow.addClass('loaded').show();
                        sessionStorage.setItem('hiddenRowVisible', drillDownKey);
                        ajaxInProgress = false;
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                        ajaxInProgress = false;
                    }
                });
            } else {
                // Hide the hidden row
                // Toggle the visibility of the hidden row
                console.log('Hidden row is already loaded. Toggling visibility.');
                hiddenRow.toggle();
                sessionStorage.setItem('hiddenRowVisible', '');
                ajaxInProgress = false;
            }

        });

        $(document).on('click', '.crm-toggle-btn, .crm-edit-icon', function() {
            const button = $(this);
            const customerId = button.data('customerid');
            const crmId = button.data('crmid') || '';

            $('#crmCustomerId').val(customerId);
            $('#crmIdInput').val(crmId);
            $('#crmModal').show();
            $('#modalOverlay').show();
        });

        // Cancel button logic
        $('#cancelCrmBtn').on('click', function() {
            $('#crmModal').hide();
            $('#modalOverlay').hide();
        });

        // Save button logic
        $('#saveCrmBtn').on('click', function() {
            const customerId = $('#crmCustomerId').val();
            const newCrmId = $('#crmIdInput').val().trim();

            $.ajax({
                url: '/update-crm-id',
                method: 'POST',
                data: {
                    customer_id: customerId,
                    crm_id: newCrmId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function() {
                    const button = $('button.crm-toggle-btn[data-customerid="' + customerId +
                        '"]');
                    const icon = $('.crm-edit-icon[data-customerid="' + customerId + '"]');

                    // Update button UI
                    button.data('crmid', newCrmId);
                    icon.data('crmid', newCrmId);

                    if (newCrmId) {
                        button.css('background-color', 'red').text('I CRM');
                    } else {
                        button.css('background-color', '#ccc').text('CRM');
                    }

                    $('#crmModal').hide();
                    $('#modalOverlay').hide();
                },
                error: function() {
                    alert('Failed to update CRM ID.');
                }
            });
        });


        // Remove the extract-products-date-cell logic as it's no longer needed and caused confusion

        // Click event handler for extracting products directly from customer
        $(document).on('click', '.extract-products-cell', function(e) {
            e.stopPropagation(); // Prevent the .hidden-row1 click event

            if (ajaxInProgress) {
                return;
            }

            $('.customer-details-lastRow').hide();
            $('.customer-details-row').remove();
            $('.customer-products-row').remove(); // remove existing product rows generated this way

            var parentRow = $(this).closest('.hidden-row1');
            var date = parentRow.data('date');
            var location = parentRow.data('location');
            var customerId = parentRow.data('customerid');
            var hiddenRow = parentRow.nextAll('.hidden-row2-products').first();

            // Hide all other hidden product rows
            $('.hidden-row2-products').not(hiddenRow).hide().removeClass('loaded');
            // Hide hidden-row2 open order rows
            $('.hidden-row2').hide().removeClass('loaded');

            var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible2Products') == customerId;

            if (!isHiddenRowVisible) {
                ajaxInProgress = true;
                $.ajax({
                    url: '/customer/products-by-date',
                    method: 'GET',
                    data: {
                        date: date,
                        location: location,
                        customer_id: customerId
                    },
                    success: function(response) {
                        // Remove any existing products generated below this row
                        hiddenRow.siblings('.customer-products-row').remove();

                        response.forEach(function(product) {
                            if (product) {
                                function formatNumber(number) {
                                    return number.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                                var productHtml =
                                    '<tr class="customer-products-row alert alert-info">' +
                                    '<td><h6></h6></td>' +
                                    '<td><h6></h6></td>' +
                                    '<td><h6><a href="report/' + product.product_id + '/product" target="_blank">' + product.product_id + '</a></h6></td>' +
                                    '<td><h6>' + product.product_name + '</h6></td>' +
                                    '<td><h6></h6></td>' +
                                    '<td><h6 style="">' + formatNumber(product.count) + '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' + formatNumber(product.price) + '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' + formatNumber(product.total_price) + '</h6></td>' +
                                    '</tr>';

                                hiddenRow.after(productHtml);
                            }
                        });

                        hiddenRow.addClass('loaded').show();
                        sessionStorage.setItem('hiddenRowVisible2Products', customerId);
                        sessionStorage.setItem('hiddenRowVisible2', ''); // clear orders cache since we closed them
                        ajaxInProgress = false;
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                        ajaxInProgress = false;
                    }
                });
            } else {
                hiddenRow.toggle();
                hiddenRow.siblings('.customer-products-row').toggle();
                sessionStorage.setItem('hiddenRowVisible2Products', '');
                ajaxInProgress = false;
            }
        });

        // Click event handler for hidden-row
        $(document).on('click', '.hidden-row1', function() {
            if (ajaxInProgress) {
                return;
            }
            $('.customer-details-lastRow').hide();
            $('.customer-details-row').remove();

            var date = $(this).data('date');
            var location = $(this).data('location');
            var customerId = $(this).data('customerid');
            var hiddenRow = $(this).next('.hidden-row2');

            // Hide all other hidden rows
            $('.hidden-row2 ').not(hiddenRow).hide().removeClass('loaded');

            var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible2') == customerId;

            // Check if details for hidden-row2 are already loaded
            if (!isHiddenRowVisible) {
                console.log("loaded2")
                $.ajax({
                    url: '/customer/details',
                    method: 'GET',
                    data: {
                        date: date,
                        location: location,
                        customer_id: customerId
                    },
                    success: function(response) {
                        // Remove any existing customer details rows
                        hiddenRow.siblings('.customer-details-row').remove();

                        // Append each customer's details as individual rows
                        response.forEach(function(customer) {
                            if (customer) {

                                function formatNumber(number) {
                                    return number.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                                var customerHtml =
                                    '<tr class="customer-details-row alert alert-primary" data-orderid="' +
                                    customer.orderid +
                                    '" data-customerid="' + customerId +
                                    '" data-name="' + customer.customer_name +
                                    '">' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' + customerId + '</h6></td>' +
                                    '<td><h6>' + customer.customer_name +
                                    '</h6></td>' +
                                    '<td><h6 style="">' +
                                    String(customer.orderid) + '</h6></td>' +
                                    '<td><h6 style="">' +
                                    formatNumber(customer.product_count) +
                                    '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    (customer.unit_price != null && !isNaN(Number(customer.unit_price)) ? formatNumber(Number(customer.unit_price)) : '–') + '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    formatNumber(customer.total_product_count) +
                                    '</h6></td>' +
                                    '</tr>' +
                                    '<tr class="hidden-row3" style="display: none; color: yellow;">' +
                                    '</tr>';

                                hiddenRow.after(customerHtml);
                            }
                        });

                        // Mark hiddenRow as loaded
                        hiddenRow.addClass('loaded').show();
                        sessionStorage.setItem('hiddenRowVisible2', customerId);
                        ajaxInProgress = false;
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                        ajaxInProgress = false;
                    }
                });
            } else {
                console.log("loaded2-remove")
                hiddenRow.toggle();
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

            console.log(customerId, orderid, "rrrr")
            // Hide all other hidden rows
            $('.hidden-row3 ').not(hiddenRow).hide().removeClass('loaded');

            var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible3') == orderid;

            // Check if details for hidden-row3 are already loaded
            if (!isHiddenRowVisible) {
                console.log('loaded3')
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

                        response.forEach(function(customer) {

                            if (customer) {
                                function formatNumber(number) {
                                    return number.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                                console.log("no data", customer);
                                var customerHtml =
                                    '<tr class="customer-details-lastRow alert alert-success">' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' +
                                    '<a href="report/' + customer.product_id +
                                    '/product" target="_blank">' +
                                    customer.product_id + '</a>' + '</h6></td>' +
                                    '<td><h6>' + customer.product_name +
                                    '</h6></td>' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6 style="">' +
                                    formatNumber(customer.count) + '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    formatNumber(customer.price) + '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    formatNumber(customer.total_price) +
                                    '</h6></td>' +
                                    '</tr>';

                                hiddenRow.after(customerHtml);
                            } else {
                                console.log("no data");
                            }
                        });

                        // Mark hiddenRow as loaded
                        hiddenRow.addClass('loaded').show();
                        sessionStorage.setItem('hiddenRowVisible3', orderid);
                        ajaxInProgress = false;
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                        ajaxInProgress = false;
                    }
                });
            } else {
                console.log('Hidden row is already loaded3. Toggling visibility.');
                hiddenRow.toggle();
                sessionStorage.setItem('hiddenRowVisible3', '');
                ajaxInProgress = false;
            }
        });

    });


    function handleCustomerGraphClick(customerId) {
        console.log("it is clicked")
        // Define the URL with the customer ID
        var url = '/report/' + customerId;

        $.ajax({
            url: url,
            method: 'POST',
            success: function(data) {
                console.log(data, "data")


            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch sales data:', error);
            }
        });

    }
</script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"
    integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous">
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"
    integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous">
</script>
@endsection
