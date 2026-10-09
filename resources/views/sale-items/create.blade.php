@extends('layouts.app')

@section('content')

    <head>
        <!-- Include jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
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

        .productid-alert .product-id-info.product-id-valid {
            color: #0f5132;
            background-color: #d1e7dd;
            border-color: #badbcc;
        }

        .return-panel {
            border: 1px solid #b6d4fe;
            border-radius: 8px;
            background: #f0f6ff;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }

        .return-availability {
            display: block;
            margin-top: 0.25rem;
            color: #52606d;
            font-size: 0.8rem;
        }

        .productid-alert .product-id-info .product-id-duplicate-note {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            text-transform: none;
            letter-spacing: 0;
        }

        .zero-sales-toggle-card {
            border: 1px solid #d8e2ee;
            border-radius: 10px;
            background: #f8fafc;
        }

        .zero-sales-toggle {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 1rem;
            border: 0;
            background: transparent;
            color: #334155;
            font-weight: 600;
            text-align: left;
        }

        .zero-sales-toggle:hover,
        .zero-sales-toggle:focus {
            color: #0056b3;
            text-decoration: none;
            outline: none;
        }

        .zero-sales-toggle .toggle-icon {
            transition: transform 0.2s ease;
        }

        .zero-sales-toggle[aria-expanded="true"] .toggle-icon {
            transform: rotate(180deg);
        }

        .zero-sales-panel-inner {
            border-top: 1px solid #d8e2ee;
            padding: 1rem;
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

            @if (session('success'))
                <div class="alert alert-success" role="alert">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            <form method="POST" action="/home" id="saleForm">
                @csrf

                <div class="sale-item-box border rounded p-3 mt-4 mb-3">

                    <div class="return-panel">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_return" name="is_return" value="1" {{ old('is_return') ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold" for="is_return">Record a return from an existing order</label>
                        </div>
                        <div id="returnOrderFields" class="form-row align-items-end mt-2" style="display:none;">
                            <div class="col-12 col-md-5">
                                <label for="original_orderid">Original Order ID <small class="text-muted">(not Product ID)</small>:</label>
                                <input type="text" id="original_orderid" name="original_orderid" class="form-control" value="{{ old('original_orderid') }}" maxlength="255" placeholder="Example: 849358" disabled>
                            </div>
                            <div class="col-12 col-md-3 mt-2 mt-md-0">
                                <button type="button" id="loadOriginalOrder" class="btn btn-outline-primary btn-block">Load order</button>
                            </div>
                            <div class="col-12 col-md-4 mt-2 mt-md-0" id="return-order-status"></div>
                        </div>
                    </div>


                    <div class="form-group row align-items-center">
                        <label for="orderid" class="col-12 col-md-2 col-form-label">Order ID:</label>
                        <div class="col-12 col-md-4">
                            <input type="text" id="orderid" name="orderid" class="form-control" value="{{ old('orderid') }}" maxlength="255" required>
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
                            <input type="text" id="customerid" name="customerid" class="form-control" value="{{ old('customerid') }}" maxlength="255" required>
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
                                            <input type="text" id="productid" name="productid[]" class="form-control" value="{{ old('productid.0') }}" maxlength="255" required>
                                            <div class="productid-alert"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label for="count">Count:</label>
                                        <input type="number" name="count[]" class="form-control" value="{{ old('count.0') }}" min="-1000000" max="1000000" step="1" required placeholder="e.g. 2 or -2">
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
                </div>
            </form>

            @php
                $showZeroSalesPanel = $errors->has('zero_sales_date') || $errors->has('zero_sales_location');
            @endphp
            <div class="zero-sales-toggle-card mt-3 mb-4">
                <button type="button" id="zeroSalesToggle" class="zero-sales-toggle"
                    aria-expanded="{{ $showZeroSalesPanel ? 'true' : 'false' }}" aria-controls="zeroSalesPanel">
                    <span>
                        <i class="fa fa-calendar-times-o mr-2"></i>
                        No sales for a day? <small class="text-muted ml-1">Record 0 Sales</small>
                    </span>
                    <span class="toggle-icon" aria-hidden="true">&#9660;</span>
                </button>

                <div id="zeroSalesPanel" class="collapse{{ $showZeroSalesPanel ? ' show' : '' }}">
                    <div class="zero-sales-panel-inner">
                        <p class="text-muted mb-3">Use this only when the selected shop had no sales. No order details are required.</p>
                        <form method="POST" action="{{ route('saleitems.zero-sales.store') }}" class="form-row align-items-end mb-0">
                            @csrf
                            <div class="form-group col-md-4">
                                <label for="zero_sales_location">Location</label>
                                <select id="zero_sales_location" name="zero_sales_location" class="form-control" required>
                                    <option value="ALNABRU" {{ old('zero_sales_location') === 'ALNABRU' ? 'selected' : '' }}>ALNABRU</option>
                                    <option value="MAJORSTUEN" {{ old('zero_sales_location', 'MAJORSTUEN') === 'MAJORSTUEN' ? 'selected' : '' }}>MAJORSTUEN</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="zero_sales_date">Date</label>
                                <input type="date" id="zero_sales_date" name="zero_sales_date" class="form-control"
                                    value="{{ old('zero_sales_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="form-group col-md-4">
                                <button type="submit" class="btn btn-outline-primary btn-block">Save 0 Sales</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var toggle = document.getElementById('zeroSalesToggle');
                    var panel = document.getElementById('zeroSalesPanel');

                    if (!toggle || !panel) {
                        return;
                    }

                    toggle.addEventListener('click', function () {
                        var isOpen = panel.classList.toggle('show');
                        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

                        if (isOpen) {
                            var firstField = document.getElementById('zero_sales_location');
                            if (firstField) {
                                firstField.focus();
                            }
                        }
                    });
                });
            </script>
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

                var alertClass = options.isDuplicate ? 'alert-danger' : 'alert-success product-id-valid';
                var html = '<h6 class="alert ' + alertClass + ' p-2 mb-0 product-id-info">';
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

            function setReturnMode(enabled) {
                $('#returnOrderFields').toggle(enabled);
                $('#original_orderid').prop('disabled', !enabled).prop('required', enabled);
                $('input[name="count[]"]').attr('min', enabled ? '1' : '-1000000')
                    .attr('placeholder', enabled ? 'Quantity to return' : 'e.g. 2 or -2');
                if (!enabled) {
                    $('#orderid').prop('readonly', false);
                    $('#return-order-status').empty();
                    if (additionalFieldsContainer.find('input[name="productid[]"][readonly]').length) {
                        var blankRow = additionalFieldsTemplate.clone(true);
                        blankRow.find('input').val('');
                        blankRow.find('.productid-alert').empty();
                        additionalFieldsContainer.empty().append(blankRow);
                    }
                }
            }

            function renderReturnProducts(products) {
                additionalFieldsContainer.empty();

                (products || []).filter(function(product) {
                    return Number(product.available_return_count) > 0;
                }).forEach(function(product, index) {
                    var row = additionalFieldsTemplate.clone(true);
                    var productInput = row.find('input[name="productid[]"]');
                    var countInput = row.find('input[name="count[]"]');

                    row.find('input').val('');
                    productInput.removeAttr('id').val(product.product_id).prop('readonly', true);
                    countInput.attr({
                        min: 1,
                        max: product.available_return_count,
                        placeholder: 'Max ' + product.available_return_count
                    });
                    row.find('.productid-alert').html(
                        '<h6 class="alert alert-success product-id-info product-id-valid p-2 mb-0">' +
                        formatProductNameLines(product.product_name || product.product_id) +
                        '<span class="return-availability">Available to return: ' +
                        escapeHtml(product.available_return_count) + ' of ' +
                        escapeHtml(product.original_count) + '</span></h6>'
                    );
                    row.find('#addMoreFields').remove();
                    row.find('.add-more-label').remove();
                    additionalFieldsContainer.append(row);
                });
            }

            $('#is_return').on('change', function() {
                setReturnMode(this.checked);
            });

            $('#loadOriginalOrder').on('click', function() {
                var originalOrderId = ($('#original_orderid').val() || '').trim();
                if (!originalOrderId) {
                    $('#return-order-status').html('<span class="text-danger">Enter the original Order ID.</span>');
                    return;
                }

                var button = $(this).prop('disabled', true).text('Loading...');
                $('#return-order-status').empty();
                $.ajax({
                    url: "{{ route('get_order_details') }}",
                    method: 'GET',
                    data: { orderid: originalOrderId },
                    success: function(response) {
                        var order = response.order || response;
                        $('#orderid').val(order.orderid || originalOrderId).prop('readonly', true);
                        $('#customerid').val(order.customer_id || '');
                        $('#location').val(order.location || '');
                        $('#type').val(order.type || '');
                        $('#payment').val(order.payment || '');
                        renderReturnProducts(order.products || []);
                        var available = (order.products || []).filter(function(product) {
                            return Number(product.available_return_count) > 0;
                        }).length;
                        $('#return-order-status').html(available
                            ? '<span class="text-success">Order loaded. Enter return quantities below.</span>'
                            : '<span class="text-danger">All products on this order have already been returned.</span>');
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Original order could not be loaded.';
                        $('#return-order-status').html('<span class="text-danger">' + escapeHtml(message) + '</span>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Load order');
                    }
                });
            });

            setReturnMode($('#is_return').is(':checked'));

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

            // Handle Scandinavian trailing minus format (e.g. 2- or 2,00-)
            $(document).on('blur change', 'input[name="count[]"]', function() {
                var raw = ($(this).val() || '').toString().trim();
                if (raw.endsWith('-')) {
                    var num = raw.slice(0, -1).trim().replace(',', '.');
                    if (!isNaN(num) && num !== '') {
                        $(this).val(-Math.abs(Math.round(parseFloat(num))));
                    }
                }
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

                var hasZeroCount = false;
                $('input[name="count[]"]').each(function() {
                    var val = parseInt($(this).val(), 10);
                    if (val === 0) {
                        hasZeroCount = true;
                    }
                });

                if (hasZeroCount) {
                    alert('Count cannot be 0. Enter a positive number for sales or a negative number for returns.');
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
                    var submitButton = document.getElementById('createSaleItemForm');
                    submitButton.disabled = true;
                    submitButton.textContent = 'SAVING...';
                    this.submit();
                } else {
                    alert('Please enter at least one product ID.');
                }
            });
        });
    </script>



@endsection
