@extends('layouts.app')

@section('content')

<div class="container">
    <h1>Edit Sale Item</h1>
    
    <a href="/93WwgVzcc9shQaxnd34c" class="btn btn-secondary">Back to Dashboard</a>
    
   <!-- Add CSV Download Link with Start and End Date Filter -->
    <form action="{{ route('download.csv') }}" method="GET" class="d-inline">
        <!--<div class="form-group">-->
        <!--    <label for="startDate">Start Date:</label>-->
        <!--    <input type="date" name="startDate" class="form-control" required>-->
        <!--</div>-->
        <!--<div class="form-group">-->
        <!--    <label for="endDate">End Date:</label>-->
        <!--    <input type="date" name="endDate" class="form-control" required>-->
        <!--</div>-->
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
    
    <table class="table">
        <thead>
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
            @foreach($saleItems as $saleItem)
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
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this sale item?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
</div>
@endsection