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

        <div class="card card-body">
            <canvas id="salesChart" width="400" height="100"></canvas>
            <div class="pagination-controls">
                <button id="prevPage" disabled>Previous</button>
                <span id="pageInfo"></span>
                <button id="nextPage">Next</button>
            </div>
        </div>

    </div>
    <div class="card-header">
        <form method="GET" action="{{ route('report') }}" class="form-inline my-2 my-lg-1" id="salesReportForm">
            <label for="days">Select days:</label>
            <select name="days" id="days" onchange="handleDaysChange()">
                <option value="" disabled {{ is_null($days) ? 'selected' : '' }}>Select days</option>
                <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 days</option>
                <option value="28" {{ $days == 28 ? 'selected' : '' }}>Last 28 days</option>
                <option value="56" {{ $days == 56 ? 'selected' : '' }}>Last 56 days</option>
                <option value="all" {{ $days == 'all' ? 'selected' : '' }}>All Days</option>
            </select>

            <input type="date" name="searchDate" id="searchDate" value="{{ request()->input('searchDate') }}"
                class="form-control mx-2" onchange="clearDaysDropdown()">
            <button type="submit" class="btn btn-secondary">Search by Date</button>
        </form>
    </div>



    <!-- Drill-Down Table -->
    <div class="card">
        <div class="card-header">
            Transactions of Last {{ $days }} Days
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
                            <th style="text-align: right">Sum.Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($salesData as $sale)
                        <tr class="mobile-row" data-date="{{ $sale->date }}">
                            <td>{{ \Carbon\Carbon::parse($sale->date)->format('d.m.y') }}</td>
                            <td>{{ $sale->location }}</td>
                            <td style="max-width: 250px; overflow-wrap: break-word; color: #007bff;">
                                {{ $sale->customer_count }}
                            </td>
                            <td></td>
                            <td style="">{{ number_format($sale->order_id_count, 0, ',', ' ') }}
                            </td>
                            <td style="">
                                {{ number_format($sale->product_id_count, 0, ',', ' ') }}
                            </td>
                            <td style="text-align: right;">{{ number_format(0, 2, ',', ' ') }}</td>
                            <td style="text-align: right;">
                                {{ number_format($sale->total_products_price, 2, ',', ' ') }}
                            </td>
                        </tr>


                        <tr class="hidden-row" style="display: none;color:brown" data-date="{{ $sale->date }}">
                        </tr>
                        <tr class="hidden-row1-products" style="display: none;">
                        </tr>
                        @endforeach

                    </tbody>
                </table>

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
        document.getElementById("salesReportForm").submit();
    }

    function clearDaysDropdown() {
        // When a date is selected, clear the 'Select days' dropdown
        document.getElementById("days").value = '';
    }
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

        let currentPage = 0;
        const pageSize = 10; // Adjust the number of items per page if needed

        const salesData1 = @json($salesData1);
        const labels = salesData1.map(item => item.date);
        const sales = salesData1.map(item => Number(item.total_sales));

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
            const sumX = data.reduce((acc, val, idx) => acc + idx, 0);
            const sumY = data.reduce((acc, val) => acc + val, 0);
            const sumXY = data.reduce((acc, val, idx) => acc + idx * val, 0);
            const sumX2 = data.reduce((acc, val, idx) => acc + idx * idx, 0);

            const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
            const intercept = (sumY - slope * sumX) / n;

            return data.map((val, idx) => slope * idx + intercept);
        }

        // Calculate the rolling average and trendline for the full dataset
        const rollingAverage = calculateRollingAverage(sales, 7); // 7-day rolling average
        const trendline = calculateTrendline(sales);

        function paginateData(data, page, perPage) {
            const start = page * perPage;
            return data.slice(start, start + perPage);
        }

        function updateChart(chart, page) {
            const paginatedLabels = paginateData(labels, page, pageSize);
            const paginatedSales = paginateData(sales, page, pageSize);
            const paginatedRollingAvg = paginateData(rollingAverage, page, pageSize);
            const paginatedTrendline = paginateData(trendline, page, pageSize);

            chart.data.labels = paginatedLabels;
            chart.data.datasets[0].data = paginatedSales;
            chart.data.datasets[1].data = paginatedRollingAvg;
            chart.data.datasets[2].data = paginatedTrendline;

            chart.update();

            $("#pageInfo").text(`Page ${page + 1} of ${Math.ceil(salesData1.length / pageSize)}`);
            $("#prevPage").prop("disabled", page === 0);
            $("#nextPage").prop("disabled", page >= Math.ceil(salesData1.length / pageSize) - 1);
        }

        if (typeof Chart !== 'undefined' && document.getElementById('salesChart')) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
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
                            label: '7-Day Rolling Average',
                            data: [],
                            backgroundColor: 'rgba(255, 159, 64, 0.2)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1,
                            borderDash: [5, 5]
                        },
                        {
                            label: 'Trendline',
                            data: [],
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1,
                            borderDash: [10, 5]
                        }
                    ]
                },
                options: {
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day'
                            }
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Update the chart with the initial page
            updateChart(chart, currentPage);

            // Pagination controls
            $('#prevPage').click(function() {
                if (currentPage > 0) {
                    currentPage--;
                    updateChart(chart, currentPage);
                }
            });

            $('#nextPage').click(function() {
                if (currentPage < Math.ceil(salesData1.length / pageSize) - 1) {
                    currentPage++;
                    updateChart(chart, currentPage);
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
            // console.log(date, "date");
            var hiddenRow = $(this).siblings('.hidden-row1');
            var clickedRow = $(this); // Store reference to the clicked row


            if (!hiddenRow.length) {
                hiddenRow = $('<tr class="hidden-row"></tr>').insertAfter(clickedRow);
            }

            var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible') === date;
            var customer_name = @json($customer_name);
            if (!isHiddenRowVisible && !customer_name) {
                console.log(!hiddenRow.hasClass('loaded'), "tttttt")

                $.ajax({
                    url: '/customers/' + date,
                    method: 'GET',
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
                                return number.toLocaleString('fr-FR', {
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
                                    '<button class="bigin-btn btn btn-sm" ' +
                                    'data-customerid="' + name.customer_id + '" ' +
                                    'data-crmid="' + name.crm_id + '" ' +
                                    'style="margin-left:6px; padding: 1px 4px; background-color:#1a73e8; color:white; border:none; border-radius: 3px; font-size:11px; font-weight: bold;">' +
                                    'BIGIN</button>' +
                                    '</td>' +

                                    '<td style="padding: 4px 8px; vertical-align: middle;">' +
                                    formatNumber(name.order_count) + '</td>' +
                                    '<td style="padding: 4px 8px; vertical-align: middle;">' +
                                    formatNumber(name.product_count) +
                                    '</td>' +
                                    '<td style="padding: 4px 8px; text-align: right; vertical-align: middle;">' +
                                    formatNumber(0) + '</td>' +
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
                        sessionStorage.setItem('hiddenRowVisible', date);
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



        $(document).on('click', '.bigin-btn', function() {
            const customerId = $(this).data('customerid');

            $.ajax({
                url: '/get-latest-crm-id',
                method: 'POST',
                data: {
                    customer_id: customerId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    const crmId = response.crm_id;

                    if (crmId) {
                        const fullCrmLink =
                            'https://bigin.zoho.eu/bigin/org20096296201/Home#/companies/' +
                            crmId + '?section=child_accounts';
                        window.open(fullCrmLink, '_blank');
                    } else {
                        window.open(
                            'https://forms.zohopublic.eu/fabconcept/form/BASICFORM/formperma/oQ-8YWi6BLwQUOd7YtrqgJyqFVhpW_KJBCL6eA8QapE',
                            '_blank'
                        );
                    }
                },
                error: function() {
                    alert('Failed to fetch CRM ID from the server.');
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
                                    return number.toLocaleString('fr-FR', {
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
                                    return number.toLocaleString('fr-FR', {
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
                                    formatNumber(customer.orderid) + '</h6></td>' +
                                    '<td><h6 style="">' +
                                    formatNumber(customer.product_count) +
                                    '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    formatNumber(0) + '</h6></td>' +
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
                                    return number.toLocaleString('fr-FR', {
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