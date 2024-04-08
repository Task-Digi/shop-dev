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
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous">
        </script>
    </head>

    <div class="container">
        <h1>Day-Based Report</h1>

        <!-- Date Selector -->
        <div class="form-group ">
            <label for="datepicker">Select a Date:</label>
            <input type="date" id="datepicker" class="form-control">
        </div>

        <!-- Summary Information -->
        <div class="card mb-3">
            <div class="card-header">Summary Information</div>
            <div class="card-body">
                <!-- Display summary information here -->
            </div>
        </div>

        <!-- Drill-Down Table -->
        <div class="card">
            <div class="card-header">Transactions for Selected Day</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Payment</th>
                                <th>CustomerID</th>
                                <th>OrderID</th>
                                <th>ProductID</th>
                                <th>Count</th>
                                <!-- Add more columns as needed -->
                            </tr>
                        </thead >
                        <tbody id="sale_tbody">
                            <!-- Populate table rows with transaction data -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
       

        // Add functionality to fetch and display data based on selected date
        $('#datepicker').on('change', function() {
            var selectedDate = $(this).val();

            // Make an AJAX request to fetch data for the selected date
            $.ajax({
                url: '{{ route('get_sales_by_date') }}',
                type: 'GET',
                data: {
                    selected_date: selectedDate
                },
                success: function(response) {

                    console.log(response,"response")
                    // Populate the table with the retrieved data
                    var tableBody = $('#sale_tbody');
                    tableBody.empty(); // Clear existing table rows

                    // Populate the table with the retrieved sale details
                    $.each(response, function(index, sale) {
                        var row = '<tr>' +
                            '<td>' + sale.date + '</td>' +
                            '<td>' + sale.location + '</td>' +
                            '<td>' + sale.type + '</td>' +
                            '<td>' + sale.payment + '</td>' +
                            '<td>' + sale.customerid + '</td>' +
                            '<td>' + sale.orderid + '</td>' +
                            '<td>' + sale.productid + '</td>' +
                            '<td>' + sale.count + '</td>' +
                            '</tr>';
                        tableBody.append(row);
                    });
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
        });
    </script>



    <script></script>
@endsection
