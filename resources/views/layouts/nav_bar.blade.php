<!-- Desktop View: Horizontal Buttons (Accurate to original request) -->
<div class="card d-none d-lg-block mb-3">
    <div class="card-body d-flex flex-wrap">
        <a href="/93WwgVzcc9shQaxnd34c" class="btn {{ request()->is('93WwgVzcc9shQaxnd34c') ? 'btn-secondary' : 'btn-primary' }}" style="margin: 5px;">Data Entry</a>
        <a href="{{ route('saleitems.view') }}" class="btn {{ request()->routeIs('saleitems.view') ? 'btn-secondary' : 'btn-primary' }}" style="margin: 5px;">SoldRegistry</a>
        <a href="{{ route('report') }}" class="btn {{ request()->routeIs('report') ? 'btn-secondary' : 'btn-primary' }}" style="margin: 5px;">Daily Sales</a>
        <a href="/report/all" class="btn {{ request()->is('report/all') ? 'btn-secondary' : 'btn-primary' }}" style="margin: 5px;">Customers</a>
        <a href="/report/all/product" class="btn {{ request()->is('report/all/product') ? 'btn-secondary' : 'btn-primary' }}" style="margin: 5px;">Products</a>
        <a href="{{ route('ict') }}" class="btn {{ request()->routeIs('ict') ? 'btn-secondary' : 'btn-primary' }}" style="margin: 5px;">Farrow & Ball</a>
        <a href="{{ route('order-delivery.index') }}" class="btn {{ request()->routeIs('order-delivery.*') ? 'btn-secondary' : 'btn-primary' }}" style="margin: 5px;">Order Delivery</a>
        <a href="{{ route('payroll.index') }}" class="btn {{ request()->routeIs('payroll.*') ? 'btn-secondary' : 'btn-primary' }}" style="margin: 5px;">Payroll</a>
    </div>
</div>

<!-- Mobile View: Navbar with Offcanvas Sidebar -->
<nav class="navbar navbar-light bg-white border d-lg-none mb-3 rounded shadow-sm">
    <div class="container-fluid">
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</nav>

<!-- Offcanvas Sidebar Component -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
    <div class="offcanvas-header bg-light border-bottom">
        <h5 class="offcanvas-title fw-bold text-primary" id="mobileMenuLabel">Navigation</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="d-grid gap-2">
            <span class="text-uppercase text-muted fw-bold small mb-2">Apps</span>
            <a href="/93WwgVzcc9shQaxnd34c" class="btn btn-outline-secondary text-start py-2">
                Data Entry
            </a>
            <a href="{{ route('saleitems.view') }}" class="btn btn-outline-primary text-start py-2">
                SoldRegistry
            </a>
            <a href="{{ route('report') }}" class="btn btn-outline-primary text-start py-2">
                Daily Sales
            </a>
            <a href="/report/all" class="btn btn-outline-primary text-start py-2">
                Customers
            </a>
            <a href="/report/all/product" class="btn btn-outline-primary text-start py-2">
                Products
            </a>
            <a href="{{ route('ict') }}" class="btn btn-outline-primary text-start py-2">
                Farrow & Ball
            </a>
            <div class="border-top my-2"></div>
            <a href="{{ route('order-delivery.index') }}" class="btn btn-primary text-start py-2 shadow-sm">
                Order Delivery
            </a>
            <a href="{{ route('payroll.index') }}" class="btn btn-primary text-start py-2 shadow-sm mt-2">
                Payroll
            </a>
        </div>
    </div>
</div>