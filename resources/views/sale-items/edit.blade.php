@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Edit Sale Item</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="/{{ $saleItem->id }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="date">Date:</label>
            <input type="date" name="date" class="form-control" value="{{ $saleItem->date }}" >
        </div>

        <div class="form-group">
            <label for="location">Location:</label>
            <select name="location" class="form-control" >
                <option value="ALNABRU" {{ $saleItem->location === 'ALNABRU' ? 'selected' : '' }}>ALNABRU</option>
                <option value="MAJORSTUEN" {{ $saleItem->location === 'MAJORSTUEN' ? 'selected' : '' }}>MAJORSTUEN</option>
            </select>
        </div>

        <div class="form-group">
            <label for="type">Type:</label>
            <select name="type" class="form-control" >
                <option value="FARGERIKE" {{ $saleItem->type === 'FARGERIKE' ? 'selected' : '' }}>FARGERIKE</option>
                <option value="MalProff MPP" {{ $saleItem->type === 'MalProff MPP' ? 'selected' : '' }}>MalProff MPP</option>
                <!-- Add more options as needed -->
            </select>
        </div>

        <div class="form-group">
            <label for="payment">Payment:</label>
            <select name="payment" class="form-control" >
                <option value="Cash/Card" {{ $saleItem->payment === 'Cash/Card' ? 'selected' : '' }}>Cash/Card</option>
                <option value="Invoice" {{ $saleItem->payment === 'Invoice' ? 'selected' : '' }}>Invoice</option>
                <!-- Add more options as needed -->
            </select>
        </div>

        <div class="form-group">
            <label for="customerid">Customer ID:</label>
            <input type="text" name="customerid" class="form-control" value="{{ $saleItem->customer_id }}" >
        </div>

        <div class="form-group">
            <label for="productid">Product ID:</label>
            <input type="text" name="productid" class="form-control" value="{{ $saleItem->product_id }}" >
        </div>

        <div class="form-group">
            <label for="orderid">Order ID:</label>
            <input type="text" name="orderid" class="form-control" value="{{ $saleItem->orderid }}" >
        </div>

        <div class="form-group">
            <label for="count">Count:</label>
            <input type="text" name="count" class="form-control" value="{{ $saleItem->count }}" >
        </div>

        <button type="submit" class="btn btn-primary">Update</button>
        <a href="/Dashboard" class="btn btn-secondary">Cancel</a>

    </form>
</div>
@endsection
