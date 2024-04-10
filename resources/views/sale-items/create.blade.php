@extends('layouts.app')

@section('content')

    <head>
        <!-- Include jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
            integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous">
        </script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous">
        </script>
        <link href="{{ asset('css/styles.css') }}" rel="stylesheet">
    </head>

    <div class="card">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <h4>Enter Sales Data</h4>
                </div>
                <div class="col-md-6 text-md-right">
                    <a href="/view" class="btn btn-primary">DASHBOARD</a>
                </div>
            </div>
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

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label for="orderid">Order ID:</label>
                                <input type="text" id="orderid" name="orderid" class="form-control">
                                <div id="orderid-alert">
                                    <!-- Customer details will be displayed here -->
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="location">Location:</label>
                                <select name="location" class="form-control">
                                    <option value="ALNABRU">ALNABRU</option>
                                    <option value="MAJORSTUEN" selected>MAJORSTUEN</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="type">Type:</label>
                                <select name="type" class="form-control">
                                    <option value="MalProff MPP">MalProff MPP</option>
                                    <option value="FARGERIKE">FARGERIKE</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label for="payment">Payment:</label>
                                <select name="payment" class="form-control">
                                    <option value="Invoice">Invoice</option>
                                    <option value="Cash/Card">Cash/Card</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Customer ID:</label>

                                <input type="text" id="customerid" name="customerid" class="form-control">
                                <div id="customer-details">
                                    <!-- Customer details will be displayed here -->
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="date">Date:</label>
                                <input type="date" name="date" class="form-control">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">

                            <div id="additionalFields">
                                <!-- Initially, display one set of fields -->
                                <div class="additional-fields">
                                    <div class="form-group row">
                                        <div class="col-8">
                                            <label for="productid[]">Product ID:</label>
                                            <input type="text" id="productid" name="productid[]" class="form-control">
                                            <div id="productid-alert">
                                                <!-- Customer details will be displayed here -->
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <label for="count[]">Count:</label>
                                            <input type="text" name="count[]" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>



                <button type="button" id="addMoreFields" class="btn btn-secondary">Add More</button>

                <button type="submit" id="createSaleItemForm" class="btn btn-primary">SAVE</button>
            </form>
        </div>
    </div>

    <script>
        $('#customerid').on('blur', function() {
            var customerId = $(this).val();
            console.log(customerId, "Keyup event triggered");
            if (customerId != '') {
                $('#customer-details').html('');
                $.ajax({
                    url: "{{ route('get_customer_details') }}",
                    method: 'GET',
                    data: {
                        customer_id: customerId
                    },
                    success: function(response) {
                        if (response && response.customer_name !== undefined) {
                            var customerDetailsHtml =
                                '<p class="alert alert-primary">Customer Name: ' + response
                                .customer_name + '</p>';
                                
                            $('#customer-details').html(customerDetailsHtml);
                            
                            // Remove the alert after 5 seconds
                            setTimeout(function() {
                                $('#customer-details').html('');
                            }, 4000);
                        }
                    }
                });
            }
        });
        $('#orderid').on('blur', function() {
            var orderid = $(this).val();
            console.log(orderid, "Keyup event triggered orderid");
            if (orderid != '') {
                // Clear the previous order ID alert message
                $('#orderid-alert').html('');

                $.ajax({
                    url: "{{ route('validate_order_id') }}",
                    method: 'GET',
                    data: {
                        orderid: orderid
                    },
                    success: function(response) {
                        if (response && response.error) {
                            var orderidalertHtml = '<p class="alert alert-danger">' +
                                response.error +
                                '</p>';
                            $('#orderid-alert').html(orderidalertHtml);

                            // Remove the alert after 5 seconds
                            setTimeout(function() {
                                $('#orderid-alert').html('');
                            }, 1000);
                        } else if (response && response.success) {
                            // Handle success case if needed
                        }
                    }
                });
            }
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


                // Attach a blur event listener to the cloned product ID input boxes
                newFields.querySelectorAll('input[name="productid[]"]').forEach(function(productInput) {
                    productInput.addEventListener('blur', function() {
                        var productid = this.value;
                        console.log(productid, "Keyup event triggered productid");
                        if (productid != '') {
                            // Clear the previous product name message
                            this.nextElementSibling.innerHTML = '';

                            var self = this; // Save the current input box reference

                            $.ajax({
                                url: "{{ route('get-product-details') }}",
                                method: 'GET',
                                data: {
                                    product_id: productid
                                },
                                success: function(response) {
                                    if (response.product_name !== undefined) {
                                        var orderidalertHtml =
                                            '<p class="alert alert-danger">Product Name: ' +
                                            response.product_name + '</p>';
                                        self.nextElementSibling.innerHTML =
                                            orderidalertHtml;

                                        // Remove the alert after 5 seconds
                                        setTimeout(function() {
                                            self.nextElementSibling
                                                .innerHTML = '';
                                        }, 4000);
                                    }
                                }
                            });
                        }
                    });
                });
            });

            // Attach a blur event listener to the existing product ID input boxes
            additionalFieldsContainer.querySelectorAll('input[name="productid[]"]').forEach(function(productInput) {
                productInput.addEventListener('blur', function() {
                    var productid = this.value;
                    console.log(productid, "Keyup event triggered productid");
                    if (productid != '') {
                        // Clear the previous product name message
                        this.nextElementSibling.innerHTML = '';

                        var self = this; // Save the current input box reference

                        $.ajax({
                            url: "{{ route('get-product-details') }}",
                            method: 'GET',
                            data: {
                                product_id: productid
                            },
                            success: function(response) {
                                if (response.product_name !== undefined) {
                                    var orderidalertHtml =
                                        '<p class="alert alert-danger">Product Name: ' +
                                        response.product_name + '</p>';
                                    self.nextElementSibling.innerHTML =
                                        orderidalertHtml;

                                    // Remove the alert after 5 seconds
                                    setTimeout(function() {
                                        self.nextElementSibling.innerHTML = '';
                                    }, 5000);
                                }
                            }
                        });
                    }
                });
            });

            // Trigger the blur event manually for the existing product ID input boxes
            additionalFieldsContainer.querySelectorAll('input[name="productid[]"]').forEach(function(productInput) {
                productInput.dispatchEvent(new Event('blur'));
            });
        });
    </script>



@endsection
