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

    <body>
        <div style="padding: 15px">
            <h4>Dashboard-View</h4>
            @include('layouts.nav_bar')
            {{-- <a href="/93WwgVzcc9shQaxnd34c" class="btn btn-secondary">Back to DataEntry</a> --}}
            {{-- <a href="{{ route('Report-view') }}" target="_blank" class="btn btn-primary">Report</a>
            <a href="{{ route('saleitems.view') }}" target="_blank" class="btn btn-primary">SaleItems Dashboard</a>
            <a href="{{ route('ict') }}" target="_blank" class="btn btn-primary">Farrow & Ball</a> --}}
            <!-- Add CSV Download Link with Start and End Date Filter -->
            {{-- <form action="{{ route('download.csv') }}" method="GET" class="d-inline">
                <button type="submit" class="btn btn-success">Download CSV</button>
            </form> --}}


        </div>
    </body>
@endsection
