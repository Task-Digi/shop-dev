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
    <style>

    </style>
 @include('layouts.nav_bar')
    <div class="card">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <h4>Enter Sales Data</h4>
                </div>
                {{-- <div class="col-md-6 text-md-right">
                    <a href="/view" class="btn btn-primary">DASHBOARD</a>
                </div> --}}
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

            <form method="POST" action="/home" id="saleForm">
                @csrf

                <div class="sale-item-box border rounded p-3 mt-4 mb-5">


                    <div class="form-group row">
                        <label for="orderid" class="col-sm-2 col-form-label">Order ID:</label>
                        <div class="col-sm-4">
                            <input type="text" id="orderid" name="orderid" class="form-control" required>
                        </div>
                        <div id="orderid-alert" class="col-sm-4">
                            <!-- Customer details will be displayed here -->
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-2 col-form-label">Customer ID:</label>
                        <div class="col-sm-4">
                            <input type="text" id="customerid" name="customerid" class="form-control" required>
                        </div>
                        <div id="customer-details" class="col-sm-4">
                            <!-- Customer details will be displayed here -->
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="Location" class="col-sm-2 col-form-label">Location:</label>
                        <div class="col-sm-4">
                            <select name="location" class="form-control">
                                <option value="ALNABRU">ALNABRU</option>
                                <option value="MAJORSTUEN" selected>MAJORSTUEN</option>
                            </select>
                        </div>
                        <label for="Type" class="col-sm-1 col-form-label">Type:</label>
                        <div class="col-sm-4">
                            <select name="type" class="form-control" required>
                                <option value="MalProff MPP">MalProff MPP</option>
                                <option value="FARGERIKE">FARGERIKE</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-group row">
                        <label for="Date" class="col-sm-2 col-form-label">Date:</label>
                        <div class="col-sm-4">
                            <input type="date" name="date" id="date" class="form-control" required>
                        </div>
                        <label for="Payment" class="col-sm-1 col-form-label">Payment:</label>
                        <div class="col-sm-4">
                            <select name="payment" class="form-control" required>
                                <option value="Invoice">Invoice</option>
                                <option value="Cash/Card">Cash/Card</option>
                            </select>
                        </div>

                    </div>
                    <div class="col">

                        <div id="additionalFields" class="d-flex flex-wrap">
                            <!-- Initially, display one set of fields -->
                            <div class="additional-fields">
                                <div class="form-group row">
                                    <div class="col">
                                        <label for="productid[]">Product ID:</label>
                                        <input type="text" id="productid" name="productid[]" class="form-control">
                                        <div id="productid-alert">
                                            <!-- Customer details will be displayed here -->
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label for="count[]">Count:</label>
                                        <input type="text" name="count[]" class="form-control">
                                    </div>

                                    <div class="col">
                                        <label for="productidICon" class="mr-2">Add more:</label><br />
                                        <button id="addMoreFields" class="btn btn-secondary" type="button">Add
                                            more</button>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="col-md-12 text-md-right mt-1">

                            <button type="submit" id="createSaleItemForm" class="btn btn-primary">SAVE</button>
                        </div>

                    </div>
            </form>
        </div>
    </div>






    {{-- <div class="row">

                        <div class="col-12 col-md-3">
                            <div class="form-group">
                                <label for="orderid">Order ID:</label>
                                <input type="text" id="orderid" name="orderid" class="form-control" required>
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
                                <select name="type" class="form-control" required>
                                    <option value="MalProff MPP">MalProff MPP</option>
                                    <option value="FARGERIKE">FARGERIKE</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <div class="form-group">
                                <label>Customer ID:</label>

                                <input type="text" id="customerid" name="customerid" class="form-control" required>
                                <div id="customer-details">
                                    <!-- Customer details will be displayed here -->
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="payment">Payment:</label>
                                <select name="payment" class="form-control" required>
                                    <option value="Invoice">Invoice</option>
                                    <option value="Cash/Card">Cash/Card</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="date">Date:</label>
                                <input type="date" name="date" id="date" class="form-control" required>

                            </div>
                        </div>

                        <div class="col-12 col-md-4">

                            <div id="additionalFields">
                                <!-- Initially, display one set of fields -->
                                <div class="additional-fields">
                                    <div class="form-group row">
                                        <div class="col-8">
                                            <label for="productid[]">Product ID:</label>
                                            <input type="text" id="productid" name="productid[]" class="form-control"
                                                required>
                                            <div id="productid-alert">
                                                <!-- Customer details will be displayed here -->
                                            </div>
                                        </div>
                                        <div class="col">
                                            <label for="count[]">Count:</label>
                                            <input type="text" name="count[]" class="form-control" required>

                                        </div>

                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="col-12 col-md-2 mt-1">
                            <div class="col">
                                <label for="productidICon" class="mr-2">Add more:</label><br />
                                <button id="addMoreFields" style="border: none; background-color: transparent;"> <img src="{{ asset('images/addicon1.png') }}" width="40" /> </button>
                            </div>
                        </div>
                    </div> --}}



    <script>
        document.getElementById('customerid').addEventListener('keydown', function(e) {
            if (e.key === 'Tab' || e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('productid').focus();
            }
        });
        // Function to hide customer name alert box
        function hideCustomerNameAlert() {
            $('#customer-details').html('');
        }

        // Function to hide Order id alert box
        function hideOrderidAlert() {
            $('#orderid-alert').html('');
        }

        // Function to hide product name alert box
        function hideProductNameAlert() {
            $('.productid-alert').hide();
        }


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
                                '<h6 class="alert alert-primary p-1">Name: ' + response
                                .customer_name + '</h6>';
                            $('#customer-details').html(customerDetailsHtml);
                        }
                    }
                });
            }
        });

        // Listen for focus event on other input boxes to hide customer name alert
        $('input[type=text], select').on('focus', function() {
            hideCustomerNameAlert();
            hideOrderidAlert();
            hideProductNameAlert();
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
                        if (response) {
                            if (response.error) {
                                var orderidalertHtml = '<h6 class="alert alert-danger p-1">' +
                                    response.error +
                                    '</h6>';
                                $('#orderid-alert').html(orderidalertHtml);
                            } else if (response.success) {
                                // Handle success case if needed
                            }
                        }
                    }
                });
            }
        });

        $(document).ready(function() {
            // Fetch last submission date
            fetch('/date-get')
                .then(response => response.json())
                .then(data => {
                    // Extract the last submission date from the response
                    const lastSubmissionDate = data.lastSubmissionDate;

                    // Set the initial value of the date picker to the last submission date
                    $('#date').val(lastSubmissionDate);
                })
                .catch(error => console.error('Error:', error));

            // Function to handle blur event for product ID input
            function handleProductIdBlur() {
                var productInput = $(this);
                var productid = productInput.val();
                console.log(productid, "Keyup event triggered productid");
                if (productid != '') {
                    // Clear the previous product name message
                    var productNameAlert = productInput.next('.alert.alert-danger');
                    if (productNameAlert.length > 0) {
                        productNameAlert.remove();
                    }

                    $.ajax({
                        url: "{{ route('get-product-details') }}",
                        method: 'GET',
                        data: {
                            product_id: productid
                        },
                        success: function(response) {
                            if (response.product_name !== undefined) {
                                // Check if product_name is defined
                                var productidalertHtml = '<h6 class="alert alert-danger">' +
                                    response.product_name + '</h6>';
                                productInput.after(productidalertHtml);
                            }
                        }
                    });
                }
            }

            // Function to handle focus event for input fields other than product ID
            function handleNonProductIdFocus() {
                // Hide all product name alerts
                $('.alert.alert-danger').remove();
            }

            // Attach blur event listener to existing and dynamically added product ID input fields
            $(document).on('blur', 'input[name="productid[]"]', handleProductIdBlur);

            // Get the container where additional fields will be appended
            var additionalFieldsContainer = $('#additionalFields');

            // Get the template of the additional fields
            var additionalFieldsTemplate = $('.additional-fields').first();

            // Get the "Add More" button
            var addMoreButton = $('#addMoreFields');

            // Attach a click event listener to the "Add More" button
            addMoreButton.on('click', function() {
                // Remove any existing alerts
                $('.alert.alert-danger').remove();

                // Clone the template
                var newFields = additionalFieldsTemplate.clone(true);

                // Clear input values in the cloned fields
                var inputFields = newFields.find('input');
                inputFields.val('');

                // Append the cloned fields to the container
                additionalFieldsContainer.append(newFields);

                // Attach focus event listener to other input fields
                newFields.find('input[type="text"], select').on('focus', handleNonProductIdFocus);

                // Focus the newly added productid input box
                newFields.find('input[name="productid[]"]').last().focus();
            });

            // Form submission event
            $('#saleForm').on('submit', function(e) {
                // Prevent form submission
                e.preventDefault();

                // Filter out empty product ID fields
                $('input[name="productid[]"]').each(function(index, element) {
                    if ($(element).val().trim() === '') {
                        $(element).closest('.additional-fields').remove();
                    }
                });

                // If there are still product ID fields left, submit the form
                if ($('input[name="productid[]"]').length > 0) {
                    this.submit();
                } else {
                    alert('Please enter at least one product ID.');
                }
            });
        });
    </script>



@endsection
