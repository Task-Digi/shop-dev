@extends('layouts.app')

@section('content')

    <head>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous">
        </script>
        <!-- DataTables CSS -->
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">

        <!-- jQuery -->
        <script type="text/javascript" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <!-- DataTables JS -->
        <script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
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
                        <th>Customer ID</th>
                        <th>Order ID</th>
                        <th>Product ID</th>
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
        var table = $('#sale_items_table').DataTable({
            "paging": true,
            "searching": true,
            "responsive": true,
            "pageLength": 100,
            "order": [],
            "columnDefs": [{
                    "targets": [0],
                    "orderable": false
                },
                {
                    "targets": [4],
                    "orderable": false
                }
            ]
        });
    </script>
@endsection
