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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>
    </head>
    @include('layouts.nav_bar')
    <div style="padding: 15px">
        <h4>Sale Item dashboard </h4>

        {{-- <a href="/93WwgVzcc9shQaxnd34c" class="btn btn-secondary">Back to DataEntry</a>
        <a href="{{ route('report') }}" target="_blank" class="btn btn-primary">Report</a>
        <a href="{{ route('ict') }}" class="btn btn-primary">Farrow & Ball</a> --}}
        <!-- Add CSV Download Link with Start and End Date Filter -->
        <form action="{{ route('download.csv') }}" method="GET" class="d-inline">
            <button type="submit" class="btn btn-success">Download CSV</button>
        </form>
        <button id="clearFilters" type="button" class="btn btn-warning ml-2">Clear Filters</button>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="row">
            <div class="col-8">
                <form method="GET" action="data-search" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="search" id="search" class="form-control"
                            placeholder="Search by Date, Location, Payment etc." value="{{ request()->input('search') }}">
                        <div id="searchResults" class="dropdown-menu" aria-labelledby="search"></div>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>
            <div class="col-4">
                <form method="GET" action="list-all" class="mb-3">
                    <div class="input-group">
                        <button type="submit" class="btn btn-primary">List All</button>
                    </div>
                </form>
            </div>
            <div class="col-12">
                <div class="table table-responsive">
                    <table id="sale_items_table" class="table">
                        <thead class="thead-light">
                            <tr>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Payment</th>
                                <th>CustomerID</th>
                                <th>CustomerName</th>
                                <th>OrderID</th>
                                <th>ProductID</th>
                                <th>Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($saleItems as $saleItem)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($saleItem->date)->format('d/m/Y') }}</td>
                                    <td>{{ $saleItem->location }}</td>
                                    <td>{{ $saleItem->type }}</td>
                                    <td>{{ $saleItem->payment }}</td>
                                    <td>{{ $saleItem->customer_id }}</td>
                                    <td>{{ $saleItem->customer_name }}</td>
                                    <td>{{ $saleItem->orderid }}</td>
                                    <td>{{ $saleItem->product_id }}</td>
                                    <td>{{ $saleItem->count }}</td>
                                    <td>
                                        <a href="/{{ $saleItem->id }}/edit" class="btn btn-sm btn-warning">&nbsp Edit
                                            &nbsp</a>
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

        </div>

    </div>
    <style>
        /* Hide the horizontal scrollbar */
        #sale_items_table_wrapper::-webkit-scrollbar-thumb {
            display: none;
        }
    </style>

    <script>
        jQuery.noConflict();

        jQuery(document).ready(function($) {
            // Initialize DataTable with responsive extension
            var table = $('#sale_items_table').DataTable({
                "paging": true,
                "searching": true,
                "responsive": true,
                "pageLength": 25,
                "ordering": false,
                "columnDefs": [{
                    "orderable": false,
                    "targets": [7, 8] // Disable sorting for Count and Action columns
                }],
                "order": [
                    [0, 'desc']
                ]
            });

            // Custom date filter function
            // Note: Server-side filtering is now used for the Date column to handle historical data
            // invalidating the need for client-side filtering on the limited 50 records.

            // Initialize Datepicker for Date column
            var datepickerFilter = $('<input type="date" id="datepickerFilter" placeholder="Select a date" class="form-control" style="width: 100%;">')
                .appendTo($('#sale_items_table thead tr th').eq(0));

            // Pre-fill date from URL
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('date')) {
                $('#datepickerFilter').val(urlParams.get('date'));
            }

            // Trigger server-side search on change
            datepickerFilter.on('change', function() {
                var val = $(this).val();
                if (val) {
                    // Reload with date parameter
                    window.location.href = "{{ route('saleitems.view') }}?date=" + val;
                } else {
                    // Clear filter, reload to default view
                    window.location.href = "{{ route('saleitems.view') }}";
                }
            });

            // Add dropdown filters to other header cells, excluding Date column
            $('#sale_items_table thead tr th:not(:first, :nth-child(9), :nth-child(10))').each(function() {
                var title = $(this).text();
                // Create and append the dropdown
                var dropdown = '<select><option value="">' + title + '</option></select>';
                $(this).html(dropdown);

                var column = table.column(this);

                // Apply filtering on dropdown change
                $('select', this).on('change', function() {
                    var val = $.fn.dataTable.util.escapeRegex(
                        $(this).val()
                    );
                    column.search(val ? '^' + val + '$' : '', true, false).draw();
                });

                // Populate dropdown options with unique column values
                var dropdownOptions = '<option value="">All</option>';
                column.data().unique().sort().each(function(value) {
                    dropdownOptions += '<option value="' + value + '">' + value + '</option>';
                });
                $('select', this).append(dropdownOptions);
            });

            $('#search').keyup(function() {
                var query = $(this).val();
                if (query != '') {
                    $.ajax({
                        url: "{{ route('autocomplete.search') }}",
                        method: "POST",
                        data: {
                            query: query,
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(data) {
                            $('#searchResults').fadeIn();
                            $('#searchResults').html(data);
                        }
                    });
                }
            });

            $(document).on('click', 'li', function() {
                $('#search').val($(this).text());
                $('#searchResults').fadeOut();
            });

            // Clear Filter Button Click Event
            $('#clearFilters').click(function(e) {
                e.preventDefault();
                // If a server-side search is active (URL has 'search='), reload to the main dashboard to reset data
                if (window.location.search.indexOf('search=') > -1) {
                    window.location.href = "{{ route('saleitems.view') }}";
                    return;
                }

                // Client-side reset for DataTables filters
                // Clear Date Picker
                $('#datepickerFilter').val('').trigger('change');
                
                // Clear Dropdowns
                $('#sale_items_table thead select').val('');
                
                // Clear Search Input
                $('#search').val('');
                
                // Reset DataTable Search and Columns
                table.search('').columns().search('').draw();
            });

        });
    </script>
@endsection
