<style>
    .app-nav .btn {
        margin: 4px;
    }

    @media (max-width: 767.98px) {
        .app-nav-mobile {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            padding: 10px;
            margin-bottom: 12px;
        }

        .app-nav-mobile .btn {
            width: 100%;
            margin: 0 0 8px 0;
            text-align: left;
        }

        .app-nav-mobile .btn:last-child {
            margin-bottom: 0;
        }
    }
</style>

<!-- Desktop/Tablet View -->
<div class="card d-none d-md-block mb-3 app-nav">
    <div class="card-body d-flex flex-wrap align-items-center">
        <a href="/93WwgVzcc9shQaxnd34c" class="btn {{ request()->is('93WwgVzcc9shQaxnd34c') ? 'btn-secondary' : 'btn-primary' }}">Data Entry</a>
        <a href="{{ route('saleitems.view') }}" class="btn {{ request()->routeIs('saleitems.view') ? 'btn-secondary' : 'btn-primary' }}">SoldRegistry</a>
        <a href="{{ route('report') }}" class="btn {{ request()->routeIs('report') ? 'btn-secondary' : 'btn-primary' }}">Daily Sales</a>
        <a href="/report/all" class="btn {{ request()->is('report/all') ? 'btn-secondary' : 'btn-primary' }}">Customers</a>
        <a href="/report/all/product" class="btn {{ request()->is('report/all/product') ? 'btn-secondary' : 'btn-primary' }}">Products</a>
        <a href="{{ route('ict') }}" class="btn {{ request()->routeIs('ict') ? 'btn-secondary' : 'btn-primary' }}">Farrow & Ball</a>
        <a href="{{ route('order-delivery.index') }}" class="btn {{ request()->routeIs('order-delivery.*') ? 'btn-secondary' : 'btn-primary' }}">Orders</a>
        <a href="{{ route('timesheet.index') }}" class="btn {{ request()->routeIs('timesheet.index') ? 'btn-secondary' : 'btn-primary' }}">Payroll</a>
    </div>
</div>

<!-- Mobile View -->
<div class="d-md-none app-nav-mobile">
    <a href="/93WwgVzcc9shQaxnd34c" class="btn {{ request()->is('93WwgVzcc9shQaxnd34c') ? 'btn-secondary' : 'btn-outline-primary' }}">Data Entry</a>
    <a href="{{ route('saleitems.view') }}" class="btn {{ request()->routeIs('saleitems.view') ? 'btn-secondary' : 'btn-outline-primary' }}">SoldRegistry</a>
    <a href="{{ route('report') }}" class="btn {{ request()->routeIs('report') ? 'btn-secondary' : 'btn-outline-primary' }}">Daily Sales</a>
    <a href="/report/all" class="btn {{ request()->is('report/all') ? 'btn-secondary' : 'btn-outline-primary' }}">Customers</a>
    <a href="/report/all/product" class="btn {{ request()->is('report/all/product') ? 'btn-secondary' : 'btn-outline-primary' }}">Products</a>
    <a href="{{ route('ict') }}" class="btn {{ request()->routeIs('ict') ? 'btn-secondary' : 'btn-outline-primary' }}">Farrow & Ball</a>
    <a href="{{ route('order-delivery.index') }}" class="btn {{ request()->routeIs('order-delivery.*') ? 'btn-secondary' : 'btn-outline-primary' }}">Orders</a>
    <a href="{{ route('timesheet.index') }}" class="btn {{ request()->routeIs('timesheet.index') ? 'btn-secondary' : 'btn-outline-primary' }}">Payroll</a>
</div>