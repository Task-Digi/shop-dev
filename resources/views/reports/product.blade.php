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

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
            integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
        <!-- Bootstrap JavaScript -->
        {{-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous">
        </script> --}}
    </head>

    <div class="card">
        @include('layouts.nav_bar')
        <h4>Product-Report View</h4>

        <div class="card-body row">
            <div class="col-4">
                <form method="GET" action="{{ route('product.report', ['productid' => 'ProductName']) }}">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Product Name">
                    <button type="submit" class="btn btn-secondary">Search</button>
                </form>

            </div>
            <div class="col-2">
                <a href="/report/all/product" class="btn btn-primary">All Products</a>
            </div>
            <div class="col-2">
                <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#collapseExample"
                    aria-expanded="false" aria-controls="collapseExample">
                    Sales Graph View
                </button>
            </div>
        </div>
        <div class="container">
            <div class="collapse" id="collapseExample">
                <div class="card card-body">
                    <canvas id="salesChart" width="400" height="100"></canvas>
                    <div class="pagination-controls">
                        <button id="prevPage" disabled>Previous</button>
                        <span id="pageInfo"></span>
                        <button id="nextPage">Next</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Drill-Down Table -->
        <div class="card">
            <div class="card-header">
                <form method="GET" action="{{ route('product.report', ['productid' => $productid]) }}" id="daysForm">
                    <input type="hidden" name="search" value="{{ request('search') }}"> <!-- Preserve search keyword -->
                    <label for="days">Select days:</label>
                    <select name="days" id="days" onchange="document.getElementById('daysForm').submit()">
                        <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 days</option>
                        <option value="30" {{ $days == 30 ? 'selected' : '' }}>Last 30 days</option>
                        <option value="90" {{ $days == 90 ? 'selected' : '' }}>Last 90 days</option>
                        <option value="all" {{ $days === 'all' ? 'selected' : '' }}>All Days</option>
                        <!-- New All Days option -->
                    </select>
                </form>
                Transactions of Last {{ $days }} days
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="Reporttable" class="table table">
                        <thead>
                            <tr>
                                <th>ProductName</th>
                                <th>Sales.Date</th>
                                <th>Sales.Location</th>
                                <th>No.Customers</th>
                                <th>No.Orders</th>
                                <th>No.Sold</th>
                                <th style="text-align: right;">Price</th>
                                <th style="text-align: right;"> Sum.Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($salesData as $sale)
                                <tr class="mobile-row" data-date="">
                                    <td data-product_id="{{ $sale->product_id }}">{{ $sale->product_name }}</td>
                                    <td></td>
                                    <td data-location="{{ $sale->location }}">{{ $sale->location }}</td>
                                    <td>{{ number_format($sale->customer_count, 0, ',', ' ') }}</td>
                                    <td style="">{{ number_format($sale->order_id_count, 0, ',', ' ') }}
                                    </td>
                                    <td style="">
                                        {{ number_format($sale->product_id_count, 0, ',', ' ') }}</td>
                                    <td style="text-align: right;">{{ number_format(0, 0, ',', ' ') }}</td>
                                    <td style="text-align: right;">
                                        {{ number_format($sale->total_products_price, 0, ',', ' ') }} </td>
                                </tr>
                                <tr class="hidden-row2" style="display: none;color:green">
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('searchForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent the form from submitting immediately

            var searchValue = document.getElementById('searchInput').value.trim();

            // Update the form action URL with the search value
            if (searchValue) {
                this.action = '/report/' + encodeURIComponent(searchValue) + '/product';
            }

            // Submit the form
            this.submit();
        });
    </script>
    <script>
        $(document).on('click', '.mobile-row', function() {
            // console.log("gdgdgdg")

            if (ajaxInProgress) {
                return;
            };

            ajaxInProgress = true;

            $('.customer-details-row').remove();
             $('.customer-details-lastRow').hide();

            // var date = $(this).data('date');
            var location = $(this).find('td[data-location]').data('location');
            // console.log(location, "location");
            var product_id = $(this).find('td[data-product_id]').data('product_id');
            var hiddenRow = $(this).next('.hidden-row2');

            // Hide all other hidden rows
            $('.hidden-row2 ').not(hiddenRow).hide().removeClass('loaded');

            var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible2') == product_id;

            // console.log()
            if (!isHiddenRowVisible) {
                $.ajax({
                    url: '/product/details',
                    method: 'GET',
                    data: {
                        // date: date,
                        location: location,
                        product_id: product_id,
                        days: $('#days').val()
                    },
                    success: function(response) {

                        response.forEach(function(product) {
                            if (product) {

                                // function formatNumber(number) {
                                //     return number.toLocaleString('fr-FR', {
                                //         minimumFractionDigits: 2,
                                //         maximumFractionDigits: 2
                                //     });
                                // }
                                function formatDate(dateString) {
                                    const date = new Date(dateString);
                                    // Ensure date is valid
                                    if (isNaN(date))
                                        return dateString; // Return original string if invalid

                                    // Format to DD.MM.YY
                                    const day = String(date.getDate()).padStart(2,
                                        '0'); // Get day and pad with 0 if needed
                                    const month = String(date.getMonth() + 1).padStart(2,
                                        '0'); // Get month (0-based)
                                    const year = String(date.getFullYear()).slice(-
                                        2); // Get last 2 digits of year

                                    return `${day}.${month}.${year}`;
                                }
                                var productHtml =
                                    '<tr class="customer-details-row alert alert-primary" data-date="' +
                                    product.date +
                                    '" data-product_id="' + product_id +
                                    '" data-location="' + product.location +
                                    '">' +
                                    '<td><h6>' + product.product_id + '</h6></td>' +
                                    '<td><h6>' + formatDate(product.date) + '</h6></td>' +
                                    '<td><h6>' + product.location + '</h6></td>' +
                                    '<td><h6>' + product.customer_count +
                                    '</h6></td>' +
                                    '<td><h6 style="">' +
                                    (product.order_id_count) + '</h6></td>' +
                                    '<td><h6 style="">' +
                                    (product.product_quantity_sold) +
                                    '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    (0) + '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    (product.total_sales) +
                                    '</h6></td>' +
                                    '</tr>' +
                                    '<tr class="hidden-row3" style="display: none; color: yellow;">' +
                                    '</tr>';

                                hiddenRow.after(productHtml);
                            }
                        });

                        // Mark hiddenRow as loaded
                        hiddenRow.addClass('loaded').show();
                        sessionStorage.setItem('hiddenRowVisible2', product_id);
                        ajaxInProgress = false;
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                        ajaxInProgress = false;
                    }
                });
            } else {
                //  console.log("loaded2-remove")
                hiddenRow.toggle();
                sessionStorage.setItem('hiddenRowVisible2', '');
                ajaxInProgress = false;
            }
        });
        $(document).on('click', '.customer-details-row', function() {
            if (ajaxInProgress) {
                return;
            }

            $('.customer-details-lastRow').hide();
            // var date = $(this).data('date');
            var date = $(this).data('date');
            var productId = $(this).data('product_id');
            var location = $(this).data('location');
            var hiddenRow = $(this).next('.hidden-row3');

            // Hide all other hidden rows
            $('.hidden-row3 ').not(hiddenRow).hide().removeClass('loaded');

            var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible3') == productId;

            // Check if details for hidden-row3 are already loaded
            if (!isHiddenRowVisible) {
                console.log(location,'location')
                $.ajax({
                    url: '/product/finaldetails',
                    method: 'GET',
                    data: {
                        date: date,
                        location: location,
                        productId: productId
                    },
                    success: function(response) {

                        // Remove any existing customer details rows
                        hiddenRow.siblings('.customer-details-lastRow').remove();

                        response.forEach(function(product) {

                            if (product) {
                                function formatNumber(number) {
                                    return number.toLocaleString('fr-FR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                                console.log("no data", product);
                                var customerHtml =
                                    '<tr class="customer-details-lastRow alert alert-success">' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' + product.customer_name +
                                    '</h6></td>' +
                                    '<td><h6>' +  product.orderid + '</h6></td>' +
                                    '<td><h6 style="">' +
                                        product.total_quantity_sold + '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    0 + '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                        product.total_sales +
                                    '</h6></td>' +
                                    '</tr>';

                                hiddenRow.after(customerHtml);
                            } else {
                                console.log("no data");
                            }
                        });

                        // Mark hiddenRow as loaded
                        hiddenRow.addClass('loaded').show();
                        sessionStorage.setItem('hiddenRowVisible3', productId);
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
    </script>
    <script>
        var ajaxInProgress = false;
        // Use jQuery instead of $ to avoid conflicts with other libraries
        jQuery(document).ready(function($) {
            let currentPage = 0;
            const itemsPerPage = 10; // Adjust as needed

            // Initial dataset setup
            const salesData = {!! json_encode($salesData1) !!};
            const labels = salesData.map(item => item.product_name);
            const sales = salesData.map(item => Number(item.total_sales));

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
                const sumX = data.reduce((acc, val, idx) => acc + idx, 0);
                const sumY = data.reduce((acc, val) => acc + val, 0);
                const sumXY = data.reduce((acc, val, idx) => acc + idx * val, 0);
                const sumX2 = data.reduce((acc, val, idx) => acc + idx * idx, 0);

                const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
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

                $("#pageInfo").text(`Page ${page + 1} of ${Math.ceil(salesData.length / itemsPerPage)}`);
                $("#prevPage").prop("disabled", page === 0);
                $("#nextPage").prop("disabled", page >= Math.ceil(salesData.length / itemsPerPage) - 1);
            }

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
                            label: 'Rolling Average',
                            data: [],
                            backgroundColor: 'rgba(255, 159, 64, 0.2)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1,
                            type: 'line',
                            borderDash: [5, 5]
                        },
                        {
                            label: 'Trendline',
                            data: [],
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1,
                            type: 'line',
                            borderDash: [10, 5]
                        }
                    ]
                },
                options: {
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Product Name'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Sales'
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
                                    return `Total Sales: $${context.raw.toFixed(2)}`;
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
                if (currentPage < Math.ceil(salesData.length / itemsPerPage) - 1) {
                    currentPage++;
                    updateChart(chart, currentPage);
                }
            });

        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"
        integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"
        integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous">
    </script>
@endsection
