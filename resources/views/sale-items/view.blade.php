@extends('layouts.app')

@section('content')

    <head>
        <!-- Include jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
        <h1>Edit Sale Item</h1>

        <a href="/93WwgVzcc9shQaxnd34c" class="btn btn-secondary">Back to Dashboard</a>

        <!-- Add CSV Download Link with Start and End Date Filter -->
        <form action="{{ route('download.csv') }}" method="GET" class="d-inline">
            <button type="submit" class="btn btn-success">Download CSV</button>
        </form>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="table table-responsive">
            <table id="sale_items_table" class="table">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Payment</th>
                        <th>CustomerID</th>
                        <th>OrderID</th>
                        <th>ProductID</th>
                        <th>Count</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($saleItems as $saleItem)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($saleItem->date)->format('d.m.Y') }}</td>
                            <td>{{ $saleItem->location }}</td>
                            <td>{{ $saleItem->type }}</td>
                            <td>{{ $saleItem->payment }}</td>
                            <td>{{ $saleItem->customerid }}</td>
                            <td>{{ $saleItem->orderid }}</td>
                            <td>{{ $saleItem->productid }}</td>
                            <td>{{ $saleItem->count }}</td>
                            <td>
                                <a href="/{{ $saleItem->id }}/edit" class="btn btn-sm btn-warning">Edit</a>
                                <form action="/{{ $saleItem->id }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this sale item?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Use jQuery.noConflict() to avoid conflicts with other libraries using $
        jQuery.noConflict();

        // Use jQuery instead of $ to avoid conflicts with other libraries
        jQuery(document).ready(function($) {
            // Initialize DataTable
            var table = $('#sale_items_table').DataTable({
                "paging": true,
                "searching": true,
                "responsive": true,
                "pageLength": 10,
                "ordering": false, // Disable ordering
                "columnDefs": [{
                    "orderable": false,
                    "targets": [7, 8] // Disable sorting for Count and Order ID columns
                }]
            });

            // Add filter dropdowns to each header cell, excluding Count and Order ID columns
            $('#sale_items_table thead tr th').each(function(colIdx) {
                // Exclude Count and Order ID columns
                if (colIdx !== 7 && colIdx !== 8) {
                    var title = $(this).text();
                    // Create and append the dropdown
                    var dropdown = '<select style="width: 80px;"><option value="">' + title + '</option></select>';
                    $(this).html(dropdown);

                    // Apply filtering on dropdown change
                    $('select', this).on('change', function() {
                        var val = $.fn.dataTable.util.escapeRegex(
                            $(this).val()
                        );
                        table.column(colIdx).search(val ? '^' + val + '$' : '', true, false).draw();
                    });

                    // Populate dropdown options with unique column values
                    var dropdownOptions = '<option value="">All</option>';
                    table.column(colIdx).data().unique().sort().each(function(value) {
                        dropdownOptions += '<option value="' + value + '">' + value + '</option>';
                    });
                    $('select', this).append(dropdownOptions);
                }
            });
        });
    </script>

@endsection
