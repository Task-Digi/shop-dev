@extends('layouts.app')

@section('content')

<div class="container">
    <h1>Create Sale Item</h1>

    <a href="/view" class="btn btn-primary mb-2">Sales Item List</a>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="/home">
        @csrf

        <div class="sale-item-box border rounded p-3 mt-4 mb-5">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="date">Date:</label>
                        <input type="date" name="date" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="location">Location:</label>
                        <select name="location" class="form-control">
                            <option value="ALNABRU">ALNABRU</option>
                            <option value="MAJORSTUEN">MAJORSTUEN</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="type">Type:</label>
                        <select name="type" class="form-control">
                            <option value="MalProff MPP">MalProff MPP</option>
                            <option value="FARGERIKE">FARGERIKE</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="payment">Payment:</label>
                        <select name="payment" class="form-control">
                            <option value="Invoice">Invoice</option>
                            <option value="Cash/Card">Cash/Card</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="customerid">Customer ID:</label>
                        <input type="text" name="customerid" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="orderid">Order ID:</label>
                        <input type="text" name="orderid" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div id="additionalFields">
            <!-- Initially, display one set of fields -->
            <div class="additional-fields">
                <div class="form-group row">
                    <div class="col-4">
                        <label for="productid[]">Product ID:</label>
                        <input type="text" name="productid[]" class="form-control">
                    </div>
                    <div class="col-3">
                        <label for="count[]">Count:</label>
                        <input type="text" name="count[]" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <button type="button" id="addMoreFields" class="btn btn-secondary">Add More</button>

        <button type="submit" id="createSaleItemForm" class="btn btn-primary">SAVE</button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Get the container where additional fields will be appended
        var additionalFieldsContainer = document.getElementById('additionalFields');

        // Get the template of the additional fields
        var additionalFieldsTemplate = document.querySelector('.additional-fields');

        // Get the "Add More" button
        var addMoreButton = document.getElementById('addMoreFields');

        // Attach a click event listener to the "Add More" button
        addMoreButton.addEventListener('click', function () {
            // Clone the template
            var newFields = additionalFieldsTemplate.cloneNode(true);

            // Clear input values in the cloned fields
            var inputFields = newFields.querySelectorAll('input');
            inputFields.forEach(function (input) {
                input.value = '';
            });

            // Append the cloned fields to the container
            additionalFieldsContainer.appendChild(newFields);
        });
    });
</script>

@endsection
