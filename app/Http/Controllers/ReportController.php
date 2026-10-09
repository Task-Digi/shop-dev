<?php

namespace App\Http\Controllers;

use App\Models\SalesList;
use App\Models\ICT;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

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

        return DB::raw("CASE WHEN SUM({$c}) != 0 THEN ROUND(SUM({$c} * {$p}) / SUM({$c}), 2) ELSE NULL END as unit_price_avg");
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

    protected function applyProductNameSearch(Builder $query, string $searchName, string $saleAlias = 'sale_data', string $productAlias = 'products'): void
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

    /**
     * Latest distinct dates containing sales. Fetching the values first keeps
     * this compatible with MariaDB versions that reject LIMIT in an IN subquery.
     */
    protected function latestSalesDates(int $count)
    {
        if (Schema::hasTable('sales_reporting_days')) {
            $dates = DB::table('sale_data')->select('date')
                ->union(DB::table('sales_reporting_days')->select('date'));

            return DB::query()->fromSub($dates, 'report_dates')
                ->whereNotNull('date')
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->limit($count)
                ->pluck('date');
        }

        return DB::table('sale_data')
            ->select('date')
            ->distinct()
            ->orderBy('date', 'DESC')
            ->limit($count)
            ->pluck('date');
    }

    public function index(Request $request, string $customerId)
    {
        $request->validate([
            'days' => 'nullable|in:0,7,28,56,all,month,year',
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
                DB::raw('COUNT(DISTINCT CASE WHEN sale_data.count > 0 THEN sale_data.orderid END) as order_id_count'),
                DB::raw('SUM(sale_data.count) as total_products_sold'),
                DB::raw('SUM(sale_data.count * sale_data.price) as total_sales'),
                $this->weightedUnitPriceAvg('sale_data')
            )
            ->groupBy(
                'sale_data.location',
                'sale_data.customer_id',
                'sale_data.customer_name',
                'customers.KS_exists'
            )
            ->orderBy('total_sales', 'DESC');

        // Period filter
        if ($days === 'month') {
            $startDate = now()->startOfMonth()->toDateString();
            $salesData1Query->where('sale_data.date', '>=', $startDate);
            $salesDataQuery->where('sale_data.date', '>=', $startDate);
        } elseif ($days === 'year') {
            $startDate = now()->startOfYear()->toDateString();
            $salesData1Query->where('sale_data.date', '>=', $startDate);
            $salesDataQuery->where('sale_data.date', '>=', $startDate);
        } elseif ((string) $days !== '0' && $days !== 'all' && is_numeric($days) && (int) $days > 0) {
            $latestSalesDates = $this->latestSalesDates((int) $days);
            $salesData1Query->whereIn('sale_data.date', $latestSalesDates);
            $salesDataQuery->whereIn('sale_data.date', $latestSalesDates);
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

        $salesData1 = $salesData1Query->limit(100)->get();

        $perPage = (int) $request->input('per_page', 25);
        $salesData = $salesDataQuery->paginate($perPage)->withQueryString();

        return view('reports.customer', compact('salesData', 'salesData1', 'days', 'search', 'customerId'));
    }

    public function getCustomerReportData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'location' => 'required|string|max:255',
                'customer_id' => 'required|string|max:255',
                'days' => 'required|in:0,7,28,56,all,month,year',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer report filters.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $location = $request->input('location');
            $customerId = $request->input('customer_id');
            $days = $request->input('days');

            $query = "
                SELECT
                    orderid,
                    MAX(CASE WHEN original_orderid IS NOT NULL AND original_orderid <> orderid THEN original_orderid ELSE NULL END) AS original_orderid,
                    MAX(date) AS sales_date,
                    location,
                    type,
                    payment,
                    customer_id,
                    customer_name,
                    MAX(crm_link) AS crm_link,
                    ROUND(SUM(count * price), 2) AS total_sales,
                    ROUND(SUM(count * retail), 2) AS total_products_price,
                    SUM(count) AS total_products_sold,
                    ROUND(SUM(count * price) / NULLIF(SUM(count), 0), 2) AS unit_price,
                    SUM(CASE WHEN count > 0 THEN count ELSE 0 END) AS gross_sold,
                    ROUND(SUM(CASE WHEN count > 0 THEN count * price ELSE 0 END), 2) AS gross_sales,
                    ROUND(SUM(CASE WHEN count > 0 THEN count * retail ELSE 0 END), 2) AS gross_retail
                FROM
                    sale_data
                WHERE
                    location = ?
                    AND customer_id = ?
            ";

            if ($days == '0' || $days === 'all') {
                $params = [$location, $customerId];
            } elseif ($days === 'month') {
                $startDate = now()->startOfMonth()->toDateString();
                $query .= " AND date >= ?";
                $params = [$location, $customerId, $startDate];
            } elseif ($days === 'year') {
                $startDate = now()->startOfYear()->toDateString();
                $query .= " AND date >= ?";
                $params = [$location, $customerId, $startDate];
            } else {
                $salesDates = $this->latestSalesDates((int) $days)->values()->all();
                if ($salesDates === []) {
                    return response()->json([]);
                }
                $datePlaceholders = implode(', ', array_fill(0, count($salesDates), '?'));
                $query .= " AND date IN ({$datePlaceholders})";
                $params = array_merge([$location, $customerId], $salesDates);
            }

            $query .= "
                GROUP BY
                    orderid, location, type, payment, customer_id, customer_name
                ORDER BY
                    sales_date DESC,
                    orderid DESC
            ";

            $customerData = DB::select($query, $params);

            // Fetch any returns linked to these orders
            $orderIds = array_filter(array_map(fn($o) => (string)($o->orderid ?? ''), $customerData));
            $returnsByParent = [];
            if (!empty($orderIds)) {
                $linkedReturns = DB::table('sale_data')
                    ->whereIn('original_orderid', $orderIds)
                    ->whereColumn('original_orderid', '!=', 'orderid')
                    ->select(
                        'original_orderid',
                        'orderid',
                        DB::raw('SUM(count) AS returned_count'),
                        DB::raw('SUM(count * price) AS returned_sales'),
                        DB::raw('SUM(count * retail) AS returned_retail')
                    )
                    ->groupBy('original_orderid', 'orderid')
                    ->get();

                $returnsByParent = $linkedReturns->groupBy('original_orderid');
            }

            foreach ($customerData as $order) {
                $origId = (string) $order->orderid;
                $isLinkedReturn = !empty($order->original_orderid ?? null);

                if ($isLinkedReturn) {
                    $order->is_return = true;
                    $order->is_linked_return = true;
                    $order->has_returns = false;
                    $order->gross_products_sold = 0;
                    $order->gross_sales = 0.00;
                    $order->returned_products_sold = abs((int) $order->total_products_sold);
                    $order->returned_count = $order->returned_products_sold;
                    $order->returned_sales = abs((float) $order->total_sales);
                    $order->return_orderids = [];
                } elseif (isset($returnsByParent[$origId])) {
                    $order->is_return = false;
                    $parentReturns = $returnsByParent[$origId];
                    $retQty = abs((int) $parentReturns->sum('returned_count'));
                    $retAmount = abs((float) $parentReturns->sum('returned_sales'));
                    $retRetail = abs((float) $parentReturns->sum('returned_retail'));

                    $grossQty = (int) ($order->gross_sold ?? $order->total_products_sold);
                    $grossSales = (float) ($order->gross_sales ?? $order->total_sales);
                    $grossRetail = (float) ($order->gross_retail ?? $order->total_products_price);

                    $netQty = $grossQty - $retQty;
                    $netSales = round($grossSales - $retAmount, 2);
                    $netRetail = round($grossRetail - $retRetail, 2);

                    $order->total_products_sold = $netQty;
                    $order->total_sales = $netSales;
                    $order->total_products_price = $netRetail;
                    $order->unit_price = $netQty != 0 ? round($netSales / $netQty, 2) : ($grossQty != 0 ? round($grossSales / $grossQty, 2) : 0.00);

                    $order->has_returns = true;
                    $order->is_linked_return = false;
                    $order->gross_products_sold = $grossQty;
                    $order->gross_sales = round($grossSales, 2);
                    $order->returned_products_sold = $retQty;
                    $order->returned_count = $retQty;
                    $order->returned_sales = round($retAmount, 2);
                    $order->return_orderids = $parentReturns->pluck('orderid')->unique()->values()->all();
                } else {
                    $grossQty = (int) ($order->gross_sold ?? $order->total_products_sold);
                    $netQty = (int) $order->total_products_sold;
                    $sameOrderReturned = max(0, $grossQty - $netQty);
                    $order->is_return = $grossQty === 0 && $netQty < 0;
                    $order->has_returns = $sameOrderReturned > 0;
                    $order->is_linked_return = false;
                    $order->gross_products_sold = $grossQty;
                    $order->gross_sales = (float) ($order->gross_sales ?? $order->total_sales ?? 0);
                    $order->returned_products_sold = $sameOrderReturned;
                    $order->returned_count = $sameOrderReturned;
                    $order->returned_sales = max(0, round($order->gross_sales - (float) ($order->total_sales ?? 0), 2));
                    $order->return_orderids = [];
                }
            }

            // Calculate daily totals: linked returns are already subtracted from parent order, so skip them to prevent double deduction
            $dailyTotals = collect($customerData)
                ->groupBy('sales_date')
                ->map(fn ($orders) => $orders->sum(function ($order) {
                    if (!empty($order->is_linked_return)) {
                        return 0; // Prevent deducting twice
                    }
                    return (float) $order->total_products_sold;
                }));

            foreach ($customerData as $order) {
                $order->daily_total_orders = $dailyTotals->get($order->sales_date, 0);
            }

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
            'days' => 'nullable|in:0,7,28,56,all,month,year',
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
                DB::raw('COUNT(DISTINCT CASE WHEN count > 0 THEN orderid END) as order_count'),
                DB::raw('SUM(count) as quantity_sold'),
                $this->weightedUnitPriceAvg(),
                DB::raw('SUM(count * price) as total_sales')
            )
            ->groupBy('location', 'customer_id', 'customer_name')
            ->orderBy('total_sales', 'DESC');

        if ($days === 'month') {
            $query->where('date', '>=', now()->startOfMonth()->toDateString());
        } elseif ($days === 'year') {
            $query->where('date', '>=', now()->startOfYear()->toDateString());
        } elseif ($days !== '0' && $days !== 'all' && is_numeric($days) && (int) $days > 0) {
            $query->whereIn('date', $this->latestSalesDates((int) $days));
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
        $periodLabel = match ($days) {
            '0', 'all' => 'All time',
            'month' => 'Current month',
            'year' => 'Current year',
            default => "Last {$days} sales days",
        };

        return response()->streamDownload(function () use ($query, $periodLabel, $search) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Period', $periodLabel]);
            fputcsv($output, ['Search', $search !== '' ? $search : 'All customers']);
            fputcsv($output, []);
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

    public function productIndex(Request $request, string $productid)
    {
        $request->validate([
            'days' => 'nullable|in:7,28,56,all,month,year',
            'per_page' => 'nullable|integer|in:10,25,50,100,200',
            'search' => 'nullable|string|max:100',
        ]);

        $days = $request->input('days', 7);
        $searchName = trim((string) $request->input('search', ''));

        if ($productid === 'ProductName' && $searchName === '') {
            $productid = 'all';
        }

        $salesDataQuery = DB::table('sale_data')
            ->join('products', 'sale_data.product_id', '=', 'products.product_id')
            ->select(
                'sale_data.product_id',
                $this->resolvedProductNameSelect(),
                'sale_data.location',
                DB::raw('COUNT(DISTINCT sale_data.customer_id) AS customer_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN sale_data.count > 0 THEN sale_data.orderid END) AS order_id_count'),
                DB::raw('SUM(sale_data.count) AS product_id_count'),
                DB::raw('SUM(sale_data.count * sale_data.price) AS total_products_price'),
                $this->weightedUnitPriceAvg('sale_data')
            )
            ->whereNotNull('sale_data.product_id')
            ->where('sale_data.product_id', '!=', '')
            ->groupBy('sale_data.product_id', 'sale_data.location')
            ->orderBy('product_name', 'ASC');

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

        if ($days === 'month') {
            $startDate = now()->startOfMonth()->toDateString();
            $salesDataQuery->where('sale_data.date', '>=', $startDate);
            $salesData1Query->where('sale_data.date', '>=', $startDate);
        } elseif ($days === 'year') {
            $startDate = now()->startOfYear()->toDateString();
            $salesDataQuery->where('sale_data.date', '>=', $startDate);
            $salesData1Query->where('sale_data.date', '>=', $startDate);
        } elseif ($days !== 'all' && is_numeric($days) && (int) $days > 0) {
            $latestSalesDates = $this->latestSalesDates((int) $days);
            $salesDataQuery->whereIn('sale_data.date', $latestSalesDates);
            $salesData1Query->whereIn('sale_data.date', $latestSalesDates);
        }

        if ($searchName !== '') {
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

        $perPage = (int) $request->input('per_page', 25);
        $salesData  = $salesDataQuery->paginate($perPage)->withQueryString();
        $salesData1 = $salesData1Query->limit(100)->get();

        return view('reports.product', compact('salesData', 'salesData1', 'product_name', 'productid', 'days', 'searchName'));
    }

    public function exportProducts(Request $request)
    {
        $request->validate([
            'days' => 'nullable|in:7,28,56,all,month,year',
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
                DB::raw('COUNT(DISTINCT CASE WHEN sale_data.count > 0 THEN sale_data.orderid END) AS order_id_count'),
                DB::raw('SUM(sale_data.count) AS product_id_count'),
                DB::raw('SUM(sale_data.count * sale_data.price) AS total_products_price'),
                $this->weightedUnitPriceAvg('sale_data')
            )
            ->whereNotNull('sale_data.product_id')
            ->where('sale_data.product_id', '!=', '')
            ->groupBy('sale_data.product_id', 'sale_data.location')
            ->orderBy('product_name', 'ASC');

        if ($days === 'month') {
            $query->where('sale_data.date', '>=', now()->startOfMonth()->toDateString());
        } elseif ($days === 'year') {
            $query->where('sale_data.date', '>=', now()->startOfYear()->toDateString());
        } elseif ($days !== 'all' && is_numeric($days) && (int) $days > 0) {
            $query->whereIn('sale_data.date', $this->latestSalesDates((int) $days));
        }

        if ($search !== '') {
            $this->applyProductNameSearch($query, $search);
        } elseif ($productId !== '' && $productId !== 'all' && $productId !== 'ProductName') {
            $query->where('sale_data.product_id', $productId);
        }

        $filename = 'product-sales-' . now()->format('Y-m-d') . '.csv';
        $periodLabel = match ($days) {
            'all' => 'All time',
            'month' => 'Current month',
            'year' => 'Current year',
            default => "Last {$days} sales days",
        };

        return response()->streamDownload(function () use ($query, $periodLabel, $search) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Period', $periodLabel]);
            fputcsv($output, ['Search', $search !== '' ? $search : 'All products']);
            fputcsv($output, []);
            fputcsv($output, ['Product ID', 'Product Name', 'Location', 'Customers', 'Orders', 'Sold', 'Unit Price', 'Total Sales']);

            foreach ($query->cursor() as $row) {
                fputcsv($output, [
                    $row->product_id,
                    $row->product_name,
                    $row->location,
                    $row->customer_count,
                    $row->order_id_count,
                    $row->product_id_count,
                    $row->unit_price_avg,
                    $row->total_products_price,
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function getSalesData()
    {
        $dateFiftyDaysAgo = now()->subDays(29)->toDateString();

        $salesData = DB::table('sales_lists')
            ->select(DB::raw('date, SUM(count * price) as total_sales'))
            ->join('products', 'sales_lists.productid', '=', 'products.product_id')
            ->where('date', '>=', $dateFiftyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json($salesData);
    }

    public function getSalesDataCustomer(string $customerId)
    {
        $dateFiftyDaysAgo = now()->subDays(29)->toDateString();

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
        $request->validate([
            'days' => 'nullable|in:7,28,56,all,month,year',
            'searchDate' => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|integer|in:25,50,100',
        ]);

        $days = $request->input('days', 7);
        $searchDate = $request->input('searchDate');
        $perPage = (int) $request->input('per_page', 25);

        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $actualSalesDataQuery = DB::table('sale_data')
            ->select(
                'date',
                'location',
                DB::raw('COUNT(DISTINCT customer_id) AS customer_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN count > 0 THEN orderid END) AS order_id_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN count < 0 THEN orderid END) AS return_order_count'),
                DB::raw('SUM(count) AS product_id_count'),
                DB::raw('SUM(count * price) AS total_products_price'),
                DB::raw('SUM(count * retail) AS total_retail_value'),
                DB::raw("SUM(CASE WHEN count > 0 THEN count ELSE 0 END) AS gross_sold"),
                DB::raw("SUM(CASE WHEN count < 0 THEN ABS(count) ELSE 0 END) AS returned_sold"),
                DB::raw("SUM(CASE WHEN type = 'MalProff MPP' THEN count * price ELSE 0 END) AS mpp_sales"),
                DB::raw("SUM(CASE WHEN type = 'Fargerike' THEN count * price ELSE 0 END) AS fargerike_sales"),
                $this->weightedUnitPriceAvg()
            )
            ->groupBy('date', 'location');

        if (Schema::hasTable('sales_reporting_days')) {
            $zeroSalesDataQuery = DB::table('sales_reporting_days')->select(
                'date',
                'location',
                DB::raw('0 AS customer_count'),
                DB::raw('0 AS order_id_count'),
                DB::raw('0 AS return_order_count'),
                DB::raw('0 AS product_id_count'),
                DB::raw('0 AS total_products_price'),
                DB::raw('0 AS total_retail_value'),
                DB::raw('0 AS gross_sold'),
                DB::raw('0 AS returned_sold'),
                DB::raw('0 AS mpp_sales'),
                DB::raw('0 AS fargerike_sales'),
                DB::raw('NULL AS unit_price_avg')
            );
            $salesDataQuery = DB::query()->fromSub($actualSalesDataQuery->unionAll($zeroSalesDataQuery), 'daily_sales');
        } else {
            $salesDataQuery = $actualSalesDataQuery;
        }
        $salesDataQuery->orderBy('date', 'DESC');

        $actualDailyTotals = DB::table('sale_data')
            ->select(
                'date', 
                DB::raw('SUM(count * price) as total_sales'),
                DB::raw("SUM(CASE WHEN type = 'MalProff MPP' THEN count * price ELSE 0 END) AS mpp_sales"),
                DB::raw("SUM(CASE WHEN type = 'Fargerike' THEN count * price ELSE 0 END) AS fargerike_sales")
            )
            ->groupBy('date');

        if (Schema::hasTable('sales_reporting_days')) {
            $zeroDailyTotals = DB::table('sales_reporting_days')->select(
                'date',
                DB::raw('0 AS total_sales'),
                DB::raw('0 AS mpp_sales'),
                DB::raw('0 AS fargerike_sales')
            );
            $salesData1Query = DB::query()
                ->fromSub($actualDailyTotals->unionAll($zeroDailyTotals), 'daily_totals')
                ->select('date', DB::raw('SUM(total_sales) AS total_sales'), DB::raw('SUM(mpp_sales) AS mpp_sales'), DB::raw('SUM(fargerike_sales) AS fargerike_sales'))
                ->groupBy('date');
        } else {
            $salesData1Query = $actualDailyTotals;
        }
        $salesData1Query->orderBy('date', 'ASC');

        if ($searchDate) {
            $salesDataQuery->where('date', $searchDate);
            $salesData1Query->where('date', $searchDate);
            $days = null;
        } elseif ($days === 'month') {
            $startDate = now()->startOfMonth()->toDateString();
            $salesDataQuery->where('date', '>=', $startDate);
            $salesData1Query->where('date', '>=', $startDate);
        } elseif ($days === 'year') {
            $startDate = now()->startOfYear()->toDateString();
            $salesDataQuery->where('date', '>=', $startDate);
            $salesData1Query->where('date', '>=', $startDate);
        } elseif ($days && $days !== 'all' && is_numeric($days) && (int) $days > 0) {
            $latestSalesDates = $this->latestSalesDates((int) $days);
            $salesDataQuery->whereIn('date', $latestSalesDates);
            $salesData1Query->whereIn('date', $latestSalesDates);
        }

        $salesData = $salesDataQuery->paginate($perPage)->withQueryString();

        $chartLimited = false;
        if (!$searchDate && $days === 'all') {
            $chartStartDate = now()->subDays(365)->toDateString();
            $salesData1Query->where('date', '>=', $chartStartDate);
            $chartLimited = true;
        }

        $salesData1 = $salesData1Query->get();

        $chartSeries = $this->buildDailySalesChartSeries($searchDate, $days, $salesData1);

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

    protected function buildDailySalesChartSeries(?string $searchDate, string|int|null $days, Collection $salesData1): array
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
            foreach ($salesData1 as $row) {
                $labels[] = \Carbon\Carbon::parse($row->date)->toDateString();
                $values[] = (float) $row->total_sales;
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

    public function fetchCustomerData(int|string $saleId)
    {
        $customerData = SalesList::findOrFail($saleId)->customers;
        return response()->json($customerData);
    }

    public function getCustomersByDate(Request $request, string $date)
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
                    DB::raw('COUNT(DISTINCT CASE WHEN count > 0 THEN orderid END) AS order_count'),
                    DB::raw('COUNT(DISTINCT CASE WHEN count < 0 THEN orderid END) AS return_count'),
                    DB::raw('SUM(count) AS product_count'),
                    DB::raw('ROUND(SUM(count * price), 2) AS total_price'),
                    DB::raw("ROUND(SUM(CASE WHEN type = 'MalProff MPP' THEN count * price ELSE 0 END), 2) AS mpp_sales"),
                    DB::raw("ROUND(SUM(CASE WHEN type = 'Fargerike' THEN count * price ELSE 0 END), 2) AS fargerike_sales"),
                    DB::raw('ROUND(SUM(count * price) / NULLIF(SUM(count), 0), 2) AS unit_price_avg')
                )
                ->where('date', $date);

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

            $customerData = DB::select("
                SELECT
                    orderid,
                    MAX(original_orderid) AS original_orderid,
                    date AS sales_date,
                    location,
                    customer_name,
                    SUM(count) AS product_count,
                    ROUND(SUM(count * price), 2) AS total_product_count,
                    ROUND(SUM(count * price) / NULLIF(SUM(count), 0), 2) AS unit_price,
                    SUM(CASE WHEN count > 0 THEN count ELSE 0 END) AS gross_count,
                    ROUND(SUM(CASE WHEN count > 0 THEN count * price ELSE 0 END), 2) AS gross_sales
                FROM
                    sale_data
                WHERE
                    date = ?
                    AND location = ?
                    AND customer_id = ?
                GROUP BY
                    orderid, date, location, customer_name
            ", [$date, $location, $customerId]);

            $orderIds = array_filter(array_map(fn($o) => (string)($o->orderid ?? ''), $customerData));
            $returnsByParent = [];
            if (!empty($orderIds)) {
                $linkedReturns = DB::table('sale_data')
                    ->whereIn('original_orderid', $orderIds)
                    ->select(
                        'original_orderid',
                        DB::raw('SUM(count) AS returned_count'),
                        DB::raw('SUM(count * price) AS returned_sales')
                    )
                    ->groupBy('original_orderid')
                    ->get()
                    ->keyBy('original_orderid');
                $returnsByParent = $linkedReturns;
            }

            foreach ($customerData as $order) {
                $origId = (string) $order->orderid;
                $isLinkedReturn = !empty($order->original_orderid);

                if ($isLinkedReturn) {
                    $order->is_linked_return = true;
                    $order->has_returns = false;
                } elseif (isset($returnsByParent[$origId])) {
                    $ret = $returnsByParent[$origId];
                    $retQty = abs((int) $ret->returned_count);
                    $retSales = abs((float) $ret->returned_sales);
                    $grossQty = (int) $order->gross_count;
                    $grossSales = (float) $order->gross_sales;

                    $order->product_count = $grossQty - $retQty;
                    $order->total_product_count = round($grossSales - $retSales, 2);
                    $order->unit_price = $order->product_count != 0 ? round($order->total_product_count / $order->product_count, 2) : 0.00;
                    $order->gross_count = $grossQty;
                    $order->returned_count = $retQty;
                    $order->has_returns = true;
                    $order->is_linked_return = false;
                } else {
                    $order->has_returns = false;
                    $order->is_linked_return = false;
                    $order->returned_count = 0;
                }
            }

            return response()->json($customerData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProductData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'days' => 'required|in:7,28,56,all,month,year',
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

            $days = $request->input('days');
            $location = $request->input('location');
            $product_id = $request->input('product_id');

            $productDataQuery = DB::table('sale_data')
                ->join('products', 'sale_data.product_id', '=', 'products.product_id')
                ->select(
                    'sale_data.date',
                    'sale_data.location',
                    'sale_data.product_id',
                    $this->resolvedProductNameSelect(),
                    DB::raw('COUNT(DISTINCT sale_data.customer_id) AS customer_count'),
                    DB::raw('COUNT(DISTINCT CASE WHEN sale_data.count > 0 THEN sale_data.orderid END) AS order_id_count'),
                    DB::raw('SUM(sale_data.count) AS product_quantity_sold'),
                    DB::raw('SUM(sale_data.count * sale_data.price) AS total_sales'),
                    $this->weightedUnitPriceAvg('sale_data')
                );

            if ($location) {
                $productDataQuery->where('sale_data.location', $location);
            }

            if ($product_id) {
                $productDataQuery->where('sale_data.product_id', $product_id);
            }

            if ($days === 'month') {
                $productDataQuery->where('sale_data.date', '>=', now()->startOfMonth()->toDateString());
            } elseif ($days === 'year') {
                $productDataQuery->where('sale_data.date', '>=', now()->startOfYear()->toDateString());
            } elseif ($days && $days !== 'all' && is_numeric($days) && (int) $days > 0) {
                $productDataQuery->whereIn('sale_data.date', $this->latestSalesDates((int) $days));
            }

            $productDataQuery->groupBy('sale_data.date', 'sale_data.location', 'sale_data.product_id')
                ->orderBy('sale_data.date', 'DESC')
                ->orderBy('sale_data.location')
                ->orderBy('sale_data.product_id');

            $productData = $productDataQuery->get();

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
                    'sale_data.original_orderid',
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

            $orderIds = $rows->pluck('orderid')->filter()->unique();
            $linkedReturns = DB::table('sale_data')
                ->whereIn('original_orderid', $orderIds)
                ->where('product_id', $productId)
                ->select('original_orderid', 'orderid', DB::raw('SUM(count) as total_returned'))
                ->groupBy('original_orderid', 'orderid')
                ->get()
                ->groupBy('original_orderid');

            foreach ($rows as $row) {
                $oid = trim((string) ($row->orderid ?? ''));
                $row->order_show_url = ($oid !== '' && strpos($oid, ',') === false)
                    ? url('/order-delivery/' . rawurlencode($oid))
                    : null;

                $returns = $linkedReturns->get($row->orderid);
                if ($returns && $returns->isNotEmpty()) {
                    $retQty = abs((int) $returns->sum('total_returned'));
                    $origQty = (int) $row->count;
                    $row->gross_count = $origQty;
                    $row->returned_count = $retQty;
                    $row->net_count = $origQty - $retQty;
                    $row->gross_line_total = (float) $row->line_total;
                    $row->net_line_total = round(($origQty - $retQty) * (float) $row->price, 2);
                    $row->has_returns = true;
                    $row->return_orderids = $returns->pluck('orderid')->unique()->values()->all();
                } else {
                    $row->gross_count = (int) $row->count;
                    $row->returned_count = 0;
                    $row->net_count = (int) $row->count;
                    $row->gross_line_total = (float) $row->line_total;
                    $row->net_line_total = (float) $row->line_total;
                    $row->has_returns = false;
                    $row->return_orderids = [];
                }
                $row->is_linked_return = !empty($row->original_orderid);
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

            $items = DB::table('sale_data')
                ->select(
                    'type',
                    'payment',
                    'customer_id',
                    'product_id',
                    'count',
                    'product_name',
                    'price',
                    'original_orderid',
                    DB::raw('ROUND(count * price, 2) AS total_price')
                )
                ->where('customer_id', $customerId)
                ->where('orderid', $orderId)
                ->get();

            // Look up any returns linked to this order
            $linkedReturns = DB::table('sale_data')
                ->where('original_orderid', $orderId)
                ->select(
                    'product_id',
                    'orderid',
                    'date',
                    DB::raw('SUM(count) as ret_count'),
                    DB::raw('ROUND(SUM(count * price), 2) as ret_sales')
                )
                ->groupBy('product_id', 'orderid', 'date')
                ->get()
                ->groupBy('product_id');

            foreach ($items as $item) {
                $isLinkedReturn = !empty($item->original_orderid);
                $prodReturns = $linkedReturns->get($item->product_id);

                if ($isLinkedReturn) {
                    $item->gross_count = 0;
                    $item->returned_count = abs((int) $item->count);
                    $item->gross_total_price = 0.00;
                    $item->returned_total_price = abs((float) $item->total_price);
                    $item->has_returns = false;
                    $item->is_return = true;
                    $item->return_receipts = [];
                    $item->returns = [];
                } elseif ($prodReturns && $prodReturns->isNotEmpty()) {
                    $retQty = abs((int) $prodReturns->sum('ret_count'));
                    $retSales = abs((float) $prodReturns->sum('ret_sales'));
                    $origCount = (int) $item->count;
                    $netCount = $origCount - $retQty;
                    $grossTotal = (float) $item->total_price;
                    $netTotal = round($netCount * (float) $item->price, 2);

                    $item->gross_count = $origCount;
                    $item->returned_count = $retQty;
                    $item->count = $netCount;
                    $item->gross_total_price = $grossTotal;
                    $item->returned_total_price = $retSales;
                    $item->total_price = $netTotal;
                    $item->has_returns = true;
                    $item->is_return = false;
                    $item->return_receipts = $prodReturns->map(fn($r) => [
                        'orderid' => $r->orderid,
                        'date' => $r->date,
                        'count' => abs($r->ret_count),
                        'sales' => abs($r->ret_sales),
                    ])->values()->all();
                    $item->returns = $prodReturns->map(fn($r) => [
                        'return_orderid' => $r->orderid,
                        'date' => $r->date,
                        'count' => abs($r->ret_count),
                        'sales' => abs($r->ret_sales),
                    ])->values()->all();
                } else {
                    $item->gross_count = (int) $item->count;
                    $item->returned_count = 0;
                    $item->gross_total_price = (float) $item->total_price;
                    $item->returned_total_price = 0.00;
                    $item->has_returns = false;
                    $item->is_return = (int) $item->count < 0;
                    $item->return_receipts = [];
                    $item->returns = [];
                }
            }

            return response()->json($items);
        } catch (\Exception $e) {
            Log::error('Failed to load customer order items', [
                'error' => $e->getMessage(),
                'customer_id' => $request->input('customerId'),
                'order_id' => $request->input('orderid'),
            ]);
            return response()->json(['success' => false, 'message' => 'Unable to load order items.'], 500);
        }
    }

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

    public function search(Request $request)
    {
        return $this->ict($request);
    }

    public function updateQuantity(Request $request)
    {
        Log::info('=== ICT QUANTITY UPDATE START ===');

        try {
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
                    'user' => Auth::id() ?? 'unknown'
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
                    'success' => true,
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

    public function ksPage(Request $request)
    {
        try {
            $search = $request->input('search', '');

            $query = DB::table('customers')
                ->select('customer_id', 'customer_name', 'KS_exists', 'updated_at')
                ->distinct();

            if ($search) {
                $query->where('customer_name', 'LIKE', "%{$search}%");
            }

            $customers = $query->orderBy('updated_at', 'DESC')->orderBy('customer_name', 'ASC')->get();

            return view('customers.ks_customers', compact('customers', 'search'));
        } catch (\Exception $e) {
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
                    DB::raw('SUM(CASE WHEN count > 0 THEN count ELSE 0 END) as gross_count'),
                    DB::raw('SUM(CASE WHEN count < 0 THEN ABS(count) ELSE 0 END) as returned_count'),
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
