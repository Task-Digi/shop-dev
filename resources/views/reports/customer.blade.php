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
    @include('layouts.nav_bar')
    <div class="card"> 
        <h4>Customer-Report View</h4>
        <div class="card-body" style="">
            {{-- <a href="/Report_view" class="btn btn-primary"> Back to DateView </a> --}}

            <a class="btn btn-primary" data-toggle="collapse" href="#collapseExample" type="button" aria-expanded="false"
                aria-controls="collapseExample">
                Sales Graph-Customer View
            </a>
            <a href="{{ route('report-customer', ['customerId' => 'all']) }}" class="btn btn-primary"
                id="allCustomersButton">All Customers</a><br><br>

            <form method="GET" action="{{ route('report-customer', ['customerId' => request()->route('customerId')]) }}"
                id="searchForm">
                <!-- Search Input -->
                <input type="text" name="search" id="searchInput" value="{{ request()->input('search') }}"
                    placeholder="Search Customer Name" class="form-control">

                <!-- Days Filter -->
                <label for="days">Select days:</label>
                <select name="days" id="days" onchange="document.getElementById('searchForm').submit()">
                    <option value="7" {{ $days == 7 ? 'selected' : '' }}>Last 7 days</option>
                    <option value="28" {{ $days == 28 ? 'selected' : '' }}>Last 28 days</option>
                    <option value="56" {{ $days == 56 ? 'selected' : '' }}>Last 56 days</option>
                    <option value="0" {{ $days == 0 ? 'selected' : '' }}>All days</option>
                </select>

                <button type="submit" class="btn btn-secondary">Search</button>
            </form>
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
                Transactions of Last {{ $days }} days
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="Reporttable" class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th colspan="2">Customer Name</th>
                                <!-- <th>bankrupt</th> -->
                                <th>Sales.Date</th>
                                <th>Sales.Location</th>
                                <th>No.Orders</th>
                                <th>No.Sold</th>
                                <th style="text-align: right">Price</th>
                                <th style="text-align: right">Sum.Sales</th>
  
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($salesData as $sale)
                                <tr class="mobile-row" data-date="">
                                    <td data-customer_id="{{ $sale->customer_id }}">{{ $sale->customer_name }} @if(($sale->KS_exists ?? 0) == 1)
        <span class="badge badge-pill badge-danger">KS</span>
    @endif</td>
                                    <!-- <td>
                                        <button class="ks-button" 
                                                data-customer-id="{{ $sale->customer_id }}"
                                                data-customer-name="{{ $sale->customer_name }}"
                                                data-ks-status="{{ $sale->KS_exists ?? 0 }}"
                                                style="padding: 5px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;"
                                                class="ks-button ">
                                            KS
                                        </button>
                                    </td> -->
                                    <td></td>
                                    <td></td>
                                    <td data-location="{{ $sale->location }}">{{ $sale->location }}</td>
                                    <td style="">{{ number_format($sale->order_id_count, 0, ',', ' ') }}
                                    </td>
                                    <td style="">
                                        {{ number_format($sale->total_products_sold, 0, ',', ' ') }}</td>
                                    <td style="text-align: right;">{{ number_format(0, 0, ',', ' ') }}</td>
                                    <td style="text-align: right;">
                                        {{ number_format($sale->total_sales, 2, ',', ' ') }}</td>
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


    <script>
        document.getElementById('searchForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent the form from submitting immediately

            var searchValue = document.getElementById('searchInput').value.trim();

            // Update the form action URL with the search value
            if (searchValue) {
                this.action = '/report/' + encodeURIComponent(searchValue);
            }

            // Submit the form
            this.submit();
        });
    </script>
    <script>
        var ajaxInProgress = false;
        // console.log("nanban");
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
            const salesData1 = {!! json_encode($salesData1) !!};
            const labels = salesData1.map(item => item.customer_name); // Customer names for x-axis
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

                $("#pageInfo").text(`Page ${page + 1} of ${Math.ceil(salesData1.length / itemsPerPage)}`);
                $("#prevPage").prop("disabled", page === 0);
                $("#nextPage").prop("disabled", page >= Math.ceil(salesData1.length / itemsPerPage) - 1);
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
                if (currentPage < Math.ceil(salesData1.length / itemsPerPage) - 1) {
                    currentPage++;
                    updateChart(chart, currentPage);
                }
            });

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
            // console.log(location,"location");
            var customerId = $(this).find('td[data-customer_id]').data('customer_id');
            var hiddenRow = $(this).next('.hidden-row2');

            // Hide all other hidden rows
            $('.hidden-row2 ').not(hiddenRow).hide().removeClass('loaded');

            var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible2') == customerId;

            if (!isHiddenRowVisible) {
                $.ajax({
                    url: '/CustomerReport/details',
                    method: 'GET',
                    data: {
                        // date: date,
                        location: location,
                        customer_id: customerId,
                        days: {{ $days }}
                    },
                    success: function(response) {
                        console.log(response, "response");
                        // Remove any existing customer details rows
                        hiddenRow.siblings('.customer-details-row').remove();

                        // Append each customer's details as individual rows
                        response.forEach(function(customer) {
                            console.log(customer, "customer");
                            if (customer) {

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
                                var customerHtml =
                                    '<tr class="customer-details-row alert alert-primary" data-orderid="' +
                                    customer.orderid +
                                    '" data-customerid="' + customerId +
                                    '" data-name="' + customer.customer_name +
                                    '">' +
                                    '<td><h6>' + customer.customer_id +
                                    '</h6></td>' +
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' + formatDate(customer.sales_date) +
                                    '</h6></td>' +
                                    '<td><h6>' + customer.location + '</h6></td>' +

                                    '<td><h6 style="">' +
                                    customer.orderid + '</h6></td>' +
                                    '<td><h6 style="">' +
                                    customer.total_products_sold +
                                    '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' + 0 +
                                    '</h6></td>' +
                                    '<td><h6 style="text-align: right;">' +
                                    customer.total_sales +
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
                                    '<td><h6>' + '' + '</h6></td>' +
                                    '<td><h6>' + 'product --' + customer.product_name +
                                    '</h6></td>' +
                                    '<td><h6>' + 'id --' + customer.product_id + '</h6></td>' +
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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"
        integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"
        integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous">
    </script>

    <style>
        /* CSS for KS Button - Default (Inactive) State */
        .ks-button.ks-inactive {
            background-color: #d3d3d3;  /* Light gray */
            color: #333;
            transition: all 0.3s ease;
        }

        .ks-button.ks-inactive:hover {
            background-color: #b0b0b0;  /* Darker gray on hover */
        }

        /* CSS for KS Button - Active State */
        .ks-button.ks-active {
            background-color: #038221ff;  /* Green */
            color: white;
            transition: all 0.3s ease;
        }

        .ks-button.ks-active:hover {
            background-color: #026017ff;  /* Darker green on hover */
        }
    </style>

    <script>
        // JavaScript to handle KS button clicks
        document.addEventListener('DOMContentLoaded', function() {
            // Select all KS buttons
            const ksButtons = document.querySelectorAll('.ks-button');

            ksButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const customerId = this.getAttribute('data-customer-id');
                    const customerName = this.getAttribute('data-customer-name');
                    const currentStatus = parseInt(this.getAttribute('data-ks-status'));
                    
                    // Toggle the status (0 to 1, or 1 to 0)
                    const newStatus = currentStatus === 0 ? 1 : 0;

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
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the button status and color
                            this.setAttribute('data-ks-status', newStatus);
                            this.classList.remove('ks-inactive', 'ks-active');
                            this.classList.add(newStatus === 1 ? 'ks-active' : 'ks-inactive');
                            
                            console.log('KS status updated successfully for customer: ' + customerName);
                        } else {
                            alert('Error updating KS status: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating KS status');
                    });
                });
            });
        });
    </script>
@endsection
