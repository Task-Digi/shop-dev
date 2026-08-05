@extends('layouts.app')

@section('content')

    <head>
        <!-- Include jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <!-- DataTables CSS -->
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

        <!-- DataTables JS -->
        <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

        <!-- Bootstrap JavaScript -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous">
        </script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    @include('layouts.nav_bar')
    <div class="sold-registry-container">
        <h4>Sale Item dashboard </h4>

        <div class="top-actions mb-3">
            <form action="{{ route('download.csv') }}" method="GET" class="d-inline-block">
                <button type="submit" class="btn btn-success top-action-btn">Download CSV</button>
            </form>
            <button id="clearFilters" type="button" class="btn btn-warning top-action-btn">Clear Filters</button>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
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
        <div id="registryFeedback" class="alert mb-3" role="status" style="display:none;"></div>
        <small id="activeFilterSummary" class="text-muted d-block mb-2">No active filters</small>
        <div class="row">
            <div class="col-12">
                <form method="GET" action="{{ route('saleitems.datasearch') }}" class="mb-3" id="soldRegistrySearchForm" onsubmit="return false;">
                    <div class="input-group">
                        <input type="text" name="search" id="search" class="form-control"
                            placeholder="Search by Date, Location, Payment etc." value="{{ $initialSearch ?? '' }}" autocomplete="off">
                        <div id="searchResults" class="dropdown-menu w-100" style="max-height: 240px; overflow-y: auto;"></div>
                        <div class="input-group-append">
                            <button type="button" id="globalSearchBtn" class="btn btn-primary">Search</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-12">
                <div class="table-card">
                <div class="table-responsive">
                    <table id="sale_items_table" class="table table-sm" style="width:100%">
                        <thead class="thead-light">
                            <tr class="filter-row">
                                <th><input type="date" id="datepickerFilter" class="form-control col-filter" value="{{ $initialDate ?? '' }}" title="Filter by date"></th>
                                <th><select class="form-control col-filter" data-col="location"><option value="">All Location</option></select></th>
                                <th><select class="form-control col-filter" data-col="type"><option value="">All Type</option></select></th>
                                <th><select class="form-control col-filter" data-col="payment"><option value="">All Payment</option></select></th>
                                <th><input type="text" class="form-control col-filter" data-col="customer_id" placeholder="All CustomerID"></th>
                                <th><input type="text" class="form-control col-filter" data-col="customer_name" placeholder="All CustomerName"></th>
                                <th><input type="text" class="form-control col-filter" data-col="orderid" placeholder="All OrderID"></th>
                                <th><input type="text" class="form-control col-filter" data-col="product_id" placeholder="All ProductID"></th>
                                <th><input type="text" class="form-control col-filter" data-col="count" placeholder="All Count"></th>
                                <th class="action-col-header">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                </div>
            </div>

        </div>

    </div>
    <style>
        .sold-registry-container {
            padding: 15px;
            background: #f8fafc;
            border-radius: 14px;
        }

        #soldRegistrySearchForm {
            max-width: 980px;
            margin-left: 0;
        }

        #soldRegistrySearchForm .input-group {
            align-items: stretch;
        }

        #soldRegistrySearchForm .form-control,
        #soldRegistrySearchForm .btn {
            height: 38px;
        }

        .top-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .top-action-btn {
            min-width: 130px;
            border-radius: 999px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            border: none;
        }

        .table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        #sale_items_table {
            width: 100% !important;
            margin-bottom: 0 !important;
        }

        #sale_items_table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-weight: 600;
            vertical-align: middle;
            padding: 8px 10px;
        }

        #sale_items_table thead th.action-col-header {
            text-align: center;
        }

        /* Hide DataTables' default sort indicators since the inputs live in the header. */
        #sale_items_table thead th.sorting,
        #sale_items_table thead th.sorting_asc,
        #sale_items_table thead th.sorting_desc {
            background-image: none !important;
            cursor: default;
        }

        #sale_items_table tbody td {
            vertical-align: middle;
            border-color: #eef2f7;
        }

        #sale_items_table th:last-child,
        #sale_items_table td:last-child {
            min-width: 108px;
        }

        #sale_items_table th.action-col-header,
        #sale_items_table td.action-cell {
            text-align: center;
            vertical-align: middle;
        }

        .col-filter {
            min-width: 110px;
            width: 100%;
            height: 32px;
            font-size: 12px;
            line-height: 1.2;
        }

        .action-group {
            display: inline-flex;
            flex-direction: row;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
        }

        .action-group form {
            display: inline-block !important;
            margin: 0;
        }

        .action-cell .action-btn {
            width: auto;
            margin: 0;
        }

        .action-icon-only {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            text-align: center;
            text-decoration: none !important;
            border: 1px solid transparent;
            background: #f8fafc !important;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .action-icon-svg {
            width: 16px;
            height: 16px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .action-edit-icon {
            color: #2563eb !important;
            border-color: #bfdbfe;
        }

        .action-delete-icon {
            color: #dc2626 !important;
            border-color: #fecaca;
        }

        .action-edit-icon:hover,
        .action-delete-icon:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12);
        }

        .action-edit-icon:hover {
            background: #eff6ff !important;
        }

        .action-delete-icon:hover {
            background: #fef2f2 !important;
        }

        /* DataTables paging / length / info chrome */
        .dataTables_wrapper .dataTables_paginate {
            padding: 6px 12px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 4px 10px !important;
            margin: 0 2px !important;
            border-radius: 6px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #2563eb !important;
            color: #fff !important;
            border: 1px solid #2563eb !important;
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_length {
            padding: 8px 12px;
            color: #475569;
            font-size: 13px;
        }

        .dataTables_wrapper .dataTables_length select {
            height: 30px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            padding: 0 8px;
            margin: 0 6px;
        }

        .dt-loading {
            padding: 24px;
            text-align: center;
            color: #64748b;
        }

        .sold-registry-container .input-group {
            position: relative;
        }

        #searchResults {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1050;
            margin-top: 2px;
            display: none;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
            background: #fff;
        }

        #searchResults.show {
            display: block;
        }

        #searchResults .sold-registry-suggest {
            white-space: normal;
            line-height: 1.3;
            gap: 0.75rem;
        }

        #searchResults .sold-registry-suggest:hover,
        #searchResults .sold-registry-suggest:focus {
            background: #eff6ff;
            color: #1e293b;
        }

        #searchResults .suggest-value {
            font-weight: 600;
            word-break: break-word;
            flex: 1;
            min-width: 0;
        }

        #searchResults .suggest-label {
            flex-shrink: 0;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #f1f5f9;
            color: #475569 !important;
            border-radius: 999px;
            padding: 2px 8px;
        }

        @media (max-width: 767.98px) {
            .sold-registry-container {
                padding: 12px;
                border-radius: 0;
            }

            .top-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .sold-registry-container .input-group {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .sold-registry-container .input-group .form-control {
                width: 100%;
            }

            .sold-registry-container .input-group .btn,
            .sold-registry-container .input-group-append {
                width: 100%;
            }

            .top-action-btn,
            .sold-registry-container .input-group .btn,
            .sold-registry-container .input-group-append {
                width: 100%;
            }
        }

    </style>

    <script>
        jQuery.noConflict();

        jQuery(document).ready(function($) {
            var csrfToken = "{{ csrf_token() }}";
            var dtUrl = "{{ route('saleitems.datatable') }}";
            var filterOptionsUrl = "{{ route('saleitems.filter-options') }}";
            var autocompleteUrl = "{{ route('autocomplete.search') }}";

            // Build the DataTable in server-side mode. Rows are fetched per
            // page via AJAX, so the page stays fast even with 100k+ records.
            var table = $('#sale_items_table').DataTable({
                processing: true,
                serverSide: true,
                deferRender: true,
                paging: true,
                searching: true,
                lengthChange: true,
                pageLength: 25,
                lengthMenu: [[25, 50, 100, 250, 500], [25, 50, 100, 250, 500]],
                ordering: false,
                order: [[0, 'desc']],
                dom: "rt<'d-flex justify-content-between align-items-center px-2 py-2'lip>",
                ajax: {
                    url: dtUrl,
                    type: 'GET',
                    data: function(d) {
                        // Send the active column-header filters.
                        d.date_filter          = $('#datepickerFilter').val() || '';
                        d.location_filter      = $('.col-filter[data-col="location"]').val() || '';
                        d.type_filter          = $('.col-filter[data-col="type"]').val() || '';
                        d.payment_filter       = $('.col-filter[data-col="payment"]').val() || '';
                        d.customer_id_filter   = $('.col-filter[data-col="customer_id"]').val() || '';
                        d.customer_name_filter = $('.col-filter[data-col="customer_name"]').val() || '';
                        d.orderid_filter       = $('.col-filter[data-col="orderid"]').val() || '';
                        d.product_id_filter    = $('.col-filter[data-col="product_id"]').val() || '';
                        d.count_filter         = $('.col-filter[data-col="count"]').val() || '';
                        d.search_global        = $('#search').val() || '';
                    },
                    error: function() {
                        $('#registryFeedback')
                            .removeClass('alert-info alert-success')
                            .addClass('alert-danger')
                            .text('Could not load Sold Registry data. Please retry or clear the filters.')
                            .show();
                    }
                },
                columns: [
                    { data: 'date',          name: 'date' },
                    { data: 'location',      name: 'location' },
                    { data: 'type',          name: 'type' },
                    { data: 'payment',       name: 'payment' },
                    { data: 'customer_id',   name: 'customer_id' },
                    { data: 'customer_name', name: 'customer_name' },
                    { data: 'orderid',       name: 'orderid' },
                    { data: 'product_id',    name: 'product_id' },
                    { data: 'count',         name: 'count', orderable: false },
                    {
                        data: 'id',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'action-cell',
                        render: function(id) {
                            return ''
                                + '<div class="action-group">'
                                +   '<a href="/' + id + '/edit" class="btn btn-sm btn-link action-btn action-icon-only action-edit-icon" title="Edit" aria-label="Edit">'
                                +     '<svg viewBox="0 0 24 24" class="action-icon-svg" aria-hidden="true">'
                                +       '<path d="M3 17.25V21h3.75L19.81 7.94l-3.75-3.75L3 17.25z"></path>'
                                +       '<path d="M14.06 4.19l3.75 3.75"></path>'
                                +     '</svg>'
                                +   '</a>'
                                +   '<form action="/' + id + '" method="POST" style="display:inline;" onsubmit="return confirmDeleteSale(this);">'
                                +     '<input type="hidden" name="_token" value="' + csrfToken + '">'
                                +     '<input type="hidden" name="_method" value="DELETE">'
                                +     '<button type="submit" class="btn btn-sm btn-link action-btn action-icon-only action-delete-icon" title="Delete" aria-label="Delete">'
                                +       '<svg viewBox="0 0 24 24" class="action-icon-svg" aria-hidden="true">'
                                +         '<path d="M3 6h18"></path>'
                                +         '<path d="M8 6V4h8v2"></path>'
                                +         '<path d="M19 6l-1 14H6L5 6"></path>'
                                +         '<path d="M10 11v6"></path>'
                                +         '<path d="M14 11v6"></path>'
                                +       '</svg>'
                                +     '</button>'
                                +   '</form>'
                                + '</div>';
                        }
                    }
                ],
                language: {
                    processing: '<div class="dt-loading">Loading…</div>',
                    emptyTable: 'No sale items found',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoFiltered: '(filtered from _MAX_ total entries)',
                    lengthMenu: 'Show _MENU_ entries'
                }
            });

            function updateActiveFilterSummary() {
                var activeCount = 0;
                if (($('#search').val() || '').trim() !== '') activeCount++;
                $('.col-filter').each(function() {
                    if (String($(this).val() || '').trim() !== '') activeCount++;
                });

                $('#activeFilterSummary').text(activeCount
                    ? activeCount + ' active filter' + (activeCount === 1 ? '' : 's')
                    : 'No active filters');
            }

            table.on('processing.dt', function(e, settings, processing) {
                if (processing) {
                    $('#registryFeedback')
                        .removeClass('alert-danger alert-success')
                        .addClass('alert-info')
                        .text('Loading Sold Registry...')
                        .show();
                } else if (!$('#registryFeedback').hasClass('alert-danger')) {
                    $('#registryFeedback').hide();
                }
            });

            table.on('draw.dt', updateActiveFilterSummary);

            window.confirmDeleteSale = function(form) {
                if (form.dataset.submitting === 'true') return false;

                var cells = $(form).closest('tr').children('td');
                var orderId = cells.eq(6).text().trim();
                var productId = cells.eq(7).text().trim();
                var message = 'Order ' + orderId + ' / Product ' + productId + '. This action cannot be undone.';

                // Keep a native fallback in case the SweetAlert CDN is unavailable.
                if (typeof Swal === 'undefined') {
                    if (!window.confirm('Delete ' + message)) return false;
                    form.dataset.submitting = 'true';
                    $(form).find('button[type="submit"]').prop('disabled', true);
                    return true;
                }

                Swal.fire({
                    title: 'Delete sale item?',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    focusCancel: true
                }).then(function(result) {
                    if (!result.isConfirmed || form.dataset.submitting === 'true') return;

                    form.dataset.submitting = 'true';
                    $(form).find('button[type="submit"]').prop('disabled', true);
                    form.submit();
                });

                return false;
            };

            // Debounced reload for text filters so we don't hammer the server on every keystroke.
            var reloadTimer = null;
            function reloadTable() {
                if (reloadTimer) clearTimeout(reloadTimer);
                reloadTimer = setTimeout(function() {
                    table.ajax.reload(null, false); // false = stay on current page
                }, 350);
            }

            $(document).on('keyup change', '.col-filter[type="text"], .col-filter[type="date"]', reloadTable);
            $(document).on('change', 'select.col-filter', function() {
                table.ajax.reload(null, false);
            });

            // Populate Location / Type / Payment dropdowns from the server.
            $.getJSON(filterOptionsUrl, function(opts) {
                $.each(['location', 'type', 'payment'], function(_, col) {
                    var $sel = $('.col-filter[data-col="' + col + '"]');
                    if (!$sel.length || !opts[col]) return;
                    $.each(opts[col], function(_, val) {
                        if (val === null || val === '') return;
                        $sel.append($('<option>', { value: val, text: val }));
                    });
                });

                // Pre-fill from initial URL params if present.
                var initialDate = $('#datepickerFilter').val();
                if (initialDate) {
                    $('#datepickerFilter').val(initialDate);
                    table.ajax.reload(null, false);
                }
            });

            // Top free-text search + autocomplete
            var autocompleteXhr = null;
            var autocompleteTimer = null;
            var lastAppliedSearch = ($('#search').val() || '').trim();

            function applySearch() {
                lastAppliedSearch = ($('#search').val() || '').trim();
                $('#searchResults').empty().removeClass('show');
                table.ajax.reload(null, false);
            }

            $('#globalSearchBtn').on('click', function() {
                applySearch();
            });

            $('#search').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applySearch();
                } else if (e.key === 'Escape') {
                    $('#searchResults').empty().removeClass('show');
                }
            });

            // Debounced autocomplete with in-flight request cancellation.
            $('#search').on('input', function() {
                var query = $(this).val().trim();

                if (query === '') {
                    $('#searchResults').empty().removeClass('show');
                    if (autocompleteXhr) { try { autocompleteXhr.abort(); } catch (e) {} autocompleteXhr = null; }
                    if (autocompleteTimer) { clearTimeout(autocompleteTimer); autocompleteTimer = null; }
                    // Restore the full table when the user clears the search box.
                    if (lastAppliedSearch !== '') {
                        lastAppliedSearch = '';
                        table.ajax.reload(null, false);
                    }
                    return;
                }

                if (autocompleteTimer) clearTimeout(autocompleteTimer);
                autocompleteTimer = setTimeout(function() {
                    if (autocompleteXhr) { try { autocompleteXhr.abort(); } catch (e) {} }
                    autocompleteXhr = $.ajax({
                        url: autocompleteUrl,
                        method: 'POST',
                        data: { query: query, _token: csrfToken },
                        success: function(data) {
                            if (data && String(data).trim() !== '') {
                                $('#searchResults').html(data).addClass('show');
                            } else {
                                $('#searchResults').empty().removeClass('show');
                            }
                        },
                        error: function(xhr, status) {
                            if (status === 'abort') return; // ignore aborted requests
                            $('#searchResults').empty().removeClass('show');
                        }
                    });
                }, 250);
            });

            $(document).on('click', '#searchResults li.sold-registry-suggest', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var raw = $(this).attr('data-value');
                var val = (raw !== undefined && raw !== null) ? String(raw) : $(this).find('.suggest-value').text().trim();
                if (!val) {
                    val = $(this).text().trim();
                }
                $('#search').val(val);
                applySearch();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#search, #searchResults, .sold-registry-suggest').length) {
                    $('#searchResults').empty().removeClass('show');
                }
            });

            // Clear Filter Button Click Event
            $('#clearFilters').click(function(e) {
                e.preventDefault();
                $('#search').val('');
                lastAppliedSearch = '';
                if (autocompleteXhr) { try { autocompleteXhr.abort(); } catch (e) {} autocompleteXhr = null; }
                if (autocompleteTimer) { clearTimeout(autocompleteTimer); autocompleteTimer = null; }
                $('#searchResults').empty().removeClass('show');
                $('#datepickerFilter').val('');
                $('.col-filter').not('#datepickerFilter').each(function() {
                    if ($(this).is('select')) {
                        $(this).val('');
                    } else {
                        $(this).val('');
                    }
                });
                table.search('').order([[0, 'desc']]).ajax.reload(null, false);
            });

            // If the page was loaded with ?search=, kick off the initial filter.
            var initialSearch = ($('#search').val() || '').trim();
            if (initialSearch) {
                // value already populated in the input via the blade attribute
                table.ajax.reload(null, false);
            }
        });
    </script>
@endsection
