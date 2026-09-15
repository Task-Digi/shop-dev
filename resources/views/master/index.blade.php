@extends('layouts.app')

@section('content')
<div class="card">
    @include('layouts.nav_bar')

    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Master Data Management</h4>
            <a href="{{ route('report') }}" class="btn btn-secondary btn-sm">Back to Daily Sales</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success:</strong> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error:</strong> {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- Navigation Buttons -->
        <div class="mb-3">
            <a href="{{ route('master.index', ['tab' => 'customers']) }}" class="btn {{ $activeTab === 'customers' ? 'btn-secondary' : 'btn-primary' }}">
                Customers ({{ $totalCustomers }})
            </a>
            <a href="{{ route('master.index', ['tab' => 'products']) }}" class="btn {{ $activeTab === 'products' ? 'btn-secondary' : 'btn-primary' }}">
                Products ({{ $totalProducts }})
            </a>
            <a href="{{ route('master.index', ['tab' => 'sales']) }}" class="btn {{ $activeTab === 'sales' ? 'btn-secondary' : 'btn-primary' }}">
                Sales Data Table ({{ $totalSalesCount }})
            </a>
            <a href="{{ route('master.index', ['tab' => 'orders']) }}" class="btn {{ $activeTab === 'orders' ? 'btn-secondary' : 'btn-primary' }}">
                Orders & Returns
            </a>
        </div>

        <!-- ================= TAB 1: CUSTOMERS ================= -->
        @if($activeTab === 'customers')
        <div class="card mb-3">
            <div class="card-body bg-light">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <form action="{{ route('master.index') }}" method="GET" class="form-inline">
                            <input type="hidden" name="tab" value="customers">
                            <div class="input-group w-100">
                                <input type="text" name="search" class="form-control" placeholder="Search Customer ID or Name..." value="{{ $search }}">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    @if(!empty($search) || $filterMissing)
                                        <a href="{{ route('master.index', ['tab' => 'customers']) }}" class="btn btn-outline-secondary">Reset</a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-5 text-md-right mt-2 mt-md-0">
                        <a href="{{ route('master.index', ['tab' => 'customers', 'missing_only' => $filterMissing ? 0 : 1]) }}" 
                           class="btn {{ $filterMissing ? 'btn-warning text-dark font-weight-bold' : 'btn-outline-warning text-dark' }}">
                            {{ $filterMissing ? 'Showing Missing Names' : 'Filter Missing Names' }} ({{ $missingCustomersCount }})
                        </a>
                        <button type="button" class="btn btn-success ml-2" onclick="openCustomerModal('', '')">
                            + Add Customer
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width: 160px;">Customer ID</th>
                            <th>Customer / Company Name</th>
                            <th style="width: 120px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $c)
                        <tr>
                            <td><strong>{{ $c->customer_id }}</strong></td>
                            <td>
                                @if(str_starts_with($c->customer_name ?? '', 'customer_') || empty($c->customer_name))
                                    <span class="badge badge-danger">Missing Name</span>
                                    <span class="text-muted ml-1">{{ $c->customer_name }}</span>
                                @else
                                    <span class="font-weight-bold">{{ $c->customer_name }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary" onclick="openCustomerModal('{{ $c->customer_id }}', '{{ addslashes($c->customer_name) }}')">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">No customers found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($customers->hasPages())
            <div class="card-footer bg-white">
                {{ $customers->appends(['tab' => 'customers', 'search' => $search, 'missing_only' => $filterMissing])->links() }}
            </div>
            @endif
        </div>
        @endif

        <!-- ================= TAB 2: PRODUCTS ================= -->
        @if($activeTab === 'products')
        <div class="card mb-3">
            <div class="card-body bg-light">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <form action="{{ route('master.index') }}" method="GET" class="form-inline">
                            <input type="hidden" name="tab" value="products">
                            <div class="input-group w-100">
                                <input type="text" name="search" class="form-control" placeholder="Search Product ID or Name..." value="{{ $search }}">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    @if(!empty($search) || $filterMissing)
                                        <a href="{{ route('master.index', ['tab' => 'products']) }}" class="btn btn-outline-secondary">Reset</a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-5 text-md-right mt-2 mt-md-0">
                        <a href="{{ route('master.index', ['tab' => 'products', 'missing_only' => $filterMissing ? 0 : 1]) }}" 
                           class="btn {{ $filterMissing ? 'btn-warning text-dark font-weight-bold' : 'btn-outline-warning text-dark' }}">
                            {{ $filterMissing ? 'Showing Missing Names' : 'Filter Missing Names' }} ({{ $missingProductsCount }})
                        </a>
                        <button type="button" class="btn btn-success ml-2" onclick="openProductModal('', '')">
                            + Add Product
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width: 160px;">Product ID</th>
                            <th>Product Name</th>
                            <th style="width: 120px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $p)
                        <tr>
                            <td><strong>{{ $p->product_id }}</strong></td>
                            <td>
                                @if(str_starts_with($p->product_name ?? '', 'product_') || empty($p->product_name) || $p->product_name == $p->product_id)
                                    <span class="badge badge-danger">Missing Name</span>
                                    <span class="text-muted ml-1">{{ $p->product_name }}</span>
                                @else
                                    <span class="font-weight-bold">{{ $p->product_name }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary" onclick="openProductModal('{{ $p->product_id }}', '{{ addslashes($p->product_name) }}')">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">No products found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
            <div class="card-footer bg-white">
                {{ $products->appends(['tab' => 'products', 'search' => $search, 'missing_only' => $filterMissing])->links() }}
            </div>
            @endif
        </div>
        @endif

        <!-- ================= TAB 3: SALES DATA TABLE ================= -->
        @if($activeTab === 'sales')
        <div class="card mb-3">
            <div class="card-body bg-light">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <form action="{{ route('master.index') }}" method="GET" class="form-inline">
                            <input type="hidden" name="tab" value="sales">
                            <div class="input-group w-100">
                                <input type="text" name="search" class="form-control" placeholder="Search Order ID, Customer ID/Name, Product ID/Name, Date..." value="{{ $search }}">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">Search Sales</button>
                                    @if(!empty($search))
                                        <a href="{{ route('master.index', ['tab' => 'sales']) }}" class="btn btn-outline-secondary">Reset</a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4 text-md-right mt-2 mt-md-0">
                        <span class="text-muted font-weight-bold">Total Sales Records: {{ number_format($totalSalesCount) }}</span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th style="width: 100px;">Date</th>
                            <th style="width: 110px;">Order ID</th>
                            <th>Customer (ID / Name)</th>
                            <th>Product (ID / Name)</th>
                            <th style="width: 80px;" class="text-center">Qty</th>
                            <th style="width: 100px;" class="text-right">Price</th>
                            <th style="width: 100px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $s)
                        <tr>
                            <td>{{ $s->id }}</td>
                            <td>{{ $s->date }}</td>
                            <td><strong>{{ $s->orderid }}</strong></td>
                            <td>
                                <span class="badge badge-info">{{ $s->customer_id }}</span>
                                <span class="font-weight-bold ml-1">{{ $s->customer_name }}</span>
                            </td>
                            <td>
                                <span class="badge badge-secondary">{{ $s->product_id }}</span>
                                <span class="font-weight-bold ml-1">{{ $s->product_name }}</span>
                            </td>
                            <td class="text-center">
                                @if($s->count < 0)
                                    <span class="badge badge-danger">{{ $s->count }}</span>
                                @else
                                    <span class="badge badge-success">{{ $s->count }}</span>
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($s->price, 2) }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary" onclick="openSaleModal({{ json_encode($s) }})">
                                    Edit Row
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No sales records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($sales->hasPages())
            <div class="card-footer bg-white">
                {{ $sales->appends(['tab' => 'sales', 'search' => $search])->links() }}
            </div>
            @endif
        </div>
        @endif

        <!-- ================= TAB 4: ORDERS & RETURNS ================= -->
        @if($activeTab === 'orders')
        <div class="card mb-3">
            <div class="card-body bg-light">
                <form action="{{ route('master.index') }}" method="GET" class="form-inline">
                    <input type="hidden" name="tab" value="orders">
                    <label class="mr-2 font-weight-bold">Order ID:</label>
                    <div class="input-group">
                        <input type="text" name="order_id" class="form-control" placeholder="Enter Order ID (e.g. 673611, 734503)" value="{{ $orderId }}" required>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">Fetch Order Items</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                @if(!empty($orderId))
                    <div class="p-3 bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center">
                        <div>
                            <strong>Order:</strong> <span class="badge badge-primary px-2 py-1" style="font-size: 1rem;">{{ $orderId }}</span>
                            @if($orderCustomer)
                                <span class="ml-3"><strong>Customer:</strong> {{ $orderCustomer['name'] }} ({{ $orderCustomer['id'] }})</span>
                                <span class="ml-3 text-muted"><strong>Date:</strong> {{ $orderCustomer['date'] }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="badge badge-secondary">Total Lines: {{ count($orderItems) }}</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 70px;">ID</th>
                                    <th style="width: 140px;">Product ID</th>
                                    <th>Product Name</th>
                                    <th style="width: 120px;" class="text-center">Qty / Count</th>
                                    <th style="width: 120px;" class="text-right">Unit Price</th>
                                    <th style="width: 130px;" class="text-right">Total Price</th>
                                    <th style="width: 160px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orderItems as $item)
                                <tr class="{{ $item->count < 0 ? 'table-warning' : '' }}">
                                    <td>{{ $item->id }}</td>
                                    <td><strong>{{ $item->product_id }}</strong></td>
                                    <td>{{ $item->product_name }}</td>
                                    <td class="text-center">
                                        @if($item->count < 0)
                                            <span class="badge badge-danger px-2 py-1" style="font-size: 0.9rem;">{{ $item->count }} (Return)</span>
                                        @elseif($item->count == 0)
                                            <span class="badge badge-secondary px-2 py-1" style="font-size: 0.9rem;">0</span>
                                        @else
                                            <span class="badge badge-success px-2 py-1" style="font-size: 0.9rem;">{{ $item->count }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($item->price, 2) }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format($item->count * $item->price, 2) }}</td>
                                    <td class="text-center">
                                        <!-- Adjust Count Form -->
                                        <form action="{{ route('master.order.adjust') }}" method="POST" class="d-inline-flex align-items-center">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $item->id }}">
                                            <input type="hidden" name="order_id" value="{{ $orderId }}">
                                            <input type="number" name="count" class="form-control form-control-sm text-center mr-1" style="width: 65px;" value="{{ $item->count }}" step="1" required>
                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        </form>

                                        <!-- Delete Item Form -->
                                        <form action="{{ route('master.order.delete') }}" method="POST" class="d-inline ml-1" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $item->id }}">
                                            <input type="hidden" name="order_id" value="{{ $orderId }}">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No items found for Order ID {{ $orderId }}.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <p class="mb-0">Please enter an Order ID above to view and adjust order items.</p>
                    </div>
                @endif
            </div>
        </div>
        @endif

    </div>
</div>

<!-- Modal: Edit Customer -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" role="dialog" aria-labelledby="customerModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('master.customer.update') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="customerModalTitle">Edit Customer</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="$('#editCustomerModal').modal('hide')">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Customer ID <span class="text-danger">*</span></label>
                    <input type="text" id="modalCustomerId" name="customer_id" class="form-control" placeholder="e.g. 32802" required>
                </div>
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Customer Name <span class="text-danger">*</span></label>
                    <input type="text" id="modalCustomerName" name="customer_name" class="form-control" placeholder="e.g. INDIAN CLUB HOUSE AS" required>
                    <small class="form-text text-muted">Auto-updates in customers and sale_data tables.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="$('#editCustomerModal').modal('hide')">Cancel</button>
                <button type="submit" class="btn btn-success font-weight-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Product -->
<div class="modal fade" id="editProductModal" tabindex="-1" role="dialog" aria-labelledby="productModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('master.product.update') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="productModalTitle">Edit Product</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="$('#editProductModal').modal('hide')">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Product ID <span class="text-danger">*</span></label>
                    <input type="text" id="modalProductId" name="product_id" class="form-control" placeholder="e.g. 319763" required>
                </div>
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Product Name <span class="text-danger">*</span></label>
                    <input type="text" id="modalProductName" name="product_name" class="form-control" placeholder="e.g. SLIPERULL GOLD PROFL P1.20" required>
                    <small class="form-text text-muted">Auto-updates in products and sale_data tables.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="$('#editProductModal').modal('hide')">Cancel</button>
                <button type="submit" class="btn btn-success font-weight-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Sales Record -->
<div class="modal fade" id="editSaleModal" tabindex="-1" role="dialog" aria-labelledby="saleModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{ route('master.sale.update') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" id="modalSaleId" name="id" value="">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="saleModalTitle">Edit Sales Record</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="$('#editSaleModal').modal('hide')">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="font-weight-bold">Order ID</label>
                        <input type="text" id="modalSaleOrderId" class="form-control" readonly disabled>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label class="font-weight-bold">Date</label>
                        <input type="date" id="modalSaleDate" name="date" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold">Customer ID <span class="text-danger">*</span></label>
                        <input type="text" id="modalSaleCustomerId" name="customer_id" class="form-control" required>
                    </div>
                    <div class="col-md-8 form-group mb-3">
                        <label class="font-weight-bold">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" id="modalSaleCustomerName" name="customer_name" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold">Product ID <span class="text-danger">*</span></label>
                        <input type="text" id="modalSaleProductId" name="product_id" class="form-control" required>
                    </div>
                    <div class="col-md-8 form-group mb-3">
                        <label class="font-weight-bold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" id="modalSaleProductName" name="product_name" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold">Quantity (Count) <span class="text-danger">*</span></label>
                        <input type="number" id="modalSaleCount" name="count" class="form-control" step="1" required>
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold">Price <span class="text-danger">*</span></label>
                        <input type="number" id="modalSalePrice" name="price" class="form-control" step="0.01" required>
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold">Retail Price</label>
                        <input type="number" id="modalSaleRetail" name="retail" class="form-control" step="0.01">
                    </div>
                </div>
                <small class="text-muted">
                    Updating this row will auto-sync across <code>sale_data</code>, <code>sales_lists</code>, <code>customers</code>, and <code>products</code> tables safely.
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="$('#editSaleModal').modal('hide')">Cancel</button>
                <button type="submit" class="btn btn-success font-weight-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCustomerModal(id, name) {
    document.getElementById('modalCustomerId').value = id || '';
    document.getElementById('modalCustomerName').value = name || '';
    document.getElementById('customerModalTitle').innerText = id ? 'Edit Customer [' + id + ']' : 'Add New Customer';
    
    if (window.jQuery && typeof jQuery.fn.modal !== 'undefined') {
        $('#editCustomerModal').modal('show');
    } else {
        var myModal = new bootstrap.Modal(document.getElementById('editCustomerModal'));
        myModal.show();
    }
}

function openProductModal(id, name) {
    document.getElementById('modalProductId').value = id || '';
    document.getElementById('modalProductName').value = name || '';
    document.getElementById('productModalTitle').innerText = id ? 'Edit Product [' + id + ']' : 'Add New Product';
    
    if (window.jQuery && typeof jQuery.fn.modal !== 'undefined') {
        $('#editProductModal').modal('show');
    } else {
        var myModal = new bootstrap.Modal(document.getElementById('editProductModal'));
        myModal.show();
    }
}

function openSaleModal(sale) {
    document.getElementById('modalSaleId').value = sale.id || '';
    document.getElementById('modalSaleOrderId').value = sale.orderid || '';
    document.getElementById('modalSaleDate').value = sale.date || '';
    document.getElementById('modalSaleCustomerId').value = sale.customer_id || '';
    document.getElementById('modalSaleCustomerName').value = sale.customer_name || '';
    document.getElementById('modalSaleProductId').value = sale.product_id || '';
    document.getElementById('modalSaleProductName').value = sale.product_name || '';
    document.getElementById('modalSaleCount').value = sale.count !== undefined ? sale.count : '';
    document.getElementById('modalSalePrice').value = sale.price !== undefined ? sale.price : '';
    document.getElementById('modalSaleRetail').value = sale.retail !== undefined ? sale.retail : (sale.price || '');
    
    document.getElementById('saleModalTitle').innerText = 'Edit Sales Record #' + sale.id + ' (Order #' + sale.orderid + ')';

    if (window.jQuery && typeof jQuery.fn.modal !== 'undefined') {
        $('#editSaleModal').modal('show');
    } else {
        var myModal = new bootstrap.Modal(document.getElementById('editSaleModal'));
        myModal.show();
    }
}
</script>
@endsection
