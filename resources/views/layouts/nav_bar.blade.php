<style>
    .shop-nav-shell {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        padding: 12px;
    }

    .shop-nav-scroll,
    .shop-nav-account {
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .shop-nav-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
        white-space: nowrap;
    }

    .shop-nav-account {
        margin-left: auto;
        padding-left: 10px;
        border-left: 1px solid #e2e8f0;
    }

    .shop-nav-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 8px 12px;
        border: 1px solid #dbe5f1;
        border-radius: 9px;
        background: #f8fafc;
        color: #334155;
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
        text-decoration: none !important;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
    }

    .shop-nav-link:hover {
        border-color: #93c5fd;
        background: #eff6ff;
        color: #1d4ed8;
        transform: translateY(-1px);
    }

    .shop-nav-link.is-active {
        border-color: #2563eb;
        background: #2563eb;
        color: #fff;
        box-shadow: 0 3px 8px rgba(37, 99, 235, 0.22);
    }

    .shop-nav-link.sms-link {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .shop-nav-link.sms-link.is-active {
        border-color: #2563eb;
        background: #2563eb;
        color: #fff;
    }

    .shop-nav-link.logout-link {
        border-color: #fecaca;
        background: #fff;
        color: #dc2626;
        cursor: pointer;
    }

    .shop-nav-link.logout-link:hover {
        border-color: #ef4444;
        background: #fef2f2;
        color: #b91c1c;
    }

    .shop-nav-mobile {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        padding: 12px;
        margin-bottom: 12px;
    }

    .shop-nav-mobile-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .shop-nav-mobile .shop-nav-link,
    .shop-nav-mobile form,
    .shop-nav-mobile form .shop-nav-link {
        width: 100%;
    }

    .shop-nav-mobile-account {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
    }

    @media (max-width: 420px) {
        .shop-nav-mobile-grid,
        .shop-nav-mobile-account {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $navItems = [
        ['label' => 'Data Entry', 'url' => url('/93WwgVzcc9shQaxnd34c'), 'active' => request()->is('93WwgVzcc9shQaxnd34c')],
        ['label' => 'Sold Registry', 'url' => route('saleitems.view'), 'active' => request()->routeIs('saleitems.view')],
        ['label' => 'Daily Sales', 'url' => route('report'), 'active' => request()->routeIs('report')],
        ['label' => 'Customers', 'url' => url('/report/all'), 'active' => request()->is('report/all')],
        ['label' => 'Products', 'url' => url('/report/all/product'), 'active' => request()->is('report/all/product')],
        ['label' => 'Farrow & Ball', 'url' => route('ict'), 'active' => request()->routeIs('ict')],
        ['label' => 'Order Delivery', 'url' => route('order-delivery.index'), 'active' => request()->routeIs('order-delivery.*')],
    ];

    if (\Illuminate\Support\Facades\Route::has('timesheet.index')) {
        $navItems[] = ['label' => 'Payroll', 'url' => route('timesheet.index'), 'active' => request()->routeIs('timesheet.index')];
    }
@endphp

<nav class="shop-nav-shell d-none d-md-block mb-3" aria-label="SHOP navigation">
    <div class="shop-nav-scroll">
        @foreach($navItems as $item)
            <a href="{{ $item['url'] }}" class="shop-nav-link {{ $item['active'] ? 'is-active' : '' }}">
                {{ $item['label'] }}
            </a>
        @endforeach

        @auth
            <a href="{{ route('sms.index') }}" class="shop-nav-link sms-link {{ request()->routeIs('sms.*') ? 'is-active' : '' }}">
                SMS Solution
            </a>

            <div class="shop-nav-account">
                @if(auth()->user()?->is_admin)
                    <a href="{{ route('admin.users.index') }}" class="shop-nav-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">Users</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="shop-nav-link logout-link">Logout</button>
                </form>
            </div>
        @endauth
    </div>
</nav>

<nav class="shop-nav-mobile d-md-none" aria-label="SHOP mobile navigation">
    <div class="shop-nav-mobile-grid">
        @foreach($navItems as $item)
            <a href="{{ $item['url'] }}" class="shop-nav-link {{ $item['active'] ? 'is-active' : '' }}">
                {{ $item['label'] }}
            </a>
        @endforeach

        @auth
            <a href="{{ route('sms.index') }}" class="shop-nav-link sms-link {{ request()->routeIs('sms.*') ? 'is-active' : '' }}">
                SMS Solution
            </a>
        @endauth
    </div>

    @auth
        <div class="shop-nav-mobile-account">
            @if(auth()->user()?->is_admin)
                <a href="{{ route('admin.users.index') }}" class="shop-nav-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">Users</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="shop-nav-link logout-link">Logout</button>
            </form>
        </div>
    @endauth
</nav>
