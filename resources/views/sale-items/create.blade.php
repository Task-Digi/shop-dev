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
        .sale-entry-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .sale-item-box .form-group.row {
            margin-bottom: 0.75rem;
        }

        /* Lay out 2 Product ID / Count / Add more entries per horizontal row. */
        #additionalFields {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -8px;
        }

        #additionalFields .additional-fields {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 8px;
            margin-bottom: 0.5rem;
            box-sizing: border-box;
        }

        @media (max-width: 767.98px) {
            #additionalFields .additional-fields {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        .add-more-group {
            display: flex;
            flex-direction: column;
        }

        .add-more-label {
            display: block;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .product-id-field .form-control.has-product-info {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .productid-alert .product-id-info {
            margin: 0;
            border-top: 0;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
            font-size: 0.85rem;
            line-height: 1.4;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #842029;
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }

        .productid-alert .product-id-info .product-id-duplicate-note {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            text-transform: none;
            letter-spacing: 0;
        }

        @media (max-width: 767.98px) {
            .sale-entry-card {
                border-radius: 10px;
                margin: 0 8px;
            }

            .sale-entry-card .card-body {
                padding: 0.9rem;
            }

            .sale-item-box {
                padding: 1rem !important;
            }

            .sale-item-box .col-form-label {
                margin-bottom: 0.25rem;
            }

            #addMoreFields,
            #createSaleItemForm {
                width: 100%;
            }

            #additionalFields .form-group .col-12 {
                margin-bottom: 0.4rem;
            }
        }
    </style>
 @include('layouts.nav_bar')
    <div class="card sale-entry-card">
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


                    <div class="form-group row align-items-center">
                        <label for="orderid" class="col-12 col-md-2 col-form-label">Order ID:</label>
                        <div class="col-12 col-md-4">
                            <input type="text" id="orderid" name="orderid" class="form-control" required>
                        </div>
                        <label for="type" class="col-12 col-md-1 col-form-label">Type:</label>
                        <div class="col-12 col-md-4">
                            <select id="type" name="type" class="form-control" required>
                                <option value="MalProff MPP">MalProff MPP</option>
                                <option value="FARGERIKE">FARGERIKE</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-12 col-md-4 offset-md-2" id="orderid-alert">
                            <!-- Order details will be displayed here -->
                        </div>
                    </div>
                    <div class="form-group row align-items-center">
                        <label for="customerid" class="col-12 col-md-2 col-form-label">Customer ID:</label>
                        <div class="col-12 col-md-4">
                            <input type="text" id="customerid" name="customerid" class="form-control" required>
                        </div>
                        <label for="payment" class="col-12 col-md-1 col-form-label">Payment:</label>
                        <div class="col-12 col-md-4">
                            <select id="payment" name="payment" class="form-control" required>
                                <option value="Invoice">Invoice</option>
                                <option value="Cash/Card">Cash/Card</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-12 col-md-4 offset-md-2" id="customer-details">
                            <!-- Customer details will be displayed here -->
                        </div>
                    </div>
                    <div class="form-group row align-items-center">
                        <label for="location" class="col-12 col-md-2 col-form-label">Location:</label>
                        <div class="col-12 col-md-4">
                            <select id="location" name="location" class="form-control">
                                <option value="ALNABRU">ALNABRU</option>
                                <option value="MAJORSTUEN" selected>MAJORSTUEN</option>
                            </select>
                        </div>
                        <label for="date" class="col-12 col-md-1 col-form-label">Date:</label>
                        <div class="col-12 col-md-4">
                            <input type="date" name="date" id="date" class="form-control" required>
                        </div>
                    </div>
                    <div class="col">

                        <div id="additionalFields">
                            <!-- Initially, display one set of fields -->
                            <div class="additional-fields">
                                <div class="form-group row">
                                    <div class="col-12 col-md-4">
                                        <label for="productid">Product ID:</label>
                                        <div class="product-id-field">
                                            <input type="text" id="productid" name="productid[]" class="form-control">
                                            <div class="productid-alert"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label for="count">Count:</label>
                                        <input type="text" name="count[]" class="form-control">
                                    </div>

                                    <div class="col-12 col-md-4">
                                        <div class="add-more-group">
                                        <span class="add-more-label">Add more:</span>
                                        <button id="addMoreFields" class="btn btn-secondary" type="button">Add
                                            more</button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="col-md-12 text-md-right mt-3">

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
            $('input[name="productid[]"]').each(function() {
                $(this).removeClass('has-product-info');
            });
            $('.productid-alert').empty();
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

            function normalizeProductId(value) {
                return (value || '').trim();
            }

            function countProductIdOccurrences(productId) {
                var normalized = normalizeProductId(productId);
                if (!normalized) return 0;
                var count = 0;
                $('input[name="productid[]"]').each(function() {
                    if (normalizeProductId($(this).val()) === normalized) {
                        count++;
                    }
                });
                return count;
            }

            function escapeHtml(text) {
                return $('<div>').text(text || '').html();
            }

            function formatProductNameLines(productName) {
                var name = (productName || '').trim();
                if (!name) {
                    return '';
                }
                if (name.indexOf('\n') >= 0) {
                    return name.split(/\r?\n/).map(function(line) {
                        return escapeHtml(line.trim().toUpperCase());
                    }).filter(Boolean).join('<br>');
                }
                var words = name.toUpperCase().split(/\s+/).filter(Boolean);
                var lines = [];
                for (var i = 0; i < words.length; i += 3) {
                    lines.push(escapeHtml(words.slice(i, i + 3).join(' ')));
                }
                return lines.join('<br>');
            }

            function showProductIdAlert(productInput, options) {
                options = options || {};
                var lines = formatProductNameLines(options.productName || '');
                if (!lines && !options.isDuplicate) {
                    clearProductIdAlert(productInput);
                    return;
                }

                var html = '<h6 class="alert alert-danger p-2 mb-0 product-id-info">';
                if (options.isDuplicate) {
                    html += '<span class="product-id-duplicate-note">This product ID already exists.</span>';
                }
                html += lines || escapeHtml('Unknown product');
                html += '</h6>';

                productInput.addClass('has-product-info');
                productInput.siblings('.productid-alert').html(html);
            }

            function clearProductIdAlert(productInput) {
                productInput.removeClass('has-product-info');
                productInput.siblings('.productid-alert').empty();
            }

            function fetchProductName(productid, callback) {
                $.ajax({
                    url: "{{ route('get-product-details') }}",
                    method: 'GET',
                    data: {
                        product_id: productid
                    },
                    success: function(response) {
                        callback(response && response.product_name ? response.product_name : null);
                    },
                    error: function() {
                        callback(null);
                    }
                });
            }

            function refreshDuplicateProductIdWarnings() {
                $('input[name="productid[]"]').each(function() {
                    var productInput = $(this);
                    var productid = normalizeProductId(productInput.val());
                    if (!productid) {
                        clearProductIdAlert(productInput);
                        return;
                    }
                    if (countProductIdOccurrences(productid) > 1) {
                        fetchProductName(productid, function(productName) {
                            if (countProductIdOccurrences(productid) <= 1) {
                                return;
                            }
                            showProductIdAlert(productInput, {
                                productName: productName || productid,
                                isDuplicate: true
                            });
                        });
                    } else if (productInput.siblings('.productid-alert').find('.product-id-duplicate-note').length) {
                        clearProductIdAlert(productInput);
                    }
                });
            }

            function hasDuplicateProductIds() {
                var seen = {};
                var duplicate = false;
                $('input[name="productid[]"]').each(function() {
                    var productid = normalizeProductId($(this).val());
                    if (!productid) return;
                    if (seen[productid]) {
                        duplicate = true;
                        return false;
                    }
                    seen[productid] = true;
                });
                return duplicate;
            }

            // Function to handle blur event for product ID input
            function handleProductIdBlur() {
                var productInput = $(this);
                var productid = normalizeProductId(productInput.val());
                if (productid === '') {
                    clearProductIdAlert(productInput);
                    refreshDuplicateProductIdWarnings();
                    return;
                }

                var isDuplicate = countProductIdOccurrences(productid) > 1;

                fetchProductName(productid, function(productName) {
                    if (normalizeProductId(productInput.val()) !== productid) {
                        return;
                    }
                    var stillDuplicate = countProductIdOccurrences(productid) > 1;
                    if (!productName && !stillDuplicate) {
                        clearProductIdAlert(productInput);
                        return;
                    }
                    showProductIdAlert(productInput, {
                        productName: productName || productid,
                        isDuplicate: stillDuplicate
                    });
                    refreshDuplicateProductIdWarnings();
                });
            }

            // Function to handle focus event for input fields other than product ID
            function handleNonProductIdFocus() {
                hideProductNameAlert();
            }

            // Attach blur event listener to existing and dynamically added product ID input fields
            $(document).on('blur', 'input[name="productid[]"]', handleProductIdBlur);

            // Get the container where additional fields will be appended
            var additionalFieldsContainer = $('#additionalFields');

            // Snapshot the very first row as our clone template BEFORE the user
            // can type into it, so cloned rows always start empty.
            var additionalFieldsTemplate = $('.additional-fields').first().clone(true);

            // Use event delegation so EVERY "Add more" button (the one in the
            // first row and the ones in cloned rows) triggers the same behavior.
            additionalFieldsContainer.on('click', '#addMoreFields', function() {
                // Clone the template
                var newFields = additionalFieldsTemplate.clone(true);

                // Make sure the cloned inputs start empty and don't carry
                // duplicated ids (only the very first row keeps id="productid").
                newFields.find('input').val('');
                newFields.find('input[name="productid[]"]').removeAttr('id');
                newFields.find('.productid-alert').empty();

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

                refreshDuplicateProductIdWarnings();
                if (hasDuplicateProductIds()) {
                    alert('Each product ID can only be entered once. Remove duplicate product IDs before saving.');
                    return;
                }

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
