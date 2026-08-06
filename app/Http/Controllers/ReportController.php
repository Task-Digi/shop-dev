<?php

namespace App\Http\Controllers;

use App\Models\SalesList;
use App\Models\ICT;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * Weighted average unit price (sale_data.price × qty) for the current query GROUP BY.
     *
     * @param  string|null  $tableAlias  e.g. "sale_data" when the query uses a table alias
     */
    protected function weightedUnitPriceAvg(?string $tableAlias = null): \Illuminate\Database\Query\Expression
    {
        $c = $tableAlias ? "{$tableAlias}.count" : 'count';
        $p = $tableAlias ? "{$tableAlias}.price" : 'price';

        return DB::raw("CASE WHEN SUM({$c}) > 0 THEN ROUND(SUM({$c} * {$p}) / SUM({$c}), 2) ELSE NULL END as unit_price_avg");
    }

    /**
     * Remove leading * from names (legacy ERP marker stored in the database).
     */
    protected function stripLeadingAsteriskSql(string $columnExpr): string
    {
        return "TRIM(LEADING '*' FROM TRIM({$columnExpr}))";
    }

    /**
     * Display name for product reports: sale_data name, then products table, then product_id.
     */
    protected function resolvedProductNameSql(string $saleAlias = 'sale_data', string $productAlias = 'products'): string
    {
        $saleName = $this->stripLeadingAsteriskSql("{$saleAlias}.product_name");
        $catalogName = $this->stripLeadingAsteriskSql("{$productAlias}.product_name");

        return "COALESCE(
            NULLIF({$saleName}, ''),
            NULLIF({$catalogName}, ''),
            NULLIF(TRIM({$saleAlias}.product_id), '')
        )";
    }

    protected function resolvedProductNameSelect(string $saleAlias = 'sale_data', string $productAlias = 'products'): \Illuminate\Database\Query\Expression
    {
        $expr = $this->resolvedProductNameSql($saleAlias, $productAlias);

        return DB::raw("MAX({$expr}) AS product_name");
    }

    protected function applyProductNameSearch($query, string $searchName, string $saleAlias = 'sale_data', string $productAlias = 'products'): void
    {
        $like = '%' . addcslashes($searchName, '%_\\') . '%';
        $resolved = $this->resolvedProductNameSql($saleAlias, $productAlias);

        $query->where(function ($q) use ($like, $resolved, $saleAlias, $productAlias) {
            $q->whereRaw("{$resolved} LIKE ?", [$like])
                ->orWhere("{$saleAlias}.product_id", 'LIKE', $like)
                ->orWhere("{$productAlias}.product_id", 'LIKE', $like)
                ->orWhere("{$saleAlias}.product_name", 'LIKE', $like)
                ->orWhere("{$productAlias}.product_name", 'LIKE', $like);
        });
    }

    public function index(Request $request, $customerId)
    {
        $request->validate([
            'days' => 'nullable|in:0,7,28,56',
            'per_page' => 'nullable|integer|in:10,25,50,100,200',
            'search' => 'nullable|string|max:100',
        ]);

        // Get the days filter, defaulting to 7 days
        $days = $request->input('days', 7);
        $search = trim((string) $request->input('search', ''));

        // Search form uses CustomerName route; empty search means all customers.
        if ($customerId === 'CustomerName' && $search === '') {
            $customerId = 'all';
        }

        // Initialize the sales data queries
        $salesData1Query = DB::table('sale_data')
            ->select(
                'customer_id',
                'customer_name',
                DB::raw('SUM(count * price) as total_sales')
            )
            ->groupBy('customer_id', 'customer_name')
            ->orderBy('total_sales', 'DESC');

        $salesDataQuery = DB::table('sale_data')
            ->leftJoin('customers', 'sale_data.customer_id', '=', 'customers.customer_id')
            ->select(
                'sale_data.location',
                'sale_data.customer_id',
                'sale_data.customer_name',
                'customers.KS_exists',
                DB::raw('COUNT(DISTINCT sale_data.orderid) as order_id_count'),
                DB::raw('SUM(sale_data.count) as total_products_sold'),
                DB::raw('SUM(sale_data.count * sale_data.price) as total_sales'),
                $this->weightedUnitPriceAvg('sale_data')
            )
            ->groupBy('sale_data.location', 'sale_data.customer_id', 'sale_data.customer_name', 'customers.KS_exists')
            ->orderBy('total_sales', 'DESC');

        // Period filter (days = 0 means all time)
        if ((string) $days !== '0' && $days != 0) {
            $dateFrom = now()->subDays(max(0, (int) $days - 1))->toDateString();
            $salesData1Query->where('sale_data.date', '>=', $dateFrom);
            $salesDataQuery->where('sale_data.date', '>=', $dateFrom);
        }

        // Search or single-customer route filter
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $salesData1Query->where(function ($q) use ($like) {
                $q->where('sale_data.customer_name', 'LIKE', $like)
                    ->orWhere('sale_data.customer_id', 'LIKE', $like);
            });
            $salesDataQuery->where(function ($q) use ($like) {
                $q->where('sale_data.customer_name', 'LIKE', $like)
                    ->orWhere('sale_data.customer_id', 'LIKE', $like);
            });
            $customerId = 'CustomerName';
        } elseif ($customerId !== 'all' && $customerId !== 'CustomerName') {
            if (is_numeric($customerId)) {
                $salesData1Query->where('sale_data.customer_id', $customerId);
                $salesDataQuery->where('sale_data.customer_id', $customerId);
            } else {
                $salesData1Query->where('sale_data.customer_name', 'LIKE', '%' . addcslashes($customerId, '%_\\') . '%');
                $salesDataQuery->where('sale_data.customer_name', 'LIKE', '%' . addcslashes($customerId, '%_\\') . '%');
            }
        }

        // Execute the queries
        // $salesData1 drives the (currently collapsed) chart, so we limit it to the top 100 to prevent memory exhaustion.
        $salesData1 = $salesData1Query->limit(100)->get();

        // $salesData drives the main table — paginate it so the page stays
        // fast even with thousands of customers, and so the user gets proper
        // page navigation at the bottom.
        $perPage = (int) $request->input('per_page', 25);
        $salesData = $salesDataQuery->simplePaginate($perPage)->withQueryString();

        // Return both salesData and salesData1 to the view, along with search term
        return view('reports.customer', compact('salesData', 'salesData1', 'days', 'search', 'customerId'));
    }

    public function getCustomerReportData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'location' => 'required|string|max:255',
                'customer_id' => 'required|string|max:255',
                'days' => 'required|in:0,7,28,56',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer report filters.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Retrieve inputs
            $location = $request->input('location');
            $customerId = $request->input('customer_id');
            $days = $request->input('days');

            // Prepare the SQL query
            $query = "
                SELECT
                    orderid,
                    MAX(date) AS sales_date, -- Get the latest date for each order
                    location,
                    type,
                    payment,
                    customer_id,
                    customer_name,
                    MAX(crm_link) AS crm_link,
                    ROUND(SUM(count * price), 2) AS total_sales, -- Total sales for the order
                    ROUND(SUM(count * retail), 2) AS total_products_price, -- Total product price for the order
                    SUM(count) AS total_products_sold, -- Total products sold for the order
                    ROUND(SUM(count * price) / NULLIF(SUM(count), 0), 2) AS unit_price -- Weighted unit (cost) price for the order
                FROM
                    sale_data
                WHERE
                    location = ?
                    AND customer_id = ?
            ";

            // Add the date filtering condition based on the 'days' variable
            if ($days == 0) {
                $params = [$location, $customerId]; // No date filter, just location and customer_id
            } else {
                $startDate = now()->subDays(max(0, (int) $days - 1))->toDateString();
                $query .= " AND date >= ?";
                $params = [$location, $customerId, $startDate]; // Add the start date to the parameters
            }

            // Group and order the results
            $query .= "
                GROUP BY
                    orderid, location, type, payment, customer_id, customer_name
                ORDER BY
                    sales_date ASC
            ";

            // Fetch data from the database
            $customerData = DB::select($query, $params);

            return response()->json($customerData);
        } catch (\Exception $e) {
            Log::error('Failed to load customer order drill-down', [
                'error' => $e->getMessage(),
                'customer_id' => $request->input('customer_id'),
            ]);
            return response()->json(['success' => false, 'message' => 'Unable to load customer orders.'], 500);
        }
    }

    public function exportCustomers(Request $request)
    {
        $request->validate([
            'days' => 'nullable|in:0,7,28,56',
            'search' => 'nullable|string|max:100',
            'customer_id' => 'nullable|string|max:255',
        ]);

        $days = (string) $request->input('days', '7');
        $search = trim((string) $request->input('search', ''));
        $customerId = trim((string) $request->input('customer_id', ''));

        $query = DB::table('sale_data')
            ->select(
                'location',
                'customer_id',
                'customer_name',
                DB::raw('COUNT(DISTINCT orderid) as order_count'),
                DB::raw('SUM(count) as quantity_sold'),
                $this->weightedUnitPriceAvg(),
                DB::raw('SUM(count * price) as total_sales')
            )
            ->groupBy('location', 'customer_id', 'customer_name')
            ->orderBy('total_sales', 'DESC');

        if ($days !== '0') {
            $query->where('date', '>=', now()->subDays(max(0, (int) $days - 1))->toDateString());
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('customer_name', 'LIKE', $like)
                    ->orWhere('customer_id', 'LIKE', $like);
            });
        } elseif ($customerId !== '' && $customerId !== 'all' && $customerId !== 'CustomerName') {
            $query->where('customer_id', $customerId);
        }

        $filename = 'customer-sales-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Customer ID', 'Customer Name', 'Location', 'Orders', 'Sold', 'Unit Price', 'Total Sales']);

            foreach ($query->cursor() as $row) {
                fputcsv($output, [
                    $row->customer_id,
                    $row->customer_name,
                    $row->location,
                    $row->order_count,
                    $row->quantity_sold,
                    $row->unit_price_avg,
                    $row->total_sales,
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reportindex()
    {
        return view('reports.graph');
    }

    public function productIndex(Request $request, $productid)
    {
        $request->validate([
            'days' => 'nullable|in:7,30,90,all',
            'per_page' => 'nullable|integer|in:10,25,50,100,200',
            'search' => 'nullable|string|max:100',
        ]);

        $days = $request->input('days', 7);
        $searchName = trim((string) $request->input('search', ''));

        // Period/search form posts to ProductName route; without a search term that must mean "all".
        if ($productid === 'ProductName' && $searchName === '') {
            $productid = 'all';
        }

        // Only products that exist in the catalog (products table). Orphan sale_data rows
        // (e.g. mistyped product_id with no products row) are excluded from this report.
        $salesDataQuery = DB::table('sale_data')
            ->join('products', 'sale_data.product_id', '=', 'products.product_id')
            ->select(
                'sale_data.product_id',
                $this->resolvedProductNameSelect(),
                'sale_data.location',
                DB::raw('COUNT(DISTINCT sale_data.customer_id) AS customer_count'),
                DB::raw('COUNT(DISTINCT sale_data.orderid) AS order_id_count'),
                DB::raw('SUM(sale_data.count) AS product_id_count'),
                DB::raw('SUM(sale_data.count * sale_data.price) AS total_products_price'),
                $this->weightedUnitPriceAvg('sale_data')
            )
            ->whereNotNull('sale_data.product_id')
            ->where('sale_data.product_id', '!=', '')
            ->groupBy('sale_data.product_id', 'sale_data.location')
            ->orderBy('product_name', 'ASC');

        // Base query to retrieve total sales per product for graph data
        $salesData1Query = DB::table('sale_data')
            ->join('products', 'sale_data.product_id', '=', 'products.product_id')
            ->select(
                $this->resolvedProductNameSelect(),
                DB::raw('SUM(sale_data.count * sale_data.price) as total_sales')
            )
            ->whereNotNull('sale_data.product_id')
            ->where('sale_data.product_id', '!=', '')
            ->groupBy('sale_data.product_id')
            ->orderBy('product_name', 'ASC');

        // Apply date filter if 'days' is not set to 'all'
        if ($days !== 'all') {
            $dateNDaysAgo = now()->subDays(max(0, (int) $days - 1))->toDateString();
            $salesDataQuery->where('sale_data.date', '>=', $dateNDaysAgo);
            $salesData1Query->where('sale_data.date', '>=', $dateNDaysAgo);
        }

        // Apply filters based on search text, product id route, or all products
        if ($searchName !== '') {
            // Exact catalog id match → only that product (all locations still listed separately)
            $exactProductId = DB::table('products')
                ->where('product_id', $searchName)
                ->value('product_id');

            if ($exactProductId !== null) {
                $salesDataQuery->where('sale_data.product_id', $exactProductId);
                $salesData1Query->where('sale_data.product_id', $exactProductId);
            } else {
                $this->applyProductNameSearch($salesDataQuery, $searchName);
                $this->applyProductNameSearch($salesData1Query, $searchName);
            }

            $product_name = $searchName;
            $productid = 'ProductName';
        } elseif ($productid !== 'all' && $productid !== 'ProductName') {
            $salesDataQuery->where('sale_data.product_id', $productid);
            $salesData1Query->where('sale_data.product_id', $productid);

            $resolvedName = DB::table('sale_data')
                ->join('products', 'sale_data.product_id', '=', 'products.product_id')
                ->where('sale_data.product_id', $productid)
                ->selectRaw($this->resolvedProductNameSql() . ' as product_name')->value('product_name');

            $product_name = $resolvedName ?: 'Unknown Product';
        } else {
            $product_name = 'All Products';
        }

        // Paginate the main product table so the page stays fast even with
        // thousands of product/location combos. The graph data ($salesData1)
        // is kept as a plain collection for the (collapsed) chart.
        $perPage = (int) $request->input('per_page', 25);
        $salesData  = $salesDataQuery->simplePaginate($perPage)->withQueryString();
        $salesData1 = $salesData1Query->limit(100)->get();

        // Return the view with necessary data
        return view('reports.product', compact('salesData', 'salesData1', 'product_name', 'productid', 'days', 'searchName'));
    }

    public function exportProducts(Request $request)
    {
        $request->validate([
            'days' => 'nullable|in:7,30,90,all',
            'search' => 'nullable|string|max:100',
            'product_id' => 'nullable|string|max:255',
        ]);

        $days = (string) $request->input('days', '7');
        $search = trim((string) $request->input('search', ''));
        $productId = trim((string) $request->input('product_id', ''));

        $query = DB::table('sale_data')
            ->join('products', 'sale_data.product_id', '=', 'products.product_id')
            ->select(
                'sale_data.product_id',
                $this->resolvedProductNameSelect(),
                'sale_data.location',
                DB::raw('COUNT(DISTINCT sale_data.customer_id) AS customer_count'),
                DB::raw('COUNT(DISTINCT sale_data.orderid) AS order_count'),
                DB::raw('SUM(sale_data.count) AS quantity_sold'),
                $this->weightedUnitPriceAvg('sale_data'),
                DB::raw('SUM(sale_data.count * sale_data.price) AS total_sales')
            )
            ->groupBy('sale_data.product_id', 'sale_data.location')
            ->orderBy('total_sales', 'DESC');

        if ($days !== 'all') {
            $query->where('sale_data.date', '>=', now()->subDays(max(0, (int) $days - 1))->toDateString());
        }

        if ($search !== '') {
            $this->applyProductNameSearch($query, $search);
        } elseif ($productId !== '' && $productId !== 'all' && $productId !== 'ProductName') {
            $query->where('sale_data.product_id', $productId);
        }

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Product ID', 'Product Name', 'Location', 'Customers', 'Orders', 'Sold', 'Unit Price', 'Total Sales']);
            foreach ($query->cursor() as $row) {
                fputcsv($output, [
                    $row->product_id,
                    $row->product_name,
                    $row->location,
                    $row->customer_count,
                    $row->order_count,
                    $row->quantity_sold,
                    $row->unit_price_avg,
                    $row->total_sales,
                ]);
            }
            fclose($output);
        }, 'product-sales-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function getSalesData()
    {
        // Calculate the date 50 days ago
        $dateFiftyDaysAgo = now()->subDays(29)->toDateString();

        // Query to get the sales data
        $salesData = DB::table('sales_lists')
            ->select(DB::raw('date, SUM(count * price) as total_sales'))
            ->join('products', 'sales_lists.productid', '=', 'products.product_id')
            ->where('date', '>=', $dateFiftyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json($salesData);
    }

    public function getSalesDataCustomer($customerId)
    {
        $dateFiftyDaysAgo = now()->subDays(29)->toDateString();
        // Query to get the sales data
        $salesData = DB::table('sales_lists')
            ->select(DB::raw('date, SUM(count * price) as total_sales'))
            ->join('products', 'sales_lists.productid', '=', 'products.product_id')
            ->where('customerid', "=", $customerId)
            ->where('date', '>=', $dateFiftyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json($salesData);
    }

    public function viewIndex(Request $request)
    {
        // Get the 'days' and 'searchDate' values from the request
        $days = $request->input('days', 7); // Default to 7 days if no selection is provided
        $searchDate = $request->input('searchDate');
        $perPage = (int) $request->input('per_page', 25);

        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        // Prepare the base query for sales data
        $salesDataQuery = DB::table('sale_data')
            ->select(
                'date',
                'location',
                DB::raw('COUNT(DISTINCT customer_id) AS customer_count'),
                DB::raw('COUNT(DISTINCT orderid) AS order_id_count'),
                DB::raw('SUM(count) AS product_id_count'),
                DB::raw('SUM(count * price) AS total_products_price'),
                DB::raw('SUM(count * retail) AS total_retail_value'),
                DB::raw("SUM(CASE WHEN type = 'MalProff MPP' THEN count * price ELSE 0 END) AS mpp_sales"),
                DB::raw("SUM(CASE WHEN type = 'Fargerike' THEN count * price ELSE 0 END) AS fargerike_sales"),
                $this->weightedUnitPriceAvg()
            )
            ->groupBy('date', 'location')
            ->orderBy('date', 'DESC');

        // Prepare the query for total sales per day
        $salesData1Query = DB::table('sale_data')
            ->select(
                'date', 
                DB::raw('SUM(count * price) as total_sales'),
                DB::raw("SUM(CASE WHEN type = 'MalProff MPP' THEN count * price ELSE 0 END) AS mpp_sales"),
                DB::raw("SUM(CASE WHEN type = 'Fargerike' THEN count * price ELSE 0 END) AS fargerike_sales")
            )
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        // Apply date filtering based on the input
        if ($searchDate) {
            // If a specific date is searched, filter by that date
            // `date` is already a DATE column, so a direct comparison can use
            // an index while WHERE DATE(date) generally cannot.
            $salesDataQuery->where('date', $searchDate);
            $salesData1Query->where('date', $searchDate);
            $days = null;
        } elseif ($days && $days !== 'all') {
            // If $days is provided and not 'all', filter by the range of days
            $dateNDaysAgo = now()->subDays(max(0, (int) $days - 1))->toDateString();
            $salesDataQuery->where('date', '>=', $dateNDaysAgo);
            $salesData1Query->where('date', '>=', $dateNDaysAgo);
        }
        // No filter applied for 'all' days selection, as it fetches all records

        // Keep the drill-down table bounded. The selected filters and page size
        // remain in the URL while the user moves through the result pages.
        $salesData = $salesDataQuery
            ->simplePaginate($perPage)
            ->withQueryString();

        // An all-time daily chart can create a very large JSON/DOM payload. The
        // table above still covers the complete history; only the chart is
        // bounded to the most recent 366 calendar days for browser performance.
        $chartLimited = false;
        if (!$searchDate && $days === 'all') {
            $chartStartDate = now()->subDays(365)->toDateString();
            $salesData1Query->where('date', '>=', $chartStartDate);
            $chartLimited = true;
        }

        $salesData1 = $salesData1Query->get();

        // Full calendar-aligned series for the chart (same filters as $salesData1)
        $chartSeries = $this->buildDailySalesChartSeries($searchDate, $days, $salesData1);

        // Assuming you want to initialize the customer_name and customerId variables
        $customer_name = "";
        $customerId = "";

        return view('reports.index', compact(
            'salesData',
            'customer_name',
            'customerId',
            'salesData1',
            'days',
            'searchDate',
            'chartSeries',
            'chartLimited',
            'perPage'
        ));
    }

    /**
     * Build one row per calendar day so the graph matches the selected date range,
     * missing days use 0, and JS can compute rolling averages and a linear trend on
     * the same numbers the bars use.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $salesData1
     * @return array{labels: string[], values: float[]}
     */
    protected function buildDailySalesChartSeries(?string $searchDate, $days, $salesData1): array
    {
        $salesMap = [];
        foreach ($salesData1 as $row) {
            $key = \Carbon\Carbon::parse($row->date)->toDateString();
            $salesMap[$key] = (float) $row->total_sales;
        }

        $labels = [];
        $values = [];

        if ($searchDate) {
            $key = \Carbon\Carbon::parse($searchDate)->toDateString();
            $labels[] = $key;
            $values[] = $salesMap[$key] ?? 0.0;

            return ['labels' => $labels, 'values' => $values];
        }

        if ($days !== null && $days !== '' && $days !== 'all') {
            $dateNDaysAgo = now()->subDays(max(0, (int) $days - 1))->toDateString();
            $start = \Carbon\Carbon::parse($dateNDaysAgo)->startOfDay();
            $end = now()->startOfDay();

            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $ds = $d->toDateString();
                $labels[] = $ds;
                $values[] = $salesMap[$ds] ?? 0.0;
            }

            return ['labels' => $labels, 'values' => $values];
        }

        if ($salesData1->isEmpty()) {
            return ['labels' => [], 'values' => []];
        }

        $start = \Carbon\Carbon::parse($salesData1->min('date'))->startOfDay();
        $end = \Carbon\Carbon::parse($salesData1->max('date'))->startOfDay();

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds = $d->toDateString();
            $labels[] = $ds;
            $values[] = $salesMap[$ds] ?? 0.0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function reportDashboard(Request $request)
    {
        return view('reports.dashboard');
    }

    public function fetchCustomerData($saleId)
    {
        $customerData = SalesList::findOrFail($saleId)->customers;

        // Return customer data as JSON response
        return response()->json($customerData);
    }

    public function getCustomersByDate(Request $request, $date)
    {
        try {
            $query = DB::table('sale_data')
                ->select(
                    'customer_id',
                    'customer_name',
                    'date',
                    'location',
                    'crm_exists',
                    'crm_link',
                    'crm_id',
                    DB::raw('COUNT(DISTINCT orderid) AS order_count'),
                    DB::raw('SUM(count) AS product_count'),
                    DB::raw('ROUND(SUM(count * price), 2) AS total_price'),
                    DB::raw("ROUND(SUM(CASE WHEN type = 'MalProff MPP' THEN count * price ELSE 0 END), 2) AS mpp_sales"),
                    DB::raw("ROUND(SUM(CASE WHEN type = 'Fargerike' THEN count * price ELSE 0 END), 2) AS fargerike_sales"),
                    DB::raw('ROUND(SUM(count * price) / NULLIF(SUM(count), 0), 2) AS unit_price_avg')
                )
                ->where('date', $date);

            // The parent report is grouped by date + location. Restricting the
            // drill-down to that location avoids fetching unrelated customers.
            if ($request->filled('location')) {
                $query->where('location', $request->input('location'));
            }

            $customerData = $query
                ->groupBy('customer_id', 'customer_name', 'date', 'location', 'crm_exists', 'crm_link', 'crm_id')
                ->get();

            return response()->json($customerData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update KS_exists flag for a customer.
     * Receives JSON: { customer_id: string|int, KS_exists: 0|1 }
     * Only updates the customers table to avoid changing other existing behaviour.
     */
    public function updateKsStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer_id' => 'required|string|max:255|exists:customers,customer_id',
                'KS_exists' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid KS status request.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $customerId = (string) $request->input('customer_id');
            $ksExists = (int) $request->input('KS_exists');

            // Update only the customers table as requested
            DB::table('customers')->where('customer_id', $customerId)->update(['KS_exists' => $ksExists]);

            return response()->json(['success' => true, 'message' => 'KS status updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update customer KS status', [
                'error' => $e->getMessage(),
                'customer_id' => $request->input('customer_id'),
            ]);
            return response()->json(['success' => false, 'message' => 'Unable to update KS status.'], 500);
        }
    }

    public function markCrmExists(Request $request)
    {
        try {
            $customerId = $request->input('customer_id');
            $crmExists = $request->input('crm_exists');

            DB::table('sale_data')->where('customer_id', $customerId)->update(['crm_exists' => $crmExists]);
            DB::table('customers')->where('customer_id', $customerId)->update(['crm_exists' => $crmExists]);

            return response()->json(['message' => 'CRM status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update CRM status: ' . $e->getMessage()], 500);
        }
    }

    public function updateCrmId(Request $request)
    {
        try {
            $customerId = $request->input('customer_id');
            $crmId = $request->input('crm_id');

            DB::table('customers')->where('customer_id', $customerId)->update(['crm_id' => $crmId]);
            DB::table('sale_data')->where('customer_id', $customerId)->update(['crm_id' => $crmId]);

            return response()->json(['message' => 'CRM ID updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update CRM ID: ' . $e->getMessage()], 500);
        }
    }

    public function getLatestCrmId(Request $request)
    {
        $customerId = $request->input('customer_id');

        $crmId = DB::table('customers')
            ->where('customer_id', $customerId)
            ->value('crm_id');

        return response()->json(['crm_id' => $crmId]);
    }

    public function getCustomerData(Request $request)
    {
        try {
            $date = $request->input('date');
            $location = $request->input('location');
            $customerId = $request->input('customer_id');

            // Execute raw SQL query to fetch customer data from the sale_data table
            $customerData = DB::select("
            SELECT
                orderid,
                date AS sales_date,
                location,
                customer_name,
                SUM(count) AS product_count,
                ROUND(SUM(count * price), 2) AS total_product_count,
                ROUND(SUM(count * price) / NULLIF(SUM(count), 0), 2) AS unit_price
            FROM
                sale_data
            WHERE
                date = ?
                AND location = ?
                AND customer_id = ?
            GROUP BY
                orderid, date, location, customer_name
        ", [$date, $location, $customerId]);

            return response()->json($customerData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProductData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'days' => 'required|in:7,30,90,all',
                'location' => 'required|string|max:255',
                'product_id' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid product report filters.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Retrieve input values
            $days = $request->input('days'); // 'days' can be 'all', 7, 30, 90, etc.
            $location = $request->input('location');
            $product_id = $request->input('product_id');

            // Prepare the base query for fetching product data
            $productDataQuery = DB::table('sale_data')
                ->join('products', 'sale_data.product_id', '=', 'products.product_id')
                ->select(
                    'sale_data.date',
                    'sale_data.location',
                    'sale_data.product_id',
                    $this->resolvedProductNameSelect(),
                    DB::raw('COUNT(DISTINCT sale_data.customer_id) AS customer_count'),
                    DB::raw('COUNT(DISTINCT sale_data.orderid) AS order_id_count'),
                    DB::raw('SUM(sale_data.count) AS product_quantity_sold'),
                    DB::raw('SUM(sale_data.count * sale_data.price) AS total_sales'),
                    $this->weightedUnitPriceAvg('sale_data')
                );

            // Apply filters based on user input
            if ($location) {
                $productDataQuery->where('sale_data.location', $location);
            }

            if ($product_id) {
                $productDataQuery->where('sale_data.product_id', $product_id);
            }

            // Calculate the date range based on the 'days' input
            if ($days && $days !== 'all') {
                // Calculate the date range for last 'days'
                $endDate = now()->toDateString(); // Today's date
                $startDate = now()->subDays(max(0, (int) $days - 1))->toDateString();

                // Filter by date range
                $productDataQuery->whereBetween('sale_data.date', [$startDate, $endDate]);
            }

            // Group by date, location, and product_id (not product_name) to avoid duplicate drill-down rows
            $productDataQuery->groupBy('sale_data.date', 'sale_data.location', 'sale_data.product_id')
                ->orderBy('sale_data.date', 'DESC')
                ->orderBy('sale_data.location')
                ->orderBy('sale_data.product_id');

            // Execute the query
            $productData = $productDataQuery->get();

            // Return the response as JSON
            return response()->json($productData);
        } catch (\Exception $e) {
            Log::error('Failed to load product drill-down', [
                'error' => $e->getMessage(),
                'product_id' => $request->input('product_id'),
            ]);
            return response()->json(['success' => false, 'message' => 'Unable to load product sales.'], 500);
        }
    }

    public function getProductfinalData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'location' => 'required|string|max:255',
            'productId' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid product details request.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
        $date = $request->input('date');
        $location = $request->input('location');
        $productId = $request->input('productId');

        $query = DB::table('sale_data')
            ->leftJoin('order_records', 'sale_data.orderid', '=', 'order_records.order_id')
            ->select(
                'sale_data.date as sale_date',
                'order_records.order_date as order_date',
                'sale_data.location',
                'sale_data.orderid',
                'sale_data.customer_name',
                'sale_data.crm_link',
                'sale_data.count',
                'sale_data.price',
                DB::raw('ROUND(sale_data.count * sale_data.price, 2) AS line_total')
            );

        if ($date) {
            $query->whereDate('sale_data.date', '=', $date);
        }

        if ($productId) {
            $query->where('sale_data.product_id', $productId);
        }

        if ($location) {
            $query->where('sale_data.location', $location);
        }

        $rows = $query
            ->orderBy('sale_data.date', 'DESC')
            ->orderBy('sale_data.orderid', 'DESC')
            ->get();

        foreach ($rows as $row) {
            $oid = trim((string) ($row->orderid ?? ''));
            $row->order_show_url = ($oid !== '' && strpos($oid, ',') === false)
                ? url('/order-delivery/' . rawurlencode($oid))
                : null;
        }

        return response()->json($rows);
        } catch (\Exception $e) {
            Log::error('Failed to load product sale details', [
                'error' => $e->getMessage(),
                'product_id' => $request->input('productId'),
            ]);
            return response()->json(['success' => false, 'message' => 'Unable to load product details.'], 500);
        }
    }

    public function getCustomerfinalData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customerId' => 'required|string|max:255',
            'orderid' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid order details request.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $customerId = $request->input('customerId');
            $orderId = $request->input('orderid');

            $salesDetails = DB::table('sale_data')
                ->select(
                    'type',
                    'payment',
                    'customer_id',
                    'product_id',
                    'count',
                    'product_name',
                    'price',
                    DB::raw('ROUND(count * price, 2) AS total_price')
                )
                ->where('customer_id', $customerId)
                ->where('orderid', $orderId)
                ->get();

            return response()->json($salesDetails);
        } catch (\Exception $e) {
            Log::error('Failed to load customer order items', [
                'error' => $e->getMessage(),
                'customer_id' => $request->input('customerId'),
                'order_id' => $request->input('orderid'),
            ]);
            return response()->json(['success' => false, 'message' => 'Unable to load order items.'], 500);
        }
    }

    /**
     * Display ICT products with pagination and search
     */
    public function ict(Request $request)
    {
        $search = $request->input('search', '');

        $query = DB::table('ict');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ean_code', 'like', "%{$search}%")
                    ->orWhere('colour_code', 'like', "%{$search}%")
                    ->orWhere('colour_name', 'like', "%{$search}%")
                    ->orWhere('finish', 'like', "%{$search}%")
                    ->orWhere('tin_size', 'like', "%{$search}%")
                    ->orWhere('ean_code_base', 'like', "%{$search}%")
                    ->orWhere('base_description', 'like', "%{$search}%")
                    ->orWhere('base_code', 'like', "%{$search}%");
            });
        }

        $ictData = $query->orderBy('id', 'asc')->paginate(20)->withQueryString();

        return view('reports.ict', compact('ictData', 'search'));
    }

    /**
     * Search ICT products
     */
    public function search(Request $request)
    {
        return $this->ict($request); // Reuse the ict method for consistency
    }

    /**
     * Update quantity for ICT product
     */
    /**
     * Update quantity for ICT product
     */
    public function updateQuantity(Request $request)
    {
        Log::info('=== ICT QUANTITY UPDATE START ===');

        try {
            // Check if we're receiving the request
            if (!$request->has('id') || !$request->has('quantity')) {
                Log::error('Missing required fields', ['request' => $request->all()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields: id or quantity'
                ], 400);
            }

            $id = $request->input('id');
            $quantity = $request->input('quantity');

            $eanCodeBase = $request->input('ean_code_base');

            Log::info('Processing update:', ['id' => $id, 'ean_code_base' => $eanCodeBase, 'quantity' => $quantity]);

            // Basic validation
            if (!is_numeric($quantity) || $quantity < 0) {
                Log::error('Invalid quantity', ['quantity' => $quantity]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid quantity'
                ], 422);
            }

            if (empty($eanCodeBase)) {
                Log::error('Missing EAN Code Base', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Missing EAN Code Base'
                ], 422);
            }

            // Update the database by EAN Code Base
            $affected = DB::table('ict')
                ->where('ean_code_base', $eanCodeBase)
                ->update([
                    'qty' => (int)$quantity
                ]);

            Log::info('Update result:', ['affected_rows' => $affected]);

            if ($affected) {
                Log::info("ICT product quantity updated successfully by EAN", [
                    'ean_code_base' => $eanCodeBase,
                    'quantity' => $quantity,
                    'user' => auth()->id() ?? 'unknown'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Quantity updated successfully',
                    'data' => [
                        'ean_code_base' => $eanCodeBase,
                        'quantity' => $quantity
                    ]
                ]);
            } else {
                // Check if records exist with this EAN
                $exists = DB::table('ict')->where('ean_code_base', $eanCodeBase)->exists();

                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No records found with this EAN Code Base'
                    ], 404);
                }

                Log::warning("No rows affected - quantity may already be set to this value", [
                    'ean_code_base' => $eanCodeBase,
                    'quantity' => $quantity
                ]);

                return response()->json([
                    'success' => true, // Still success if no change needed
                    'message' => 'Quantity already set to this value',
                    'data' => [
                        'ean_code_base' => $eanCodeBase,
                        'quantity' => $quantity
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update ICT quantity', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        } finally {
            Log::info('=== ICT QUANTITY UPDATE END ===');
        }
    }

    /**
     * Display KS page with all customers and search functionality.
     * This method fetches unique customers and displays them in a table.
     * Users can search for customers and toggle KS status with buttons.
     */
    public function ksPage(Request $request)
    {
        try {
            $search = $request->input('search', ''); // Get search term if provided

            // Query to get all unique customers with their KS_exists status
            // Include updated_at so we can order by the most recently updated customers first
            $query = DB::table('customers')
                ->select('customer_id', 'customer_name', 'KS_exists', 'updated_at')
                ->distinct();

            // Apply search filter if search term is provided
            if ($search) {
                $query->where('customer_name', 'LIKE', "%{$search}%");
            }

            // Order by latest updated customer first, then fallback to customer_name
            $customers = $query->orderBy('updated_at', 'DESC')->orderBy('customer_name', 'ASC')->get();

            // Return the view with customers data
            return view('customers.ks_customers', compact('customers', 'search'));
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error in ksPage: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load KS page: ' . $e->getMessage()], 500);
        }
    }
    public function customerProductsByDate(Request $request)
    {
        try {
            $date = $request->input('date');
            $location = $request->input('location');
            $customerId = $request->input('customer_id');

            $query = DB::table('sale_data')
                ->select(
                    'product_id',
                    'product_name',
                    DB::raw('SUM(count) as count'),
                    'price',
                    DB::raw('ROUND(SUM(count * price), 2) AS total_price')
                )
                ->where('date', $date)
                ->where('location', $location);

            if ($customerId !== null && $customerId !== 'null' && $customerId !== '') {
                $query->where('customer_id', $customerId);
            }

            $salesDetails = $query->groupBy('product_id', 'product_name', 'price')->get();

            return response()->json($salesDetails);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
