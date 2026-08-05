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

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <form method="POST" action="/{{ $saleItem->id }}" id="editSaleForm">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="date">Date:</label>
            <input type="date" id="date" name="date" class="form-control" value="{{ old('date', $saleItem->date) }}" required>
        </div>

        <div class="form-group">
            <label for="location">Location:</label>
            <select id="location" name="location" class="form-control">
                <option value="ALNABRU" {{ $saleItem->location === 'ALNABRU' ? 'selected' : '' }}>ALNABRU</option>
                <option value="MAJORSTUEN" {{ $saleItem->location === 'MAJORSTUEN' ? 'selected' : '' }}>MAJORSTUEN</option>
            </select>
        </div>

        <div class="form-group">
            <label for="type">Type:</label>
            <select id="type" name="type" class="form-control">
                <option value="FARGERIKE" {{ $saleItem->type === 'FARGERIKE' ? 'selected' : '' }}>FARGERIKE</option>
                <option value="MalProff MPP" {{ $saleItem->type === 'MalProff MPP' ? 'selected' : '' }}>MalProff MPP</option>
                <!-- Add more options as needed -->
            </select>
        </div>

        <div class="form-group">
            <label for="payment">Payment:</label>
            <select id="payment" name="payment" class="form-control">
                <option value="Cash/Card" {{ $saleItem->payment === 'Cash/Card' ? 'selected' : '' }}>Cash/Card</option>
                <option value="Invoice" {{ $saleItem->payment === 'Invoice' ? 'selected' : '' }}>Invoice</option>
                <!-- Add more options as needed -->
            </select>
        </div>

        <div class="form-group">
            <label for="customerid">Customer ID:</label>
            <input type="text" id="customerid" name="customerid" class="form-control" value="{{ old('customerid', $saleItem->customer_id) }}" maxlength="255" required>
        </div>

        <div class="form-group">
            <label for="productid">Product ID:</label>
            <input type="text" id="productid" name="productid" class="form-control" value="{{ old('productid', $saleItem->product_id) }}" maxlength="255" required>
        </div>

        <div class="form-group">
            <label for="orderid">Order ID:</label>
            <input type="text" id="orderid" name="orderid" class="form-control" value="{{ old('orderid', $saleItem->orderid) }}" maxlength="255" required>
        </div>

        <div class="form-group">
            <label for="count">Count:</label>
            <input type="number" id="count" name="count" class="form-control" value="{{ old('count', $saleItem->count) }}" min="1" max="1000000" step="1" required>
        </div>

        <button type="submit" class="btn btn-primary" id="updateSaleButton">Update</button>
        <a href="/Dashboard" class="btn btn-secondary">Cancel</a>

    </form>
</div>
<script>
    document.getElementById('editSaleForm').addEventListener('submit', function () {
        var button = document.getElementById('updateSaleButton');
        button.disabled = true;
        button.textContent = 'Updating...';
    });
</script>
@endsection
