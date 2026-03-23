@extends('layouts.app')

@section('content')

<head>
    <link href="{{ asset('css/styles.css') }}" rel="stylesheet">
</head>
@include('layouts.nav_bar')
<div class="card">
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6 mb-2 mb-md-0">
                <h4>Timesheet Lønnsrapport</h4>
            </div>
        </div>

        @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
        @endif

        @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('payroll.generate') }}" enctype="multipart/form-data">
            @csrf
            <div class="p-4 border rounded mb-3 text-center" style="border: 2px dashed #ccc !important; background-color: #f9f9f9;">
                <p class="mb-2">Velg fil fra Tamigo (CSV, XLS, XLSX)</p>
                <input type="file" name="csv_file" accept=".csv, .xls, .xlsx" required class="form-control-file d-inline-block w-auto">
            </div>
            <button type="submit" class="btn btn-primary w-100">Generer Lønnsrapport</button>
        </form>
    </div>
</div>
@endsection