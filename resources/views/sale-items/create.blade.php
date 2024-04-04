@extends('layouts.app')

@section('content')

    <head>
        <!-- Include jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    </head>

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
                            <label>Customer ID:</label>
                            <input type="text" id="customerid" name="customerid" class="form-control">
                            <div id="customer-details">
                                <!-- Customer details will be displayed here -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="orderid">Order ID:</label>
                            <input type="text" id="orderid" name="orderid" class="form-control">
                            <div id="orderid-alert">
                                <!-- Customer details will be displayed here -->
                            </div>
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
                            <input type="text" id="productid" name="productid[]" class="form-control">
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
        $(document).ready(function() {
            $('#customerid').on('blur', function() {

                var customerId = $(this).val();
                console.log(customerId, "Keyup event triggered");
                if (customerId != '') {
                    $.ajax({
                        url: "{{ route('get_customer_details') }}",
                        method: 'GET',
                        data: {
                            customer_id: customerId
                        },
                        success: function(response) {
                            if (response) {
                                var customerDetailsHtml = '<p>Customer Name: ' + response
                                    .customer_name + '</p>';
                                $('#customer-details').html(customerDetailsHtml);
                            }
                        }
                    });
                }
            });
            $('#orderid').on('blur', function() {

                var orderid = $(this).val();
                console.log(orderid, "Keyup event triggered orderid");
                if (orderid != '') {
                    $.ajax({
                        url: "{{ route('validate_order_id') }}",
                        method: 'GET',
                        data: {
                            orderid: orderid
                        },
                        success: function(response) {
                            if (response) {
                                if (response.error) {
                                    var orderidalertHtml = '<p>' + response.error +
                                        '</p>';
                                    $('#orderid-alert').html(orderidalertHtml);
                                } else if (response.success) {
                                    // Handle success case if needed
                                }
                            }
                        }
                    });
                }
            });
            $('#productid').on('blur', function() {

                var productid = $(this).val();
                console.log(productid, "Keyup event triggered orderid");
                if (productid != '') {
                    $.ajax({
                        url: "{{ route('validate_order_id') }}",
                        method: 'GET',
                        data: {
                            orderid: productid
                        },
                        success: function(response) {
                            if (response) {
                                if (response.error) {
                                    var orderidalertHtml = '<p>' + response.error +
                                        '</p>';
                                    $('#productid-alert').html(orderidalertHtml);
                                } else if (response.success) {
                                    // Handle success case if needed
                                }
                            }
                        }
                    });
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Get the container where additional fields will be appended
            var additionalFieldsContainer = document.getElementById('additionalFields');

            // Get the template of the additional fields
            var additionalFieldsTemplate = document.querySelector('.additional-fields');

            // Get the "Add More" button
            var addMoreButton = document.getElementById('addMoreFields');

            // Attach a click event listener to the "Add More" button
            addMoreButton.addEventListener('click', function() {
                // Clone the template
                var newFields = additionalFieldsTemplate.cloneNode(true);

                // Clear input values in the cloned fields
                var inputFields = newFields.querySelectorAll('input');
                inputFields.forEach(function(input) {
                    input.value = '';
                });

                // Append the cloned fields to the container
                additionalFieldsContainer.appendChild(newFields);
            });
        });
    </script>



@endsection
