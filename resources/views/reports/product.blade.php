@extends('layouts.app')

@section('content')

<head>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <!-- Bootstrap JavaScript -->
    {{-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous">
        </script> --}}
</head>

@php
    $activeSearch = trim((string) request('search', ''));
    $daysLabel = $days === 'all' ? 'All time' : 'Last ' . $days . ' sales days';
    $reportBaseParams = array_filter([
        'days' => $days,
        'per_page' => request('per_page'),
        'search' => $activeSearch !== '' ? $activeSearch : null,
    ], fn ($v) => $v !== null && $v !== '');
    $productSearchClearUrl = route('product.report', array_merge(
        ['productid' => 'all'],
        array_filter(
            ['days' => $days, 'per_page' => request('per_page')],
            fn ($v) => $v !== null && $v !== ''
        )
    ));
    $routeProductId = $productid ?? request()->route('productid');
@endphp

<div class="card product-report-page">
    @include('layouts.nav_bar')
    <div class="card-body product-report-head">
        <h4 class="product-report-title mb-3">Product-Report View</h4>

        <div class="product-report-toolbar">
            <form method="GET" action="{{ route('product.report', ['productid' => 'ProductName']) }}" id="productReportFilters" class="product-report-filters">
                <input type="hidden" name="per_page" value="{{ request('per_page', 25) }}">

                <div class="product-report-filters-row">
                    <label for="productSearch" class="form-label product-report-label">Search product</label>
                    <div class="input-group product-search-group">
                        <input type="search" name="search" id="productSearch" class="form-control"
                            value="{{ $activeSearch }}" placeholder="Name or product ID…" autocomplete="off">
                        <button type="submit" class="btn btn-primary" id="productSearchButton">Search</button>
                        <button type="button" id="productSearchClear" data-clear-url="{{ $productSearchClearUrl }}"
                            class="btn btn-outline-secondary {{ $activeSearch === '' ? 'd-none' : '' }}">Clear</button>
                    </div>
                </div>

                <div class="product-report-filters-row">
                    <label for="days" class="form-label product-report-label">Period</label>
                    <select name="days" id="days" class="form-select form-select-sm product-days-select"
                        onchange="document.getElementById('productReportFilters').requestSubmit()">
                        <option value="7" {{ (string) $days === '7' ? 'selected' : '' }}>Last 7 sales days</option>
                        <option value="28" {{ (string) $days === '28' ? 'selected' : '' }}>Last 28 sales days</option>
                        <option value="56" {{ (string) $days === '56' ? 'selected' : '' }}>Last 56 sales days</option>
                        <option value="all" {{ $days === 'all' ? 'selected' : '' }}>All time</option>
                    </select>
                </div>
            </form>

            <div class="product-report-actions">
                <a href="{{ route('report.products.export', array_filter([
                    'days' => $days,
                    'search' => $activeSearch !== '' ? $activeSearch : null,
                    'product_id' => !in_array($routeProductId, ['all', 'ProductName'], true) ? $routeProductId : null,
                ], fn ($value) => $value !== null && $value !== '')) }}" class="btn btn-outline-success">Export CSV</a>
                <a href="{{ route('product.report', array_merge(['productid' => 'all'], $reportBaseParams)) }}"
                    class="btn btn-outline-primary {{ $productid === 'all' && $activeSearch === '' ? 'active' : '' }}">
                    All Products
                </a>
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#productSalesGraph" aria-expanded="false" aria-controls="productSalesGraph">
                    Sales graph
                </button>
            </div>
        </div>

        @if($activeSearch !== '')
            <p class="product-report-meta mb-0 mt-2">Results for <strong>{{ $activeSearch }}</strong> · {{ $daysLabel }}</p>
        @else
            <p class="product-report-meta text-muted mb-0 mt-2">{{ $daysLabel }}</p>
        @endif

        <div class="collapse mt-3" id="productSalesGraph">
            <div class="card card-body product-graph-card">
                <canvas id="salesChart" width="400" height="100"></canvas>
                <div class="pagination-controls d-flex align-items-center gap-2 mt-2">
                    <button type="button" id="prevPage" class="btn btn-sm btn-outline-secondary" disabled>Previous</button>
                    <span id="pageInfo" class="small text-muted"></span>
                    <button type="button" id="nextPage" class="btn btn-sm btn-outline-secondary">Next</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-none product-report-table-card">
        <div class="card-body pt-0">
            <div id="productReportStatus" class="alert py-2" role="status" style="display:none;"></div>
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 product-table-tools">
                <span class="small text-muted">Click a row to expand sales by date.</span>
                <div class="d-flex align-items-center gap-2">
                    <label for="perPageSelect" class="mb-0 small text-muted">Rows per page</label>
                    <select id="perPageSelect" class="form-select form-select-sm" style="width:auto; min-width:4.5rem;">
                    @foreach ([10, 25, 50, 100, 200] as $size)
                        <option value="{{ $size }}" {{ (int) request('per_page', 25) === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
                </div>
            </div>
            <div class="table-responsive">
                <table id="Reporttable" class="table table">
                    <thead>
                        <tr>
                            <th>ProductName</th>
                            <th>Sales.Date</th>
                            <th>Sales.Location</th>
                            <th>No.Customers</th>
                            <th>No.Orders</th>
                            <th>No.Sold</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: right;"> Sum.Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($salesData as $sale)
                        <tr class="mobile-row" data-date="">
                            <td data-product_id="{{ $sale->product_id }}">{{ $sale->product_name }}</td>
                            <td></td>
                            <td data-location="{{ $sale->location }}">{{ $sale->location }}</td>
                            <td>{{ number_format($sale->customer_count, 0, '.', ',') }}</td>
                            <td style="">{{ number_format($sale->order_id_count, 0, '.', ',') }}
                            </td>
                            <td style="">
                                {{ number_format($sale->product_id_count, 0, '.', ',') }}
                            </td>
                            <td style="text-align: right;">{{ $sale->unit_price_avg !== null ? number_format($sale->unit_price_avg, 2, '.', ',') : '–' }}</td>
                            <td style="text-align: right;">
                                {{ number_format($sale->total_products_price, 2, '.', ',') }}
                            </td>
                        </tr>
                        <tr class="hidden-row2" style="display: none;color:green">
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-5"><strong>No products found.</strong><br>Try another search or period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-report-pagination :paginator="$salesData" item-label="products" aria-label="Product report pages" />
        </div>
    </div>
</div>

<style>
    .product-report-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 1rem 1.5rem;
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }

    .product-report-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 1rem 1.25rem;
        flex: 1 1 320px;
        min-width: 0;
    }

    .product-report-filters-row {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .product-report-filters-row:first-child {
        flex: 1 1 280px;
        max-width: 520px;
    }

    .product-report-label {
        margin: 0;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
    }

    .product-search-group .form-control {
        min-width: 12rem;
    }

    .product-days-select {
        min-width: 9rem;
    }

    .product-report-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .product-report-actions .btn.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }

    .product-report-meta {
        font-size: 0.9rem;
    }

    .product-graph-card {
        border: 1px solid #e2e8f0;
    }

    /* Unified pagination footer — same look as customer report and ICT. */
    .report-pagination-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 1rem;
        padding: 0 4px;
        width: 100%;
    }

    .report-pagination-wrap .results-info {
        color: #64748b;
        font-size: 13px;
    }

    .report-pagination-wrap .pagination {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        padding-left: 0;
        list-style: none;
        margin: 0;
    }

    .report-pagination-wrap .pagination .page-item .page-link,
    .report-pagination-wrap .pagination .page-item span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 34px;
        padding: 0 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        color: #334155;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        line-height: 1;
    }

    .report-pagination-wrap .pagination .page-item.active .page-link,
    .report-pagination-wrap .pagination .page-item.active span {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
        font-weight: 600;
    }

    .report-pagination-wrap .pagination .page-item.disabled .page-link,
    .report-pagination-wrap .pagination .page-item.disabled span {
        color: #94a3b8;
        background: #f8fafc;
        cursor: not-allowed;
    }

    .report-pagination-wrap .pagination .page-item .page-link:hover {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    @media (max-width: 575.98px) {
        .report-pagination-wrap {
            justify-content: center;
            text-align: center;
        }
    }
</style>
<script>
    // Legacy search-form binding (only wires up if the element exists).
    (function() {
        var form = document.getElementById('searchForm');
        var input = document.getElementById('searchInput');
        if (!form || !input) return;
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            var searchValue = input.value.trim();
            if (searchValue) {
                this.action = '/report/' + encodeURIComponent(searchValue) + '/product';
            }
            this.submit();
        });
    })();

    // "Rows per page" selector — reload the current URL with the new size
    // while preserving the existing search/days query params.
    (function() {
        var sel = document.getElementById('perPageSelect');
        if (!sel) return;
        sel.addEventListener('change', function() {
            var url = new URL(window.location.href);
            url.searchParams.set('per_page', sel.value);
            url.searchParams.delete('page'); // jump back to first page on size change
            window.location.href = url.toString();
        });
    })();

    // Clear search: empty the field immediately, then reload all products.
    (function() {
        var input = document.getElementById('productSearch');
        var clearBtn = document.getElementById('productSearchClear');
        if (!input || !clearBtn) return;

        var clearUrl = clearBtn.dataset.clearUrl;

        function toggleClearVisibility() {
            clearBtn.classList.toggle('d-none', input.value.trim() === '');
        }

        input.addEventListener('input', toggleClearVisibility);

        clearBtn.addEventListener('click', function() {
            input.value = '';
            input.focus();
            toggleClearVisibility();
            window.location.href = clearUrl;
        });
    })();

    document.getElementById('productReportFilters').addEventListener('submit', function() {
        var button = document.getElementById('productSearchButton');
        if (button) {
            button.disabled = true;
            button.textContent = 'Loading...';
        }
    });
</script>
<script>
    function showProductReportStatus(message, type) {
        var status = document.getElementById('productReportStatus');
        if (!status) return;
        status.className = 'alert py-2 alert-' + (type || 'info');
        status.textContent = message;
        status.style.display = 'block';
    }

    function escapeProductHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $(document).on('click', '.mobile-row', function() {
        if (ajaxInProgress) {
            return;
        };

        ajaxInProgress = true;

        // var date = $(this).data('date');
        var location = $(this).find('td[data-location]').data('location');
        var product_id = $(this).find('td[data-product_id]').data('product_id');
        var hiddenRow = $(this).next('.hidden-row2');
        var requestKey = String(product_id) + '|' + String(location);

        // Hide all other hidden rows
        $('.hidden-row2 ').not(hiddenRow).hide().removeClass('loaded');

        var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible2') === requestKey;

        if (!isHiddenRowVisible) {
            $('.customer-details-row, .customer-details-lastRow, .hidden-row3').remove();
            hiddenRow.html('<td colspan="8" class="text-center text-muted py-3">Loading product sales...</td>').show();
            $.ajax({
                url: '/product/details',
                method: 'GET',
                data: {
                    // date: date,
                    location: location,
                    product_id: product_id,
                    days: $('#days').val()
                },
                success: function(response) {
                    function formatDate(dateString) {
                        const date = new Date(dateString);
                        if (isNaN(date)) return dateString;
                        const day = String(date.getDate()).padStart(2, '0');
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const year = String(date.getFullYear()).slice(-2);
                        return `${day}.${month}.${year}`;
                    }
                    function formatMoney(n) {
                        if (n === null || n === undefined || n === '') return '–';
                        return Number(n).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }

                    var rowsHtml = '';
                    if (!Array.isArray(response) || response.length === 0) {
                        hiddenRow.html('<td colspan="8" class="text-center text-muted py-3">No sales found for this product and period.</td>').show();
                        sessionStorage.setItem('hiddenRowVisible2', requestKey);
                        return;
                    }
                    response.forEach(function(product) {
                        if (!product) return;
                        rowsHtml +=
                            '<tr class="customer-details-row alert alert-primary" data-date="' +
                            escapeProductHtml(product.date) +
                            '" data-product_id="' + escapeProductHtml(product_id) +
                            '" data-location="' + escapeProductHtml(product.location) +
                            '">' +
                            '<td><h6>' + escapeProductHtml(product.product_name || product.product_id || '-') + '</h6></td>' +
                            '<td><h6>' + escapeProductHtml(formatDate(product.date)) + '</h6></td>' +
                            '<td><h6>' + escapeProductHtml(product.location) + '</h6></td>' +
                            '<td><h6>' + product.customer_count + '</h6></td>' +
                            '<td><h6>' + (product.order_id_count) + '</h6></td>' +
                            '<td><h6>' + Number(product.product_quantity_sold).toLocaleString('en-US', {
                                maximumFractionDigits: 0
                            }) + '</h6></td>' +
                            '<td><h6 style="text-align: right;">' + formatMoney(product.unit_price_avg) + '</h6></td>' +
                            '<td><h6 style="text-align: right;">' + formatMoney(product.total_sales) + '</h6></td>' +
                            '</tr>' +
                            '<tr class="hidden-row3" style="display: none; color: yellow;"></tr>';
                    });
                    hiddenRow.after(rowsHtml);

                    // Mark hiddenRow as loaded
                    hiddenRow.empty().addClass('loaded').hide();
                    sessionStorage.setItem('hiddenRowVisible2', requestKey);
                },
                error: function() {
                    hiddenRow.html('<td colspan="8" class="text-center text-danger py-3">Unable to load product sales. Click the product row to retry.</td>').show();
                    showProductReportStatus('Unable to load product sales. Please retry.', 'danger');
                    sessionStorage.setItem('hiddenRowVisible2', '');
                },
                complete: function() {
                    ajaxInProgress = false;
                }
            });
        } else {
            $('.customer-details-row, .customer-details-lastRow, .hidden-row3').remove();
            hiddenRow.empty().hide();
            sessionStorage.setItem('hiddenRowVisible2', '');
            ajaxInProgress = false;
        }
    });
    $(document).on('click', '.customer-details-row', function() {
        if (ajaxInProgress) {
            return;
        }

        $('.customer-details-lastRow').remove();
        // var date = $(this).data('date');
        var date = $(this).data('date');
        var productId = $(this).data('product_id');
        var location = $(this).data('location');
        var hiddenRow = $(this).next('.hidden-row3');
        var detailKey = String(productId) + '|' + String(date) + '|' + String(location);

        // Hide all other hidden rows
        $('.hidden-row3 ').not(hiddenRow).hide().removeClass('loaded');

        var isHiddenRowVisible = sessionStorage.getItem('hiddenRowVisible3') === detailKey;

        // Check if details for hidden-row3 are already loaded
        if (!isHiddenRowVisible) {
            ajaxInProgress = true;
            hiddenRow.html('<td colspan="8" class="text-center text-muted py-3">Loading sale details...</td>').show();
            $.ajax({
                url: '/product/finaldetails',
                method: 'GET',
                data: {
                    date: date,
                    location: location,
                    productId: productId
                },
                success: function(response) {
                    function escapeHtml(text) {
                        if (text == null || text === '') return '';
                        return String(text)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;');
                    }
                    function formatDate(dateString) {
                        const date = new Date(dateString);
                        if (isNaN(date)) return escapeHtml(String(dateString));
                        const day = String(date.getDate()).padStart(2, '0');
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const year = String(date.getFullYear()).slice(-2);
                        return `${day}.${month}.${year}`;
                    }
                    function formatMoney(n) {
                        if (n === null || n === undefined || n === '') return '–';
                        return Number(n).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                    function dateCell(saleDate, orderDate) {
                        var main = formatDate(saleDate);
                        if (!orderDate) return main;
                        var od = formatDate(orderDate);
                        if (od === main) return main;
                        return main + '<div class="small text-muted">Order: ' + od + '</div>';
                    }
                    function orderLinksCell(oid, orderShowUrl, crmLink) {
                        var parts = [];
                        if (orderShowUrl) {
                            parts.push('<a href="' + escapeHtml(orderShowUrl) +
                                '" target="_blank" rel="noopener noreferrer">Open order</a>');
                        }
                        if (crmLink) {
                            parts.push('<a href="' + escapeHtml(crmLink) +
                                '" target="_blank" rel="noopener noreferrer">CRM</a>');
                        }
                        parts.push('<span class="text-muted">#' + escapeHtml(String(oid)) + '</span>');
                        return parts.join(' <span class="text-muted">·</span> ');
                    }

                    var $nextRm = $(hiddenRow).next();
                    while ($nextRm.length && $nextRm.hasClass('customer-details-lastRow')) {
                        var $kill = $nextRm;
                        $nextRm = $nextRm.next();
                        $kill.remove();
                    }

                    var rowsHtml = '';
                    if (!Array.isArray(response) || response.length === 0) {
                        hiddenRow.html('<td colspan="8" class="text-center text-muted py-3">No sale details found.</td>').show();
                        sessionStorage.setItem('hiddenRowVisible3', detailKey);
                        return;
                    }
                    response.forEach(function(product) {
                        if (!product) return;
                        rowsHtml +=
                            '<tr class="customer-details-lastRow alert alert-success">' +
                            '<td><h6>–</h6></td>' +
                            '<td><h6>' + dateCell(product.sale_date, product.order_date) + '</h6></td>' +
                            '<td><h6>' + escapeHtml(product.location) + '</h6></td>' +
                            '<td><h6>' + escapeHtml(product.customer_name) + '</h6></td>' +
                            '<td><h6>' + orderLinksCell(product.orderid, product.order_show_url, product.crm_link) + '</h6></td>' +
                            '<td><h6>' + Number(product.count).toLocaleString('en-US', {
                                maximumFractionDigits: 0
                            }) + '</h6></td>' +
                            '<td><h6 style="text-align: right;">' + formatMoney(product.price) + '</h6></td>' +
                            '<td><h6 style="text-align: right;">' + formatMoney(product.line_total) + '</h6></td>' +
                            '</tr>';
                    });
                    hiddenRow.after(rowsHtml);

                    // Mark hiddenRow as loaded
                    hiddenRow.empty().addClass('loaded').hide();
                    sessionStorage.setItem('hiddenRowVisible3', detailKey);
                },
                error: function() {
                    hiddenRow.html('<td colspan="8" class="text-center text-danger py-3">Unable to load sale details. Click the row to retry.</td>').show();
                    showProductReportStatus('Unable to load product sale details. Please retry.', 'danger');
                    sessionStorage.setItem('hiddenRowVisible3', '');
                },
                complete: function() {
                    ajaxInProgress = false;
                }
            });
        } else {
            $('.customer-details-lastRow').remove();
            hiddenRow.empty().hide();
            sessionStorage.setItem('hiddenRowVisible3', '');
            ajaxInProgress = false;
        }
    });
</script>
<script type="application/json" id="productChartData">{!! json_encode($salesData1, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script>
    var ajaxInProgress = false;
    // Use jQuery instead of $ to avoid conflicts with other libraries
    jQuery(document).ready(function($) {
        let currentPage = 0;
        const itemsPerPage = 10; // Adjust as needed

        // Chart series: sort by total sales (high → low) so the x-order matches "rank by
        // revenue". A regression on alphabetical order is usually flat at the mean (looks
        // "broken"); rank order gives a meaningful downward trendline for comparison.
        const salesDataRaw = JSON.parse(document.getElementById('productChartData').textContent || '[]');
        const salesData = Array.isArray(salesDataRaw) ?
            salesDataRaw.slice().sort(function(a, b) {
                return Number(b.total_sales) - Number(a.total_sales);
            }) : [];
        const labels = salesData.map(function(item) {
            return item.product_name;
        });
        const sales = salesData.map(function(item) {
            var v = Number(item.total_sales);
            return Number.isFinite(v) ? v : 0;
        });

        function paginateData(data, page, perPage) {
            const start = page * perPage;
            return data.slice(start, start + perPage);
        }

        function calculateRollingAverage(data, windowSize) {
            let rollingAverages = [];
            for (let i = 0; i < data.length; i++) {
                if (i < windowSize - 1) {
                    rollingAverages.push(null); // Not enough data to calculate rolling average
                } else {
                    let windowData = data.slice(i - windowSize + 1, i + 1);
                    let sum = windowData.reduce((acc, val) => acc + val, 0);
                    rollingAverages.push(sum / windowSize);
                }
            }
            return rollingAverages;
        }

        function calculateTrendline(data) {
            const n = data.length;
            if (n === 0) {
                return [];
            }
            if (n === 1) {
                return [data[0]];
            }
            const sumX = data.reduce(function(acc, val, idx) {
                return acc + idx;
            }, 0);
            const sumY = data.reduce(function(acc, val) {
                return acc + val;
            }, 0);
            const sumXY = data.reduce(function(acc, val, idx) {
                return acc + idx * val;
            }, 0);
            const sumX2 = data.reduce(function(acc, val, idx) {
                return acc + idx * idx;
            }, 0);

            const denom = n * sumX2 - sumX * sumX;
            if (!Number.isFinite(denom) || Math.abs(denom) < 1e-12) {
                var meanY = sumY / n;
                return data.map(function() {
                    return meanY;
                });
            }
            const slope = (n * sumXY - sumX * sumY) / denom;
            const intercept = (sumY - slope * sumX) / n;

            return data.map(function(val, idx) {
                return slope * idx + intercept;
            });
        }

        const rollingWindow = 3;
        const rollingAverage = calculateRollingAverage(sales, rollingWindow);
        const trendline = calculateTrendline(sales);

        function updateChart(chart, page) {
            const paginatedLabels = paginateData(labels, page, itemsPerPage);
            const paginatedSales = paginateData(sales, page, itemsPerPage);
            const paginatedRollingAvg = paginateData(rollingAverage, page, itemsPerPage);
            const paginatedTrendline = paginateData(trendline, page, itemsPerPage);

            chart.data.labels = paginatedLabels;
            chart.data.datasets[0].data = paginatedSales;
            chart.data.datasets[1].data = paginatedRollingAvg;
            chart.data.datasets[2].data = paginatedTrendline;

            chart.update();

            $("#pageInfo").text(`Page ${page + 1} of ${Math.ceil(salesData.length / itemsPerPage)}`);
            $("#prevPage").prop("disabled", page === 0);
            $("#nextPage").prop("disabled", page >= Math.ceil(salesData.length / itemsPerPage) - 1);
        }

        if (typeof Chart !== 'undefined' && document.getElementById('salesChart')) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                            label: 'Total Sales',
                            data: [],
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            type: 'line',
                            label: 'Rolling Average (3)',
                            data: [],
                            backgroundColor: 'rgba(255, 159, 64, 0.2)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1,
                            borderDash: [5, 5],
                            fill: true,
                            tension: 0,
                            spanGaps: false
                        },
                        {
                            type: 'line',
                            label: 'Trendline (linear fit)',
                            data: [],
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1,
                            borderDash: [10, 5],
                            fill: false,
                            tension: 0,
                            spanGaps: false
                        }
                    ]
                },
                options: {
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Product (ranked by total sales, high → low)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Sales'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var ds = context.dataset.label || 'Value';
                                    var raw = context.raw;
                                    if (raw === null || raw === undefined ||
                                        (typeof raw === 'number' && isNaN(raw))) {
                                        return ds + ': —';
                                    }
                                    return ds + ': ' + Number(raw).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    }
                }
            });

            // Update chart with initial page
            updateChart(chart, currentPage);

            // Pagination controls
            $('#prevPage').click(function() {
                if (currentPage > 0) {
                    currentPage--;
                    updateChart(chart, currentPage);
                }
            });

            $('#nextPage').click(function() {
                if (currentPage < Math.ceil(salesData.length / itemsPerPage) - 1) {
                    currentPage++;
                    updateChart(chart, currentPage);
                }
            });
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"
    integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous">
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"
    integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous">
</script>
@endsection
